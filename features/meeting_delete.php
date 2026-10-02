<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($id <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid Safety Meeting ID.']);
        exit();
    }
    header("Location: safety-meetings.php?error=invalid_id");
    exit();
}

try {
    // Check if meeting exists and get file
    $stmt = $pdo->prepare("SELECT signed_roster_file FROM safety_meeting_logs WHERE id = ?");
    $stmt->execute([$id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($meeting) {
        // Delete uploaded roster file if present
        if (!empty($meeting['signed_roster_file'])) {
            $filePath = __DIR__ . '/../uploads/safety/' . $meeting['signed_roster_file'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Delete meeting log
        $del = $pdo->prepare("DELETE FROM safety_meeting_logs WHERE id = ?");
        $del->execute([$id]);
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Safety meeting record deleted successfully.']);
        exit();
    }

    header("Location: safety-meetings.php?msg=deleted");
    exit();
} catch (PDOException $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    header("Location: safety-meetings.php?error=delete_failed");
    exit();
}
