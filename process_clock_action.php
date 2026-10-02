<?php
/**
 * BuildNexus - Production Attendance and Timekeeping Action Handler
 * Supports AJAX JSON responses and standard POST redirects with session flash banners.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

// Enforce role-based access control
checkRole(['Foreman', 'Admin', 'Project Manager']);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
           || !empty($_POST['ajax']) 
           || !empty($_GET['ajax']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($is_ajax && !headers_sent()) {
    header('Content-Type: application/json');
}

if ($user_id <= 0) {
    if ($is_ajax) {
        echo json_encode(['status' => 'error', 'message' => 'User session expired. Please log in again.']);
        exit();
    }
    header("Location: login.php");
    exit();
}

try {
    // -------------------------------------------------------------
    // ACTION 1: CLOCK IN
    // -------------------------------------------------------------
    if ($action === 'clock_in') {
        // 1. Validate user is not already clocked in
        $check_stmt = $pdo->prepare("
            SELECT tc.id, tc.project_id, p.project_name, tc.clock_in 
            FROM time_cards tc
            LEFT JOIN projects p ON tc.project_id = p.id
            WHERE tc.user_id = :uid AND tc.status = 'On-Site' 
            LIMIT 1
        ");
        $check_stmt->execute([':uid' => $user_id]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $msg = "You are already clocked in to '{$existing['project_name']}'.";
            if ($is_ajax) {
                echo json_encode([
                    'status' => 'error',
                    'message' => $msg,
                    'time_card_id' => $existing['id'],
                    'project_id' => $existing['project_id'],
                    'project_name' => $existing['project_name']
                ]);
                exit();
            }
            $_SESSION['flash_error'] = $msg;
            header("Location: foreman_logs.php");
            exit();
        }

        $project_id = (int)($_POST['project_id'] ?? 0);
        if ($project_id <= 0) {
            $msg = "Please select a valid active project to clock in.";
            if ($is_ajax) {
                echo json_encode(['status' => 'error', 'message' => $msg]);
                exit();
            }
            $_SESSION['flash_error'] = $msg;
            header("Location: foreman_logs.php");
            exit();
        }

        // Get user's hourly rate from users table
        $rate_stmt = $pdo->prepare("SELECT COALESCE(hourly_rate, 45.00) FROM users WHERE id = ?");
        $rate_stmt->execute([$user_id]);
        $hourly_rate = floatval($rate_stmt->fetchColumn() ?: 45.00);

        // Fetch project name
        $p_stmt = $pdo->prepare("SELECT project_name FROM projects WHERE id = ?");
        $p_stmt->execute([$project_id]);
        $project_name = $p_stmt->fetchColumn() ?: 'Active Site';

        // Insert new time card with status 'On-Site'
        $insert_stmt = $pdo->prepare("
            INSERT INTO time_cards 
            (user_id, project_id, work_date, clock_in, total_hours, hourly_rate, status, approval_status, created_at)
            VALUES 
            (:user_id, :project_id, CURRENT_DATE(), NOW(), 0.00, :hourly_rate, 'On-Site', 'Pending', NOW())
        ");
        $insert_stmt->execute([
            ':user_id'     => $user_id,
            ':project_id'  => $project_id,
            ':hourly_rate' => $hourly_rate
        ]);

        $time_card_id = (int)$pdo->lastInsertId();

        // Fetch inserted clock_in timestamp
        $fetch_stmt = $pdo->prepare("SELECT clock_in FROM time_cards WHERE id = ?");
        $fetch_stmt->execute([$time_card_id]);
        $clock_in_raw = $fetch_stmt->fetchColumn();
        $clock_in_time = date('H:i:s', strtotime($clock_in_raw));
        $clock_in_formatted = date('M d, Y h:i A', strtotime($clock_in_raw));

        if ($is_ajax) {
            echo json_encode([
                'status'             => 'success',
                'message'            => 'Clocked in successfully',
                'time_card_id'       => $time_card_id,
                'project_id'         => $project_id,
                'project_name'       => $project_name,
                'clock_in_time'      => $clock_in_time,
                'clock_in_formatted' => $clock_in_formatted,
                'clock_in_iso'       => date('c', strtotime($clock_in_raw))
            ]);
            exit();
        }

        $_SESSION['flash_success'] = "Successfully clocked in to {$project_name} at {$clock_in_time}.";
        header("Location: foreman_logs.php");
        exit();
    }

    // -------------------------------------------------------------
    // ACTION 2: CLOCK OUT
    // -------------------------------------------------------------
    if ($action === 'clock_out') {
        // Find the active open record for the user
        $find_stmt = $pdo->prepare("
            SELECT tc.id, tc.project_id, tc.clock_in, p.project_name 
            FROM time_cards tc
            LEFT JOIN projects p ON tc.project_id = p.id
            WHERE tc.user_id = :uid AND tc.status = 'On-Site'
            ORDER BY tc.id DESC LIMIT 1
        ");
        $find_stmt->execute([':uid' => $user_id]);
        $active_shift = $find_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$active_shift) {
            $msg = "No active clocked-in session found to clock out.";
            if ($is_ajax) {
                echo json_encode(['status' => 'error', 'message' => $msg]);
                exit();
            }
            $_SESSION['flash_error'] = $msg;
            header("Location: foreman_logs.php");
            exit();
        }

        $card_id = (int)$active_shift['id'];
        $work_notes = trim($_POST['work_notes'] ?? '');

        // Update record with clock_out, calculate total_hours, status='Completed'
        $update_stmt = $pdo->prepare("
            UPDATE time_cards 
            SET clock_out = NOW(),
                total_hours = ROUND(TIMESTAMPDIFF(MINUTE, clock_in, NOW()) / 60, 2),
                status = 'Completed',
                work_notes = :work_notes
            WHERE id = :id
        ");
        $update_stmt->execute([
            ':work_notes' => $work_notes,
            ':id'         => $card_id
        ]);

        // Re-fetch computed hours and clock out
        $out_stmt = $pdo->prepare("SELECT total_hours, clock_out FROM time_cards WHERE id = ?");
        $out_stmt->execute([$card_id]);
        $result = $out_stmt->fetch(PDO::FETCH_ASSOC);

        $total_hours = floatval($result['total_hours'] ?? 0.00);
        $clock_out_time = date('H:i:s', strtotime($result['clock_out'] ?? 'now'));

        // Query new weekly total
        $week_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_hours), 0) 
            FROM time_cards 
            WHERE user_id = :uid AND YEARWEEK(work_date, 1) = YEARWEEK(CURRENT_DATE(), 1)
        ");
        $week_stmt->execute([':uid' => $user_id]);
        $weekly_hours = floatval($week_stmt->fetchColumn() ?: 0.00);

        if ($is_ajax) {
            echo json_encode([
                'status'         => 'success',
                'message'        => 'Clocked out successfully',
                'time_card_id'   => $card_id,
                'total_hours'    => $total_hours,
                'clock_out_time' => $clock_out_time,
                'weekly_hours'   => $weekly_hours
            ]);
            exit();
        }

        $_SESSION['flash_success'] = "Shift completed! Clocked out at {$clock_out_time}. Total logged: {$total_hours} hrs.";
        header("Location: foreman_logs.php");
        exit();
    }

    // -------------------------------------------------------------
    // ACTION 3: GET STATUS (Polling / Sync helper)
    // -------------------------------------------------------------
    if ($action === 'get_status') {
        $status_stmt = $pdo->prepare("
            SELECT tc.id, tc.project_id, p.project_name, tc.clock_in,
                   TIMESTAMPDIFF(SECOND, tc.clock_in, NOW()) AS elapsed_seconds
            FROM time_cards tc
            LEFT JOIN projects p ON tc.project_id = p.id
            WHERE tc.user_id = :uid AND tc.status = 'On-Site'
            ORDER BY tc.id DESC LIMIT 1
        ");
        $status_stmt->execute([':uid' => $user_id]);
        $shift = $status_stmt->fetch(PDO::FETCH_ASSOC);

        $week_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_hours), 0) 
            FROM time_cards 
            WHERE user_id = :uid AND YEARWEEK(work_date, 1) = YEARWEEK(CURRENT_DATE(), 1)
        ");
        $week_stmt->execute([':uid' => $user_id]);
        $weekly_hours = floatval($week_stmt->fetchColumn() ?: 0.00);

        echo json_encode([
            'status'          => 'success',
            'is_clocked_in'   => !empty($shift),
            'active_shift'    => $shift ?: null,
            'weekly_hours'    => $weekly_hours
        ]);
        exit();
    }

    // Invalid action
    if ($is_ajax) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action request.']);
        exit();
    }
    header("Location: foreman_logs.php");
    exit();

} catch (PDOException $e) {
    if ($is_ajax) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
    header("Location: foreman_logs.php");
    exit();
}
