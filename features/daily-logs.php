<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$success_msg = '';
$error_msg = '';

// Helper to handle multiple photo uploads
function handleLogPhotosUpload($files, $reportId, $pdo) {
    if (!empty($files['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/daily_logs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && $files['size'][$i] <= 10485760) {
                    $fileName = 'log_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $fileName)) {
                        $photoUrl = 'uploads/daily_logs/' . $fileName;
                        $pdo->prepare("INSERT INTO daily_report_photos (report_id, photo_url, caption) VALUES (?, ?, ?)")
                            ->execute([$reportId, $photoUrl, 'Site field photo']);
                    }
                }
            }
        }
    }
}

// 1. Handle POST Actions (Create & Delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create New Daily Log
    if ($action === 'create_log') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $report_date = !empty($_POST['report_date']) ? $_POST['report_date'] : date('Y-m-d');
        $weather = trim($_POST['weather_condition'] ?? 'Sunny, 30°C');
        $work_summary = trim($_POST['work_summary'] ?? '');
        $crew_count = intval($_POST['crew_count'] ?? 0);
        $subcontractor_notes = trim($_POST['subcontractor_notes'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['Draft', 'Submitted', 'Reviewed']) ? $_POST['status'] : 'Submitted';
        $foreman_id = $_SESSION['user_id'] ?? null;

        if ($project_id <= 0) {
            $error_msg = "Please select a valid project.";
        } elseif (empty($work_summary)) {
            $error_msg = "Work summary description is required.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO daily_reports 
                    (project_id, foreman_id, report_date, weather_condition, work_summary, crew_count, subcontractor_notes, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $project_id, $foreman_id, $report_date, $weather, $work_summary, $crew_count, $subcontractor_notes, $status
                ]);
                $newReportId = $pdo->lastInsertId();

                // Process attached photos
                if (isset($_FILES['photo_files'])) {
                    handleLogPhotosUpload($_FILES['photo_files'], $newReportId, $pdo);
                }

                $pdo->commit();
                $success_msg = "Daily site log recorded successfully!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_msg = "Failed to create log: " . $e->getMessage();
            }
        }
    }

    // Delete Daily Log
    elseif ($action === 'delete_log') {
        $report_id = intval($_POST['report_id'] ?? 0);
        if ($report_id > 0) {
            try {
                // Delete photo records and physical files
                $pStmt = $pdo->prepare("SELECT photo_url FROM daily_report_photos WHERE report_id = ?");
                $pStmt->execute([$report_id]);
                $photos = $pStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($photos as $pUrl) {
                    $fullPath = __DIR__ . '/../' . $pUrl;
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                $delStmt = $pdo->prepare("DELETE FROM daily_reports WHERE id = ?");
                $delStmt->execute([$report_id]);
                $success_msg = "Daily log #{$report_id} and associated photos deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Failed to delete daily log: " . $e->getMessage();
            }
        }
    }
}

// 2. Fetch Projects for Dropdown Filter & Creation Modal
try {
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Query All Daily Logs with Live Joins
    $sql = "
        SELECT dr.*,
               p.project_name, p.project_code,
               COALESCE(u.full_name, 'Sunil Perera') AS author_name,
               (SELECT COUNT(*) FROM daily_report_photos WHERE report_id = dr.id) AS photo_count
        FROM daily_reports dr
        JOIN projects p ON dr.project_id = p.id
        LEFT JOIN users u ON dr.foreman_id = u.id
        ORDER BY dr.report_date DESC, dr.id DESC
    ";
    $daily_logs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Daily Logs - BuildNexus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Pure CSS Design Standards matching BuildNexus */
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8fafc; 
            color: #1e293b; 
            margin: 0;
            padding: 0;
        }

        .main-container { 
            max-width: 1420px; 
            margin: 0 auto; 
            padding: 2.25rem 2.5rem; 
        }

        .page-header-title {
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0;
            letter-spacing: -0.02em;
        }

        .nexus-card { 
            background: #fff; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            padding: 1.75rem 2rem; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.03); 
        }
        
        /* Filter Header Controls */
        .search-wrapper { 
            position: relative; 
            max-width: 440px; 
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
            padding: 8px 14px 8px 40px; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 0.9rem; 
            background: #fff; 
            transition: all 0.2s;
        }
        .nexus-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }

        .nexus-select { 
            padding: 8px 36px 8px 14px; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 0.875rem; 
            color: #475569; 
            background: #fff; 
            appearance: none; 
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); 
            background-repeat: no-repeat; 
            background-position: right 12px center; 
            min-width: 230px; 
            transition: all 0.2s;
        }
        .nexus-select:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }

        /* Table */
        .table thead th { 
            border-bottom: 1px solid #f1f5f9; 
            color: #64748b; 
            font-weight: 500; 
            font-size: 0.85rem; 
            padding: 1.1rem 1rem; 
        }
        .table tbody td { 
            padding: 1.25rem 1rem; 
            border-bottom: 1px solid #f8fafc; 
            font-size: 0.875rem; 
            vertical-align: middle; 
        }
        
        .project-link { 
            color: #10b981; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 0.9rem;
            transition: color 0.15s;
        }
        .project-link:hover { 
            color: #059669; 
            text-decoration: underline; 
        }

        /* Status Pills */
        .pill { 
            padding: 4px 14px; 
            border-radius: 20px; 
            font-size: 0.73rem; 
            font-weight: 600; 
            display: inline-block; 
            letter-spacing: 0.02em;
        }
        .pill-submitted { 
            background: #f0fdf4; 
            color: #16a34a; 
        }
        .pill-draft { 
            background: #f1f5f9; 
            color: #64748b; 
        }
        .pill-reviewed { 
            background: #eff6ff; 
            color: #2563eb; 
        }

        /* Action Buttons */
        .btn-export { 
            background: #fff; 
            border: 1px solid #e2e8f0; 
            color: #1e293b; 
            font-weight: 600; 
            border-radius: 8px; 
            padding: 8px 18px; 
            font-size: 0.875rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-export:hover { 
            background: #f8fafc; 
            color: #0f172a;
            border-color: #cbd5e1;
        }

        .btn-new-log { 
            background-color: #22c55e; 
            border: none; 
            color: #fff; 
            font-weight: 600; 
            border-radius: 8px; 
            padding: 8px 18px; 
            font-size: 0.875rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-new-log:hover { 
            background-color: #16a34a; 
            color: #fff;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.25);
        }

        .btn-nexus-primary {
            background-color: #22c55e;
            border: none;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 18px;
        }
        .btn-nexus-primary:hover {
            background-color: #16a34a;
            color: #fff;
        }

        .action-dot-btn {
            background: transparent;
            border: none;
            padding: 4px 8px;
            color: #94a3b8;
            font-size: 1.1rem;
            transition: color 0.15s;
        }
        .action-dot-btn:hover {
            color: #0f172a;
        }

        /* Modal styling */
        .modal-content {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 1.25rem 1.5rem;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 1rem 1.5rem;
        }
    </style>
</head>
<body>

    <div class="main-container">

        <!-- Flash Notifications -->
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
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h1 class="page-header-title">Daily Logs</h1>
            <div class="d-flex gap-2">
                <a href="export_daily_logs.php" id="exportBtn" class="btn-export">
                    <i class="bi bi-box-arrow-up"></i> Export All
                </a>
                <button type="button" class="btn-new-log" data-bs-toggle="modal" data-bs-target="#newLogModal">
                    <i class="bi bi-plus-lg"></i> New Log
                </button>
            </div>
        </div>

        <!-- Main Card -->
        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1 text-dark">All Logs</h4>
                <p class="text-muted small mb-0">View and manage all daily logs from all projects.</p>
            </div>

            <!-- Filter Row -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="logSearch" class="nexus-input" placeholder="Search logs...">
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <select id="projectFilter" class="nexus-select">
                        <option value="All">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= htmlspecialchars($proj['project_name']) ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="position-relative" style="min-width: 190px;">
                        <i class="bi bi-calendar3 nexus-icon"></i>
                        <input type="date" id="dateFilter" class="nexus-input" placeholder="Pick a date">
                    </div>

                    <button id="clearFiltersBtn" class="btn btn-sm btn-outline-secondary d-none" title="Clear all filters">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Responsive Table -->
            <div class="table-responsive">
                <table class="table align-middle" id="logsTable">
                    <thead>
                        <tr>
                            <th width="14%">Date</th>
                            <th width="26%">Project</th>
                            <th width="20%">Author</th>
                            <th width="20%">Weather</th>
                            <th width="15%">Status</th>
                            <th width="5%" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <?php if (empty($daily_logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-journal-x fs-2 d-block mb-2 text-secondary"></i>
                                    No daily logs recorded yet. Click "+ New Log" to add one.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($daily_logs as $log): 
                            $statusClass = 'pill-submitted';
                            if ($log['status'] === 'Draft') $statusClass = 'pill-draft';
                            if ($log['status'] === 'Reviewed') $statusClass = 'pill-reviewed';

                            $logJson = htmlspecialchars(json_encode([
                                'id' => $log['id'],
                                'project_name' => $log['project_name'],
                                'project_code' => $log['project_code'] ?? '',
                                'author_name' => $log['author_name'],
                                'report_date' => $log['report_date'],
                                'weather_condition' => $log['weather_condition'] ?: 'Sunny, 30°C',
                                'crew_count' => $log['crew_count'],
                                'work_summary' => $log['work_summary'],
                                'subcontractor_notes' => $log['subcontractor_notes'] ?? '',
                                'status' => $log['status'],
                                'photo_count' => $log['photo_count']
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="log-row" 
                            data-project="<?= htmlspecialchars(strtolower($log['project_name'])) ?>"
                            data-author="<?= htmlspecialchars(strtolower($log['author_name'])) ?>"
                            data-date="<?= htmlspecialchars($log['report_date']) ?>"
                            data-search="<?= htmlspecialchars(strtolower($log['project_name'] . ' ' . $log['author_name'] . ' ' . $log['work_summary'] . ' ' . ($log['project_code'] ?? ''))) ?>">
                            
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($log['report_date']) ?></td>
                            <td>
                                <a href="daily_log_view.php?id=<?= $log['id'] ?>" class="project-link">
                                    <?= htmlspecialchars($log['project_name']) ?>
                                </a>
                                <?php if (!empty($log['project_code'])): ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($log['project_code']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($log['author_name']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($log['weather_condition'] ?: 'Sunny, 30°C') ?></td>
                            <td>
                                <span class="pill <?= $statusClass ?>">
                                    <?= htmlspecialchars($log['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="action-dot-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2">
                                        <li>
                                            <a class="dropdown-item small" href="#" onclick="openQuickViewModal(<?= $logJson ?>)">
                                                <i class="bi bi-eye me-2 text-muted"></i> View Log
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small" href="generate_daily_log_pdf.php?id=<?= $log['id'] ?>" target="_blank">
                                                <i class="bi bi-file-earmark-pdf me-2 text-muted"></i> Download PDF
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <a class="dropdown-item small text-danger" href="#" onclick="confirmDeleteLog(<?= $log['id'] ?>, '<?= htmlspecialchars(addslashes($log['project_name'])) ?>', '<?= $log['report_date'] ?>')">
                                                <i class="bi bi-trash3 me-2"></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr id="noResultsRow" class="d-none">
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-search fs-3 d-block mb-2 text-secondary"></i>
                                No matching daily logs found for the selected criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 1: "+ New Log" Modal                                    -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="newLogModal" tabindex="-1" aria-labelledby="newLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="daily-logs.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_log">
                    
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="newLogModalLabel">
                            <i class="bi bi-journal-plus text-success me-2"></i>Create New Daily Site Log
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <!-- Project Selection -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Project <span class="text-danger">*</span></label>
                                <select name="project_id" class="form-select bg-white" required>
                                    <option value="">Select Project Site...</option>
                                    <?php foreach ($projects as $prj): ?>
                                        <option value="<?= $prj['id'] ?>">
                                            <?= htmlspecialchars($prj['project_name']) ?> <?= !empty($prj['project_code']) ? '(' . htmlspecialchars($prj['project_code']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Date Picker -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Log Date <span class="text-danger">*</span></label>
                                <input type="date" name="report_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Weather Condition Input -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Weather Condition</label>
                                <div class="input-group">
                                    <input type="text" name="weather_condition" id="weatherInput" class="form-control" value="Sunny, 32°C" placeholder="e.g. Sunny, 32°C">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="setWeather('Sunny, 32°C')">☀️ Sunny, 32°C</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setWeather('Partly Cloudy, 30°C')">⛅ Partly Cloudy, 30°C</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setWeather('Rainy, 28°C')">🌧️ Rainy, 28°C</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setWeather('Stormy, 26°C')">⛈️ Stormy, 26°C</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setWeather('Clear, 29°C')">🌤️ Clear, 29°C</a></li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Crew Count / Manpower -->
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">On-Site Crew Count</label>
                                <input type="number" name="crew_count" class="form-control" value="12" min="0">
                            </div>

                            <!-- Initial Status -->
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-muted">Status</label>
                                <select name="status" class="form-select bg-white">
                                    <option value="Submitted" selected>Submitted</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Reviewed">Reviewed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Work Summary -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Work Summary & Progress <span class="text-danger">*</span></label>
                            <textarea name="work_summary" class="form-control" rows="4" placeholder="Detail the construction progress, tasks completed, structural elements worked on, or material deliveries..." required></textarea>
                        </div>

                        <!-- Subcontractor Notes -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Subcontractor Activity & Notes <span class="text-muted fw-normal">(Optional)</span></label>
                            <textarea name="subcontractor_notes" class="form-control" rows="2" placeholder="Trades on site, specialized equipment used, testing or safety observations..."></textarea>
                        </div>

                        <!-- Multi-file Photo Upload -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Photographic Evidence / Site Photos <span class="text-muted fw-normal">(JPG, PNG, WEBP up to 10MB)</span></label>
                            <input type="file" name="photo_files[]" class="form-control" multiple accept="image/*">
                            <div class="form-text small">You can select multiple photos simultaneously to create a site audit gallery.</div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-nexus-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Submit Daily Log
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 2: Quick "View Log" Preview Modal                       -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="quickViewModal" tabindex="-1" aria-labelledby="quickViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="quickViewProjectTitle"></h5>
                        <div class="text-muted small" id="quickViewMetaSubtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">WEATHER</div>
                                <div class="fw-semibold text-dark" id="quickViewWeather"></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">AUTHOR</div>
                                <div class="fw-semibold text-dark" id="quickViewAuthor"></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">CREW COUNT</div>
                                <div class="fw-semibold text-dark" id="quickViewCrew"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold small text-muted text-uppercase mb-1">Work Summary</h6>
                        <div class="p-3 bg-light rounded border text-dark" id="quickViewSummary" style="white-space: pre-line; line-height: 1.6;"></div>
                    </div>

                    <div id="quickViewSubNotesWrapper" class="mb-3 d-none">
                        <h6 class="fw-bold small text-muted text-uppercase mb-1">Subcontractor Activity</h6>
                        <div class="p-3 bg-light rounded border text-muted small" id="quickViewSubNotes" style="white-space: pre-line;"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <a id="quickViewPdfBtn" href="#" target="_blank" class="btn btn-outline-dark btn-sm me-2">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                        </a>
                        <a id="quickViewFullBtn" href="#" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Full Page
                        </a>
                    </div>
                    <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Deletion -->
    <form id="deleteLogForm" method="POST" action="daily-logs.php">
        <input type="hidden" name="action" value="delete_log">
        <input type="hidden" name="report_id" id="deleteReportId">
    </form>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Weather helper dropdown
        function setWeather(val) {
            document.getElementById('weatherInput').value = val;
        }

        // Multi-attribute live filtering
        const searchInput = document.getElementById('logSearch');
        const projectFilter = document.getElementById('projectFilter');
        const dateFilter = document.getElementById('dateFilter');
        const clearBtn = document.getElementById('clearFiltersBtn');
        const exportBtn = document.getElementById('exportBtn');

        function filterLogs() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedProject = projectFilter.value.toLowerCase();
            const selectedDate = dateFilter.value.trim();

            const isFiltered = (query !== '' || selectedProject !== 'all' || selectedDate !== '');
            clearBtn.classList.toggle('d-none', !isFiltered);

            // Update CSV export link with active filters
            let exportUrl = 'export_daily_logs.php?';
            if (query !== '') exportUrl += 'search=' + encodeURIComponent(query) + '&';
            if (selectedDate !== '') exportUrl += 'date=' + encodeURIComponent(selectedDate) + '&';
            exportBtn.href = exportUrl;

            const rows = document.querySelectorAll('.log-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                const projectData = row.getAttribute('data-project') || '';
                const dateData = row.getAttribute('data-date') || '';

                const matchesQuery = (query === '' || searchData.includes(query));
                const matchesProject = (selectedProject === 'all' || projectData === selectedProject);
                const matchesDate = (selectedDate === '' || dateData === selectedDate);

                if (matchesQuery && matchesProject && matchesDate) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const noResultsRow = document.getElementById('noResultsRow');
            if (noResultsRow) {
                noResultsRow.classList.toggle('d-none', visibleCount > 0 || rows.length === 0);
            }
        }

        searchInput.addEventListener('input', filterLogs);
        projectFilter.addEventListener('change', filterLogs);
        dateFilter.addEventListener('change', filterLogs);

        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            projectFilter.value = 'All';
            dateFilter.value = '';
            filterLogs();
        });

        // Quick View Modal
        function openQuickViewModal(log) {
            document.getElementById('quickViewProjectTitle').innerText = log.project_name;
            document.getElementById('quickViewMetaSubtitle').innerText = log.report_date + ' • Report #DSR-' + String(log.id).padStart(5, '0');
            document.getElementById('quickViewWeather').innerText = log.weather_condition;
            document.getElementById('quickViewAuthor').innerText = log.author_name;
            document.getElementById('quickViewCrew').innerText = (log.crew_count || 0) + ' Workers';
            document.getElementById('quickViewSummary').innerText = log.work_summary;

            const subNotesWrapper = document.getElementById('quickViewSubNotesWrapper');
            if (log.subcontractor_notes && log.subcontractor_notes.trim() !== '') {
                subNotesWrapper.classList.remove('d-none');
                document.getElementById('quickViewSubNotes').innerText = log.subcontractor_notes;
            } else {
                subNotesWrapper.classList.add('d-none');
            }

            document.getElementById('quickViewPdfBtn').href = 'generate_daily_log_pdf.php?id=' + log.id;
            document.getElementById('quickViewFullBtn').href = 'daily_log_view.php?id=' + log.id;

            let qModal = new bootstrap.Modal(document.getElementById('quickViewModal'));
            qModal.show();
        }

        // Delete Confirmation
        function confirmDeleteLog(id, projectName, date) {
            if (confirm(`Are you sure you want to permanently delete Daily Log #${id} for ${projectName} on ${date}?`)) {
                document.getElementById('deleteReportId').value = id;
                document.getElementById('deleteLogForm').submit();
            }
        }
    </script>
</body>
</html>