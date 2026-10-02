<?php
// Companion endpoint for comments / notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/process_project_note.php';
    exit();
}

// If JSON fetch requested
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    require_once __DIR__ . '/auth_check.php';
    require_once __DIR__ . '/../db.php';
    checkRole(['Client', 'Admin', 'Project Manager']);

    header('Content-Type: application/json');
    $project_id = (int)($_GET['project_id'] ?? 0);
    $user_role = $_SESSION['role'];

    $where = "WHERE n.project_id = ? AND n.parent_id IS NULL";
    if ($user_role === 'Client') {
        $where .= " AND n.is_internal_only = 0";
    }

    $stmt = $pdo->prepare("
        SELECT n.*, u.full_name, u.role
        FROM project_notes n
        JOIN users u ON n.user_id = u.id
        $where
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$project_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'notes' => $notes]);
    exit();
}

// Default: include client_notes.php
require_once __DIR__ . '/client_notes.php';
