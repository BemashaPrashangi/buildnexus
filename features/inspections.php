<?php
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$success_msg = '';
$error_msg = '';

// Helper for file uploads
function uploadInspectionDoc($file) {
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/inspections/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp']) && $file['size'] <= 26214400) { // 25MB
            $filename = 'insp_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                return 'uploads/inspections/' . $filename;
            }
        }
    }
    return null;
}

// -------------------------------------------------------------------------
// Handle POST Actions
// -------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || !empty($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_POST['ajax']);

    // 1. Create New Inspection
    if ($action === 'create_inspection') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $inspection_type = trim($_POST['inspection_type'] ?? '');
        $inspector_name = trim($_POST['inspector_name'] ?? 'Municipal Inspector');
        $inspector_agency = trim($_POST['inspector_agency'] ?? 'City Council');
        $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : date('Y-m-d');
        $result_notes = trim($_POST['result_notes'] ?? '');
        $created_by = $_SESSION['user_id'] ?? null;

        if ($project_id <= 0 || empty($inspection_type)) {
            $error_msg = "Please select a project and specify the inspection type.";
        } else {
            try {
                $pdo->beginTransaction();

                // Auto-generate code INSP-YYYY-XXX
                $year = date('Y', strtotime($scheduled_date));
                $seqStmt = $pdo->prepare("SELECT inspection_code FROM project_inspections WHERE inspection_code LIKE ? ORDER BY id DESC LIMIT 1");
                $seqStmt->execute(["INSP-{$year}-%"]);
                $lastCode = $seqStmt->fetchColumn();

                if ($lastCode && preg_match("/INSP-{$year}-(\d+)/", $lastCode, $m)) {
                    $nextSeq = intval($m[1]) + 1;
                } else {
                    $maxStmt = $pdo->query("SELECT MAX(id) FROM project_inspections");
                    $nextSeq = ($maxStmt->fetchColumn() ?: 0) + 1;
                }
                $inspection_code = sprintf("INSP-%s-%03d", $year, $nextSeq);

                // Handle file upload if provided
                $report_file_url = uploadInspectionDoc($_FILES['report_file'] ?? null);

                $insStmt = $pdo->prepare("
                    INSERT INTO project_inspections 
                    (inspection_code, project_id, inspection_type, inspector_name, inspector_agency, scheduled_date, status, result_notes, report_file_url, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?, ?)
                ");
                $insStmt->execute([
                    $inspection_code, $project_id, $inspection_type, $inspector_name, $inspector_agency, $scheduled_date, $result_notes, $report_file_url, $created_by
                ]);
                $inspId = $pdo->lastInsertId();

                // Automatic Milestone Synchronization with project_milestones
                $msTitle = "{$inspection_type} Inspection: {$inspector_name}";
                $msStmt = $pdo->prepare("
                    INSERT INTO project_milestones 
                    (project_id, title, phase_name, event_type, start_date, status, color_hex, description, created_by)
                    VALUES (?, ?, ?, 'Inspection', ?, 'Upcoming', '#2563eb', ?, ?)
                ");
                $msStmt->execute([$project_id, $msTitle, $msTitle, $scheduled_date, $result_notes, $created_by]);

                $pdo->commit();
                $success_msg = "Inspection {$inspection_code} ({$inspection_type}) scheduled successfully!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_msg = "Failed to schedule inspection: " . $e->getMessage();
            }
        }
    }

    // 2. Log Result (Pass / Fail)
    elseif ($action === 'log_result') {
        $insp_id = intval($_POST['insp_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['Passed', 'Failed']) ? $_POST['status'] : 'Passed';
        $completed_date = !empty($_POST['completed_date']) ? $_POST['completed_date'] : date('Y-m-d');
        $result_notes = trim($_POST['result_notes'] ?? '');
        $defect_desc = trim($_POST['defect_description'] ?? '');

        if ($insp_id > 0) {
            try {
                $pdo->beginTransaction();

                $report_file_url = uploadInspectionDoc($_FILES['report_file'] ?? null);

                if ($report_file_url) {
                    $upd = $pdo->prepare("
                        UPDATE project_inspections 
                        SET status = ?, completed_date = ?, result_notes = ?, report_file_url = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$status, $completed_date, $result_notes, $report_file_url, $insp_id]);
                } else {
                    $upd = $pdo->prepare("
                        UPDATE project_inspections 
                        SET status = ?, completed_date = ?, result_notes = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$status, $completed_date, $result_notes, $insp_id]);
                }

                // If Failed and defect description provided, link defect
                if ($status === 'Failed' && !empty($defect_desc)) {
                    $pdo->prepare("
                        INSERT INTO inspection_defects (inspection_id, defect_description, remedy_status)
                        VALUES (?, ?, 'Pending')
                    ")->execute([$insp_id, $defect_desc]);
                }

                $pdo->commit();
                $success_msg = "Inspection outcome recorded as '{$status}'.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_msg = "Error logging inspection result: " . $e->getMessage();
            }
        }
    }

    // 3. Reschedule Inspection
    elseif ($action === 'reschedule') {
        $insp_id = intval($_POST['insp_id'] ?? 0);
        $new_date = !empty($_POST['new_date']) ? $_POST['new_date'] : '';

        if ($insp_id > 0 && !empty($new_date)) {
            try {
                $pdo->prepare("UPDATE project_inspections SET scheduled_date = ?, status = 'Scheduled' WHERE id = ?")
                    ->execute([$new_date, $insp_id]);
                $success_msg = "Inspection rescheduled to {$new_date}.";
            } catch (Exception $e) {
                $error_msg = "Failed to reschedule inspection: " . $e->getMessage();
            }
        }
    }

    // 4. Cancel Inspection
    elseif ($action === 'cancel_inspection') {
        $insp_id = intval($_POST['insp_id'] ?? 0);
        if ($insp_id > 0) {
            try {
                $pdo->prepare("UPDATE project_inspections SET status = 'Cancelled' WHERE id = ?")
                    ->execute([$insp_id]);
                $success_msg = "Inspection marked as Cancelled.";
            } catch (Exception $e) {
                $error_msg = "Failed to cancel inspection: " . $e->getMessage();
            }
        }
    }

    // 5. Delete Inspection
    elseif ($action === 'delete_inspection') {
        $insp_id = intval($_POST['insp_id'] ?? 0);
        if ($insp_id > 0) {
            try {
                $pdo->prepare("DELETE FROM project_inspections WHERE id = ?")->execute([$insp_id]);
                $success_msg = "Inspection record deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Failed to delete inspection: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------------
// Query Projects & Inspections Ledger
// -------------------------------------------------------------------------
try {
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $sql = "
        SELECT pi.*, 
               p.project_name, p.project_code,
               u.full_name AS creator_name,
               (SELECT COUNT(*) FROM inspection_defects WHERE inspection_id = pi.id) AS defect_count
        FROM project_inspections pi
        JOIN projects p ON pi.project_id = p.id
        LEFT JOIN users u ON pi.created_by = u.id
        ORDER BY pi.scheduled_date DESC, pi.id DESC
    ";
    $inspections = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Inspections - BuildNexus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Pure CSS Custom Standards matching BuildNexus Design Language */
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

        .page-title {
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
        
        /* Filter Controls */
        .search-wrapper { 
            position: relative; 
            max-width: 460px; 
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
            min-width: 200px; 
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

        /* Status Pills (matching exact screenshot styling) */
        .pill { 
            padding: 4px 14px; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            display: inline-block; 
            letter-spacing: 0.02em;
            text-align: center;
        }
        .pill-passed { 
            background: #f0fdf4; 
            color: #16a34a; 
            border: 1px solid #dcfce7;
        }
        .pill-scheduled { 
            background: #eff6ff; 
            color: #2563eb; 
            border: 1px solid #dbeafe;
        }
        .pill-failed { 
            background: #fef2f2; 
            color: #dc2626; 
            border: 1px solid #fee2e2;
        }
        .pill-cancelled { 
            background: #f1f5f9; 
            color: #64748b; 
            border: 1px solid #e2e8f0;
        }

        /* Buttons */
        .btn-new-inspection { 
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
        .btn-new-inspection:hover { 
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

        /* Modal custom design */
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
            <h1 class="page-title">Inspections</h1>
            <button type="button" class="btn-new-inspection" data-bs-toggle="modal" data-bs-target="#newInspectionModal">
                <i class="bi bi-plus-lg"></i> New Inspection
            </button>
        </div>

        <!-- Main Card -->
        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1 text-dark">All Inspections</h4>
                <p class="text-muted small mb-0">Manage and track all building inspections across projects.</p>
            </div>

            <!-- Filter Controls -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="inspSearch" class="nexus-input" placeholder="Search inspections...">
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <!-- Project Filter -->
                    <select id="projectFilter" class="nexus-select">
                        <option value="All">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= htmlspecialchars($proj['project_name']) ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Status Filter -->
                    <select id="statusFilter" class="nexus-select">
                        <option value="All">All Statuses</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Passed">Passed</option>
                        <option value="Failed">Failed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>

                    <button id="clearFiltersBtn" class="btn btn-sm btn-outline-secondary d-none" title="Clear all filters">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Responsive Table -->
            <div class="table-responsive">
                <table class="table align-middle" id="inspectionsTable">
                    <thead>
                        <tr>
                            <th width="15%">Date</th>
                            <th width="25%">Project</th>
                            <th width="20%">Type</th>
                            <th width="20%">Inspector</th>
                            <th width="15%">Status</th>
                            <th width="5%" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody id="inspectionsTableBody">
                        <?php if (empty($inspections)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-clipboard-x fs-2 d-block mb-2 text-secondary"></i>
                                    No inspection records found. Click "+ New Inspection" to schedule one.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($inspections as $insp): 
                            $statusPill = 'pill-scheduled';
                            if ($insp['status'] === 'Passed') $statusPill = 'pill-passed';
                            if ($insp['status'] === 'Failed') $statusPill = 'pill-failed';
                            if ($insp['status'] === 'Cancelled') $statusPill = 'pill-cancelled';

                            $inspJson = htmlspecialchars(json_encode([
                                'id' => $insp['id'],
                                'code' => $insp['inspection_code'],
                                'project_name' => $insp['project_name'],
                                'project_code' => $insp['project_code'] ?? '',
                                'inspection_type' => $insp['inspection_type'],
                                'inspector_name' => $insp['inspector_name'],
                                'inspector_agency' => $insp['inspector_agency'] ?: 'City Council',
                                'scheduled_date' => $insp['scheduled_date'],
                                'completed_date' => $insp['completed_date'] ?: 'Pending',
                                'status' => $insp['status'],
                                'result_notes' => $insp['result_notes'] ?? '',
                                'report_file_url' => $insp['report_file_url'] ?? '',
                                'defect_count' => $insp['defect_count']
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="insp-row" 
                            data-project="<?= htmlspecialchars(strtolower($insp['project_name'])) ?>"
                            data-type="<?= htmlspecialchars(strtolower($insp['inspection_type'])) ?>"
                            data-status="<?= htmlspecialchars($insp['status']) ?>"
                            data-search="<?= htmlspecialchars(strtolower($insp['inspection_code'] . ' ' . $insp['project_name'] . ' ' . $insp['inspection_type'] . ' ' . $insp['inspector_name'] . ' ' . ($insp['result_notes'] ?? ''))) ?>">
                            
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($insp['scheduled_date']) ?></td>

                            <td>
                                <a href="inspection_view.php?id=<?= $insp['id'] ?>" class="project-link">
                                    <?= htmlspecialchars($insp['project_name']) ?>
                                </a>
                                <?php if (!empty($insp['project_code'])): ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($insp['project_code']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="text-muted"><?= htmlspecialchars($insp['inspection_type']) ?></td>

                            <td class="text-muted"><?= htmlspecialchars($insp['inspector_name']) ?></td>

                            <td>
                                <span class="pill <?= $statusPill ?>">
                                    <?= htmlspecialchars($insp['status']) ?>
                                </span>
                            </td>

                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="action-dot-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2">
                                        <li>
                                            <a class="dropdown-item small" href="#" onclick="openViewDetailsModal(<?= $inspJson ?>)">
                                                <i class="bi bi-eye me-2 text-muted"></i> View Details
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small" href="#" onclick="openLogResultModal(<?= $insp['id'] ?>, '<?= htmlspecialchars(addslashes($insp['inspection_type'])) ?>', '<?= htmlspecialchars(addslashes($insp['project_name'])) ?>')">
                                                <i class="bi bi-check2-circle me-2 text-success"></i> Log Result (Pass/Fail)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small" href="#" onclick="openRescheduleModal(<?= $insp['id'] ?>, '<?= $insp['scheduled_date'] ?>', '<?= htmlspecialchars(addslashes($insp['inspection_type'])) ?>')">
                                                <i class="bi bi-calendar-event me-2 text-primary"></i> Reschedule
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small" href="generate_inspection_report.php?id=<?= $insp['id'] ?>" target="_blank">
                                                <i class="bi bi-file-earmark-pdf me-2 text-muted"></i> Download Report
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <a class="dropdown-item small text-danger" href="#" onclick="confirmCancelInspection(<?= $insp['id'] ?>, '<?= htmlspecialchars(addslashes($insp['inspection_type'])) ?>')">
                                                <i class="bi bi-x-circle me-2"></i> Cancel Inspection
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small text-danger" href="#" onclick="confirmDeleteInspection(<?= $insp['id'] ?>, '<?= htmlspecialchars(addslashes($insp['inspection_code'])) ?>')">
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
                                No matching inspections found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 1: "+ New Inspection" Modal                             -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="newInspectionModal" tabindex="-1" aria-labelledby="newInspectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="inspections.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_inspection">

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="newInspectionModalLabel">
                            <i class="bi bi-clipboard-plus text-success me-2"></i>Schedule Building Inspection
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <!-- Project Selection -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Project Site <span class="text-danger">*</span></label>
                                <select name="project_id" class="form-select bg-white" required>
                                    <option value="">Select Project Site...</option>
                                    <?php foreach ($projects as $prj): ?>
                                        <option value="<?= $prj['id'] ?>">
                                            <?= htmlspecialchars($prj['project_name']) ?> <?= !empty($prj['project_code']) ? '(' . htmlspecialchars($prj['project_code']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Inspection Type -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Inspection Type <span class="text-danger">*</span></label>
                                <input type="text" name="inspection_type" class="form-control" list="typeSuggestions" placeholder="e.g. Foundation, Framing, Electrical Rough-in" required>
                                <datalist id="typeSuggestions">
                                    <option value="Foundation">
                                    <option value="Framing">
                                    <option value="Electrical Rough-in">
                                    <option value="Plumbing">
                                    <option value="Final Plumbing">
                                    <option value="Roofing">
                                    <option value="Fire & Life Safety">
                                    <option value="Final Occupancy">
                                </datalist>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Inspector Name -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Inspector Name <span class="text-danger">*</span></label>
                                <input type="text" name="inspector_name" class="form-control" value="Municipal Inspector" placeholder="Inspector full name or title" required>
                            </div>

                            <!-- Authority / Agency -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Inspection Authority / Agency</label>
                                <input type="text" name="inspector_agency" class="form-control" value="City Council Building Dept" placeholder="e.g. Urban Development Authority, City Council">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Scheduled Date -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Scheduled Inspection Date <span class="text-danger">*</span></label>
                                <input type="date" name="scheduled_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <!-- Upload Inspection Plan / Checklist -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Inspection Plan / Scanned Cert (Optional)</label>
                                <input type="file" name="report_file" class="form-control" accept=".pdf,image/*">
                            </div>
                        </div>

                        <!-- Preparation Checklist / Notes -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Preparation Checklist / Site Notes</label>
                            <textarea name="result_notes" class="form-control" rows="3" placeholder="Key structural items to be inspected, safety equipment required, access arrangements..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-nexus-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Confirm & Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 2: "Log Result (Pass/Fail)" Modal                       -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="logResultModal" tabindex="-1" aria-labelledby="logResultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="inspections.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="log_result">
                    <input type="hidden" name="insp_id" id="resultInspId">

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title fw-bold" id="logResultModalLabel">
                                <i class="bi bi-check2-circle text-success me-2"></i>Log Inspection Outcome
                            </h5>
                            <div class="text-muted small" id="resultInspSubtitle"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Outcome Status <span class="text-danger">*</span></label>
                            <select name="status" id="resultStatusSelect" class="form-select bg-white" onchange="toggleDefectField(this.value)" required>
                                <option value="Passed">Passed (Compliant)</option>
                                <option value="Failed">Failed (Deficiencies Found)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Completion / Signoff Date <span class="text-danger">*</span></label>
                            <input type="date" name="completed_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Inspector Signoff Notes & Findings</label>
                            <textarea name="result_notes" class="form-control" rows="3" placeholder="Inspector's signoff comments, code compliance notes..." required></textarea>
                        </div>

                        <!-- Defect Notice (Shown if Failed) -->
                        <div class="mb-3 d-none" id="defectNoticeWrapper">
                            <label class="form-label small fw-semibold text-danger">Defect Description / Remedial Notice</label>
                            <textarea name="defect_description" class="form-control border-danger" rows="2" placeholder="Describe the specific deficiency that failed inspection..."></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Attach Signed Inspection Certificate / Report Sheet</label>
                            <input type="file" name="report_file" class="form-control" accept=".pdf,image/*">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-nexus-primary px-4">Save Inspection Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 3: "Reschedule" Modal                                   -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="inspections.php">
                    <input type="hidden" name="action" value="reschedule">
                    <input type="hidden" name="insp_id" id="rescheduleInspId">

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-calendar-event text-primary me-2"></i>Reschedule Inspection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">Select the new inspection date. Status will be reset to Scheduled.</p>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">New Scheduled Date <span class="text-danger">*</span></label>
                            <input type="date" name="new_date" id="rescheduleDateInput" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Update Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 4: "View Details" Modal                                 -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="detailsTitle"></h5>
                        <div class="text-muted small" id="detailsSubtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">INSPECTOR</div>
                                <div class="fw-semibold text-dark" id="detailsInspector"></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">AGENCY</div>
                                <div class="fw-semibold text-dark" id="detailsAgency"></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">STATUS</div>
                                <span id="detailsStatusBadge" class="pill mt-1"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold small text-muted text-uppercase mb-1">Inspector Remarks & Notes</h6>
                        <div class="p-3 bg-light rounded border text-dark" id="detailsNotes" style="white-space: pre-line; line-height: 1.6;"></div>
                    </div>

                    <div id="detailsReportFileWrapper" class="p-2 border rounded bg-light d-flex justify-content-between align-items-center d-none mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-pdf fs-4 text-danger"></i>
                            <span class="small fw-semibold text-dark">Signed Inspection Report Certificate</span>
                        </div>
                        <a id="detailsReportFileLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i> Download
                        </a>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <a id="detailsCertBtn" href="#" target="_blank" class="btn btn-outline-dark btn-sm me-2">
                            <i class="bi bi-printer me-1"></i> Print Certificate
                        </a>
                        <a id="detailsFullPageBtn" href="#" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Full View
                        </a>
                    </div>
                    <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Action Forms -->
    <form id="cancelInspForm" method="POST" action="inspections.php">
        <input type="hidden" name="action" value="cancel_inspection">
        <input type="hidden" name="insp_id" id="cancelInspId">
    </form>
    <form id="deleteInspForm" method="POST" action="inspections.php">
        <input type="hidden" name="action" value="delete_inspection">
        <input type="hidden" name="insp_id" id="deleteInspId">
    </form>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Multi-attribute live search and filtering
        const inspSearch = document.getElementById('inspSearch');
        const projectFilter = document.getElementById('projectFilter');
        const statusFilter = document.getElementById('statusFilter');
        const clearBtn = document.getElementById('clearFiltersBtn');

        function filterInspections() {
            const query = inspSearch.value.toLowerCase().trim();
            const selectedProject = projectFilter.value.toLowerCase();
            const selectedStatus = statusFilter.value;

            const isFiltered = (query !== '' || selectedProject !== 'all' || selectedStatus !== 'All');
            clearBtn.classList.toggle('d-none', !isFiltered);

            const rows = document.querySelectorAll('.insp-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                const projectData = row.getAttribute('data-project') || '';
                const statusData = row.getAttribute('data-status') || '';

                const matchesQuery = (query === '' || searchData.includes(query));
                const matchesProject = (selectedProject === 'all' || projectData === selectedProject);
                const matchesStatus = (selectedStatus === 'All' || statusData === selectedStatus);

                if (matchesQuery && matchesProject && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const noResults = document.getElementById('noResultsRow');
            if (noResults) {
                noResults.classList.toggle('d-none', visibleCount > 0 || rows.length === 0);
            }
        }

        inspSearch.addEventListener('input', filterInspections);
        projectFilter.addEventListener('change', filterInspections);
        statusFilter.addEventListener('change', filterInspections);

        clearBtn.addEventListener('click', function() {
            inspSearch.value = '';
            projectFilter.value = 'All';
            statusFilter.value = 'All';
            filterInspections();
        });

        // Log Result Modal Trigger
        function openLogResultModal(id, type, project) {
            document.getElementById('resultInspId').value = id;
            document.getElementById('resultInspSubtitle').innerText = type + ' Inspection • ' + project;
            let m = new bootstrap.Modal(document.getElementById('logResultModal'));
            m.show();
        }

        function toggleDefectField(status) {
            const wrapper = document.getElementById('defectNoticeWrapper');
            if (status === 'Failed') {
                wrapper.classList.remove('d-none');
            } else {
                wrapper.classList.add('d-none');
            }
        }

        // Reschedule Modal Trigger
        function openRescheduleModal(id, currentDate, type) {
            document.getElementById('rescheduleInspId').value = id;
            document.getElementById('rescheduleDateInput').value = currentDate;
            let m = new bootstrap.Modal(document.getElementById('rescheduleModal'));
            m.show();
        }

        // View Details Modal Trigger
        function openViewDetailsModal(insp) {
            document.getElementById('detailsTitle').innerText = insp.inspection_type + ' Inspection (' + insp.code + ')';
            document.getElementById('detailsSubtitle').innerText = insp.project_name + ' • Scheduled: ' + insp.scheduled_date;
            document.getElementById('detailsInspector').innerText = insp.inspector_name;
            document.getElementById('detailsAgency').innerText = insp.inspector_agency;
            document.getElementById('detailsNotes').innerText = insp.result_notes || 'No notes entered.';

            const badge = document.getElementById('detailsStatusBadge');
            badge.className = 'pill';
            if (insp.status === 'Passed') badge.classList.add('pill-passed');
            else if (insp.status === 'Scheduled') badge.classList.add('pill-scheduled');
            else if (insp.status === 'Failed') badge.classList.add('pill-failed');
            else if (insp.status === 'Cancelled') badge.classList.add('pill-cancelled');
            badge.innerText = insp.status;

            const fileWrapper = document.getElementById('detailsReportFileWrapper');
            if (insp.report_file_url) {
                fileWrapper.classList.remove('d-none');
                let link = insp.report_file_url;
                if (!link.startsWith('http') && !link.startsWith('/')) link = '../' + link;
                document.getElementById('detailsReportFileLink').href = link;
            } else {
                fileWrapper.classList.add('d-none');
            }

            document.getElementById('detailsCertBtn').href = 'generate_inspection_report.php?id=' + insp.id;
            document.getElementById('detailsFullPageBtn').href = 'inspection_view.php?id=' + insp.id;

            let m = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
            m.show();
        }

        // Cancellation Confirmation
        function confirmCancelInspection(id, type) {
            if (confirm(`Are you sure you want to cancel the ${type} inspection?`)) {
                document.getElementById('cancelInspId').value = id;
                document.getElementById('cancelInspForm').submit();
            }
        }

        // Deletion Confirmation
        function confirmDeleteInspection(id, code) {
            if (confirm(`Are you sure you want to permanently delete inspection ${code}?`)) {
                document.getElementById('deleteInspId').value = id;
                document.getElementById('deleteInspForm').submit();
            }
        }
    </script>
</body>
</html>