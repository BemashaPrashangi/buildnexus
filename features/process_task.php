<?php
// features/process_task.php - Backward compatible form POST handler for creating tasks
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['task_title'] ?? ($_POST['title'] ?? ''));
    $project_id  = intval($_POST['project_id'] ?? 0);
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : ($_SESSION['user_id'] ?? null);
    $due_date    = trim($_POST['due_date'] ?? date('Y-m-d'));
    $priority    = in_array($_POST['priority'] ?? '', ['Low', 'Medium', 'High', 'Urgent']) ? $_POST['priority'] : 'Medium';
    $status      = in_array($_POST['status'] ?? '', ['To Do', 'In Progress', 'Done', 'Blocked']) ? $_POST['status'] : 'To Do';
    $description = trim($_POST['description'] ?? '');
    $created_by  = $_SESSION['user_id'] ?? null;
    $completed_at = ($status === 'Done') ? date('Y-m-d H:i:s') : null;

    if (!empty($title) && $project_id > 0) {
        try {
            $sql = "INSERT INTO tasks 
                    (project_id, assigned_to, title, description, priority, status, due_date, completed_at, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $project_id, $assigned_to, $title, $description, $priority, $status, $due_date, $completed_at, $created_by
            ]);

            // Sync project progress percent
            $calcStmt = $pdo->prepare("
                SELECT COUNT(*) AS total_tasks, SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) AS done_tasks
                FROM tasks WHERE project_id = ?
            ");
            $calcStmt->execute([$project_id]);
            $stats = $calcStmt->fetch(PDO::FETCH_ASSOC);
            $total = intval($stats['total_tasks'] ?? 0);
            $done = intval($stats['done_tasks'] ?? 0);
            $pct = ($total > 0) ? round(($done / $total) * 100) : 0;
            
            $pdo->prepare("UPDATE projects SET progress_percent = ? WHERE id = ?")->execute([$pct, $project_id]);

            header("Location: tasks.php?success=task_created");
            exit();
        } catch (PDOException $e) {
            header("Location: tasks.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: tasks.php?error=missing_fields");
        exit();
    }
} else {
    header("Location: tasks.php");
    exit();
}