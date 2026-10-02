<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? 'archive'; // default archive
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($id <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid Contact ID.']);
        exit();
    }
    header("Location: directory.php?error=invalid_id");
    exit();
}

try {
    if ($action === 'delete_permanently') {
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        $msg = 'contact_deleted';
    } else {
        // Archive
        $stmt = $pdo->prepare("UPDATE contacts SET status = 'Archived' WHERE id = ?");
        $stmt->execute([$id]);
        $msg = 'contact_archived';
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Contact updated successfully.']);
        exit();
    }

    header("Location: directory.php?msg={$msg}");
    exit();
} catch (PDOException $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    header("Location: directory.php?error=action_failed");
    exit();
}
