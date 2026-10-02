<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$success_msg = '';
$error_msg = '';

// Helper to handle thumbnail uploads
function uploadProjectThumbnail($file) {
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $file['size'] <= 10485760) { // 10MB
            $uploadDir = __DIR__ . '/../uploads/projects/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                return 'uploads/projects/' . $filename;
            }
        }
    }
    return null;
}

// 1. Handle Create New Project
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    $project_name = trim($_POST['project_name'] ?? '');
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $pm_id = !empty($_POST['pm_id']) ? intval($_POST['pm_id']) : null;
    $budget = floatval($_POST['budget'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $stage = in_array($_POST['stage'] ?? '', ['Planning', 'Earthwork', 'Framing', 'Roofing', 'Finishing', 'Handover']) ? $_POST['stage'] : 'Planning';

    if (empty($project_name)) {
        $error_msg = "Project name cannot be empty.";
    } else {
        try {
            $pdo->beginTransaction();

            // Auto-generate project code: PRJ-YYYY-XXX
            $year = date('Y', strtotime($start_date));
            $seqStmt = $pdo->prepare("SELECT project_code FROM projects WHERE project_code LIKE ? ORDER BY id DESC LIMIT 1");
            $seqStmt->execute(["PRJ-{$year}-%"]);
            $lastCode = $seqStmt->fetchColumn();

            if ($lastCode && preg_match("/PRJ-{$year}-(\d+)/", $lastCode, $matches)) {
                $nextSeq = intval($matches[1]) + 1;
            } else {
                $maxStmt = $pdo->query("SELECT MAX(id) FROM projects");
                $nextSeq = ($maxStmt->fetchColumn() ?: 0) + 1;
            }
            $project_code = sprintf("PRJ-%s-%03d", $year, $nextSeq);

            // Fetch cached client name if client_id is given
            $client_name = null;
            if ($client_id) {
                $cStmt = $pdo->prepare("SELECT full_name FROM clients WHERE id = ?");
                $cStmt->execute([$client_id]);
                $client_name = $cStmt->fetchColumn() ?: null;
            }

            // Thumbnail Upload
            $thumbnail_url = uploadProjectThumbnail($_FILES['thumbnail'] ?? null);
            if (!$thumbnail_url) {
                // Fallback default architectural preview
                $thumbnail_url = 'https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=140&h=90&fit=crop';
            }

            $insStmt = $pdo->prepare("
                INSERT INTO projects 
                (project_name, project_code, client_id, client_name, pm_id, budget, location, start_date, end_date, stage, status, thumbnail_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)
            ");
            $insStmt->execute([
                $project_name, $project_code, $client_id, $client_name, $pm_id, $budget, $location, $start_date, $end_date, $stage, $thumbnail_url
            ]);

            $pdo->commit();
            $success_msg = "Project {$project_code} ({$project_name}) created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to create project: " . $e->getMessage();
        }
    }
}

// 2. Handle Edit Project Details
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_project') {
    $project_id = intval($_POST['project_id'] ?? 0);
    $project_name = trim($_POST['project_name'] ?? '');
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $budget = floatval($_POST['budget'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $stage = $_POST['stage'] ?? 'Planning';
    $status = $_POST['status'] ?? 'Active';

    if ($project_id > 0 && !empty($project_name)) {
        try {
            // Update client name if client_id updated
            $client_name = null;
            if ($client_id) {
                $cStmt = $pdo->prepare("SELECT full_name FROM clients WHERE id = ?");
                $cStmt->execute([$client_id]);
                $client_name = $cStmt->fetchColumn() ?: null;
            }

            $upd = $pdo->prepare("
                UPDATE projects 
                SET project_name = ?, client_id = ?, client_name = COALESCE(?, client_name), budget = ?, location = ?, start_date = ?, end_date = ?, stage = ?, status = ?
                WHERE id = ?
            ");
            $upd->execute([$project_name, $client_id, $client_name, $budget, $location, $start_date, $end_date, $stage, $status, $project_id]);

            $success_msg = "Project details updated successfully.";
        } catch (Exception $e) {
            $error_msg = "Error updating project: " . $e->getMessage();
        }
    }
}

// 3. Handle Reassign Project Manager
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_pm') {
    $project_id = intval($_POST['project_id'] ?? 0);
    $pm_id = !empty($_POST['pm_id']) ? intval($_POST['pm_id']) : null;

    if ($project_id > 0) {
        try {
            $upd = $pdo->prepare("UPDATE projects SET pm_id = ? WHERE id = ?");
            $upd->execute([$pm_id, $project_id]);
            $success_msg = "Project Manager assigned successfully.";
        } catch (Exception $e) {
            $error_msg = "Error assigning team: " . $e->getMessage();
        }
    }
}

// 4. Handle Archive Project
if (isset($_GET['action']) && $_GET['action'] === 'archive' && isset($_GET['id'])) {
    $project_id = intval($_GET['id']);
    try {
        $upd = $pdo->prepare("UPDATE projects SET status = 'Archived' WHERE id = ?");
        $upd->execute([$project_id]);
        $success_msg = "Project archived successfully.";
    } catch (Exception $e) {
        $error_msg = "Archive failed: " . $e->getMessage();
    }
}

try {
    // Fetch all active/available Project Managers for assignment
    $pms = $pdo->query("SELECT id, full_name, email, role FROM users WHERE role IN ('Project Manager', 'Admin') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch clients for assignment
    $clients = $pdo->query("SELECT id, full_name, company FROM clients ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Query all projects with live task progress and live bills calculation
    $query = "
        SELECT p.*,
               COALESCE(c.full_name, NULLIF(p.client_name, ''), 'Client') AS display_client,
               COALESCE(u.full_name, 'John Doe') AS pm_name,
               -- Live Task Progress %
               (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) AS total_tasks,
               (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'Done') AS done_tasks,
               -- Live Bills Total Spent
               (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE project_id = p.id) AS actual_spend
        FROM projects p
        LEFT JOIN clients c ON p.client_id = c.id
        LEFT JOIN users u ON p.pm_id = u.id
        WHERE p.status != 'Archived'
        ORDER BY p.id DESC
    ";
    $projects = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Helper function for stage badges matching specifications
function getStageBadgeStyle($stage) {
    switch ($stage) {
        case 'Earthwork':
            return ['bg' => '#ffedd5', 'color' => '#c2410c', 'class' => 'pill-earthwork'];
        case 'Framing':
            return ['bg' => '#fef9c3', 'color' => '#a16207', 'class' => 'pill-framing'];
        case 'Roofing':
            return ['bg' => '#eff6ff', 'color' => '#1d4ed8', 'class' => 'pill-roofing'];
        case 'Finishing':
            return ['bg' => '#f3e8ff', 'color' => '#7e22ce', 'class' => 'pill-finishing'];
        case 'Planning':
        case 'Handover':
        default:
            return ['bg' => '#f0fdf4', 'color' => '#16a34a', 'class' => 'pill-planning'];
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
    <title>All Projects - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (BuildNexus Standard) --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        
        /* Inputs & Filters */
        .search-wrapper { position: relative; max-width: 440px; flex-grow: 1; }
        .nexus-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .nexus-input { width: 100%; padding: 8px 12px 8px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff; }
        .nexus-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }
        .nexus-select { padding: 8px 35px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 170px; }

        /* Table */
        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.85rem; padding: 1rem; }
        .table tbody td { padding: 1.15rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        
        .project-thumb { width: 68px; height: 44px; border-radius: 6px; object-fit: cover; }
        .thumb-placeholder { width: 68px; height: 44px; border-radius: 6px; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
        .project-link { color: #10b981; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .project-link:hover { text-decoration: underline; color: #059669; }

        /* Status & Stage Pills */
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; text-align: center; }
        .pill-framing { background: #fef9c3; color: #a16207; }
        .pill-planning, .pill-handover { background: #f0fdf4; color: #16a34a; }
        .pill-finishing { background: #f3e8ff; color: #7e22ce; }
        .pill-earthwork { background: #ffedd5; color: #c2410c; }
        .pill-roofing { background: #eff6ff; color: #1d4ed8; }
        
        .pill-budget-track { background: #f0fdf4; color: #16a34a; }
        .pill-budget-under { background: #f0fdf4; color: #16a34a; }
        .pill-budget-over { background: #fef2f2; color: #dc2626; }

        /* Progress Bar */
        .progress-nexus { height: 6px; width: 80px; background: #f1f5f9; border-radius: 10px; overflow: hidden; display: inline-block; margin-right: 8px; vertical-align: middle; }
        .progress-fill { height: 100%; background: #22c55e; border-radius: 10px; }

        /* Buttons */
        .btn-new-project { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 18px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s; text-decoration: none; }
        .btn-new-project:hover { background-color: #16a34a; color: #fff; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
        .btn-nexus-primary { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; }
        .btn-nexus-primary:hover { background-color: #16a34a; color: #fff; }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Notification Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($success_msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($error_msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">All Projects</h1>
            <button class="btn-new-project" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                <i class="bi bi-plus-lg"></i> New Project
            </button>
        </div>

        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">Projects</h4>
                <p class="text-muted small">Manage all construction and design projects.</p>
            </div>

            <!-- Filters Bar -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="projectSearch" class="nexus-input" placeholder="Search projects by name, code, or client...">
                </div>
                <div class="d-flex gap-2">
                    <select id="pmFilter" class="nexus-select" onchange="filterProjects()">
                        <option value="All">All PMs</option>
                        <?php foreach($pms as $pm): ?>
                            <option value="<?= htmlspecialchars($pm['full_name']) ?>"><?= htmlspecialchars($pm['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="stageFilter" class="nexus-select" onchange="filterProjects()">
                        <option value="All">All Stages</option>
                        <option value="Planning">Planning</option>
                        <option value="Earthwork">Earthwork</option>
                        <option value="Framing">Framing</option>
                        <option value="Roofing">Roofing</option>
                        <option value="Finishing">Finishing</option>
                        <option value="Handover">Handover</option>
                    </select>
                </div>
            </div>

            <!-- Projects Table -->
            <div class="table-responsive">
                <table class="table align-middle" id="projectsTable">
                    <thead>
                        <tr>
                            <th width="8%"></th>
                            <th width="20%">Project</th>
                            <th width="15%">Client</th>
                            <th width="12%">PM</th>
                            <th width="12%">Status</th>
                            <th width="15%">Progress</th>
                            <th width="13%">Budget</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($projects) === 0): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No active projects found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($projects as $p): 
                            // 1. Calculate dynamic progress %
                            $totalTasks = intval($p['total_tasks']);
                            $doneTasks = intval($p['done_tasks']);
                            if ($totalTasks > 0) {
                                $progress = round(($doneTasks / $totalTasks) * 100);
                            } else {
                                $stageDefaults = [
                                    'Planning' => 10,
                                    'Earthwork' => 20,
                                    'Framing' => 45,
                                    'Roofing' => 60,
                                    'Finishing' => 85,
                                    'Handover' => 100
                                ];
                                $progress = $stageDefaults[$p['stage']] ?? 15;
                            }

                            // 2. Calculate dynamic budget status
                            $budgetVal = floatval($p['budget']);
                            $spendVal = floatval($p['actual_spend']);
                            if ($budgetVal > 0) {
                                $spendRatio = ($spendVal / $budgetVal);
                                if ($spendVal > $budgetVal) {
                                    $budgetStatus = 'Over Budget';
                                    $budgetPillClass = 'pill-budget-over';
                                } elseif ($spendRatio >= 0.90 && $spendRatio <= 1.00) {
                                    $budgetStatus = 'On Track';
                                    $budgetPillClass = 'pill-budget-track';
                                } else {
                                    $under = round((1 - $spendRatio) * 100);
                                    $budgetStatus = "{$under}% Under";
                                    $budgetPillClass = 'pill-budget-under';
                                }
                            } else {
                                $budgetStatus = 'On Track';
                                $budgetPillClass = 'pill-budget-track';
                            }

                            // 3. Stage styling
                            $stageBadge = getStageBadgeStyle($p['stage']);
                            $thumb = !empty($p['thumbnail_url']) ? $p['thumbnail_url'] : '';
                            if (!empty($thumb) && !str_starts_with($thumb, 'http') && !str_starts_with($thumb, '/')) {
                                $thumb = '../' . $thumb;
                            }
                        ?>
                        <tr class="project-row" 
                            data-pm="<?= htmlspecialchars($p['pm_name']) ?>" 
                            data-stage="<?= htmlspecialchars($p['stage']) ?>"
                            data-search="<?= htmlspecialchars(strtolower($p['project_name'] . ' ' . ($p['project_code'] ?? '') . ' ' . $p['display_client'] . ' ' . $p['pm_name'])) ?>">
                            <td>
                                <?php if (!empty($thumb)): ?>
                                    <img src="<?= htmlspecialchars($thumb) ?>" class="project-thumb" alt="Thumbnail">
                                <?php else: ?>
                                    <div class="thumb-placeholder"><i class="bi bi-building"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="project_overview.php?id=<?= $p['id'] ?>" class="project-link">
                                    <?= htmlspecialchars($p['project_name']) ?>
                                </a>
                                <?php if (!empty($p['project_code'])): ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($p['project_code']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($p['display_client']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($p['pm_name']) ?></td>
                            <td>
                                <span class="pill" style="background: <?= $stageBadge['bg'] ?>; color: <?= $stageBadge['color'] ?>;">
                                    <?= htmlspecialchars($p['stage']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="progress-nexus"><div class="progress-fill" style="width: <?= $progress ?>%"></div></div>
                                <span class="small text-muted"><?= $progress ?>%</span>
                            </td>
                            <td>
                                <span class="pill <?= $budgetPillClass ?>">
                                    <?= htmlspecialchars($budgetStatus) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <a class="dropdown-item small" href="project_overview.php?id=<?= $p['id'] ?>">
                                                <i class="bi bi-eye me-2 text-muted"></i> View Project
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small" href="#" 
                                               onclick="openEditProjectModal(<?= htmlspecialchars(json_encode($p)) ?>)">
                                                <i class="bi bi-pencil me-2 text-muted"></i> Edit Details
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small" href="#" 
                                               onclick="openAssignPmModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['project_name']) ?>', <?= intval($p['pm_id']) ?>)">
                                                <i class="bi bi-person-gear me-2 text-muted"></i> Assign Team
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item small text-danger" href="projects.php?action=archive&id=<?= $p['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to archive project \'<?= htmlspecialchars($p['project_name']) ?>\'?');">
                                                <i class="bi bi-archive me-2"></i> Archive Project
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: + New Project -->
    <div class="modal fade" id="newProjectModal" tabindex="-1" aria-labelledby="newProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_project">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="newProjectModalLabel">Create New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-muted">Project Name / Title</label>
                            <input type="text" name="project_name" class="form-control" placeholder="e.g. Modern Residential Villa" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Initial Construction Stage</label>
                            <select name="stage" class="form-select bg-white" required>
                                <option value="Planning" selected>Planning</option>
                                <option value="Earthwork">Earthwork</option>
                                <option value="Framing">Framing</option>
                                <option value="Roofing">Roofing</option>
                                <option value="Finishing">Finishing</option>
                                <option value="Handover">Handover</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Client</label>
                            <select name="client_id" class="form-select bg-white">
                                <option value="">Select Existing Client...</option>
                                <?php foreach($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['company'] ?: 'Individual') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Assigned Project Manager</label>
                            <select name="pm_id" class="form-select bg-white">
                                <option value="">Assign Project Manager...</option>
                                <?php foreach($pms as $pm): ?>
                                    <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Contract Budget (RS)</label>
                            <input type="number" step="0.01" name="budget" class="form-control fw-bold fs-6" value="1000000.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Site Location / Address</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Kandy Road, Colombo 07">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Target Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Estimated Handover Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+6 months')) ?>">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted">Project Thumbnail / Render (Max 10MB)</label>
                        <input type="file" name="thumbnail" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary px-4">Create Project</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Details -->
    <div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST">
                <input type="hidden" name="action" value="edit_project">
                <input type="hidden" name="project_id" id="editProjectId">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Edit Project Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-muted">Project Name</label>
                            <input type="text" name="project_name" id="editProjectName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Current Stage</label>
                            <select name="stage" id="editProjectStage" class="form-select bg-white" required>
                                <option value="Planning">Planning</option>
                                <option value="Earthwork">Earthwork</option>
                                <option value="Framing">Framing</option>
                                <option value="Roofing">Roofing</option>
                                <option value="Finishing">Finishing</option>
                                <option value="Handover">Handover</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Client</label>
                            <select name="client_id" id="editProjectClientId" class="form-select bg-white">
                                <option value="">Select Existing Client...</option>
                                <?php foreach($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Status</label>
                            <select name="status" id="editProjectStatus" class="form-select bg-white">
                                <option value="Active">Active</option>
                                <option value="Completed">Completed</option>
                                <option value="On Hold">On Hold</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Contract Budget (RS)</label>
                            <input type="number" step="0.01" name="budget" id="editProjectBudget" class="form-control fw-bold fs-6" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Site Location</label>
                            <input type="text" name="location" id="editProjectLocation" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Start Date</label>
                            <input type="date" name="start_date" id="editProjectStartDate" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Target Handover Date</label>
                            <input type="date" name="end_date" id="editProjectEndDate" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Assign Project Manager -->
    <div class="modal fade" id="assignPmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST">
                <input type="hidden" name="action" value="assign_pm">
                <input type="hidden" name="project_id" id="assignProjectId">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Assign Project Manager</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border small mb-3">
                        Project: <strong id="assignProjectNameTitle">-</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Lead Project Manager</label>
                        <select name="pm_id" id="assignPmSelect" class="form-select bg-white" required>
                            <option value="">Select Project Manager...</option>
                            <?php foreach($pms as $pm): ?>
                                <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['full_name']) ?> (<?= htmlspecialchars($pm['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary px-4">Confirm Team Assignment</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Multi-attribute client-side filter
        function filterProjects() {
            let filterText = document.getElementById('projectSearch').value.toLowerCase().trim();
            let selectedPm = document.getElementById('pmFilter').value;
            let selectedStage = document.getElementById('stageFilter').value;

            let rows = document.querySelectorAll(".project-row");
            rows.forEach(row => {
                let searchData = row.getAttribute('data-search') || '';
                let pm = row.getAttribute('data-pm') || '';
                let stage = row.getAttribute('data-stage') || '';

                let matchesText = (filterText === '' || searchData.includes(filterText));
                let matchesPm = (selectedPm === 'All' || pm === selectedPm);
                let matchesStage = (selectedStage === 'All' || stage === selectedStage);

                if (matchesText && matchesPm && matchesStage) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        document.getElementById('projectSearch').addEventListener('keyup', filterProjects);

        // Prepopulate Edit Modal
        function openEditProjectModal(project) {
            document.getElementById('editProjectId').value = project.id;
            document.getElementById('editProjectName').value = project.project_name || '';
            document.getElementById('editProjectClientId').value = project.client_id || '';
            document.getElementById('editProjectBudget').value = project.budget || 0;
            document.getElementById('editProjectLocation').value = project.location || '';
            document.getElementById('editProjectStartDate').value = project.start_date || '';
            document.getElementById('editProjectEndDate').value = project.end_date || '';
            document.getElementById('editProjectStage').value = project.stage || 'Planning';
            document.getElementById('editProjectStatus').value = project.status || 'Active';

            let editModal = new bootstrap.Modal(document.getElementById('editProjectModal'));
            editModal.show();
        }

        // Prepopulate Assign PM Modal
        function openAssignPmModal(projectId, projectName, currentPmId) {
            document.getElementById('assignProjectId').value = projectId;
            document.getElementById('assignProjectNameTitle').innerText = projectName;
            document.getElementById('assignPmSelect').value = currentPmId || '';

            let assignModal = new bootstrap.Modal(document.getElementById('assignPmModal'));
            assignModal.show();
        }
    </script>
</body>
</html>