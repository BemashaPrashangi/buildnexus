<?php
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$current_user_id = $_SESSION['user_id'] ?? 1;
$success_msg = '';
$error_msg = '';

// Handle Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'log_added') $success_msg = 'Operational shift and runtime hours logged successfully!';
    if ($_GET['msg'] === 'equipment_created') $success_msg = 'New fleet asset registered into the inventory!';
    if ($_GET['msg'] === 'reallocated') $success_msg = 'Equipment reassigned to project site successfully!';
    if ($_GET['msg'] === 'maintenance_scheduled') $success_msg = 'Maintenance service date and status updated!';
    if ($_GET['msg'] === 'decommissioned') $success_msg = 'Equipment status set to Decommissioned.';
    if ($_GET['msg'] === 'deleted') $success_msg = 'Equipment asset permanently deleted.';
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid_id') $error_msg = 'Invalid equipment asset selected.';
    if ($_GET['error'] === 'invalid_input') $error_msg = 'Please complete all required fields.';
    if ($_GET['error'] === 'duplicate_code') $error_msg = 'An asset with this Equipment Code already exists.';
    if ($_GET['error'] === 'db_error') $error_msg = 'Database error occurred while processing your request.';
}

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    // 1. Log Daily Usage
    if ($_POST['action'] === 'log_usage') {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $project_id = intval($_POST['project_id'] ?? 0);
        $log_date = !empty($_POST['log_date']) ? trim($_POST['log_date']) : date('Y-m-d');
        $hours_used = floatval($_POST['hours_used'] ?? 0);
        $fuel_liters = floatval($_POST['fuel_liters'] ?? 0);
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;

        if ($equipment_id <= 0 || $project_id <= 0 || $hours_used <= 0) {
            $error_msg = 'Please select equipment, project site, and enter valid operating hours.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO equipment_logs (equipment_id, project_id, foreman_id, log_date, hours_used, fuel_liters, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$equipment_id, $project_id, $current_user_id, $log_date, $hours_used, $fuel_liters, $notes]);

                // Update equipment location & status to In Use
                $upd = $pdo->prepare("UPDATE equipment SET current_project_id = ?, status = 'In Use' WHERE id = ?");
                $upd->execute([$project_id, $equipment_id]);

                header("Location: equipment-logs.php?msg=log_added");
                exit();
            } catch (PDOException $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }

    // 2. Register New Equipment
    if ($_POST['action'] === 'create_equipment') {
        $equipment_code = trim($_POST['equipment_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $plate_number = !empty($_POST['plate_number']) ? trim($_POST['plate_number']) : null;
        $type = in_array($_POST['type'] ?? '', ['Vehicle', 'Machinery', 'Small Tool']) ? $_POST['type'] : 'Machinery';
        $current_project_id = !empty($_POST['current_project_id']) && is_numeric($_POST['current_project_id']) ? intval($_POST['current_project_id']) : null;
        $status = in_array($_POST['status'] ?? '', ['Available', 'In Use', 'Maintenance', 'Decommissioned']) ? $_POST['status'] : ($current_project_id ? 'In Use' : 'Available');
        $next_service_date = !empty($_POST['next_service_date']) ? trim($_POST['next_service_date']) : null;
        $hourly_cost = floatval($_POST['hourly_operating_cost'] ?? 0.00);

        if (empty($name) || empty($equipment_code)) {
            $error_msg = 'Equipment Code and Name are mandatory.';
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM equipment WHERE equipment_code = ?");
                $check->execute([$equipment_code]);
                if ($check->fetch()) {
                    $error_msg = "Equipment Code '{$equipment_code}' is already registered.";
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO equipment (equipment_code, name, plate_number, type, current_project_id, status, next_service_date, hourly_operating_cost)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([
                        $equipment_code,
                        $name,
                        $plate_number,
                        $type,
                        $current_project_id,
                        $status,
                        $next_service_date,
                        $hourly_cost
                    ]);

                    header("Location: equipment-logs.php?msg=equipment_created");
                    exit();
                }
            } catch (PDOException $e) {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Filter and Equipment Lists
try {
    // 1. Projects for filter and modals
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Filter Parameters
    $filter_project = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    $filter_status = !empty($_GET['status']) ? trim($_GET['status']) : '';
    $filter_search = !empty($_GET['search']) ? trim($_GET['search']) : '';

    // 3. Equipment Query joining projects
    $query = "
        SELECT e.*, p.project_name, p.project_code,
               (SELECT COALESCE(SUM(hours_used), 0) FROM equipment_logs el WHERE el.equipment_id = e.id) AS total_hours_logged,
               (SELECT COUNT(*) FROM equipment_logs el WHERE el.equipment_id = e.id) AS log_count
        FROM equipment e
        LEFT JOIN projects p ON e.current_project_id = p.id
        WHERE 1=1
    ";
    $params = [];

    if ($filter_project > 0) {
        $query .= " AND e.current_project_id = ?";
        $params[] = $filter_project;
    }
    if (!empty($filter_status)) {
        $query .= " AND e.status = ?";
        $params[] = $filter_status;
    }
    if (!empty($filter_search)) {
        $query .= " AND (e.name LIKE ? OR e.equipment_code LIKE ? OR e.plate_number LIKE ? OR p.project_name LIKE ?)";
        $term = "%{$filter_search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $query .= " ORDER BY e.id ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
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
    <title>Equipment & Vehicle Logs - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (BuildNexus Design Language) --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        
        /* Filter Header UI */
        .search-wrapper { position: relative; max-width: 480px; flex-grow: 1; }
        .nexus-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.95rem; }
        .nexus-input { width: 100%; padding: 8px 14px 8px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff; transition: border-color 0.15s ease; }
        .nexus-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }

        .nexus-select { padding: 8px 36px 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.88rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 200px; cursor: pointer; }
        .nexus-select:focus { outline: none; border-color: #22c55e; }

        /* Status Pills Exact Specifications */
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .pill-in-use { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .pill-available { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .pill-maintenance { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
        .pill-decommissioned { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

        /* Project Links */
        .project-link { color: #16a34a; text-decoration: none; font-weight: 500; transition: color 0.15s ease; }
        .project-link:hover { color: #15803d; text-decoration: underline; }
        .project-link-na { color: #94a3b8; font-weight: 500; }

        .asset-id { font-size: 0.78rem; color: #94a3b8; margin-top: 2px; }

        /* Top Action Buttons */
        .btn-export { background-color: #fff; border: 1px solid #e2e8f0; color: #334155; font-weight: 600; border-radius: 8px; padding: 8px 18px; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.15s; }
        .btn-export:hover { background-color: #f8fafc; border-color: #cbd5e1; color: #0f172a; }

        .btn-new-log { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 18px; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: background-color 0.15s; }
        .btn-new-log:hover { background-color: #16a34a; color: #fff; }

        /* Table */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600; font-size: 0.875rem; padding: 1rem; }
        .table-custom tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tr:hover td { background-color: #fafafa; }

        .dropdown-menu { border-radius: 10px; border: 1px solid #e2e8f0; padding: 6px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07); }
        .dropdown-item { border-radius: 6px; padding: 6px 12px; font-weight: 500; font-size: 0.85rem; }
        .dropdown-item:hover { background-color: #f1f5f9; }
        .dropdown-item.text-danger:hover { background-color: #fef2f2; }

        /* Modal Tabs */
        .nav-tabs .nav-link { color: #64748b; font-weight: 600; font-size: 0.9rem; border: none; border-bottom: 2px solid transparent; padding: 0.5rem 1rem; }
        .nav-tabs .nav-link.active { color: #22c55e; border-bottom-color: #22c55e; background: none; }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0 text-dark">Equipment & Vehicle Logs</h1>
            <div class="d-flex gap-2">
                <a href="export_equipment.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" class="btn-export">
                    <i class="bi bi-download"></i> Export
                </a>
                <button class="btn-new-log" data-bs-toggle="modal" data-bs-target="#newLogModal">
                    <i class="bi bi-plus-lg"></i> New Log
                </button>
            </div>
        </div>

        <!-- Main Card -->
        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1 text-dark">All Equipment Logs</h4>
                <p class="text-muted small mb-0">Track and manage your company's equipment and vehicles.</p>
            </div>

            <!-- Filter Controls -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="eqSearch" class="nexus-input" placeholder="Search by name or ID..." value="<?= htmlspecialchars($filter_search) ?>">
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Project Dropdown Filter -->
                    <select class="nexus-select" id="projectFilter" onchange="applyFilters()">
                        <option value="">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= ($filter_project == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Status Dropdown Filter -->
                    <select class="nexus-select" id="statusFilter" onchange="applyFilters()">
                        <option value="">All Statuses</option>
                        <option value="In Use" <?= ($filter_status === 'In Use') ? 'selected' : '' ?>>In Use</option>
                        <option value="Available" <?= ($filter_status === 'Available') ? 'selected' : '' ?>>Available</option>
                        <option value="Maintenance" <?= ($filter_status === 'Maintenance') ? 'selected' : '' ?>>Maintenance</option>
                        <option value="Decommissioned" <?= ($filter_status === 'Decommissioned') ? 'selected' : '' ?>>Decommissioned</option>
                    </select>

                    <!-- Reset Filters Button -->
                    <?php if ($filter_project > 0 || !empty($filter_status) || !empty($filter_search)): ?>
                        <a href="equipment-logs.php" class="btn btn-sm btn-outline-secondary border rounded-3 px-3 py-2" title="Clear Filters">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Equipment Table -->
            <div class="table-responsive">
                <table class="table-custom" id="equipmentTable">
                    <thead>
                        <tr>
                            <th width="30%">Name / ID</th>
                            <th width="25%">Assigned Project</th>
                            <th width="20%">Status</th>
                            <th width="20%">Next Service</th>
                            <th width="5%" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($equipment)): ?>
                            <?php foreach ($equipment as $e): 
                                $statusClass = 'pill-' . strtolower(str_replace(' ', '-', $e['status']));
                                $isOverdue = !empty($e['next_service_date']) && ($e['next_service_date'] < date('Y-m-d'));
                                $searchData = strtolower(htmlspecialchars($e['name'] . ' ' . $e['equipment_code'] . ' ' . ($e['plate_number'] ?? '') . ' ' . ($e['project_name'] ?? '')));
                            ?>
                            <tr class="eq-row" 
                                data-project-id="<?= $e['current_project_id'] ?? '' ?>" 
                                data-status="<?= htmlspecialchars($e['status']) ?>" 
                                data-search="<?= $searchData ?>">
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($e['name']) ?></div>
                                    <div class="asset-id font-monospace"><?= htmlspecialchars($e['equipment_code'] ?? 'EQ-N/A') ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($e['project_name'])): ?>
                                        <a href="project_overview.php?id=<?= $e['current_project_id'] ?>" class="project-link">
                                            <?= htmlspecialchars($e['project_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="project-link-na">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="pill <?= $statusClass ?>">
                                        <?= htmlspecialchars($e['status']) ?>
                                    </span>
                                </td>
                                <td class="fw-semibold <?= $isOverdue ? 'text-danger' : 'text-muted' ?>">
                                    <?= !empty($e['next_service_date']) ? htmlspecialchars($e['next_service_date']) : '<span class="text-muted fw-normal">N/A</span>' ?>
                                    <?php if ($isOverdue): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Service Overdue"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn p-0 border-0 text-muted" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openHistoryModal(<?= $e['id'] ?>)">
                                                    <i class="bi bi-clock-history text-primary me-2"></i> View Log History
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openMaintenanceModal(<?= $e['id'] ?>, '<?= addslashes($e['name']) ?>', '<?= $e['next_service_date'] ?? '' ?>', '<?= $e['status'] ?>')">
                                                    <i class="bi bi-wrench text-warning me-2"></i> Schedule Maintenance
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openAssignModal(<?= $e['id'] ?>, '<?= addslashes($e['name']) ?>', '<?= $e['current_project_id'] ?? '' ?>')">
                                                    <i class="bi bi-geo-alt text-success me-2"></i> Assign to Project
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="openRemoveModal(<?= $e['id'] ?>, '<?= addslashes($e['name']) ?>')">
                                                    <i class="bi bi-trash me-2"></i> Remove
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noRecordsRow">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-truck fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <h6 class="fw-semibold">No equipment assets found</h6>
                                    <p class="small text-muted mb-0">Try clearing filters or click "+ New Log" to register an asset.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: + NEW LOG / ASSET ================= -->
    <div class="modal fade" id="newLogModal" tabindex="-1" aria-labelledby="newLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-0">
                    <ul class="nav nav-tabs border-bottom-0" id="logTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="usage-tab" data-bs-toggle="tab" data-bs-target="#usageTabContent" type="button" role="tab">
                                <i class="bi bi-speedometer2 me-1"></i> Log Daily Usage
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="asset-tab" data-bs-toggle="tab" data-bs-target="#assetTabContent" type="button" role="tab">
                                <i class="bi bi-truck-flatbed me-1"></i> Register New Equipment
                            </button>
                        </li>
                    </ul>
                    <button type="button" class="btn-close mb-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="tab-content" id="logTabPanes">
                    <!-- Tab 1: Log Daily Usage -->
                    <div class="tab-pane fade show active p-4" id="usageTabContent" role="tabpanel">
                        <form action="equipment-logs.php" method="POST">
                            <input type="hidden" name="action" value="log_usage">

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-muted">Select Machine / Vehicle <span class="text-danger">*</span></label>
                                    <select name="equipment_id" class="form-select rounded-3" required id="logModalEquipSelect" onchange="autoFillProject(this)">
                                        <option value="" disabled selected>Select Machine...</option>
                                        <?php foreach ($equipment as $eq): ?>
                                            <option value="<?= $eq['id'] ?>" data-project="<?= $eq['current_project_id'] ?? '' ?>">
                                                <?= htmlspecialchars($eq['name']) ?> (<?= htmlspecialchars($eq['equipment_code'] ?? 'EQ-N/A') ?>) - <?= htmlspecialchars($eq['status']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-muted">Project Jobsite <span class="text-danger">*</span></label>
                                    <select name="project_id" class="form-select rounded-3" required id="logModalProjSelect">
                                        <option value="" disabled selected>Select Project Site...</option>
                                        <?php foreach ($projects as $p): ?>
                                            <option value="<?= $p['id'] ?>">
                                                <?= htmlspecialchars($p['project_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Operation Date <span class="text-danger">*</span></label>
                                    <input type="date" name="log_date" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Runtime Hours <span class="text-danger">*</span></label>
                                    <input type="number" step="0.1" name="hours_used" class="form-control rounded-3" placeholder="e.g. 6.5" required min="0.1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Fuel Consumed (Liters)</label>
                                    <input type="number" step="0.1" name="fuel_liters" class="form-control rounded-3" placeholder="e.g. 45.0" min="0">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-muted">Operator Observations / Defect Notes</label>
                                <textarea name="notes" class="form-control rounded-3" rows="3" placeholder="Engine condition, tasks completed, track tension, hydraulic hose weepage, or repairs required..."></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-new-log px-4">
                                    <i class="bi bi-check-lg me-1"></i> Save Usage Log
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab 2: Register New Equipment -->
                    <div class="tab-pane fade p-4" id="assetTabContent" role="tabpanel">
                        <form action="equipment-logs.php" method="POST">
                            <input type="hidden" name="action" value="create_equipment">

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-muted">Equipment / Machine Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Komatsu PC200 Excavator" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-muted">Asset Code (ID) <span class="text-danger">*</span></label>
                                    <input type="text" name="equipment_code" class="form-control rounded-3 font-monospace" placeholder="e.g. EQ-007" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Asset Type <span class="text-danger">*</span></label>
                                    <select name="type" class="form-select rounded-3" required>
                                        <option value="Machinery" selected>Heavy Machinery</option>
                                        <option value="Vehicle">Vehicle / Fleet</option>
                                        <option value="Small Tool">Small Power Tool</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Plate / Serial Number</label>
                                    <input type="text" name="plate_number" class="form-control rounded-3" placeholder="e.g. WP-EX-4491">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Operating Cost ($/hr)</label>
                                    <input type="number" step="0.01" name="hourly_operating_cost" class="form-control rounded-3" placeholder="e.g. 85.00" value="0.00">
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Initial Assigned Project</label>
                                    <select name="current_project_id" class="form-select rounded-3">
                                        <option value="">None / Storage Yard</option>
                                        <?php foreach ($projects as $p): ?>
                                            <option value="<?= $p['id'] ?>">
                                                <?= htmlspecialchars($p['project_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Asset Status</label>
                                    <select name="status" class="form-select rounded-3">
                                        <option value="Available" selected>Available</option>
                                        <option value="In Use">In Use</option>
                                        <option value="Maintenance">Maintenance</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted">Next Scheduled Service</label>
                                    <input type="date" name="next_service_date" class="form-control rounded-3">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-new-log px-4">
                                    <i class="bi bi-plus-circle me-1"></i> Register Asset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: VIEW LOG HISTORY ================= -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Equipment Log History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="historyModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status"></div>
                        <div class="text-muted small mt-2">Loading shift history...</div>
                    </div>
                </div>
                <div class="modal-footer border-top pt-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: ASSIGN TO PROJECT ================= -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-geo-alt text-success me-2"></i>Assign Equipment to Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipment_action.php" method="POST" id="assignForm">
                    <input type="hidden" name="action" value="assign_project">
                    <input type="hidden" name="equipment_id" id="assignEquipmentId" value="">
                    
                    <div class="modal-body p-4">
                        <p class="mb-3 text-secondary small">Reallocate <strong id="assignEquipmentName" class="text-dark"></strong> to an active jobsite:</p>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Destination Project Site</label>
                            <select name="project_id" id="assignProjectId" class="form-select rounded-3">
                                <option value="">Storage Yard / Unassigned (Available)</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top pt-2">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-3">Update Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: SCHEDULE MAINTENANCE ================= -->
    <div class="modal fade" id="maintenanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-wrench text-warning me-2"></i>Schedule Maintenance Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipment_action.php" method="POST" id="maintenanceForm">
                    <input type="hidden" name="action" value="schedule_maintenance">
                    <input type="hidden" name="equipment_id" id="maintEquipmentId" value="">
                    
                    <div class="modal-body p-4">
                        <p class="mb-3 text-secondary small">Schedule servicing or inspection for <strong id="maintEquipmentName" class="text-dark"></strong>:</p>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Next Scheduled Service Date <span class="text-danger">*</span></label>
                            <input type="date" name="next_service_date" id="maintDateInput" class="form-control rounded-3" required>
                        </div>

                        <div class="form-check p-3 bg-light rounded-3 border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="set_maintenance" value="1" id="setMaintStatusCheck">
                            <label class="form-check-label small fw-semibold text-dark" for="setMaintStatusCheck">
                                Set status to "Maintenance" immediately (Take out of active site rotation)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer border-top pt-2">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning px-3 fw-semibold">Save Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: REMOVE EQUIPMENT ================= -->
    <div class="modal fade" id="removeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Remove Equipment Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">Are you sure you want to remove <strong id="removeEquipmentName"></strong> from the active fleet inventory?</p>
                    <div class="p-3 bg-light rounded-3 border small text-muted">
                        <i class="bi bi-info-circle me-1"></i> Decommissioning keeps historical runtime and fuel logs intact while marking the asset inactive.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                    <form action="equipment_action.php" method="POST" class="d-inline">
                        <input type="hidden" name="action" value="remove_equipment">
                        <input type="hidden" name="mode" value="decommission">
                        <input type="hidden" name="equipment_id" id="decomEquipmentId" value="">
                        <button type="submit" class="btn btn-warning px-3 fw-semibold">Decommission</button>
                    </form>
                    <form action="equipment_action.php" method="POST" class="d-inline">
                        <input type="hidden" name="action" value="remove_equipment">
                        <input type="hidden" name="mode" value="delete_permanently">
                        <input type="hidden" name="equipment_id" id="deleteEquipmentId" value="">
                        <button type="submit" class="btn btn-outline-danger px-3">Delete Permanently</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- REAL-TIME LIVE FILTERING ---
        function applyFilters() {
            const searchVal = document.getElementById('eqSearch').value.toLowerCase().trim();
            const projectVal = document.getElementById('projectFilter').value;
            const statusVal = document.getElementById('statusFilter').value;

            const rows = document.querySelectorAll(".eq-row");
            let visibleCount = 0;

            rows.forEach(row => {
                const rowProject = row.getAttribute('data-project-id');
                const rowStatus = row.getAttribute('data-status');
                const rowSearch = row.getAttribute('data-search') || '';

                const matchesSearch = !searchVal || rowSearch.includes(searchVal);
                const matchesProject = !projectVal || rowProject === projectVal;
                const matchesStatus = !statusVal || rowStatus === statusVal;

                if (matchesSearch && matchesProject && matchesStatus) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });

            // Handle no records row
            let noRecords = document.getElementById('noRecordsRow');
            if (visibleCount === 0) {
                if (!noRecords) {
                    const tbody = document.querySelector("#equipmentTable tbody");
                    const tr = document.createElement("tr");
                    tr.id = "noRecordsRow";
                    tr.innerHTML = `
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-truck fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-semibold">No matching equipment found</h6>
                            <p class="small text-muted mb-0">Try clearing filters or search terms.</p>
                        </td>
                    `;
                    tbody.appendChild(tr);
                } else {
                    noRecords.style.display = "";
                }
            } else if (noRecords) {
                noRecords.style.display = "none";
            }
        }

        document.getElementById('eqSearch').addEventListener('keyup', applyFilters);

        // Autofill project when selecting equipment in usage modal
        function autoFillProject(select) {
            const selectedOpt = select.options[select.selectedIndex];
            const projId = selectedOpt.getAttribute('data-project');
            const projSelect = document.getElementById('logModalProjSelect');
            if (projId) {
                projSelect.value = projId;
            }
        }

        // --- VIEW LOG HISTORY (AJAX) ---
        function openHistoryModal(equipmentId) {
            const modalEl = document.getElementById('historyModal');
            const modal = new bootstrap.Modal(modalEl);
            const body = document.getElementById('historyModalBody');

            body.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <div class="text-muted small mt-2">Loading shift history...</div>
                </div>
            `;
            modal.show();

            fetch(`equipment_history.php?id=${equipmentId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.text())
            .then(html => {
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = `<div class="alert alert-danger">Failed to load history: ${err.message}</div>`;
            });
        }

        // --- ASSIGN TO PROJECT MODAL ---
        function openAssignModal(id, name, currentProjId) {
            document.getElementById('assignEquipmentId').value = id;
            document.getElementById('assignEquipmentName').innerText = name;
            document.getElementById('assignProjectId').value = currentProjId || '';
            const modal = new bootstrap.Modal(document.getElementById('assignModal'));
            modal.show();
        }

        // --- SCHEDULE MAINTENANCE MODAL ---
        function openMaintenanceModal(id, name, nextDate, status) {
            document.getElementById('maintEquipmentId').value = id;
            document.getElementById('maintEquipmentName').innerText = name;
            document.getElementById('maintDateInput').value = nextDate || '';
            document.getElementById('setMaintStatusCheck').checked = (status === 'Maintenance');
            const modal = new bootstrap.Modal(document.getElementById('maintenanceModal'));
            modal.show();
        }

        // --- REMOVE CONFIRMATION MODAL ---
        function openRemoveModal(id, name) {
            document.getElementById('decomEquipmentId').value = id;
            document.getElementById('deleteEquipmentId').value = id;
            document.getElementById('removeEquipmentName').innerText = `"${name}"`;
            const modal = new bootstrap.Modal(document.getElementById('removeModal'));
            modal.show();
        }
    </script>
</body>
</html>