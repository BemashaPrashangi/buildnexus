<?php
// features/floor-plans.php - Production-Ready Floor Plans & Architectural Blueprint Management
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$user_id = $_SESSION['user_id'] ?? 1;

// Flash message handler
$flash_msg = null;
if (isset($_GET['msg'])) {
    $msg_map = [
        'uploaded'     => ['type' => 'success', 'text' => 'New blueprint drawing uploaded and registered successfully!'],
        'revised'      => ['type' => 'success', 'text' => 'New blueprint revision published. Previous version marked as Superseded.'],
        'deleted'      => ['type' => 'warning', 'text' => 'Blueprint record and associated spatial pins deleted.'],
        'not_found'    => ['type' => 'danger',  'text' => 'Requested blueprint drawing could not be found.'],
        'invalid_file' => ['type' => 'danger',  'text' => 'Invalid file format. Please upload PDF, PNG, JPG, or WEBP drawings.']
    ];
    if (isset($msg_map[$_GET['msg']])) {
        $flash_msg = $msg_map[$_GET['msg']];
    }
}

// Handle Direct Upload Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_plan') {
    $project_id = intval($_POST['project_id'] ?? 0);
    $plan_code = trim($_POST['plan_code'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $discipline = $_POST['discipline'] ?? 'Architectural';
    $version_tag = trim($_POST['version_tag'] ?? 'Rev 1.0');
    $status = $_POST['status'] ?? 'Approved for Construction';

    $file_url = '';
    if (isset($_FILES['plan_file']) && $_FILES['plan_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/blueprints/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['plan_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp'])) {
            $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['plan_file']['name']);
            if (move_uploaded_file($_FILES['plan_file']['tmp_name'], $upload_dir . $safe_name)) {
                $file_url = '../uploads/blueprints/' . $safe_name;
            }
        }
    } elseif (!empty($_POST['file_url'])) {
        $file_url = trim($_POST['file_url']);
    }

    if (empty($file_url)) {
        // Fallback default sample architectural layout
        $file_url = 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1600&auto=format&fit=crop';
    }

    if ($project_id > 0 && !empty($plan_code) && !empty($title)) {
        $ins = $pdo->prepare("
            INSERT INTO project_floor_plans 
            (project_id, plan_code, title, discipline, file_url, version_tag, status, uploaded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $ins->execute([$project_id, $plan_code, $title, $discipline, $file_url, $version_tag, $status, $user_id]);
        header("Location: floor-plans.php?msg=uploaded");
        exit();
    }
}

// 1. Fetch Projects for filter and upload modal
try {
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
}

// 2. Fetch Filters
$filter_project = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$filter_discipline = !empty($_GET['discipline']) ? trim($_GET['discipline']) : '';
$filter_status = !empty($_GET['status']) ? trim($_GET['status']) : '';
$filter_search = !empty($_GET['search']) ? trim($_GET['search']) : '';
$view_mode = $_GET['view'] ?? 'grid';

// 3. Query Floor Plans with Pins Count & Project Details
$sql = "
    SELECT fp.*, p.project_name, p.project_code, u.full_name as author_name,
           (SELECT COUNT(*) FROM floor_plan_pins fpp WHERE fpp.floor_plan_id = fp.id) as pins_count
    FROM project_floor_plans fp 
    JOIN projects p ON fp.project_id = p.id 
    LEFT JOIN users u ON fp.uploaded_by = u.id 
    WHERE 1=1
";
$params = [];

if ($filter_project > 0) {
    $sql .= " AND fp.project_id = ?";
    $params[] = $filter_project;
}
if (!empty($filter_discipline) && $filter_discipline !== 'All') {
    $sql .= " AND fp.discipline = ?";
    $params[] = $filter_discipline;
}
if (!empty($filter_status) && $filter_status !== 'All') {
    $sql .= " AND fp.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_search)) {
    $sql .= " AND (fp.plan_code LIKE ? OR fp.title LIKE ? OR p.project_name LIKE ?)";
    $term = "%{$filter_search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY fp.created_at DESC, fp.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $floor_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // KPI Metrics
    $total_plans = count($floor_plans);
    $approved_count = 0;
    $review_count = 0;
    $total_pins = 0;

    foreach ($floor_plans as $fp) {
        if ($fp['status'] === 'Approved for Construction') $approved_count++;
        if ($fp['status'] === 'Under Review') $review_count++;
        $total_pins += intval($fp['pins_count']);
    }

} catch (Exception $e) {
    $floor_plans = [];
    $total_plans = 0;
    $approved_count = 0;
    $review_count = 0;
    $total_pins = 0;
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
    <title>Floor Plans & Blueprints - BuildNexus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- BuildNexus Design Language (Zero Tailwind) --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }

        .main-container {
            padding: 2.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .nexus-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        /* Metric KPI Summary Cards */
        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            height: 100%;
        }

        .kpi-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .kpi-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* Filter Controls */
        .search-wrapper {
            position: relative;
            max-width: 420px;
            flex-grow: 1;
        }

        .nexus-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .nexus-input {
            width: 100%;
            padding: 9px 14px 9px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            background: #ffffff;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .nexus-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
        }

        .nexus-select {
            padding: 9px 34px 9px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #475569;
            background-color: #ffffff;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }

        .nexus-select:focus {
            outline: none;
            border-color: #22c55e;
        }

        /* Action Buttons */
        .btn-upload-plan {
            background-color: #22c55e;
            border: none;
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color 0.15s;
        }

        .btn-upload-plan:hover {
            background-color: #16a34a;
            color: #ffffff;
        }

        /* Blueprint Card Matrix */
        .plan-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: transform 0.15s, box-shadow 0.15s;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .plan-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            border-color: #cbd5e1;
        }

        .plan-thumb-container {
            width: 100%;
            height: 190px;
            position: relative;
            background-color: #0f172a;
            overflow: hidden;
            cursor: pointer;
        }

        .plan-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .plan-card:hover .plan-thumb {
            transform: scale(1.04);
        }

        .thumb-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .plan-card:hover .thumb-overlay {
            opacity: 1;
        }

        /* Discipline Badges */
        .badge-discipline {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .disc-architectural { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
        .disc-structural    { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .disc-plumbing      { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
        .disc-electrical    { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
        .disc-hvac          { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; }
        .disc-civil         { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }

        /* Status Pills */
        .status-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-block;
        }

        .pill-approved   { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .pill-review     { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
        .pill-superseded { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        /* Table Styling */
        .table thead th {
            border-bottom: 1px solid #f1f5f9;
            color: #64748b;
            font-weight: 500;
            font-size: 0.85rem;
            padding: 1rem;
            background: #ffffff;
        }

        .table tbody td {
            padding: 1.15rem 1rem;
            border-bottom: 1px solid #f8fafc;
            font-size: 0.875rem;
            vertical-align: middle;
            background: #ffffff;
        }

        .table tbody tr:hover td {
            background-color: #fafbfc;
        }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Top Navigation & Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small text-muted mb-1">
                        <li class="breadcrumb-item"><a href="../admin_dashboard.php" class="text-decoration-none text-muted">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Engineering & Blueprints</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-0">Floor Plans & Blueprints</h1>
            </div>
            <button class="btn-upload-plan" data-bs-toggle="modal" data-bs-target="#uploadPlanModal">
                <i class="bi bi-plus-lg"></i> Upload Plan
            </button>
        </div>

        <!-- Flash Message Notification -->
        <?php if ($flash_msg): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_msg['type']) ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($flash_msg['text']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <div class="kpi-title">Total Blueprints</div>
                    <div class="kpi-value text-dark"><?= number_format($total_plans) ?></div>
                    <small class="text-muted">Multi-discipline sheets</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <div class="kpi-title">Approved for Build</div>
                    <div class="kpi-value text-success"><?= number_format($approved_count) ?></div>
                    <small class="text-success"><i class="bi bi-check-circle me-1"></i>Field ready</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <div class="kpi-title">Under Review</div>
                    <div class="kpi-value text-warning"><?= number_format($review_count) ?></div>
                    <small class="text-warning">Pending architect sign-off</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <div class="kpi-title">Spatial Markup Pins</div>
                    <div class="kpi-value text-primary"><?= number_format($total_pins) ?></div>
                    <small class="text-primary"><i class="bi bi-geo-alt me-1"></i>Defects & RFIs pinned</small>
                </div>
            </div>
        </div>

        <!-- Main Card with Filter Controls -->
        <div class="nexus-card">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="blueprintSearch" class="nexus-input" placeholder="Search by plan code, title, or project..." value="<?= htmlspecialchars($filter_search) ?>" onkeyup="clientFilter()">
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Project Filter -->
                    <select id="projectFilter" class="nexus-select" onchange="applyFilters()">
                        <option value="0">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($filter_project == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Discipline Filter -->
                    <select id="disciplineFilter" class="nexus-select" onchange="applyFilters()">
                        <option value="All">All Disciplines</option>
                        <option value="Architectural" <?= ($filter_discipline === 'Architectural') ? 'selected' : '' ?>>Architectural</option>
                        <option value="Structural" <?= ($filter_discipline === 'Structural') ? 'selected' : '' ?>>Structural</option>
                        <option value="Plumbing" <?= ($filter_discipline === 'Plumbing') ? 'selected' : '' ?>>Plumbing</option>
                        <option value="Electrical" <?= ($filter_discipline === 'Electrical') ? 'selected' : '' ?>>Electrical</option>
                        <option value="HVAC" <?= ($filter_discipline === 'HVAC') ? 'selected' : '' ?>>HVAC</option>
                        <option value="Civil" <?= ($filter_discipline === 'Civil') ? 'selected' : '' ?>>Civil</option>
                    </select>

                    <!-- Status Filter -->
                    <select id="statusFilter" class="nexus-select" onchange="applyFilters()">
                        <option value="All">All Statuses</option>
                        <option value="Approved for Construction" <?= ($filter_status === 'Approved for Construction') ? 'selected' : '' ?>>Approved</option>
                        <option value="Under Review" <?= ($filter_status === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
                        <option value="Superseded" <?= ($filter_status === 'Superseded') ? 'selected' : '' ?>>Superseded</option>
                    </select>

                    <!-- Grid / Table View Switcher -->
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-secondary <?= ($view_mode === 'grid') ? 'active' : '' ?>" onclick="switchView('grid')" title="Grid View">
                            <i class="bi bi-grid-fill"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary <?= ($view_mode === 'table') ? 'active' : '' ?>" onclick="switchView('table')" title="Table View">
                            <i class="bi bi-list-ul"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Area: Grid View -->
            <div id="plansGridView" class="<?= ($view_mode === 'table') ? 'd-none' : '' ?>">
                <div class="row g-4" id="plansGridContainer">
                    <?php if (empty($floor_plans)): ?>
                        <div class="col-12 text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-diff display-4 text-secondary d-block mb-3"></i>
                            <h5>No architectural blueprints found matching criteria.</h5>
                            <p class="small text-muted">Click "+ Upload Plan" above to add drawings to your construction project.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($floor_plans as $fp): ?>
                            <?php
                                $disc_class = 'disc-' . strtolower($fp['discipline']);
                                $status_class = ($fp['status'] === 'Approved for Construction') ? 'pill-approved' : (($fp['status'] === 'Under Review') ? 'pill-review' : 'pill-superseded');
                            ?>
                            <div class="col-md-6 col-lg-4 plan-grid-col" data-code="<?= htmlspecialchars(strtolower($fp['plan_code'])) ?>" data-title="<?= htmlspecialchars(strtolower($fp['title'])) ?>" data-project="<?= htmlspecialchars(strtolower($fp['project_name'])) ?>">
                                <div class="plan-card">
                                    <!-- Drawing Thumbnail -->
                                    <div class="plan-thumb-container" onclick="window.location.href='plan_viewer.php?id=<?= $fp['id'] ?>'">
                                        <img src="<?= htmlspecialchars($fp['file_url']) ?>" class="plan-thumb" alt="<?= htmlspecialchars($fp['title']) ?>" onerror="this.src='https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600&auto=format&fit=crop'">
                                        <div class="thumb-overlay">
                                            <span class="btn btn-sm btn-light fw-bold px-3 shadow">
                                                <i class="bi bi-arrows-fullscreen me-1"></i> Open Interactive Viewer
                                            </span>
                                        </div>
                                        <div class="position-absolute top-0 start-0 m-2">
                                            <span class="badge-discipline <?= $disc_class ?>">
                                                <?= htmlspecialchars($fp['discipline']) ?>
                                            </span>
                                        </div>
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <span class="status-pill <?= $status_class ?>">
                                                <?= htmlspecialchars($fp['status']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Card Info -->
                                    <div class="p-3 d-flex flex-column flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark">
                                                    <span class="text-primary me-1"><?= htmlspecialchars($fp['plan_code']) ?>:</span> 
                                                    <?= htmlspecialchars($fp['title']) ?>
                                                </h6>
                                                <small class="text-success fw-medium d-block mt-1">
                                                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($fp['project_name']) ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-light text-dark border ms-2"><?= htmlspecialchars($fp['version_tag']) ?></span>
                                        </div>

                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="small text-muted">
                                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= intval($fp['pins_count']) ?> Pins Marked
                                            </span>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border p-1 px-2" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 10px;">
                                                    <li>
                                                        <a class="dropdown-item small" href="plan_viewer.php?id=<?= $fp['id'] ?>">
                                                            <i class="bi bi-binoculars text-primary me-2"></i> Open Drawing Viewer
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item small" href="javascript:void(0)" onclick="openRevisionModal(<?= $fp['id'] ?>, '<?= htmlspecialchars($fp['plan_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($fp['title'], ENT_QUOTES) ?>')">
                                                            <i class="bi bi-file-earmark-arrow-up text-success me-2"></i> Upload New Revision
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item small" href="<?= htmlspecialchars($fp['file_url']) ?>" target="_blank" download>
                                                            <i class="bi bi-download text-secondary me-2"></i> Download File
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <a class="dropdown-item small text-danger" href="javascript:void(0)" onclick="deletePlan(<?= $fp['id'] ?>, '<?= htmlspecialchars($fp['plan_code'], ENT_QUOTES) ?>')">
                                                            <i class="bi bi-trash3 me-2"></i> Delete Plan
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content Area: Table View -->
            <div id="plansTableView" class="table-responsive <?= ($view_mode === 'grid') ? 'd-none' : '' ?>">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th width="15%">Plan Code</th>
                            <th width="30%">Sheet Title</th>
                            <th width="20%">Project</th>
                            <th width="12%">Discipline</th>
                            <th width="8%">Version</th>
                            <th width="10%">Status</th>
                            <th width="5%" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody id="plansTableBody">
                        <?php foreach ($floor_plans as $fp): ?>
                            <?php
                                $disc_class = 'disc-' . strtolower($fp['discipline']);
                                $status_class = ($fp['status'] === 'Approved for Construction') ? 'pill-approved' : (($fp['status'] === 'Under Review') ? 'pill-review' : 'pill-superseded');
                            ?>
                            <tr class="plan-table-row" data-code="<?= htmlspecialchars(strtolower($fp['plan_code'])) ?>" data-title="<?= htmlspecialchars(strtolower($fp['title'])) ?>" data-project="<?= htmlspecialchars(strtolower($fp['project_name'])) ?>">
                                <td class="fw-bold text-primary">
                                    <a href="plan_viewer.php?id=<?= $fp['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($fp['plan_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($fp['title']) ?></div>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1 text-danger"></i><?= intval($fp['pins_count']) ?> Spatial Pins</small>
                                </td>
                                <td class="text-success fw-medium"><?= htmlspecialchars($fp['project_name']) ?></td>
                                <td><span class="badge-discipline <?= $disc_class ?>"><?= htmlspecialchars($fp['discipline']) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($fp['version_tag']) ?></span></td>
                                <td><span class="status-pill <?= $status_class ?>"><?= htmlspecialchars($fp['status']) ?></span></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border p-1 px-2" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                            <li><a class="dropdown-item small" href="plan_viewer.php?id=<?= $fp['id'] ?>"><i class="bi bi-binoculars text-primary me-2"></i> Viewer</a></li>
                                            <li><a class="dropdown-item small" href="javascript:void(0)" onclick="openRevisionModal(<?= $fp['id'] ?>, '<?= htmlspecialchars($fp['plan_code'], ENT_QUOTES) ?>', '<?= htmlspecialchars($fp['title'], ENT_QUOTES) ?>')"><i class="bi bi-file-earmark-arrow-up text-success me-2"></i> New Revision</a></li>
                                            <li><a class="dropdown-item small" href="<?= htmlspecialchars($fp['file_url']) ?>" target="_blank" download><i class="bi bi-download text-secondary me-2"></i> Download</a></li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li><a class="dropdown-item small text-danger" href="javascript:void(0)" onclick="deletePlan(<?= $fp['id'] ?>, '<?= htmlspecialchars($fp['plan_code'], ENT_QUOTES) ?>')"><i class="bi bi-trash3 me-2"></i> Delete</a></li>
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

    <!-- ========================================== -->
    <!-- MODAL: + Upload Plan                       -->
    <!-- ========================================== -->
    <div class="modal fade" id="uploadPlanModal" tabindex="-1" aria-labelledby="uploadPlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold modal-title" id="uploadPlanModalLabel">Upload Architectural Blueprint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="floor-plans.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_plan">

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Target Project</label>
                                <select name="project_id" class="form-select" required>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_code'] ?? 'PRJ') ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Discipline</label>
                                <select name="discipline" class="form-select" required>
                                    <option value="Architectural" selected>Architectural (A)</option>
                                    <option value="Structural">Structural (S)</option>
                                    <option value="Plumbing">Plumbing (P)</option>
                                    <option value="Electrical">Electrical (E)</option>
                                    <option value="HVAC">HVAC / Mechanical (M)</option>
                                    <option value="Civil">Civil / Site (C)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted mb-1">Plan Code / Sheet No.</label>
                                <input type="text" name="plan_code" class="form-control" placeholder="e.g. A-102" required>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-muted mb-1">Sheet Title</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. First Floor Partition & Framing Layout" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Revision / Version Tag</label>
                                <input type="text" name="version_tag" class="form-control" value="Rev 1.0" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Approval Status</label>
                                <select name="status" class="form-select">
                                    <option value="Approved for Construction" selected>Approved for Construction</option>
                                    <option value="Under Review">Under Review</option>
                                    <option value="Superseded">Superseded</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Drawing File (PDF, PNG, JPG, WEBP)</label>
                                <input type="file" name="plan_file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp">
                                <div class="form-text small">Upload high-resolution vector drawing or image scan up to 50MB.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Or Drawing URL (Optional)</label>
                                <input type="url" name="file_url" class="form-control" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-upload-plan">Upload & Publish</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: Upload New Revision                 -->
    <!-- ========================================== -->
    <div class="modal fade" id="newRevisionModal" tabindex="-1" aria-labelledby="newRevisionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold modal-title" id="newRevisionModalLabel">Upload New Revision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="revisionForm" onsubmit="submitRevision(event)" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_revision">
                    <input type="hidden" name="parent_plan_id" id="revParentPlanId">

                    <div class="modal-body p-4">
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="small text-muted">Parent Drawing:</div>
                            <strong id="revPlanCodeDisplay" class="text-primary"></strong>: <span id="revPlanTitleDisplay"></span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">New Version Tag</label>
                            <input type="text" name="version_tag" id="revVersionTagInput" class="form-control" placeholder="e.g. Rev 2.0" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Revised Blueprint File</label>
                            <input type="file" name="revision_file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Status</label>
                            <select name="status" class="form-select">
                                <option value="Approved for Construction" selected>Approved for Construction</option>
                                <option value="Under Review">Under Review</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold">Publish Revision</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Client-side Instant Filter
        function clientFilter() {
            const query = document.getElementById('blueprintSearch').value.toLowerCase().trim();
            
            // Grid items
            document.querySelectorAll('.plan-grid-col').forEach(col => {
                const code = col.getAttribute('data-code') || '';
                const title = col.getAttribute('data-title') || '';
                const proj = col.getAttribute('data-project') || '';
                if (code.includes(query) || title.includes(query) || proj.includes(query)) {
                    col.style.display = '';
                } else {
                    col.style.display = 'none';
                }
            });

            // Table items
            document.querySelectorAll('.plan-table-row').forEach(row => {
                const code = row.getAttribute('data-code') || '';
                const title = row.getAttribute('data-title') || '';
                const proj = row.getAttribute('data-project') || '';
                if (code.includes(query) || title.includes(query) || proj.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Apply Server-side Filters via URL
        function applyFilters() {
            const projectId = document.getElementById('projectFilter').value;
            const discipline = document.getElementById('disciplineFilter').value;
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('blueprintSearch').value;

            const params = new URLSearchParams();
            if (projectId > 0) params.set('project_id', projectId);
            if (discipline && discipline !== 'All') params.set('discipline', discipline);
            if (status && status !== 'All') params.set('status', status);
            if (search) params.set('search', search);

            window.location.href = 'floor-plans.php?' + params.toString();
        }

        // Switch Grid & Table Views
        function switchView(mode) {
            const grid = document.getElementById('plansGridView');
            const table = document.getElementById('plansTableView');

            if (mode === 'table') {
                grid.classList.add('d-none');
                table.classList.remove('d-none');
            } else {
                table.classList.add('d-none');
                grid.classList.remove('d-none');
            }
        }

        // Open Revision Modal
        function openRevisionModal(planId, code, title) {
            document.getElementById('revParentPlanId').value = planId;
            document.getElementById('revPlanCodeDisplay').innerText = code;
            document.getElementById('revPlanTitleDisplay').innerText = title;
            document.getElementById('revVersionTagInput').value = 'Rev 2.0';

            const modal = new bootstrap.Modal(document.getElementById('newRevisionModal'));
            modal.show();
        }

        // Submit Revision Form
        function submitRevision(e) {
            e.preventDefault();
            const form = document.getElementById('revisionForm');
            const formData = new FormData(form);

            fetch('plan_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'floor-plans.php?msg=revised';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Network error publishing revision.'));
        }

        // Delete Plan
        function deletePlan(planId, code) {
            if (!confirm('Are you sure you want to delete blueprint ' + code + ' and all its spatial markup pins?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_plan');
            formData.append('plan_id', planId);

            fetch('plan_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'floor-plans.php?msg=deleted';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Network error deleting plan.'));
        }
    </script>
</body>
</html>
