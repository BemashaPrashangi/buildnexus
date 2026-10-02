<?php
// FIXED PATH: Navigate up to root for db.php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $project_id = intval($_POST['project_id'] ?? 0);
    $foreman_id = $_SESSION['user_id'];
    $work_summary = trim($_POST['work_summary'] ?? '');
    $weather = trim($_POST['weather_condition'] ?? 'Sunny, 30°C');
    $crew_count = intval($_POST['crew_count'] ?? 0);
    $subcontractor_notes = trim($_POST['subcontractor_notes'] ?? '');
    $report_date = !empty($_POST['report_date']) ? $_POST['report_date'] : date('Y-m-d');
    $image_path = null;

    if ($project_id <= 0 || empty($work_summary)) {
        header("Location: ../foreman_logs.php?err=missing_fields");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Handle Image Upload
        if (isset($_FILES['site_image']) && $_FILES['site_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/daily_logs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['site_image']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $file_name = "report_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $file_ext;
                $target_file = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['site_image']['tmp_name'], $target_file)) {
                    $image_path = 'uploads/daily_logs/' . $file_name;
                }
            }
        }

        // Insert into database
        $sql = "INSERT INTO daily_reports (project_id, foreman_id, report_date, weather_condition, work_summary, crew_count, subcontractor_notes, status, site_image) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$project_id, $foreman_id, $report_date, $weather, $work_summary, $crew_count, $subcontractor_notes, $image_path]);
        $report_id = $pdo->lastInsertId();

        // Also link to daily_report_photos if photo uploaded
        if ($image_path) {
            $photoStmt = $pdo->prepare("INSERT INTO daily_report_photos (report_id, photo_url, caption) VALUES (?, ?, ?)");
            $photoStmt->execute([$report_id, $image_path, 'Foreman site upload']);
        }

        $pdo->commit();

        $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '../foreman_logs.php?msg=report_submitted';
        header("Location: " . $redirect);
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: ../foreman_logs.php");
    exit();
}