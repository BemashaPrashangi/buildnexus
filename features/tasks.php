<?php
// features/tasks.php - Production-Ready Project Management Tasks & Action Items Module
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$current_user_id = $_SESSION['user_id'] ?? 1;
$current_user_name = $_SESSION['name'] ?? 'Team Member';
$current_user_role = $_SESSION['role'] ?? 'Project Manager';

// 1. Determine active segment tab: 'today', 'this_week', 'all'
$segment = $_GET['segment'] ?? 'today';
if (!in_array($segment, ['today', 'this_week', 'all'])) {
    $segment = 'today';
}

$search = trim($_GET['search'] ?? '');
$project_filter = intval($_GET['project_id'] ?? 0);
$priority_filter = trim($_GET['priority'] ?? '');

// 2. Fetch segment counts for badge counters on tabs
try {
    $count_today = $pdo->query("
        SELECT COUNT(*) FROM tasks 
        WHERE (due_date = CURRENT_DATE() OR (due_date < CURRENT_DATE() AND status != 'Done'))
    ")->fetchColumn();

    $count_week = $pdo->query("
        SELECT COUNT(*) FROM tasks 
        WHERE YEARWEEK(due_date, 1) = YEARWEEK(CURRENT_DATE(), 1)
    ")->fetchColumn();

    $count_all = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();

    $count_in_progress = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'")->fetchColumn();
    $count_done = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Done'")->fetchColumn();
    $count_overdue = $pdo->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURRENT_DATE() AND status != 'Done'")->fetchColumn();

    // Overall completion percentage
    $overall_pct = ($count_all > 0) ? round(($count_done / $count_all) * 100) : 0;

} catch (Exception $e) {
    $count_today = $count_week = $count_all = $count_in_progress = $count_done = $count_overdue = 0;
    $overall_pct = 0;
}

// 3. Build Query for Tasks matching filters
$where = [];
$params = [];

// Date segmentation filter
if ($segment === 'today') {
    $where[] = "(t.due_date = CURRENT_DATE() OR (t.due_date < CURRENT_DATE() AND t.status != 'Done'))";
} elseif ($segment === 'this_week') {
    $where[] = "YEARWEEK(t.due_date, 1) = YEARWEEK(CURRENT_DATE(), 1)";
}
// 'all' includes everything

// Search filter
if (!empty($search)) {
    $where[] = "(t.title LIKE ? OR t.description LIKE ? OR p.project_name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Project filter
if ($project_filter > 0) {
    $where[] = "t.project_id = ?";
    $params[] = $project_filter;
}

// Priority filter
if (!empty($priority_filter) && in_array($priority_filter, ['Low', 'Medium', 'High', 'Urgent'])) {
    $where[] = "t.priority = ?";
    $params[] = $priority_filter;
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT t.*, 
           p.project_name, p.project_code, p.progress_percent as project_progress,
           u.full_name as assigned_name, u.role as assigned_role,
           c.full_name as creator_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users c ON t.created_by = c.id
    {$where_sql}
    ORDER BY 
        CASE 
            WHEN t.status = 'In Progress' THEN 1
            WHEN t.status = 'To Do' THEN 2
            WHEN t.status = 'Blocked' THEN 3
            ELSE 4
        END,
        t.due_date ASC,
        t.id DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch projects for dropdowns
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects WHERE status != 'Archived' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch users for assignment dropdown
    $users = $pdo->query("SELECT id, full_name, role FROM users WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Helper to determine status pill class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'In Progress':
            return 'status-in-progress';
        case 'Done':
            return 'status-done';
        case 'Blocked':
            return 'status-blocked';
        case 'To Do':
        default:
            return 'status-to-do';
    }
}

// Helper for priority pill styling
function getPriorityBadge($priority) {
    switch ($priority) {
        case 'Urgent':
            return ['bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca', 'icon' => 'exclamation-octagon-fill'];
        case 'High':
            return ['bg' => '#fff7ed', 'color' => '#ea580c', 'border' => '#ffedd5', 'icon' => 'arrow-up-circle-fill'];
        case 'Medium':
            return ['bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#dcfce7', 'icon' => 'dash-circle'];
        case 'Low':
        default:
            return ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0', 'icon' => 'arrow-down-circle'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- BuildNexus Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/buildnexus/images/logo.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/buildnexus/images/logo.png?v=2">
    <link rel="shortcut icon" href="/buildnexus/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="/buildnexus/images/logo.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - BuildNexus Project Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- BuildNexus Standard Design Language (Zero Tailwind) --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .main-container {
            padding: 2.5rem 3rem;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Top Header & Search Bar */
        .page-header-title {
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .search-wrapper {
            position: relative;
            min-width: 280px;
        }

        .search-bar {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 9px 15px 9px 38px;
            font-size: 0.9rem;
            width: 100%;
            background-color: #ffffff;
            color: #1e293b;
            outline: none;
            transition: all 0.2s ease;
        }

        .search-bar:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }

        .search-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.95rem;
            pointer-events: none;
        }

        .btn-nexus-success {
            background-color: #22c55e;
            border: 1px solid #16a34a;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 9px 18px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s, transform 0.15s;
        }

        .btn-nexus-success:hover {
            background-color: #16a34a;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Filter Tabs matching Screenshot */
        .filter-nav {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 4px;
            display: inline-flex;
            margin-bottom: 2rem;
            width: 100%;
            max-width: 720px;
            border: 1px solid #e2e8f0;
        }

        .filter-btn {
            flex: 1;
            border: none;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .filter-btn:hover {
            color: #1e293b;
        }

        .filter-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }

        .tab-counter {
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 12px;
            background: #e2e8f0;
            color: #475569;
            font-weight: 700;
        }

        .filter-btn.active .tab-counter {
            background: #f0fdf4;
            color: #16a34a;
        }

        /* KPI Quick Metrics Bar */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .kpi-num {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .kpi-lbl {
            font-size: 0.78rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* Task Cards Layout strictly adhering to UI Screenshot */
        .task-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            position: relative;
            cursor: pointer;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
            border-color: #cbd5e1;
        }

        .task-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.4;
            margin-bottom: 1.2rem;
            word-break: break-word;
        }

        .task-due {
            color: #dc2626; /* Bold red styling matching screenshot */
            font-weight: 700;
            font-size: 0.88rem;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .task-due.due-future {
            color: #475569;
        }

        .task-due.due-today {
            color: #ea580c;
        }

        .task-project {
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 170px;
        }

        /* Status Pills (Interactive Bottom Right) */
        .status-badge {
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
            transition: transform 0.15s, opacity 0.15s, box-shadow 0.15s;
            user-select: none;
        }

        .status-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .status-in-progress { background-color: #2563eb; } /* Blue pill */
        .status-to-do       { background-color: #475569; } /* Slate/Gray pill */
        .status-done        { background-color: #22c55e; } /* Green pill */
        .status-blocked     { background-color: #dc2626; } /* Red pill */

        .priority-chip {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .card-menu-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 1.1rem;
            line-height: 1;
        }

        .card-menu-btn:hover {
            color: #0f172a;
            background: #f1f5f9;
        }

        /* Empty State */
        .empty-tasks-wrap {
            background: #ffffff;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 4rem 2rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="main-container">

    <!-- Top Header: Title, + New Task, Search -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h1 class="page-header-title mb-0">My Tasks</h1>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <button class="btn-nexus-success" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="bi bi-plus-lg"></i> New Task
            </button>
            <div class="search-wrapper">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="taskSearchInput" class="search-bar" placeholder="Search projects..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            </div>
        </div>
    </div>

    <!-- Date Segment Filter Tabs (Today, This Week, All Tasks) -->
    <div class="filter-nav mb-4">
        <a href="tasks.php?segment=today<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= ($segment === 'today') ? 'active' : '' ?>">
            <span>Today</span>
            <span class="tab-counter"><?= $count_today ?></span>
        </a>
        <a href="tasks.php?segment=this_week<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= ($segment === 'this_week') ? 'active' : '' ?>">
            <span>This Week</span>
            <span class="tab-counter"><?= $count_week ?></span>
        </a>
        <a href="tasks.php?segment=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= ($segment === 'all') ? 'active' : '' ?>">
            <span>All Tasks</span>
            <span class="tab-counter"><?= $count_all ?></span>
        </a>
    </div>

    <!-- KPI Summary Metric Cards -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div>
                <div class="kpi-num"><?= $count_all ?></div>
                <div class="kpi-lbl">Total Tasks</div>
            </div>
            <i class="bi bi-list-check fs-3 text-secondary"></i>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-num text-primary"><?= $count_in_progress ?></div>
                <div class="kpi-lbl">In Progress</div>
            </div>
            <i class="bi bi-hourglass-split fs-3 text-primary"></i>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-num text-danger"><?= $count_overdue ?></div>
                <div class="kpi-lbl">Overdue Action</div>
            </div>
            <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-num text-success"><?= $count_done ?></div>
                <div class="kpi-lbl">Completed</div>
            </div>
            <i class="bi bi-check2-circle fs-3 text-success"></i>
        </div>
        <div class="kpi-card">
            <div class="w-100">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="kpi-lbl">Completion Rate</span>
                    <strong class="text-success small"><?= $overall_pct ?>%</strong>
                </div>
                <div class="progress" style="height: 6px; background-color: #e2e8f0; border-radius: 4px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $overall_pct ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Toast Notification Container -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="liveToast" class="toast align-items-center text-white bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2" id="toastMessage">
                    <i class="bi bi-check-circle-fill text-success"></i> Task updated.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- Task Cards Grid Layout (Matching Screenshot) -->
    <div class="row g-4" id="taskCardsContainer">
        <?php if (empty($tasks)): ?>
            <div class="col-12">
                <div class="empty-tasks-wrap">
                    <i class="bi bi-clipboard-check fs-1 text-muted mb-3 d-block"></i>
                    <h5 class="fw-bold">No tasks found for this view</h5>
                    <p class="text-muted small">You're all caught up! Click "+ New Task" to schedule upcoming construction action items.</p>
                    <button class="btn btn-sm btn-nexus-success mt-2" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                        <i class="bi bi-plus-lg"></i> Create Task
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): 
                $today_date = date('Y-m-d');
                $due_text = "Due: " . htmlspecialchars($task['due_date']);
                $is_overdue = ($task['due_date'] < $today_date && $task['status'] !== 'Done');
                $is_today = ($task['due_date'] === $today_date);
                
                $due_class = $is_overdue ? 'text-danger' : ($is_today ? 'due-today' : 'due-future');
                $status_class = getStatusBadgeClass($task['status']);
                $priority_info = getPriorityBadge($task['priority']);
            ?>
            <div class="col-md-4 task-card-col" 
                 data-task-id="<?= $task['id'] ?>"
                 data-title="<?= strtolower(htmlspecialchars($task['title'])) ?>"
                 data-project="<?= strtolower(htmlspecialchars($task['project_name'] ?? '')) ?>"
                 data-desc="<?= strtolower(htmlspecialchars($task['description'] ?? '')) ?>"
                 data-status="<?= $task['status'] ?>">
                
                <div class="task-card" onclick="openTaskDetails(<?= $task['id'] ?>, event)">
                    <!-- Card Header / Title -->
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="task-title mb-0"><?= htmlspecialchars($task['title']) ?></div>
                            <div class="dropdown" onclick="event.stopPropagation()">
                                <button class="card-menu-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Task Options">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 8px;">
                                    <li><a class="dropdown-item small" href="javascript:void(0)" onclick="openTaskDetails(<?= $task['id'] ?>)"><i class="bi bi-eye me-2 text-primary"></i> View Details</a></li>
                                    <li><a class="dropdown-item small" href="javascript:void(0)" onclick="openEditTaskModal(<?= $task['id'] ?>)"><i class="bi bi-pencil me-2 text-secondary"></i> Edit Task</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item small text-danger" href="javascript:void(0)" onclick="confirmDeleteTask(<?= $task['id'] ?>)"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Priority Chip & Assignee -->
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="priority-chip" style="background-color: <?= $priority_info['bg'] ?>; color: <?= $priority_info['color'] ?>; border: 1px solid <?= $priority_info['border'] ?>;">
                                <i class="bi bi-<?= $priority_info['icon'] ?> me-1"></i><?= htmlspecialchars($task['priority']) ?>
                            </span>
                            <?php if (!empty($task['assigned_name'])): ?>
                                <span class="small text-muted" title="Assigned to <?= htmlspecialchars($task['assigned_name']) ?>">
                                    <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($task['assigned_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Bottom Info: Due Date, Project Name, Status Pill -->
                    <div class="d-flex justify-content-between align-items-end mt-2">
                        <div>
                            <div class="task-due <?= $due_class ?>" id="taskDue-<?= $task['id'] ?>">
                                <?= $due_text ?>
                                <?php if ($is_overdue): ?>
                                    <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">Overdue</span>
                                <?php endif; ?>
                            </div>
                            <div class="task-project" title="<?= htmlspecialchars($task['project_name'] ?? 'General Project') ?>">
                                <?= htmlspecialchars($task['project_name'] ?? 'General Project') ?>
                            </div>
                        </div>
                        <div>
                            <!-- Interactive Status Button: Click cycles To Do -> In Progress -> Done -->
                            <button type="button" 
                                    class="status-badge <?= $status_class ?>" 
                                    id="statusBadge-<?= $task['id'] ?>"
                                    title="Click to cycle status (To Do -> In Progress -> Done)"
                                    onclick="cycleTaskStatus(<?= $task['id'] ?>, event)">
                                <span id="statusText-<?= $task['id'] ?>"><?= htmlspecialchars($task['status']) ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: + New Task                         -->
<!-- ========================================== -->
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold mb-0" id="createTaskModalLabel">Create New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createTaskForm" onsubmit="submitNewTask(event)">
                <div class="modal-body p-4">
                    <!-- Task Title -->
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Task Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g., Submit PO for roofing materials - Luxury Villa" required>
                    </div>

                    <!-- Related Project Dropdown -->
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Related Project <span class="text-danger">*</span></label>
                        <select name="project_id" class="form-select" required>
                            <option value="">Select a Project</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Assigned To & Priority -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Assigned To</label>
                            <select name="assigned_to" class="form-select">
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($u['id'] == $current_user_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="Medium" selected>Medium</option>
                                <option value="Low">Low</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <!-- Due Date & Initial Status -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Initial Status</label>
                            <select name="status" class="form-select">
                                <option value="To Do" selected>To Do</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Blocked">Blocked</option>
                                <option value="Done">Done</option>
                            </select>
                        </div>
                    </div>

                    <!-- Description / Action Notes -->
                    <div class="mb-2">
                        <label class="small fw-bold text-muted mb-1">Action Notes / Checklist</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Enter task specifications, submittal steps, or punch list items..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-nexus-success px-4" id="submitCreateBtn">
                        <i class="bi bi-check-lg"></i> Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: Task Details View                   -->
<!-- ========================================== -->
<div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <span class="badge" id="modalPriorityBadge" style="font-size: 0.72rem;"></span>
                    <h5 class="fw-bold mt-2 mb-0" id="modalTaskTitle">Task Details</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Meta Info Grid -->
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <span class="text-muted d-block">Project:</span>
                            <strong id="modalProjectName" class="text-dark"></strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Due Date:</span>
                            <strong id="modalDueDate" class="text-danger"></strong>
                        </div>
                        <div class="col-6 mt-2">
                            <span class="text-muted d-block">Assigned Member:</span>
                            <span id="modalAssignedName" class="fw-semibold text-dark"></span>
                        </div>
                        <div class="col-6 mt-2">
                            <span class="text-muted d-block">Status:</span>
                            <span id="modalStatusBadge" class="badge"></span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <h6 class="small fw-bold text-muted text-uppercase mb-2">Description & Instructions</h6>
                    <div id="modalDescription" class="p-3 bg-white border rounded-3 small text-secondary" style="min-height: 80px; white-space: pre-wrap;">
                        No detailed notes provided.
                    </div>
                </div>

                <!-- Project Progress Bar Sync -->
                <div class="p-3 rounded-3 border bg-white mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1 small">
                        <span class="fw-semibold text-muted">Parent Project Progress</span>
                        <strong id="modalProjectProgressPct" class="text-success">0%</strong>
                    </div>
                    <div class="progress" style="height: 6px; background-color: #f1f5f9; border-radius: 4px;">
                        <div id="modalProjectProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <a href="projects.php" id="modalProjectLink" class="btn btn-outline-secondary btn-sm fw-semibold">
                    <i class="bi bi-arrow-up-right me-1"></i> Open Project
                </a>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" id="modalEditBtn">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: Edit Task                           -->
<!-- ========================================== -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold mb-0">Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editTaskForm" onsubmit="submitEditTask(event)">
                <input type="hidden" name="task_id" id="editTaskId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Task Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="editTitle" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Related Project <span class="text-danger">*</span></label>
                        <select name="project_id" id="editProjectId" class="form-select" required>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Assigned To</label>
                            <select name="assigned_to" id="editAssignedTo" class="form-select">
                                <option value="">Unassigned</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>">
                                        <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Priority</label>
                            <select name="priority" id="editPriority" class="form-select">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" id="editDueDate" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-1">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="To Do">To Do</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Blocked">Blocked</option>
                                <option value="Done">Done</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="small fw-bold text-muted mb-1">Description / Notes</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-nexus-success px-4" id="submitEditBtn">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- Toast Notification Helper ---
    function showToast(message, isSuccess = true) {
        const toastEl = document.getElementById('liveToast');
        const toastBody = document.getElementById('toastMessage');
        toastBody.innerHTML = `<i class="bi ${isSuccess ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle-fill text-danger'}"></i> ${message}`;
        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
    }

    // --- Real-Time Instant Search Filter ---
    const searchInput = document.getElementById('taskSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            const cols = document.querySelectorAll('.task-card-col');
            let visibleCount = 0;

            cols.forEach(col => {
                const title = col.getAttribute('data-title') || '';
                const project = col.getAttribute('data-project') || '';
                const desc = col.getAttribute('data-desc') || '';
                const status = (col.getAttribute('data-status') || '').toLowerCase();

                if (title.includes(query) || project.includes(query) || desc.includes(query) || status.includes(query)) {
                    col.style.display = '';
                    visibleCount++;
                } else {
                    col.style.display = 'none';
                }
            });
        });
    }

    // --- Interactive Status Badge Cycling (AJAX to task_status_toggle.php) ---
    function cycleTaskStatus(taskId, event) {
        if (event) event.stopPropagation();

        const badge = document.getElementById('statusBadge-' + taskId);
        const statusText = document.getElementById('statusText-' + taskId);

        // Optimistic UI pulse
        badge.style.opacity = '0.6';

        const formData = new FormData();
        formData.append('task_id', taskId);

        fetch('task_status_toggle.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            badge.style.opacity = '1';
            if (data.success) {
                // Update badge text and classes
                statusText.innerText = data.new_status;
                badge.className = 'status-badge ' + data.badge_class;

                // Update column data attribute
                const col = document.querySelector(`.task-card-col[data-task-id="${taskId}"]`);
                if (col) {
                    col.setAttribute('data-status', data.new_status);
                }

                showToast(`Task updated to "${data.new_status}". Project progress synced (${data.project_progress}%).`);
            } else {
                showToast(data.message || 'Error updating status', false);
            }
        })
        .catch(err => {
            badge.style.opacity = '1';
            showToast('Network error while toggling task status.', false);
        });
    }

    // --- Create New Task Handler ---
    function submitNewTask(e) {
        e.preventDefault();
        const form = document.getElementById('createTaskForm');
        const submitBtn = document.getElementById('submitCreateBtn');
        const formData = new FormData(form);
        formData.append('action', 'create_task');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating...';

        fetch('task_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Create Task';

            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('createTaskModal')).hide();
                showToast(data.message);
                setTimeout(() => window.location.reload(), 600);
            } else {
                alert(data.message || 'Error creating task.');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Create Task';
            alert('Network communication error.');
        });
    }

    // --- Open Task Details Modal ---
    function openTaskDetails(taskId, event) {
        if (event && event.target.closest('.dropdown, .status-badge')) {
            return; // Don't trigger details modal when clicking status or dropdown
        }

        fetch(`task_actions.php?action=get_task&id=${taskId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.task) {
                const t = data.task;
                document.getElementById('modalTaskTitle').innerText = t.title;
                document.getElementById('modalProjectName').innerText = t.project_name ? `${t.project_name} (${t.project_code || 'PRJ'})` : 'General';
                document.getElementById('modalDueDate').innerText = t.due_date;
                document.getElementById('modalAssignedName').innerText = t.assigned_name ? `${t.assigned_name} (${t.assigned_role})` : 'Unassigned';
                document.getElementById('modalDescription').innerText = t.description || 'No detailed action notes provided.';

                // Priority Badge
                const prioBadge = document.getElementById('modalPriorityBadge');
                prioBadge.innerText = t.priority;
                prioBadge.className = 'badge ' + (t.priority === 'Urgent' ? 'bg-danger' : (t.priority === 'High' ? 'bg-warning text-dark' : 'bg-secondary'));

                // Status Badge
                const stBadge = document.getElementById('modalStatusBadge');
                stBadge.innerText = t.status;
                stBadge.className = 'badge ' + (t.status === 'Done' ? 'bg-success' : (t.status === 'In Progress' ? 'bg-primary' : 'bg-dark'));

                // Project Progress Sync Bar
                const prog = parseInt(t.project_progress || 0);
                document.getElementById('modalProjectProgressPct').innerText = prog + '%';
                document.getElementById('modalProjectProgressBar').style.width = prog + '%';

                // Edit Button Link
                document.getElementById('modalEditBtn').onclick = function() {
                    bootstrap.Modal.getInstance(document.getElementById('taskDetailsModal')).hide();
                    openEditTaskModal(t.id);
                };

                // Link to Project
                if (t.project_id) {
                    document.getElementById('modalProjectLink').href = `projects.php?id=${t.project_id}`;
                }

                new bootstrap.Modal(document.getElementById('taskDetailsModal')).show();
            } else {
                showToast(data.message || 'Task not found', false);
            }
        });
    }

    // --- Open Edit Task Modal ---
    function openEditTaskModal(taskId) {
        fetch(`task_actions.php?action=get_task&id=${taskId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.task) {
                const t = data.task;
                document.getElementById('editTaskId').value = t.id;
                document.getElementById('editTitle').value = t.title;
                document.getElementById('editProjectId').value = t.project_id;
                document.getElementById('editAssignedTo').value = t.assigned_to || '';
                document.getElementById('editPriority').value = t.priority;
                document.getElementById('editDueDate').value = t.due_date;
                document.getElementById('editStatus').value = t.status;
                document.getElementById('editDescription').value = t.description || '';

                new bootstrap.Modal(document.getElementById('editTaskModal')).show();
            } else {
                showToast('Failed to load task for editing.', false);
            }
        });
    }

    // --- Submit Edit Task ---
    function submitEditTask(e) {
        e.preventDefault();
        const form = document.getElementById('editTaskForm');
        const submitBtn = document.getElementById('submitEditBtn');
        const formData = new FormData(form);
        formData.append('action', 'edit_task');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        fetch('task_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Changes';

            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editTaskModal')).hide();
                showToast(data.message);
                setTimeout(() => window.location.reload(), 600);
            } else {
                alert(data.message || 'Error updating task.');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Changes';
            alert('Network communication error.');
        });
    }

    // --- Delete Task Confirmation ---
    function confirmDeleteTask(taskId) {
        if (!confirm('Are you sure you want to delete this action item? This will also update the parent project progress.')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_task');
        formData.append('task_id', taskId);

        fetch('task_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                const col = document.querySelector(`.task-card-col[data-task-id="${taskId}"]`);
                if (col) {
                    col.style.transition = 'all 0.3s ease';
                    col.style.opacity = '0';
                    col.style.transform = 'scale(0.9)';
                    setTimeout(() => col.remove(), 300);
                }
            } else {
                alert(data.message || 'Failed to delete task.');
            }
        })
        .catch(err => alert('Network error during deletion.'));
    }
</script>

</body>
</html>