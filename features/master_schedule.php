<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$success_msg = '';
$error_msg = '';

// Handle POST actions (Create, Edit, Delete Event)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create New Schedule Event / Milestone
    if ($action === 'create_event') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $event_type = in_array($_POST['event_type'] ?? '', ['Milestone', 'Phase Start', 'Phase End', 'Inspection', 'Delivery']) ? $_POST['event_type'] : 'Milestone';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $description = trim($_POST['description'] ?? '');
        $color_hex = !empty($_POST['color_hex']) ? trim($_POST['color_hex']) : null;
        $status = in_array($_POST['status'] ?? '', ['Upcoming', 'In Progress', 'Completed', 'Delayed']) ? $_POST['status'] : 'Upcoming';
        $created_by = $_SESSION['user_id'] ?? null;

        if ($project_id <= 0) {
            $error_msg = "Please select a valid project.";
        } elseif (empty($title)) {
            $error_msg = "Event title is required.";
        } elseif (empty($start_date)) {
            $error_msg = "Event date is required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO project_milestones 
                    (project_id, title, phase_name, event_type, start_date, end_date, description, color_hex, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $project_id, $title, $title, $event_type, $start_date, $end_date, $description, $color_hex, $status, $created_by
                ]);
                $success_msg = "Event '{$title}' successfully added to the master schedule.";
            } catch (Exception $e) {
                $error_msg = "Failed to create event: " . $e->getMessage();
            }
        }
    }

    // 2. Edit Schedule Event / Milestone
    elseif ($action === 'edit_event') {
        $event_id = intval($_POST['event_id'] ?? 0);
        $project_id = intval($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $event_type = in_array($_POST['event_type'] ?? '', ['Milestone', 'Phase Start', 'Phase End', 'Inspection', 'Delivery']) ? $_POST['event_type'] : 'Milestone';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $description = trim($_POST['description'] ?? '');
        $color_hex = !empty($_POST['color_hex']) ? trim($_POST['color_hex']) : null;
        $status = in_array($_POST['status'] ?? '', ['Upcoming', 'In Progress', 'Completed', 'Delayed']) ? $_POST['status'] : 'Upcoming';

        if ($event_id <= 0 || empty($title) || empty($start_date)) {
            $error_msg = "Invalid parameters for event update.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE project_milestones 
                    SET project_id = ?, title = ?, phase_name = ?, event_type = ?, start_date = ?, end_date = ?, description = ?, color_hex = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $project_id, $title, $title, $event_type, $start_date, $end_date, $description, $color_hex, $status, $event_id
                ]);
                $success_msg = "Event updated successfully.";
            } catch (Exception $e) {
                $error_msg = "Failed to update event: " . $e->getMessage();
            }
        }
    }

    // 3. Delete Schedule Event / Milestone
    elseif ($action === 'delete_event') {
        $event_id = intval($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM project_milestones WHERE id = ?");
                $stmt->execute([$event_id]);
                $success_msg = "Event removed from master schedule.";
            } catch (Exception $e) {
                $error_msg = "Failed to delete event: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------------
// Month & Year Navigation Logic
// -------------------------------------------------------------------------
$currentMonth = intval($_GET['month'] ?? $_GET['m'] ?? date('n'));
$currentYear = intval($_GET['year'] ?? $_GET['y'] ?? date('Y'));

if ($currentMonth < 1 || $currentMonth > 12) {
    $currentMonth = intval(date('n'));
}
if ($currentYear < 2000 || $currentYear > 2100) {
    $currentYear = intval(date('Y'));
}

$dateObj = DateTime::createFromFormat('!Y-n', "{$currentYear}-{$currentMonth}");
$monthName = $dateObj->format('F');
$daysInMonth = intval($dateObj->format('t'));
$firstDayOfMonth = intval($dateObj->format('w')); // 0 = Sun, 1 = Mon, ..., 6 = Sat

// Next and previous month calculations
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Active project filter
$filterProjectId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Date window for the current month
$monthStart = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$monthEnd = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $daysInMonth);

// -------------------------------------------------------------------------
// Dynamic Project Color Palette Mapping
// -------------------------------------------------------------------------
$presetPalettes = [
    ['hex' => '#3b82f6', 'badge' => '#2563eb', 'light' => '#eff6ff'], // Blue
    ['hex' => '#22c55e', 'badge' => '#16a34a', 'light' => '#f0fdf4'], // Green
    ['hex' => '#a855f7', 'badge' => '#9333ea', 'light' => '#faf5ff'], // Purple
    ['hex' => '#f97316', 'badge' => '#ea580c', 'light' => '#fff7ed'], // Orange
    ['hex' => '#eab308', 'badge' => '#ca8a04', 'light' => '#fefce8'], // Yellow
    ['hex' => '#06b6d4', 'badge' => '#0891b2', 'light' => '#ecfeff'], // Cyan
    ['hex' => '#ec4899', 'badge' => '#db2777', 'light' => '#fdf2f8'], // Pink
    ['hex' => '#6366f1', 'badge' => '#4f46e5', 'light' => '#eef2ff']  // Indigo
];

function getProjectColor($projectId, $projectName) {
    global $presetPalettes;
    $normalizedName = strtolower(trim($projectName));
    
    // Screenshot specific alignments
    if (str_contains($normalizedName, 'luxury villa')) {
        return '#3b82f6'; // Blue
    }
    if (str_contains($normalizedName, 'colombo office')) {
        return '#22c55e'; // Green
    }
    if (str_contains($normalizedName, 'galle boutique')) {
        return '#a855f7'; // Purple
    }
    if (str_contains($normalizedName, 'highway expansion')) {
        return '#f97316'; // Orange
    }

    // Deterministic hash assignment for any other projects
    $idx = abs(crc32((string)$projectId)) % count($presetPalettes);
    return $presetPalettes[$idx]['hex'];
}

// -------------------------------------------------------------------------
// Fetch Projects & Milestones from MySQL
// -------------------------------------------------------------------------
try {
    // 1. Fetch all projects for selection dropdowns
    $allProjects = $pdo->query("SELECT id, project_name, project_code, start_date, end_date FROM projects WHERE status != 'Archived' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Query project_milestones in current month window
    $mSql = "
        SELECT m.*, COALESCE(m.title, m.phase_name) AS event_title,
               p.project_name, p.project_code,
               u.full_name AS creator_name
        FROM project_milestones m
        JOIN projects p ON m.project_id = p.id
        LEFT JOIN users u ON m.created_by = u.id
        WHERE ((m.start_date BETWEEN :mstart AND :mend) 
           OR (m.end_date IS NOT NULL AND m.end_date >= :mstart AND m.start_date <= :mend))
    ";
    if ($filterProjectId > 0) {
        $mSql .= " AND m.project_id = :filter_pid";
    }
    $mSql .= " ORDER BY m.start_date ASC, m.id ASC";

    $mStmt = $pdo->prepare($mSql);
    $mParams = [':mstart' => $monthStart, ':mend' => $monthEnd];
    if ($filterProjectId > 0) {
        $mParams[':filter_pid'] = $filterProjectId;
    }
    $mStmt->execute($mParams);
    $milestones = $mStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Automatic Project Start Date & Handover Date Synchronization
    $pSql = "
        SELECT id, project_name, project_code, start_date, end_date
        FROM projects
        WHERE (start_date BETWEEN :mstart AND :mend)
           OR (end_date BETWEEN :mstart AND :mend)
    ";
    if ($filterProjectId > 0) {
        $pSql .= " AND id = :filter_pid";
    }
    $pStmt = $pdo->prepare($pSql);
    $pStmt->execute($mParams);
    $syncProjects = $pStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// -------------------------------------------------------------------------
// Group Events By Day of the Month
// -------------------------------------------------------------------------
$calendarEvents = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $calendarEvents[$d] = [];
}

$scheduledProjects = []; // Tracks projects with active items in this month for the legend

// 1. Process Milestones
foreach ($milestones as $m) {
    $sDate = new DateTime($m['start_date']);
    $mYear = intval($sDate->format('Y'));
    $mMonth = intval($sDate->format('n'));
    $mDay = intval($sDate->format('j'));

    $color = !empty($m['color_hex']) ? $m['color_hex'] : getProjectColor($m['project_id'], $m['project_name']);

    // Record for legend
    $scheduledProjects[$m['project_id']] = [
        'id' => $m['project_id'],
        'name' => $m['project_name'],
        'color' => $color
    ];

    if ($mYear === $currentYear && $mMonth === $currentMonth) {
        $displayTitle = htmlspecialchars($m['project_name'] . ' ' . $m['event_title']);
        $calendarEvents[$mDay][] = [
            'id' => $m['id'],
            'project_id' => $m['project_id'],
            'project_name' => $m['project_name'],
            'title' => $m['event_title'],
            'display_title' => $displayTitle,
            'event_type' => $m['event_type'] ?? 'Milestone',
            'start_date' => $m['start_date'],
            'end_date' => $m['end_date'],
            'status' => $m['status'],
            'description' => $m['description'] ?? '',
            'color' => $color,
            'is_sync' => false,
            'creator_name' => $m['creator_name'] ?? 'System'
        ];
    }
}

// 2. Process Synchronized Project Start & Handover Dates
foreach ($syncProjects as $sp) {
    $pColor = getProjectColor($sp['id'], $sp['project_name']);
    $scheduledProjects[$sp['id']] = [
        'id' => $sp['id'],
        'name' => $sp['project_name'],
        'color' => $pColor
    ];

    // Project Start Date
    if (!empty($sp['start_date'])) {
        $stDate = new DateTime($sp['start_date']);
        if (intval($stDate->format('Y')) === $currentYear && intval($stDate->format('n')) === $currentMonth) {
            $stDay = intval($stDate->format('j'));
            $displayTitle = htmlspecialchars($sp['project_name'] . ' Start Date');
            $calendarEvents[$stDay][] = [
                'id' => 'p_start_' . $sp['id'],
                'project_id' => $sp['id'],
                'project_name' => $sp['project_name'],
                'title' => 'Project Start Date',
                'display_title' => $displayTitle,
                'event_type' => 'Phase Start',
                'start_date' => $sp['start_date'],
                'end_date' => null,
                'status' => 'In Progress',
                'description' => 'Official site handover and project mobilization start date.',
                'color' => $pColor,
                'is_sync' => true,
                'sync_type' => 'Start Date'
            ];
        }
    }

    // Project Handover / End Date
    if (!empty($sp['end_date'])) {
        $enDate = new DateTime($sp['end_date']);
        if (intval($enDate->format('Y')) === $currentYear && intval($enDate->format('n')) === $currentMonth) {
            $enDay = intval($enDate->format('j'));
            $displayTitle = htmlspecialchars($sp['project_name'] . ' Handover Date');
            $calendarEvents[$enDay][] = [
                'id' => 'p_end_' . $sp['id'],
                'project_id' => $sp['id'],
                'project_name' => $sp['project_name'],
                'title' => 'Handover Date',
                'display_title' => $displayTitle,
                'event_type' => 'Phase End',
                'start_date' => $sp['end_date'],
                'end_date' => null,
                'status' => 'Upcoming',
                'description' => 'Target completion and client handover milestone.',
                'color' => $pColor,
                'is_sync' => true,
                'sync_type' => 'Handover Date'
            ];
        }
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
    <title>Master Schedule - BuildNexus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* BuildNexus Custom Design Standards (Zero Tailwind) */
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

        /* Top Header & Navigation */
        .page-title {
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0;
            letter-spacing: -0.02em;
        }

        .month-selector {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .nav-btn { 
            background: #fff; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #475569; 
            transition: all 0.2s; 
            text-decoration: none;
        }
        .nav-btn:hover { 
            background: #f1f5f9; 
            color: #0f172a;
            border-color: #cbd5e1;
        }

        .month-heading {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 4px;
            min-width: 190px;
            text-align: center;
        }

        .btn-new-event { 
            background-color: #22c55e; 
            border: none; 
            color: #fff; 
            font-weight: 600; 
            border-radius: 8px; 
            padding: 9px 20px; 
            font-size: 0.9rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .btn-new-event:hover { 
            background-color: #16a34a; 
            color: #fff;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.25); 
        }

        /* Calendar Card & Grid */
        .calendar-card { 
            background: #fff; 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-top: 1.5rem;
        }

        .calendar-header { 
            display: grid; 
            grid-template-columns: repeat(7, 1fr); 
            border-bottom: 1px solid #f1f5f9; 
            background: #fff; 
        }

        .day-label { 
            text-align: center; 
            padding: 1.1rem 0.5rem; 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: #64748b; 
        }
        
        .calendar-grid { 
            display: grid; 
            grid-template-columns: repeat(7, 1fr); 
        }

        .calendar-cell { 
            min-height: 126px; 
            border-right: 1px solid #f1f5f9; 
            border-bottom: 1px solid #f1f5f9; 
            padding: 10px 8px; 
            position: relative; 
            background-color: #fff;
            transition: background 0.15s ease;
        }
        .calendar-cell:nth-child(7n) { 
            border-right: none; 
        }
        .calendar-cell.cell-muted {
            background-color: #fbfcfe;
        }
        .calendar-cell:hover {
            background-color: #fdfdfd;
        }

        .day-num { 
            font-size: 0.875rem; 
            font-weight: 500; 
            color: #334155; 
            margin-bottom: 8px; 
            line-height: 1;
            padding-left: 2px;
        }

        .day-num.is-today {
            display: inline-block;
            background-color: #22c55e;
            color: #fff;
            font-weight: 700;
            width: 22px;
            height: 22px;
            text-align: center;
            line-height: 22px;
            border-radius: 50%;
            padding-left: 0;
        }

        /* Event Badges */
        .event-badge { 
            padding: 5px 8px; 
            border-radius: 6px; 
            color: #fff; 
            font-size: 0.73rem; 
            font-weight: 600; 
            margin-bottom: 5px; 
            line-height: 1.25;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
            word-break: break-word;
            display: block;
            text-decoration: none;
        }
        .event-badge:hover { 
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.12);
            opacity: 0.95;
            color: #fff;
        }

        /* Projects Legend Section */
        .legend-section { 
            margin-top: 2rem; 
        }
        .legend-heading {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.85rem;
        }

        .legend-item { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            padding: 7px 15px; 
            border-radius: 20px; 
            background: #fff; 
            border: 1px solid #e2e8f0; 
            font-size: 0.8125rem; 
            font-weight: 600; 
            color: #1e293b;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .legend-item:hover { 
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        .legend-item.active-filter {
            background-color: #f0fdf4;
            border-color: #22c55e;
            color: #16a34a;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
        }
        .dot { 
            width: 9px; 
            height: 9px; 
            border-radius: 50%; 
            flex-shrink: 0;
        }

        /* Modal Customizations */
        .modal-content {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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

        <!-- Master Schedule Header -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <h1 class="page-title">Master Schedule</h1>
                <?php if ($filterProjectId > 0): ?>
                    <span class="badge bg-light text-success border border-success-subtle font-monospace px-2 py-1">
                        Filtered View 
                        <a href="master_schedule.php?month=<?= $currentMonth ?>&year=<?= $currentYear ?>" class="text-danger ms-1 text-decoration-none" title="Clear Filter">✕</a>
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Month-by-Month Navigation -->
            <div class="month-selector">
                <a href="master_schedule.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?><?= $filterProjectId > 0 ? '&project_id=' . $filterProjectId : '' ?>" 
                   class="nav-btn" title="Previous Month">
                    <i class="bi bi-chevron-left"></i>
                </a>
                
                <h4 class="month-heading"><?= $monthName ?> <?= $currentYear ?></h4>
                
                <a href="master_schedule.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?><?= $filterProjectId > 0 ? '&project_id=' . $filterProjectId : '' ?>" 
                   class="nav-btn" title="Next Month">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <!-- New Event Trigger -->
            <div>
                <button type="button" class="btn-new-event" data-bs-toggle="modal" data-bs-target="#newEventModal">
                    <i class="bi bi-plus-lg"></i> New Event
                </button>
            </div>
        </div>

        <!-- Master Calendar Card -->
        <div class="calendar-card">
            <!-- Day of Week Row -->
            <div class="calendar-header">
                <div class="day-label">Sun</div>
                <div class="day-label">Mon</div>
                <div class="day-label">Tue</div>
                <div class="day-label">Wed</div>
                <div class="day-label">Thu</div>
                <div class="day-label">Fri</div>
                <div class="day-label">Sat</div>
            </div>

            <!-- Dynamic 7-Column Days Grid -->
            <div class="calendar-grid">
                <?php
                $todayDate = date('Y-n-j');

                // 1. Leading blank cells for days before the 1st of the month
                for ($i = 0; $i < $firstDayOfMonth; $i++) {
                    echo '<div class="calendar-cell cell-muted"></div>';
                }

                // 2. Days of the current month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $cellDate = "{$currentYear}-{$currentMonth}-{$day}";
                    $isToday = ($cellDate === $todayDate);
                    $numClass = $isToday ? 'day-num is-today' : 'day-num';

                    echo '<div class="calendar-cell">';
                    echo '<div class="' . $numClass . '">' . $day . '</div>';
                    
                    // Render events for this day
                    if (!empty($calendarEvents[$day])) {
                        foreach ($calendarEvents[$day] as $ev) {
                            $evJson = htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8');
                            echo '<div class="event-badge" ';
                            echo 'style="background-color: ' . htmlspecialchars($ev['color']) . ';" ';
                            echo 'onclick="openViewEventModal(' . $evJson . ')" ';
                            echo 'title="' . htmlspecialchars($ev['display_title']) . '">';
                            echo $ev['display_title'];
                            echo '</div>';
                        }
                    }
                    
                    echo '</div>';
                }

                // 3. Trailing blank cells to complete the 7-day grid row
                $totalCells = $firstDayOfMonth + $daysInMonth;
                $trailingCells = (7 - ($totalCells % 7)) % 7;
                for ($j = 0; $j < $trailingCells; $j++) {
                    echo '<div class="calendar-cell cell-muted"></div>';
                }
                ?>
            </div>
        </div>

        <!-- Multi-Project Color Legend -->
        <div class="legend-section">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="legend-heading mb-0">Projects</h6>
                <?php if ($filterProjectId > 0): ?>
                    <a href="master_schedule.php?month=<?= $currentMonth ?>&year=<?= $currentYear ?>" class="btn btn-sm btn-link text-muted p-0 text-decoration-none small">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                    </a>
                <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <?php if (empty($scheduledProjects)): ?>
                    <span class="text-muted small">No scheduled events or project milestones for this month.</span>
                <?php else: ?>
                    <?php foreach ($scheduledProjects as $pId => $pData): 
                        $isActive = ($filterProjectId === $pId);
                        $filterUrl = $isActive 
                            ? "master_schedule.php?month={$currentMonth}&year={$currentYear}" 
                            : "master_schedule.php?month={$currentMonth}&year={$currentYear}&project_id={$pId}";
                    ?>
                        <a href="<?= $filterUrl ?>" 
                           class="legend-item <?= $isActive ? 'active-filter' : '' ?>"
                           title="<?= $isActive ? 'Click to show all projects' : 'Filter by ' . htmlspecialchars($pData['name']) ?>">
                            <div class="dot" style="background: <?= htmlspecialchars($pData['color']) ?>;"></div>
                            <?= htmlspecialchars($pData['name']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 1: "+ New Event" Modal                                  -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="newEventModal" tabindex="-1" aria-labelledby="newEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="master_schedule.php?month=<?= $currentMonth ?>&year=<?= $currentYear ?><?= $filterProjectId > 0 ? '&project_id=' . $filterProjectId : '' ?>">
                    <input type="hidden" name="action" value="create_event">
                    
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="newEventModalLabel">
                            <i class="bi bi-calendar-plus text-success me-2"></i>Schedule New Event / Milestone
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <!-- Project Selection -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Project <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-select bg-white" required>
                                <option value="">Select Project...</option>
                                <?php foreach ($allProjects as $prj): ?>
                                    <option value="<?= $prj['id'] ?>" <?= $filterProjectId === $prj['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prj['project_name']) ?> <?= !empty($prj['project_code']) ? '(' . htmlspecialchars($prj['project_code']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Event Title -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Event / Milestone Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Earthwork Start, Foundation Complete" required>
                        </div>

                        <!-- Event Category & Status -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Event Category</label>
                                <select name="event_type" class="form-select bg-white">
                                    <option value="Milestone" selected>Milestone</option>
                                    <option value="Phase Start">Phase Start</option>
                                    <option value="Phase End">Phase End</option>
                                    <option value="Inspection">Inspection</option>
                                    <option value="Delivery">Delivery</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Initial Status</label>
                                <select name="status" class="form-select bg-white">
                                    <option value="Upcoming" selected>Upcoming</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Delayed">Delayed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Date Range -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Start / Event Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" value="<?= sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, min(intval(date('d')), $daysInMonth)) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">End Date <span class="text-muted fw-normal">(Optional)</span></label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>

                        <!-- Custom Color Override (Optional) -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Custom Color Tag <span class="text-muted fw-normal">(Optional)</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="color_hex" class="form-control form-control-color p-1" value="#3b82f6" title="Choose custom color">
                                <span class="small text-muted">Leave default or pick custom event banner color.</span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Description / Site Notes</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Enter scope details, inspection requirements, or logistical notes..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-nexus-primary px-4">
                            <i class="bi bi-check-lg me-1"></i> Add to Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------------------- -->
    <!-- Modal 2: "View / Edit Event" Modal                             -->
    <!-- ------------------------------------------------------------- -->
    <div class="modal fade" id="viewEventModal" tabindex="-1" aria-labelledby="viewEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <!-- For Synchronized Project Dates (Read Only view linking to Project Overview) -->
                <div id="syncEventView" style="display: none;">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="viewEventModalLabel">
                            <i class="bi bi-flag-fill text-primary me-2"></i>Project Lifecycle Milestone
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="small text-muted fw-semibold uppercase mb-1">PROJECT</div>
                            <h5 id="syncProjectTitle" class="fw-bold mb-1"></h5>
                        </div>
                        <div class="mb-3">
                            <div class="small text-muted fw-semibold mb-1">EVENT TYPE</div>
                            <span id="syncEventTypeBadge" class="badge bg-primary-subtle text-primary font-monospace px-2 py-1"></span>
                        </div>
                        <div class="mb-3">
                            <div class="small text-muted fw-semibold mb-1">SCHEDULED DATE</div>
                            <div id="syncEventDate" class="fw-semibold"></div>
                        </div>
                        <div class="mb-3">
                            <div class="small text-muted fw-semibold mb-1">NOTES</div>
                            <p id="syncEventDesc" class="text-muted small mb-0"></p>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <a id="syncProjectOverviewLink" href="#" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Project Overview
                        </a>
                        <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>

                <!-- For Interactive Project Milestones (Editable & Deletable) -->
                <div id="milestoneEditView">
                    <form method="POST" action="master_schedule.php?month=<?= $currentMonth ?>&year=<?= $currentYear ?><?= $filterProjectId > 0 ? '&project_id=' . $filterProjectId : '' ?>">
                        <input type="hidden" name="action" value="edit_event">
                        <input type="hidden" name="event_id" id="editEventId">

                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">
                                <i class="bi bi-pencil-square text-success me-2"></i>Event Details & Status
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <!-- Linked Project (read or update) -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Project</label>
                                <select name="project_id" id="editEventProjectId" class="form-select bg-white" required>
                                    <?php foreach ($allProjects as $prj): ?>
                                        <option value="<?= $prj['id'] ?>">
                                            <?= htmlspecialchars($prj['project_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Title -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Event Title</label>
                                <input type="text" name="title" id="editEventTitle" class="form-control" required>
                            </div>

                            <!-- Category & Status -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Category</label>
                                    <select name="event_type" id="editEventType" class="form-select bg-white">
                                        <option value="Milestone">Milestone</option>
                                        <option value="Phase Start">Phase Start</option>
                                        <option value="Phase End">Phase End</option>
                                        <option value="Inspection">Inspection</option>
                                        <option value="Delivery">Delivery</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Status</label>
                                    <select name="status" id="editEventStatus" class="form-select bg-white">
                                        <option value="Upcoming">Upcoming</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Delayed">Delayed</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Dates -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">Start Date</label>
                                    <input type="date" name="start_date" id="editEventStartDate" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">End Date</label>
                                    <input type="date" name="end_date" id="editEventEndDate" class="form-control">
                                </div>
                            </div>

                            <!-- Color Override -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Color Banner</label>
                                <input type="color" name="color_hex" id="editEventColor" class="form-control form-control-color p-1">
                            </div>

                            <!-- Description -->
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-muted">Description / Notes</label>
                                <textarea name="description" id="editEventDesc" class="form-control" rows="3"></textarea>
                            </div>
                        </div>

                        <div class="modal-footer justify-content-between">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteEvent()">
                                <i class="bi bi-trash3 me-1"></i> Delete
                            </button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-nexus-primary btn-sm px-4">Save Changes</button>
                            </div>
                        </div>
                    </form>

                    <!-- Separate Hidden Form for Deletion -->
                    <form id="deleteEventForm" method="POST" action="master_schedule.php?month=<?= $currentMonth ?>&year=<?= $currentYear ?><?= $filterProjectId > 0 ? '&project_id=' . $filterProjectId : '' ?>">
                        <input type="hidden" name="action" value="delete_event">
                        <input type="hidden" name="event_id" id="deleteEventId">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal Trigger for Event Details
        function openViewEventModal(ev) {
            let syncView = document.getElementById('syncEventView');
            let editView = document.getElementById('milestoneEditView');

            if (ev.is_sync) {
                // Synchronized Project Milestone View
                syncView.style.display = 'block';
                editView.style.display = 'none';

                document.getElementById('syncProjectTitle').innerText = ev.project_name;
                document.getElementById('syncEventTypeBadge').innerText = ev.sync_type || ev.event_type;
                document.getElementById('syncEventDate').innerText = ev.start_date;
                document.getElementById('syncEventDesc').innerText = ev.description;
                document.getElementById('syncProjectOverviewLink').href = 'project_overview.php?id=' + ev.project_id;
            } else {
                // Editable Project Milestone View
                syncView.style.display = 'none';
                editView.style.display = 'block';

                document.getElementById('editEventId').value = ev.id;
                document.getElementById('deleteEventId').value = ev.id;
                document.getElementById('editEventProjectId').value = ev.project_id;
                document.getElementById('editEventTitle').value = ev.title;
                document.getElementById('editEventType').value = ev.event_type || 'Milestone';
                document.getElementById('editEventStatus').value = ev.status || 'Upcoming';
                document.getElementById('editEventStartDate').value = ev.start_date;
                document.getElementById('editEventEndDate').value = ev.end_date || '';
                document.getElementById('editEventColor').value = ev.color || '#3b82f6';
                document.getElementById('editEventDesc').value = ev.description || '';
            }

            let viewModal = new bootstrap.Modal(document.getElementById('viewEventModal'));
            viewModal.show();
        }

        // Deletion Confirmation
        function confirmDeleteEvent() {
            if (confirm('Are you sure you want to remove this event from the master schedule?')) {
                document.getElementById('deleteEventForm').submit();
            }
        }
    </script>
</body>
</html>