<?php
// features/task_status_toggle.php - AJAX Quick-Action Handler for Task Status Transitions
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Expected POST.']);
    exit();
}

$task_id = intval($_POST['task_id'] ?? 0);
if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing Task ID.']);
    exit();
}

try {
    // 1. Fetch current task state
    $stmt = $pdo->prepare("SELECT id, project_id, status, title FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found.']);
        exit();
    }

    $current_status = $task['status'];
    $project_id = intval($task['project_id']);

    // 2. Determine new status: explicit or cycle
    $valid_statuses = ['To Do', 'In Progress', 'Done', 'Blocked'];
    $requested_status = trim($_POST['new_status'] ?? '');

    if (!empty($requested_status) && in_array($requested_status, $valid_statuses)) {
        $new_status = $requested_status;
    } else {
        // Cycle status: To Do -> In Progress -> Done -> To Do
        switch ($current_status) {
            case 'To Do':
                $new_status = 'In Progress';
                break;
            case 'In Progress':
                $new_status = 'Done';
                break;
            case 'Done':
                $new_status = 'To Do';
                break;
            case 'Blocked':
            default:
                $new_status = 'In Progress';
                break;
        }
    }

    // 3. Set completed_at timestamp
    $completed_at = ($new_status === 'Done') ? date('Y-m-d H:i:s') : null;

    // 4. Update task
    $updStmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?");
    $updStmt->execute([$new_status, $completed_at, $task_id]);

    // 5. Recalculate parent project completion percentage
    $calcStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_tasks,
            SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) AS done_tasks
        FROM tasks 
        WHERE project_id = ?
    ");
    $calcStmt->execute([$project_id]);
    $stats = $calcStmt->fetch(PDO::FETCH_ASSOC);

    $total_tasks = intval($stats['total_tasks'] ?? 0);
    $done_tasks = intval($stats['done_tasks'] ?? 0);
    $project_progress = ($total_tasks > 0) ? round(($done_tasks / $total_tasks) * 100) : 0;

    // Persist recalculated progress into projects table
    $updProj = $pdo->prepare("UPDATE projects SET progress_percent = ? WHERE id = ?");
    $updProj->execute([$project_progress, $project_id]);

    // Badge styling helper
    $badge_classes = [
        'To Do' => 'status-to-do',
        'In Progress' => 'status-in-progress',
        'Done' => 'status-done',
        'Blocked' => 'status-blocked'
    ];

    echo json_encode([
        'success' => true,
        'message' => "Task status updated to {$new_status}",
        'task_id' => $task_id,
        'new_status' => $new_status,
        'badge_class' => $badge_classes[$new_status] ?? 'status-to-do',
        'completed_at' => $completed_at,
        'project_id' => $project_id,
        'total_tasks' => $total_tasks,
        'done_tasks' => $done_tasks,
        'project_progress' => $project_progress
    ]);
    exit();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
