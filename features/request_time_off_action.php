<?php
/**
 * BuildNexus - Request Time Off Action Handler
 * Handles leave requests from Foremen, Project Managers, and Admins.
 * Prevents blank screens on direct GET/refresh and provides structured JSON for AJAX.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_check.php';

// Enforce role-based access control (Foreman, Admin, Project Manager)
checkRole(['Foreman', 'Admin', 'Project Manager']);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
           || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
           || !empty($_POST['ajax']);

// 1. Direct GET access handling: Gracefully redirect back to Foreman Dashboard
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: ../foreman_logs.php");
    exit();
}

// 2. Validate input fields
$reason     = trim($_POST['reason'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$end_date   = trim($_POST['end_date'] ?? '');

if (empty($reason) || empty($start_date) || empty($end_date)) {
    $err_msg = "Please provide a reason and valid date range for your leave request.";
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $err_msg]);
        exit();
    }
    $_SESSION['flash_error'] = $err_msg;
    header("Location: ../foreman_logs.php");
    exit();
}

if (strtotime($end_date) < strtotime($start_date)) {
    $err_msg = "End date cannot be earlier than start date.";
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $err_msg]);
        exit();
    }
    $_SESSION['flash_error'] = $err_msg;
    header("Location: ../foreman_logs.php");
    exit();
}

try {
    // 3. Persist record into time_off_requests table
    $sql = "INSERT INTO time_off_requests (user_id, reason, start_date, end_date, status, created_at) 
            VALUES (:uid, :reason, :s_date, :e_date, 'Pending', NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid'    => $user_id,
        ':reason' => $reason,
        ':s_date' => $start_date,
        ':e_date' => $end_date
    ]);

    $req_id = (int)$pdo->lastInsertId();
    $succ_msg = "Leave request for '{$reason}' ({$start_date} to {$end_date}) submitted successfully!";

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status'     => 'success',
            'message'    => $succ_msg,
            'request_id' => $req_id
        ]);
        exit();
    }

    $_SESSION['flash_success'] = $succ_msg;
    header("Location: ../foreman_logs.php?msg=request_sent");
    exit();

} catch (PDOException $e) {
    $err_msg = "Database Error: " . $e->getMessage();
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $err_msg]);
        exit();
    }
    $_SESSION['flash_error'] = $err_msg;
    header("Location: ../foreman_logs.php");
    exit();
}