<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$current_user_id = $_SESSION['user_id'] ?? 1;
$current_user_role = $_SESSION['role'] ?? 'Admin';
$success_msg = '';
$error_msg = '';

// Handle Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'meeting_created') $success_msg = 'Safety meeting and attendee roster recorded successfully!';
    if ($_GET['msg'] === 'deleted') $success_msg = 'Safety meeting record deleted successfully.';
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'delete_failed') $error_msg = 'Failed to delete safety meeting.';
    if ($_GET['error'] === 'upload_failed') $error_msg = 'Invalid file uploaded. Only JPG, PNG, WEBP, and PDF up to 10MB are permitted.';
}

// Handle Form Submission for New Meeting
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_meeting') {
    $project_id = intval($_POST['project_id'] ?? 0);
    $topic_select = trim($_POST['topic_id'] ?? '');
    $custom_topic = !empty($_POST['custom_topic']) ? trim($_POST['custom_topic']) : null;
    $foreman_id = intval($_POST['foreman_id'] ?? $current_user_id);
    $meeting_date = !empty($_POST['meeting_date']) ? trim($_POST['meeting_date']) : date('Y-m-d');
    $attendees_count = intval($_POST['attendees_count'] ?? 0);
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;

    $topic_id = null;
    if ($topic_select === 'custom') {
        $topic_id = null;
        if (empty($custom_topic)) {
            $custom_topic = 'Site Specific Safety Briefing';
        }
    } elseif (is_numeric($topic_select) && intval($topic_select) > 0) {
        $topic_id = intval($topic_select);
    }

    // Process File Upload if present
    $signed_roster_file = null;
    if (isset($_FILES['signed_roster']) && $_FILES['signed_roster']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['signed_roster'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions) || $file['size'] > 10 * 1024 * 1024) {
            $error_msg = 'Invalid file uploaded. Only JPG, PNG, WEBP, and PDF up to 10MB are allowed.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/safety/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'roster_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destination = $uploadDir . $newFileName;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $signed_roster_file = $newFileName;
            } else {
                $error_msg = 'Failed to upload signed roster file.';
            }
        }
    }

    if (empty($error_msg)) {
        if ($project_id <= 0) {
            $error_msg = 'Please select a valid construction project.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert meeting log
                $insertMeetingSql = "
                    INSERT INTO safety_meeting_logs 
                    (project_id, topic_id, custom_topic, foreman_id, meeting_date, attendees_count, notes, signed_roster_file)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmtM = $pdo->prepare($insertMeetingSql);
                $stmtM->execute([
                    $project_id,
                    $topic_id,
                    $custom_topic,
                    $foreman_id,
                    $meeting_date,
                    $attendees_count,
                    $notes,
                    $signed_roster_file
                ]);
                $newMeetingId = $pdo->lastInsertId();

                // 2. Insert individual attendees
                $worker_names = $_POST['worker_name'] ?? [];
                $trade_roles = $_POST['trade_role'] ?? [];
                $sig_statuses = $_POST['signature_status'] ?? [];

                $validWorkersCount = 0;
                if (!empty($worker_names) && is_array($worker_names)) {
                    $insertAttSql = "
                        INSERT INTO safety_meeting_attendees (meeting_id, worker_name, trade_role, signature_status)
                        VALUES (?, ?, ?, ?)
                    ";
                    $stmtAtt = $pdo->prepare($insertAttSql);

                    for ($i = 0; $i < count($worker_names); $i++) {
                        $wName = trim($worker_names[$i] ?? '');
                        if (!empty($wName)) {
                            $wRole = !empty($trade_roles[$i]) ? trim($trade_roles[$i]) : 'Laborer';
                            $wSig = !empty($sig_statuses[$i]) ? trim($sig_statuses[$i]) : 'Signed';
                            $stmtAtt->execute([$newMeetingId, $wName, $wRole, $wSig]);
                            $validWorkersCount++;
                        }
                    }
                }

                // If attendees_count was 0 or less than valid workers, update to reflect actual roster count
                if ($attendees_count < $validWorkersCount) {
                    $updCount = $pdo->prepare("UPDATE safety_meeting_logs SET attendees_count = ? WHERE id = ?");
                    $updCount->execute([$validWorkersCount, $newMeetingId]);
                }

                $pdo->commit();
                header("Location: safety-meetings.php?msg=meeting_created");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Filter & Form Lists
try {
    // 1. Projects
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Topics
    $topics = $pdo->query("SELECT id, title, category, content FROM safety_topics ORDER BY category ASC, title ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Leads (Foremen, Project Managers, Admins)
    $leads = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('Admin', 'Project Manager', 'Foreman') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Meeting Logs Query with Filter Parameters
    $filter_project = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    $filter_date = !empty($_GET['date']) ? trim($_GET['date']) : '';
    $filter_search = !empty($_GET['search']) ? trim($_GET['search']) : '';

    $query = "
        SELECT sm.id, sm.meeting_date, sm.attendees_count, sm.notes, sm.signed_roster_file,
               p.id AS project_id, p.project_name, p.project_code,
               COALESCE(st.title, sm.custom_topic, 'General Safety Briefing') AS topic_title,
               COALESCE(st.category, 'General Safety') AS topic_category,
               COALESCE(u.full_name, 'Meeting Conductor') AS lead_name,
               (SELECT COUNT(*) FROM safety_meeting_attendees sma WHERE sma.meeting_id = sm.id) AS roster_count
        FROM safety_meeting_logs sm
        JOIN projects p ON sm.project_id = p.id
        LEFT JOIN safety_topics st ON sm.topic_id = st.id
        LEFT JOIN users u ON sm.foreman_id = u.id
        WHERE 1=1
    ";
    $params = [];

    if ($filter_project > 0) {
        $query .= " AND sm.project_id = ?";
        $params[] = $filter_project;
    }
    if (!empty($filter_date)) {
        $query .= " AND sm.meeting_date = ?";
        $params[] = $filter_date;
    }
    if (!empty($filter_search)) {
        $query .= " AND (p.project_name LIKE ? OR u.full_name LIKE ? OR st.title LIKE ? OR sm.custom_topic LIKE ?)";
        $term = "%{$filter_search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $query .= " ORDER BY sm.meeting_date DESC, sm.id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Safety Meetings - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (BuildNexus Design Language) --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; position: relative; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        
        /* UI Components */
        .search-wrapper { position: relative; max-width: 450px; flex-grow: 1; }
        .nexus-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; z-index: 2; font-size: 0.95rem; }
        .nexus-input { width: 100%; padding: 8px 14px 8px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff; transition: border-color 0.15s ease; }
        .nexus-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }

        .nexus-select { padding: 8px 36px 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.88rem; color: #334155; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 230px; cursor: pointer; }
        .nexus-select:focus { outline: none; border-color: #22c55e; }

        /* Exact screenshot match for Project Links */
        .project-link { color: #16a34a; text-decoration: none; font-weight: 500; transition: color 0.15s ease; }
        .project-link:hover { color: #15803d; text-decoration: underline; }

        /* Top Action Buttons */
        .btn-export { background-color: #fff; border: 1px solid #e2e8f0; color: #334155; font-weight: 600; border-radius: 8px; padding: 8px 18px; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.15s; }
        .btn-export:hover { background-color: #f8fafc; border-color: #cbd5e1; color: #0f172a; }
        
        .btn-new-meeting { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 18px; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; text-decoration: none; transition: background-color 0.15s; }
        .btn-new-meeting:hover { background-color: #16a34a; color: #fff; }

        /* Calendar Picker Matching UI */
        .date-trigger { background: #22c55e; color: #fff; border: none; border-radius: 8px; padding: 8px 18px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 0.875rem; transition: background-color 0.15s; }
        .date-trigger:hover { background: #16a34a; }
        
        .calendar-dropdown { position: absolute; right: 0; top: 48px; width: 300px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); z-index: 1050; padding: 16px; display: none; }
        .calendar-dropdown.show { display: block; }
        .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-weight: 700; color: #1e293b; font-size: 0.95rem; }
        .cal-header-btn { background: none; border: none; color: #64748b; font-size: 1rem; cursor: pointer; border-radius: 4px; padding: 2px 6px; }
        .cal-header-btn:hover { background: #f1f5f9; color: #0f172a; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; font-size: 0.82rem; }
        .cal-day-name { font-weight: 600; color: #94a3b8; padding: 4px 0; }
        .cal-day { padding: 6px 0; border-radius: 6px; cursor: pointer; color: #334155; transition: background 0.15s; }
        .cal-day:hover { background: #f1f5f9; }
        .cal-day.active { background: #22c55e; color: #fff; font-weight: 700; }
        .cal-day.muted { color: #cbd5e1; cursor: default; }

        /* Table Styling */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom th { font-weight: 600; font-size: 0.875rem; color: #475569; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
        .table-custom td { padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tr:hover td { background-color: #fafafa; }

        .dropdown-menu { border-radius: 10px; border: 1px solid #e2e8f0; padding: 6px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07); }
        .dropdown-item { border-radius: 6px; padding: 6px 12px; font-weight: 500; font-size: 0.85rem; }
        .dropdown-item:hover { background-color: #f1f5f9; }
        .dropdown-item.text-danger:hover { background-color: #fef2f2; }

        /* Dynamic Attendee Row */
        .attendee-row { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; margin-bottom: 8px; transition: all 0.15s; }
        .attendee-row:hover { border-color: #cbd5e1; }
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
            <h1 class="h3 fw-bold mb-0 text-dark">Safety Meetings</h1>
            <div class="d-flex gap-2">
                <a href="export_safety_meetings.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" class="btn-export">
                    <i class="bi bi-download"></i> Export
                </a>
                <button class="btn-new-meeting" data-bs-toggle="modal" data-bs-target="#newMeetingModal">
                    <i class="bi bi-plus-lg"></i> New Meeting
                </button>
            </div>
        </div>

        <!-- Main Card -->
        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1 text-dark">All Meetings</h4>
                <p class="text-muted small mb-0">View and manage all safety meetings from all projects.</p>
            </div>

            <!-- Filter Controls -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="topicSearch" class="nexus-input" placeholder="Search by topic or project..." value="<?= htmlspecialchars($filter_search) ?>">
                </div>

                <div class="d-flex gap-2 position-relative align-items-center flex-wrap">
                    <!-- Project Dropdown Filter -->
                    <select class="nexus-select" id="projectFilter" onchange="applyFilters()">
                        <option value="">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= ($filter_project == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Date Picker Trigger Button -->
                    <button class="date-trigger" id="dateTriggerBtn" onclick="toggleCalendar()" type="button">
                        <i class="bi bi-calendar-check"></i> 
                        <span id="dateTriggerText"><?= !empty($filter_date) ? htmlspecialchars($filter_date) : 'Pick a date' ?></span>
                    </button>

                    <!-- Reset Filter Button if active -->
                    <?php if ($filter_project > 0 || !empty($filter_date) || !empty($filter_search)): ?>
                        <a href="safety-meetings.php" class="btn btn-sm btn-outline-secondary border rounded-3 px-3 py-2" title="Clear Filters">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    <?php endif; ?>

                    <!-- Custom Calendar Popup (Matches Screenshot Layout) -->
                    <div class="calendar-dropdown" id="calendarPopup">
                        <div class="cal-header">
                            <button type="button" class="cal-header-btn" onclick="prevMonth(event)"><i class="bi bi-chevron-left"></i></button>
                            <span id="calMonthYear">October 2024</span>
                            <button type="button" class="cal-header-btn" onclick="nextMonth(event)"><i class="bi bi-chevron-right"></i></button>
                        </div>
                        <div class="cal-grid" id="calGrid">
                            <!-- Populated dynamically via JS -->
                        </div>
                        <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                            <input type="date" id="nativeDateInput" class="form-control form-control-sm" style="font-size: 0.8rem; width: 145px;" value="<?= htmlspecialchars($filter_date) ?>">
                            <button type="button" class="btn btn-sm btn-light border py-1 px-2" style="font-size: 0.78rem;" onclick="clearDateFilter()">Clear Date</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Safety Meetings Table -->
            <div class="table-responsive">
                <table class="table-custom" id="meetingTable">
                    <thead>
                        <tr>
                            <th width="14%">Date</th>
                            <th width="26%">Project</th>
                            <th width="28%">Topic</th>
                            <th width="18%">Meeting Lead</th>
                            <th width="10%">Attendees</th>
                            <th width="4%" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($meetings)): ?>
                            <?php foreach ($meetings as $m): ?>
                            <tr class="meeting-row" 
                                data-project-id="<?= $m['project_id'] ?>" 
                                data-date="<?= $m['meeting_date'] ?>"
                                data-search="<?= strtolower(htmlspecialchars($m['project_name'] . ' ' . $m['topic_title'] . ' ' . $m['lead_name'] . ' ' . ($m['notes'] ?? ''))) ?>">
                                <td class="fw-semibold text-muted"><?= htmlspecialchars($m['meeting_date']) ?></td>
                                <td>
                                    <a href="project_overview.php?id=<?= $m['project_id'] ?>" class="project-link">
                                        <?= htmlspecialchars($m['project_name']) ?>
                                    </a>
                                </td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($m['topic_title']) ?>
                                    <?php if (!empty($m['signed_roster_file'])): ?>
                                        <i class="bi bi-paperclip text-muted ms-1" title="Physical sign-in roster attached"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($m['lead_name']) ?></td>
                                <td class="fw-bold text-dark"><?= intval($m['attendees_count']) ?></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn p-0 border-0 text-muted" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openMeetingDetails(<?= $m['id'] ?>)">
                                                    <i class="bi bi-eye text-primary me-2"></i> View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="generate_meeting_pdf.php?id=<?= $m['id'] ?>" target="_blank">
                                                    <i class="bi bi-file-earmark-pdf text-success me-2"></i> Download Attendance
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="confirmDelete(<?= $m['id'] ?>, '<?= addslashes($m['topic_title']) ?>')">
                                                    <i class="bi bi-trash me-2"></i> Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noRecordsRow">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <h6 class="fw-semibold">No safety meetings found</h6>
                                    <p class="small text-muted mb-0">Try adjusting your filters or click "+ New Meeting" to log a toolbox talk.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: + NEW MEETING ================= -->
    <div class="modal fade" id="newMeetingModal" tabindex="-1" aria-labelledby="newMeetingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-success-subtle text-success rounded-3">
                            <i class="bi bi-shield-check fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark" id="newMeetingModalLabel">Log Safety Meeting / Toolbox Talk</h5>
                            <div class="text-muted small">Record daily safety talks, hazard prevention, and worker sign-ins.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="safety-meetings.php" method="POST" enctype="multipart/form-data" id="createMeetingForm">
                    <input type="hidden" name="action" value="create_meeting">

                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <!-- Project Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Project <span class="text-danger">*</span></label>
                                <select name="project_id" class="form-select rounded-3" required>
                                    <option value="" disabled selected>Select Project...</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= $p['id'] ?>">
                                            <?= htmlspecialchars($p['project_name']) ?> <?= !empty($p['project_code']) ? '(' . htmlspecialchars($p['project_code']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Meeting Date -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Meeting Date <span class="text-danger">*</span></label>
                                <input type="date" name="meeting_date" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Topic Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Safety Topic <span class="text-danger">*</span></label>
                                <select name="topic_id" id="topicSelect" class="form-select rounded-3" onchange="toggleCustomTopic(this.value)" required>
                                    <option value="" disabled selected>Select Topic Curriculum...</option>
                                    <?php 
                                    $currentCat = '';
                                    foreach ($topics as $t): 
                                        if ($currentCat !== $t['category']) {
                                            if ($currentCat !== '') echo '</optgroup>';
                                            $currentCat = $t['category'];
                                            echo '<optgroup label="' . htmlspecialchars($currentCat) . '">';
                                        }
                                    ?>
                                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                                    <?php endforeach; 
                                    if ($currentCat !== '') echo '</optgroup>';
                                    ?>
                                    <option value="custom">-- Other / Custom Topic --</option>
                                </select>
                            </div>

                            <!-- Meeting Lead -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Meeting Lead / Conductor <span class="text-danger">*</span></label>
                                <select name="foreman_id" class="form-select rounded-3" required>
                                    <?php foreach ($leads as $l): ?>
                                        <option value="<?= $l['id'] ?>" <?= ($l['id'] == $current_user_id) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($l['full_name']) ?> (<?= htmlspecialchars($l['role']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Custom Topic Input (Hidden by default) -->
                        <div class="mb-3" id="customTopicWrapper" style="display: none;">
                            <label class="form-label fw-semibold small text-muted">Custom Topic Title <span class="text-danger">*</span></label>
                            <input type="text" name="custom_topic" id="customTopicInput" class="form-control rounded-3" placeholder="e.g. Confined Space Rescue Protocols">
                        </div>

                        <!-- Attendees Count -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold small text-muted mb-0">Total Workers Present</label>
                                <span class="text-muted small" id="rosterCountBadge">0 in roster</span>
                            </div>
                            <input type="number" name="attendees_count" id="totalAttendeesInput" class="form-control rounded-3" placeholder="Total number of workers attending" min="0" value="0">
                        </div>

                        <!-- Dynamic Attendee Roster Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-semibold small text-muted mb-0">
                                    <i class="bi bi-people-fill text-success me-1"></i> Individual Attendee Sign-in Roster
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-success py-1 px-2 fw-semibold" onclick="addAttendeeRow()">
                                    <i class="bi bi-person-plus-fill me-1"></i> Add Worker
                                </button>
                            </div>
                            
                            <div id="attendeeRowsContainer">
                                <!-- Dynamic rows inserted here -->
                            </div>
                        </div>

                        <!-- Physical Sign-in Sheet Upload -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">
                                <i class="bi bi-camera text-primary me-1"></i> Upload Scanned / Photographed Sign-in Sheet
                            </label>
                            <input type="file" name="signed_roster" class="form-control rounded-3" accept=".pdf,image/jpeg,image/png,image/webp">
                            <div class="form-text small">Upload photo or PDF of physical worker sign-in sheet (Max 10MB).</div>
                        </div>

                        <!-- Meeting Notes / Concerns -->
                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted">Meeting Notes & Site Hazards Discussed</label>
                            <textarea name="notes" class="form-control rounded-3" rows="3" placeholder="Key hazards reviewed, site observations, or worker questions raised during the toolbox talk..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer border-top pt-3">
                        <button type="button" class="btn btn-light border px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-new-meeting px-4">
                            <i class="bi bi-check-lg me-1"></i> Save Meeting Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: VIEW DETAILS ================= -->
    <div class="modal fade" id="viewMeetingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Safety Meeting Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="viewMeetingBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status"></div>
                        <div class="text-muted small mt-2">Loading meeting details...</div>
                    </div>
                </div>
                <div class="modal-footer border-top pt-2">
                    <a href="#" id="viewMeetingPdfBtn" target="_blank" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Print / Attendance PDF
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: DELETE CONFIRMATION ================= -->
    <div class="modal fade" id="deleteMeetingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-0">Are you sure you want to remove the safety meeting <strong id="deleteMeetingTopic"></strong>? This will permanently remove the logged talk and all attendee roster signatures.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                    <form action="meeting_delete.php" method="POST" id="deleteMeetingForm">
                        <input type="hidden" name="id" id="deleteMeetingId" value="">
                        <button type="submit" class="btn btn-danger px-3">Delete Meeting</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- CALENDAR PICKER STATE & NAVIGATION ---
        let calCurrentDate = new Date(2024, 9, 28); // Defaults around Oct 2024 per screenshot
        let selectedDate = "<?= !empty($filter_date) ? htmlspecialchars($filter_date) : '' ?>";

        function renderCalendar() {
            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            const year = calCurrentDate.getFullYear();
            const month = calCurrentDate.getMonth();

            document.getElementById('calMonthYear').innerText = `${monthNames[month]} ${year}`;

            const firstDayIndex = new Date(year, month, 1).getDay();
            const lastDay = new Date(year, month + 1, 0).getDate();
            const prevLastDay = new Date(year, month, 0).getDate();

            let gridHtml = `
                <div class="cal-day-name">Su</div><div class="cal-day-name">Mo</div><div class="cal-day-name">Tu</div>
                <div class="cal-day-name">We</div><div class="cal-day-name">Th</div><div class="cal-day-name">Fr</div><div class="cal-day-name">Sa</div>
            `;

            // Previous month buffer days
            for (let i = firstDayIndex; i > 0; i--) {
                gridHtml += `<div class="cal-day muted">${prevLastDay - i + 1}</div>`;
            }

            // Current month days
            for (let day = 1; day <= lastDay; day++) {
                const formattedMonth = String(month + 1).padStart(2, '0');
                const formattedDay = String(day).padStart(2, '0');
                const dateStr = `${year}-${formattedMonth}-${formattedDay}`;

                const isActive = (selectedDate === dateStr) ? 'active' : '';
                gridHtml += `<div class="cal-day ${isActive}" onclick="selectDate('${dateStr}')">${day}</div>`;
            }

            // Remaining cells to fill grid (up to 35 or 42 cells)
            const totalCells = firstDayIndex + lastDay;
            const nextDays = (totalCells > 35 ? 42 : 35) - totalCells;
            for (let j = 1; j <= nextDays; j++) {
                gridHtml += `<div class="cal-day muted">${j}</div>`;
            }

            document.getElementById('calGrid').innerHTML = gridHtml;
        }

        function toggleCalendar() {
            const popup = document.getElementById('calendarPopup');
            popup.classList.toggle('show');
            if (popup.classList.contains('show')) {
                renderCalendar();
            }
        }

        function prevMonth(e) {
            e.stopPropagation();
            calCurrentDate.setMonth(calCurrentDate.getMonth() - 1);
            renderCalendar();
        }

        function nextMonth(e) {
            e.stopPropagation();
            calCurrentDate.setMonth(calCurrentDate.getMonth() + 1);
            renderCalendar();
        }

        function selectDate(dateStr) {
            selectedDate = dateStr;
            document.getElementById('dateTriggerText').innerText = dateStr;
            document.getElementById('nativeDateInput').value = dateStr;
            document.getElementById('calendarPopup').classList.remove('show');
            applyFilters();
        }

        function clearDateFilter() {
            selectedDate = '';
            document.getElementById('dateTriggerText').innerText = 'Pick a date';
            document.getElementById('nativeDateInput').value = '';
            document.getElementById('calendarPopup').classList.remove('show');
            applyFilters();
        }

        document.getElementById('nativeDateInput').addEventListener('change', function() {
            if (this.value) {
                selectDate(this.value);
            } else {
                clearDateFilter();
            }
        });

        // Close calendar when clicking outside
        document.addEventListener('click', function(e) {
            const popup = document.getElementById('calendarPopup');
            const trigger = document.getElementById('dateTriggerBtn');
            if (popup && !popup.contains(e.target) && !trigger.contains(e.target)) {
                popup.classList.remove('show');
            }
        });

        // --- FILTERING LOGIC ---
        function applyFilters() {
            const projectVal = document.getElementById('projectFilter').value;
            const dateVal = selectedDate;
            const searchVal = document.getElementById('topicSearch').value.toLowerCase().trim();

            const rows = document.querySelectorAll(".meeting-row");
            let visibleCount = 0;

            rows.forEach(row => {
                const rowProject = row.getAttribute('data-project-id');
                const rowDate = row.getAttribute('data-date');
                const rowSearch = row.getAttribute('data-search') || '';

                const matchesProject = !projectVal || rowProject === projectVal;
                const matchesDate = !dateVal || rowDate === dateVal;
                const matchesSearch = !searchVal || rowSearch.includes(searchVal);

                if (matchesProject && matchesDate && matchesSearch) {
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
                    const tbody = document.querySelector("#meetingTable tbody");
                    const tr = document.createElement("tr");
                    tr.id = "noRecordsRow";
                    tr.innerHTML = `
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-semibold">No matching safety meetings</h6>
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

        // Real-time search keystroke filtering
        document.getElementById('topicSearch').addEventListener('keyup', applyFilters);

        // --- DYNAMIC ATTENDEE ROSTER ROWS IN MODAL ---
        const tradeRolesList = ['Laborer', 'Mason', 'Electrician', 'Carpenter', 'Scaffolder', 'Steel Fixer', 'Pipelayer', 'Welder', 'Painter', 'Excavator Operator', 'Surveyor Aide'];

        function addAttendeeRow(name = '', role = 'Laborer', status = 'Signed') {
            const container = document.getElementById('attendeeRowsContainer');
            const rowDiv = document.createElement('div');
            rowDiv.className = 'attendee-row d-flex gap-2 align-items-center';

            let roleOptions = tradeRolesList.map(r => `<option value="${r}" ${r === role ? 'selected' : ''}>${r}</option>`).join('');

            rowDiv.innerHTML = `
                <input type="text" name="worker_name[]" class="form-control form-control-sm rounded-2" placeholder="Worker Full Name" value="${name}" required style="flex: 2;">
                <select name="trade_role[]" class="form-select form-select-sm rounded-2" style="flex: 1.5;">
                    ${roleOptions}
                </select>
                <select name="signature_status[]" class="form-select form-select-sm rounded-2" style="flex: 1;">
                    <option value="Signed" ${status === 'Signed' ? 'selected' : ''}>Signed</option>
                    <option value="Present" ${status === 'Present' ? 'selected' : ''}>Present</option>
                    <option value="Absent" ${status === 'Absent' ? 'selected' : ''}>Absent</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="removeAttendeeRow(this)" title="Remove Worker">
                    <i class="bi bi-trash"></i>
                </button>
            `;

            container.appendChild(rowDiv);
            updateRosterCount();
        }

        function removeAttendeeRow(btn) {
            btn.closest('.attendee-row').remove();
            updateRosterCount();
        }

        function updateRosterCount() {
            const count = document.querySelectorAll('#attendeeRowsContainer .attendee-row').length;
            const badge = document.getElementById('rosterCountBadge');
            badge.innerText = `${count} in roster`;

            const totalInput = document.getElementById('totalAttendeesInput');
            if (parseInt(totalInput.value || 0) < count) {
                totalInput.value = count;
            }
        }

        // Custom Topic Toggle
        function toggleCustomTopic(val) {
            const wrapper = document.getElementById('customTopicWrapper');
            const input = document.getElementById('customTopicInput');
            if (val === 'custom') {
                wrapper.style.display = 'block';
                input.required = true;
            } else {
                wrapper.style.display = 'none';
                input.required = false;
            }
        }

        // Initial default attendee rows when opening modal
        document.getElementById('newMeetingModal').addEventListener('show.bs.modal', function() {
            const container = document.getElementById('attendeeRowsContainer');
            if (container.children.length === 0) {
                addAttendeeRow('', 'Laborer', 'Signed');
                addAttendeeRow('', 'Mason', 'Signed');
            }
        });

        // --- VIEW DETAILS MODAL (AJAX) ---
        function openMeetingDetails(meetingId) {
            const modalEl = document.getElementById('viewMeetingModal');
            const modal = new bootstrap.Modal(modalEl);
            const body = document.getElementById('viewMeetingBody');
            const pdfBtn = document.getElementById('viewMeetingPdfBtn');

            pdfBtn.href = `generate_meeting_pdf.php?id=${meetingId}`;
            body.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <div class="text-muted small mt-2">Loading meeting details...</div>
                </div>
            `;

            modal.show();

            fetch(`meeting_view.php?id=${meetingId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.text())
            .then(html => {
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = `<div class="alert alert-danger">Failed to load meeting details: ${err.message}</div>`;
            });
        }

        // --- DELETE CONFIRMATION ---
        function confirmDelete(id, topic) {
            document.getElementById('deleteMeetingId').value = id;
            document.getElementById('deleteMeetingTopic').innerText = `"${topic}"`;
            const modal = new bootstrap.Modal(document.getElementById('deleteMeetingModal'));
            modal.show();
        }

        // Initial render
        renderCalendar();
    </script>
</body>
</html>