<?php
// features/task_actions.php - Unified CRUD & Management API for Tasks
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$action = trim($_GET['action'] ?? ($_POST['action'] ?? ''));

// Helper to recalculate project completion
function syncProjectProgress($pdo, $project_id) {
    if (!$project_id) return 0;
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
    $progress = ($total_tasks > 0) ? round(($done_tasks / $total_tasks) * 100) : 0;

    $upd = $pdo->prepare("UPDATE projects SET progress_percent = ? WHERE id = ?");
    $upd->execute([$progress, $project_id]);
    return $progress;
}

try {
    if ($action === 'create_task') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid method.']);
            exit();
        }

        $title = trim($_POST['title'] ?? '');
        $project_id = intval($_POST['project_id'] ?? 0);
        $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : ($_SESSION['user_id'] ?? null);
        $due_date = trim($_POST['due_date'] ?? date('Y-m-d'));
        $priority = in_array($_POST['priority'] ?? '', ['Low', 'Medium', 'High', 'Urgent']) ? $_POST['priority'] : 'Medium';
        $status = in_array($_POST['status'] ?? '', ['To Do', 'In Progress', 'Done', 'Blocked']) ? $_POST['status'] : 'To Do';
        $description = trim($_POST['description'] ?? '');
        $created_by = $_SESSION['user_id'] ?? null;
        $completed_at = ($status === 'Done') ? date('Y-m-d H:i:s') : null;

        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Task title is required.']);
            exit();
        }
        if ($project_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select a related project.']);
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO tasks 
            (project_id, assigned_to, title, description, priority, status, due_date, completed_at, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $project_id, $assigned_to, $title, $description, $priority, $status, $due_date, $completed_at, $created_by
        ]);
        $new_id = $pdo->lastInsertId();

        $new_proj_progress = syncProjectProgress($pdo, $project_id);

        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully!',
            'task_id' => $new_id,
            'project_progress' => $new_proj_progress
        ]);
        exit();

    } elseif ($action === 'get_task') {
        $task_id = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID.']);
            exit();
        }

        $stmt = $pdo->prepare("
            SELECT t.*, 
                   p.project_name, p.project_code, p.progress_percent as project_progress,
                   u.full_name as assigned_name, u.role as assigned_role,
                   c.full_name as creator_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN users c ON t.created_by = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task not found.']);
            exit();
        }

        echo json_encode([
            'success' => true,
            'task' => $task
        ]);
        exit();

    } elseif ($action === 'edit_task') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid method.']);
            exit();
        }

        $task_id = intval($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $project_id = intval($_POST['project_id'] ?? 0);
        $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        $due_date = trim($_POST['due_date'] ?? date('Y-m-d'));
        $priority = in_array($_POST['priority'] ?? '', ['Low', 'Medium', 'High', 'Urgent']) ? $_POST['priority'] : 'Medium';
        $status = in_array($_POST['status'] ?? '', ['To Do', 'In Progress', 'Done', 'Blocked']) ? $_POST['status'] : 'To Do';
        $description = trim($_POST['description'] ?? '');

        if ($task_id <= 0 || empty($title) || $project_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Required fields missing for task update.']);
            exit();
        }

        // Check previous status and project
        $prev = $pdo->prepare("SELECT project_id, status FROM tasks WHERE id = ?");
        $prev->execute([$task_id]);
        $prevData = $prev->fetch(PDO::FETCH_ASSOC);

        $completed_at = ($status === 'Done') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET title = ?, project_id = ?, assigned_to = ?, due_date = ?, priority = ?, status = ?, description = ?, completed_at = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $project_id, $assigned_to, $due_date, $priority, $status, $description, $completed_at, $task_id
        ]);

        // Sync old project progress if project was changed
        if ($prevData && $prevData['project_id'] != $project_id) {
            syncProjectProgress($pdo, $prevData['project_id']);
        }
        $new_progress = syncProjectProgress($pdo, $project_id);

        echo json_encode([
            'success' => true,
            'message' => 'Task updated successfully!',
            'task_id' => $task_id,
            'project_progress' => $new_progress
        ]);
        exit();

    } elseif ($action === 'delete_task') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid method.']);
            exit();
        }

        $task_id = intval($_POST['task_id'] ?? 0);
        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID.']);
            exit();
        }

        $prev = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?");
        $prev->execute([$task_id]);
        $project_id = $prev->fetchColumn();

        $del = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $del->execute([$task_id]);

        if ($project_id) {
            syncProjectProgress($pdo, $project_id);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Task deleted successfully!'
        ]);
        exit();

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit();
}
