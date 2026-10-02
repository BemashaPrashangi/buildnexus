<?php
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$success_msg = '';
$error_msg = '';

// Helper for dynamic initials
function getInitials($name) {
    $words = preg_split("/\s+/", trim($name));
    $initials = '';
    if (!empty($words[0])) {
        $initials .= strtoupper(mb_substr($words[0], 0, 1));
    }
    if (count($words) > 1 && !empty($words[count($words) - 1])) {
        $initials .= strtoupper(mb_substr($words[count($words) - 1], 0, 1));
    }
    return $initials ?: 'TC';
}

// -------------------------------------------------------------------------
// Handle POST Actions (Create Time Card, Approve, Reject, Delete)
// -------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || !empty($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_POST['ajax']);

    // 1. Quick Status Update (Approve / Reject)
    if ($action === 'update_status') {
        $card_id = intval($_POST['card_id'] ?? 0);
        $new_status = in_array($_POST['status'] ?? '', ['Pending', 'Approved', 'Rejected']) ? $_POST['status'] : '';
        $reviewer_id = $_SESSION['user_id'] ?? null;

        if ($card_id > 0 && !empty($new_status)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE time_cards 
                    SET approval_status = ?, reviewed_by = ?, reviewed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$new_status, $reviewer_id, $card_id]);

                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'card_id' => $card_id, 'new_status' => $new_status]);
                    exit();
                }
                $success_msg = "Time Card #{$card_id} status updated to '{$new_status}'.";
            } catch (Exception $e) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit();
                }
                $error_msg = "Error updating timecard status: " . $e->getMessage();
            }
        }
    }

    // 2. Create New Time Card Manual Entry
    elseif ($action === 'create_time_card') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $project_id = intval($_POST['project_id'] ?? 0);
        $work_date = !empty($_POST['work_date']) ? $_POST['work_date'] : date('Y-m-d');
        $clock_in_time = trim($_POST['clock_in_time'] ?? '');
        $clock_out_time = trim($_POST['clock_out_time'] ?? '');
        $break_minutes = intval($_POST['break_minutes'] ?? 0);
        $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
        $work_notes = trim($_POST['work_notes'] ?? '');
        $approval_status = in_array($_POST['approval_status'] ?? '', ['Pending', 'Approved', 'Rejected']) ? $_POST['approval_status'] : 'Approved';

        if ($user_id <= 0 || $project_id <= 0) {
            $error_msg = "Please select an employee and project.";
        } else {
            // Build Datetimes
            $clock_in_dt = !empty($clock_in_time) ? $work_date . ' ' . $clock_in_time . ':00' : $work_date . ' 08:00:00';
            $clock_out_dt = !empty($clock_out_time) ? $work_date . ' ' . $clock_out_time . ':00' : null;

            // Compute total hours
            $total_hours = 0.00;
            if ($clock_out_dt) {
                $inTs = strtotime($clock_in_dt);
                $outTs = strtotime($clock_out_dt);
                if ($outTs > $inTs) {
                    $diffMins = max(0, round(($outTs - $inTs) / 60) - $break_minutes);
                    $total_hours = round($diffMins / 60, 2);
                }
            } else {
                $total_hours = floatval($_POST['manual_hours'] ?? 8.0);
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO time_cards 
                    (user_id, project_id, work_date, clock_in, clock_out, break_minutes, total_hours, hourly_rate, work_notes, approval_status, status, reviewed_by, reviewed_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?, NOW())
                ");
                $stmt->execute([
                    $user_id, $project_id, $work_date, $clock_in_dt, $clock_out_dt, $break_minutes, $total_hours, $hourly_rate, $work_notes, $approval_status, $_SESSION['user_id']
                ]);
                $success_msg = "Time Card recorded successfully with {$total_hours} hours.";
            } catch (Exception $e) {
                $error_msg = "Failed to create time card: " . $e->getMessage();
            }
        }
    }

    // 3. Delete Time Card
    elseif ($action === 'delete_time_card') {
        $card_id = intval($_POST['card_id'] ?? 0);
        if ($card_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM time_cards WHERE id = ?");
                $stmt->execute([$card_id]);
                $success_msg = "Time Card #{$card_id} deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Failed to delete time card: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------------
// Fetch Data for Filters & Table Rendering
// -------------------------------------------------------------------------
try {
    // 1. Projects for filter and creation modal
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Employees for timecard creation modal
    $employees = $pdo->query("SELECT id, full_name, email, role FROM users WHERE role IN ('Foreman', 'Project Manager', 'Admin') OR role IS NOT NULL ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Main Query for Time Cards Ledger
    $sql = "
        SELECT tc.*, 
               u.full_name AS employee_name, u.email AS employee_email, u.role AS employee_role,
               p.project_name, p.project_code,
               rev.full_name AS reviewer_name
        FROM time_cards tc
        JOIN users u ON tc.user_id = u.id
        JOIN projects p ON tc.project_id = p.id
        LEFT JOIN users rev ON tc.reviewed_by = rev.id
        ORDER BY tc.work_date DESC, tc.id DESC
    ";
    $time_cards = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Time Cards - BuildNexus</title>
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
        
        /* Search & Filter Header */
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

        /* Date Trigger Button */
        .date-trigger-btn {
            background-color: #22c55e;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .date-trigger-btn:hover {
            background-color: #16a34a;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.2);
        }

        /* Avatar Circle */
        .avatar-circle { 
            width: 36px; 
            height: 36px; 
            background: #f1f5f9; 
            border-radius: 50%; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 0.75rem; 
            font-weight: 700; 
            color: #64748b; 
            margin-right: 12px; 
            flex-shrink: 0;
            letter-spacing: -0.02em;
        }

        /* Links & Table */
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

        /* Status Pills (matching exact screenshot styles) */
        .pill { 
            padding: 4px 14px; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            display: inline-block; 
            letter-spacing: 0.02em;
            text-align: center;
        }
        .pill-approved { 
            background: #f0fdf4; 
            color: #16a34a; 
            border: 1px solid #dcfce7;
        }
        .pill-pending { 
            background: #fef9c3; 
            color: #a16207; 
            border: 1px solid #fef08a;
        }
        .pill-rejected { 
            background: #fef2f2; 
            color: #dc2626; 
            border: 1px solid #fee2e2;
        }

        /* Buttons */
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

        .btn-new-time { 
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
        .btn-new-time:hover { 
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
            <h1 class="page-title">Time Cards</h1>
            <div class="d-flex gap-2">
                <a href="export_timecards.php" id="exportBtn" class="btn-export">
                    <i class="bi bi-download"></i> Export
                </a>
                <button type="button" class="btn-new-time" data-bs-toggle="modal" data-bs-target="#newTimeCardModal">
                    <i class="bi bi-plus-lg"></i> New Time Card
                </button>
            </div>
        </div>

        <!-- Main Card -->
        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1 text-dark">All Time Cards</h4>
                <p class="text-muted small mb-0">Review and manage employee time cards.</p>
            </div>

            <!-- Filter Controls -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="timeSearch" class="nexus-input" placeholder="Search by employee or project...">
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Project Selector -->
                    <select id="projectFilter" class="nexus-select">
                        <option value="All">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= htmlspecialchars($proj['project_name']) ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Interactive Date Trigger & Picker -->
                    <div class="position-relative">
                        <input type="date" id="dateFilterInput" class="position-absolute opacity-0" style="left:0; top:0; width:100%; height:100%; cursor:pointer;">
                        <button type="button" class="date-trigger-btn" id="dateTriggerBtn">
                            <i class="bi bi-calendar-check"></i> <span id="dateLabel">Pick a date</span>
                        </button>
                    </div>

                    <button id="clearFiltersBtn" class="btn btn-sm btn-outline-secondary d-none" title="Clear all filters">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Responsive Table -->
            <div class="table-responsive">
                <table class="table align-middle" id="timeTable">
                    <thead>
                        <tr>
                            <th width="22%">Employee</th>
                            <th width="26%">Project</th>
                            <th width="15%">Date</th>
                            <th width="12%">Hours</th>
                            <th width="15%">Status</th>
                            <th width="10%" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody id="timeTableBody">
                        <?php if (empty($time_cards)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-clock-history fs-2 d-block mb-2 text-secondary"></i>
                                    No time card records found. Click "+ New Time Card" to log hours.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($time_cards as $tc): 
                            $initials = getInitials($tc['employee_name']);
                            $statusPill = 'pill-pending';
                            if ($tc['approval_status'] === 'Approved') $statusPill = 'pill-approved';
                            if ($tc['approval_status'] === 'Rejected') $statusPill = 'pill-rejected';

                            $cardJson = htmlspecialchars(json_encode([
                                'id' => $tc['id'],
                                'employee_name' => $tc['employee_name'],
                                'initials' => $initials,
                                'project_name' => $tc['project_name'],
                                'project_code' => $tc['project_code'] ?? '',
                                'work_date' => $tc['work_date'],
                                'clock_in' => $tc['clock_in'] ? date('h:i A', strtotime($tc['clock_in'])) : 'N/A',
                                'clock_out' => $tc['clock_out'] ? date('h:i A', strtotime($tc['clock_out'])) : 'N/A',
                                'break_minutes' => $tc['break_minutes'],
                                'total_hours' => number_format(floatval($tc['total_hours']), 1),
                                'hourly_rate' => number_format(floatval($tc['hourly_rate']), 2),
                                'approval_status' => $tc['approval_status'],
                                'status' => $tc['status'],
                                'work_notes' => $tc['work_notes'] ?? '',
                                'reviewer_name' => $tc['reviewer_name'] ?? 'Pending Review',
                                'reviewed_at' => $tc['reviewed_at'] ? date('M d, Y h:i A', strtotime($tc['reviewed_at'])) : 'Not Reviewed'
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="time-row" 
                            id="row-<?= $tc['id'] ?>"
                            data-employee="<?= htmlspecialchars(strtolower($tc['employee_name'])) ?>"
                            data-initials="<?= htmlspecialchars(strtolower($initials)) ?>"
                            data-project="<?= htmlspecialchars(strtolower($tc['project_name'])) ?>"
                            data-date="<?= htmlspecialchars($tc['work_date']) ?>"
                            data-search="<?= htmlspecialchars(strtolower($tc['employee_name'] . ' ' . $initials . ' ' . $tc['project_name'] . ' ' . ($tc['work_notes'] ?? ''))) ?>">
                            
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle"><?= htmlspecialchars($initials) ?></div>
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($tc['employee_name']) ?></div>
                                        <?php if (!empty($tc['employee_role'])): ?>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($tc['employee_role']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <a href="project_overview.php?id=<?= $tc['project_id'] ?>" class="project-link">
                                    <?= htmlspecialchars($tc['project_name']) ?>
                                </a>
                                <?php if (!empty($tc['project_code'])): ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($tc['project_code']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="text-muted"><?= htmlspecialchars($tc['work_date']) ?></td>

                            <td class="fw-bold text-dark fs-6"><?= number_format(floatval($tc['total_hours']), 1) ?></td>

                            <td>
                                <span class="pill <?= $statusPill ?>" id="status-pill-<?= $tc['id'] ?>">
                                    <?= htmlspecialchars($tc['approval_status']) ?>
                                </span>
                            </td>

                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="action-dot-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2">
                                        <li>
                                            <a class="dropdown-item small" href="#" onclick="openViewDetailsModal(<?= $cardJson ?>)">
                                                <i class="bi bi-card-text me-2 text-muted"></i> View Details
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small text-success fw-semibold" href="#" onclick="updateCardStatus(<?= $tc['id'] ?>, 'Approved')">
                                                <i class="bi bi-check2-circle me-2"></i> Approve
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item small text-danger fw-semibold" href="#" onclick="updateCardStatus(<?= $tc['id'] ?>, 'Rejected')">
                                                <i class="bi bi-x-circle me-2"></i> Reject
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <a class="dropdown-item small text-danger" href="#" onclick="confirmDeleteCard(<?= $tc['id'] ?>, '<?= htmlspecialchars(addslashes($tc['employee_name'])) ?>', '<?= $tc['work_date'] ?>')">
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
                                No matching time cards found for the selected filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 1: "+ New Time Card" Modal                              -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="newTimeCardModal" tabindex="-1" aria-labelledby="newTimeCardModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="time-cards.php" id="newTimeCardForm">
                    <input type="hidden" name="action" value="create_time_card">

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="newTimeCardModalLabel">
                            <i class="bi bi-clock-history text-success me-2"></i>Record Employee Time Card
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <!-- Employee Selection -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Employee <span class="text-danger">*</span></label>
                                <select name="user_id" class="form-select bg-white" required>
                                    <option value="">Select Employee...</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>">
                                            <?= htmlspecialchars($emp['full_name']) ?> (<?= htmlspecialchars($emp['role'] ?: 'Staff') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

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
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Work Date -->
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Shift Date <span class="text-danger">*</span></label>
                                <input type="date" name="work_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <!-- Clock-In Time -->
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Clock In <span class="text-danger">*</span></label>
                                <input type="time" name="clock_in_time" id="modalClockIn" class="form-control" value="08:00" required>
                            </div>

                            <!-- Clock-Out Time -->
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Clock Out <span class="text-danger">*</span></label>
                                <input type="time" name="clock_out_time" id="modalClockOut" class="form-control" value="17:00" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Break Duration -->
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Break Duration (Minutes)</label>
                                <input type="number" name="break_minutes" id="modalBreakMins" class="form-control" value="30" min="0" step="5">
                            </div>

                            <!-- Calculated Hours Indicator -->
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Calculated Shift Hours</label>
                                <div class="input-group">
                                    <input type="text" id="calculatedHoursDisplay" class="form-control bg-light fw-bold text-success" value="8.5 hrs" readonly>
                                    <input type="hidden" name="manual_hours" id="modalManualHours" value="8.5">
                                </div>
                            </div>

                            <!-- Initial Approval Status -->
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Approval Status</label>
                                <select name="approval_status" class="form-select bg-white">
                                    <option value="Approved" selected>Approved</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>
                        </div>

                        <!-- Hourly Wage Rate -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Hourly Rate (RS.) <span class="text-muted fw-normal">(Optional Job Costing)</span></label>
                                <input type="number" name="hourly_rate" class="form-control" value="2500.00" step="50" min="0">
                            </div>
                        </div>

                        <!-- Work Notes / Shift Tasks -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Work Summary / Shift Tasks</label>
                            <textarea name="work_notes" class="form-control" rows="3" placeholder="Describe operations completed, areas worked on, equipment operated, or reasons for overtime..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-nexus-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Save Time Card
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 2: "View Details" Modal                                 -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="detailsEmployeeName"></h5>
                        <div class="text-muted small" id="detailsProjectName"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">SHIFT DATE</div>
                                <div class="fw-semibold text-dark" id="detailsDate"></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">TOTAL RECORDED HOURS</div>
                                <div class="fw-bold text-success fs-5" id="detailsHours"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">CLOCK IN</div>
                                <div class="fw-semibold text-dark" id="detailsClockIn"></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">CLOCK OUT</div>
                                <div class="fw-semibold text-dark" id="detailsClockOut"></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="text-muted small fw-semibold">BREAK</div>
                                <div class="fw-semibold text-dark" id="detailsBreak"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold small text-muted text-uppercase mb-1">Shift Tasks & Work Notes</h6>
                        <div class="p-3 bg-light rounded border text-dark" id="detailsNotes" style="white-space: pre-line; line-height: 1.6;"></div>
                    </div>

                    <div class="p-2 border rounded bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">APPROVAL STATUS</div>
                            <span id="detailsStatusBadge" class="pill mt-1"></span>
                        </div>
                        <div class="text-end text-muted small">
                            <div>Reviewed By: <strong id="detailsReviewer" class="text-dark"></strong></div>
                            <div id="detailsReviewedAt"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between">
                    <div class="d-flex gap-2" id="modalActionButtons">
                        <button type="button" class="btn btn-outline-success btn-sm" id="modalApproveBtn">
                            <i class="bi bi-check2 me-1"></i> Approve
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="modalRejectBtn">
                            <i class="bi bi-x me-1"></i> Reject
                        </button>
                    </div>
                    <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Deletion -->
    <form id="deleteCardForm" method="POST" action="time-cards.php">
        <input type="hidden" name="action" value="delete_time_card">
        <input type="hidden" name="card_id" id="deleteCardId">
    </form>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Multi-attribute live search and filtering
        const timeSearch = document.getElementById('timeSearch');
        const projectFilter = document.getElementById('projectFilter');
        const dateFilterInput = document.getElementById('dateFilterInput');
        const dateLabel = document.getElementById('dateLabel');
        const clearBtn = document.getElementById('clearFiltersBtn');
        const exportBtn = document.getElementById('exportBtn');

        function filterTimeCards() {
            const query = timeSearch.value.toLowerCase().trim();
            const selectedProject = projectFilter.value.toLowerCase();
            const selectedDate = dateFilterInput.value.trim();

            const isFiltered = (query !== '' || selectedProject !== 'all' || selectedDate !== '');
            clearBtn.classList.toggle('d-none', !isFiltered);

            if (selectedDate !== '') {
                dateLabel.innerText = selectedDate;
            } else {
                dateLabel.innerText = 'Pick a date';
            }

            // Sync CSV export link with active filters
            let exportUrl = 'export_timecards.php?';
            if (query !== '') exportUrl += 'search=' + encodeURIComponent(query) + '&';
            if (selectedDate !== '') exportUrl += 'date=' + encodeURIComponent(selectedDate) + '&';
            exportBtn.href = exportUrl;

            const rows = document.querySelectorAll('.time-row');
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

            const noResults = document.getElementById('noResultsRow');
            if (noResults) {
                noResults.classList.toggle('d-none', visibleCount > 0 || rows.length === 0);
            }
        }

        timeSearch.addEventListener('input', filterTimeCards);
        projectFilter.addEventListener('change', filterTimeCards);
        dateFilterInput.addEventListener('change', filterTimeCards);

        clearBtn.addEventListener('click', function() {
            timeSearch.value = '';
            projectFilter.value = 'All';
            dateFilterInput.value = '';
            filterTimeCards();
        });

        // Dynamic hours calculation in modal
        function calculateModalHours() {
            const inVal = document.getElementById('modalClockIn').value;
            const outVal = document.getElementById('modalClockOut').value;
            const breakMins = parseInt(document.getElementById('modalBreakMins').value, 10) || 0;

            if (inVal && outVal) {
                const inParts = inVal.split(':');
                const outParts = outVal.split(':');
                const inMinutes = parseInt(inParts[0], 10) * 60 + parseInt(inParts[1], 10);
                const outMinutes = parseInt(outParts[0], 10) * 60 + parseInt(outParts[1], 10);

                if (outMinutes > inMinutes) {
                    const diff = Math.max(0, (outMinutes - inMinutes) - breakMins);
                    const hours = (diff / 60).toFixed(1);
                    document.getElementById('calculatedHoursDisplay').value = hours + ' hrs';
                    document.getElementById('modalManualHours').value = hours;
                    return;
                }
            }
            document.getElementById('calculatedHoursDisplay').value = '0.0 hrs';
            document.getElementById('modalManualHours').value = '0.0';
        }

        document.getElementById('modalClockIn').addEventListener('input', calculateModalHours);
        document.getElementById('modalClockOut').addEventListener('input', calculateModalHours);
        document.getElementById('modalBreakMins').addEventListener('input', calculateModalHours);

        // AJAX update for status (Approve / Reject)
        function updateCardStatus(cardId, newStatus) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('card_id', cardId);
            formData.append('status', newStatus);
            formData.append('ajax', '1');

            fetch('time-cards.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const pill = document.getElementById('status-pill-' + cardId);
                    if (pill) {
                        pill.className = 'pill';
                        if (newStatus === 'Approved') pill.classList.add('pill-approved');
                        else if (newStatus === 'Pending') pill.classList.add('pill-pending');
                        else if (newStatus === 'Rejected') pill.classList.add('pill-rejected');
                        pill.innerText = newStatus;
                    }
                } else {
                    alert('Error updating status: ' + (data.error || 'Server error'));
                }
            })
            .catch(() => {
                // Fallback standard submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'time-cards.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="card_id" value="${cardId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            });
        }

        // View Details Modal
        let currentModalCardId = 0;
        function openViewDetailsModal(card) {
            currentModalCardId = card.id;
            document.getElementById('detailsEmployeeName').innerText = card.employee_name;
            document.getElementById('detailsProjectName').innerText = card.project_name + (card.project_code ? ' (' + card.project_code + ')' : '');
            document.getElementById('detailsDate').innerText = card.work_date;
            document.getElementById('detailsHours').innerText = card.total_hours + ' hrs';
            document.getElementById('detailsClockIn').innerText = card.clock_in;
            document.getElementById('detailsClockOut').innerText = card.clock_out;
            document.getElementById('detailsBreak').innerText = card.break_minutes + ' mins';
            document.getElementById('detailsNotes').innerText = card.work_notes || 'No work notes logged for this shift.';
            
            const badge = document.getElementById('detailsStatusBadge');
            badge.className = 'pill';
            if (card.approval_status === 'Approved') badge.classList.add('pill-approved');
            else if (card.approval_status === 'Pending') badge.classList.add('pill-pending');
            else if (card.approval_status === 'Rejected') badge.classList.add('pill-rejected');
            badge.innerText = card.approval_status;

            document.getElementById('detailsReviewer').innerText = card.reviewer_name;
            document.getElementById('detailsReviewedAt').innerText = card.reviewed_at;

            // Hook modal buttons
            document.getElementById('modalApproveBtn').onclick = function() {
                updateCardStatus(currentModalCardId, 'Approved');
                bootstrap.Modal.getInstance(document.getElementById('viewDetailsModal')).hide();
            };
            document.getElementById('modalRejectBtn').onclick = function() {
                updateCardStatus(currentModalCardId, 'Rejected');
                bootstrap.Modal.getInstance(document.getElementById('viewDetailsModal')).hide();
            };

            let vModal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
            vModal.show();
        }

        // Delete Confirmation
        function confirmDeleteCard(id, employee, date) {
            if (confirm(`Are you sure you want to permanently delete Time Card #${id} for ${employee} on ${date}?`)) {
                document.getElementById('deleteCardId').value = id;
                document.getElementById('deleteCardForm').submit();
            }
        }
    </script>
</body>
</html>