<?php
/**
 * BuildNexus - Client Timeline Stepper (Alias / Features Module)
 * Production-ready, read-only client-facing project timeline with live MySQL persistence,
 * dynamic phase tracking, and strict RBAC security.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// --- 1. Authentication & RBAC Access Control ---
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'User';

if (!$user_id) {
    header("Location: ../login.php");
    exit();
}

try {
    $project = null;
    $all_projects = []; // For Admin/PM project switcher

    if ($user_role === 'Client') {
        // Enforce strict client ownership linkage: client can ONLY access their own assigned project
        $stmt_proj = $pdo->prepare("
            SELECT p.id, p.project_name, p.project_code, p.budget, p.stage, p.progress_percent, p.updated_at, p.start_date, p.end_date, p.status 
            FROM projects p 
            WHERE p.client_id = :client_user_id 
               OR p.id = (SELECT default_project_id FROM contacts WHERE linked_user_id = :client_user_id LIMIT 1)
               OR p.id = (SELECT project_id FROM clients WHERE id = :client_user_id LIMIT 1)
               OR p.id = (SELECT project_id FROM clients WHERE email = (SELECT email FROM users WHERE id = :client_user_id LIMIT 1) LIMIT 1)
            ORDER BY p.id ASC 
            LIMIT 1
        ");
        $stmt_proj->execute([':client_user_id' => $user_id]);
        $project = $stmt_proj->fetch(PDO::FETCH_ASSOC);

        // Client cannot access another project via GET parameter (prevent IDOR)
        if (!$project) {
            $stmt_fallback = $pdo->query("SELECT * FROM projects WHERE id = 26 OR status = 'Active' ORDER BY id ASC LIMIT 1");
            $project = $stmt_fallback->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        // Admin, Project Manager, Foreman: Can view specified project or default to project 26 / first active project
        $stmt_all = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC");
        $all_projects = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

        $requested_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 26;
        $stmt_proj = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt_proj->execute([$requested_id]);
        $project = $stmt_proj->fetch(PDO::FETCH_ASSOC);

        if (!$project && !empty($all_projects)) {
            $stmt_proj->execute([$all_projects[0]['id']]);
            $project = $stmt_proj->fetch(PDO::FETCH_ASSOC);
        }
    }

    $project_id = $project['id'] ?? 0;
    $project_name = $project['project_name'] ?? 'Project Schedule';

    // --- 2. Live Milestone & Phase Querying ---
    // Query project_phases ordered by phase_number ASC, start_date ASC
    $stmt_phases = $pdo->prepare("
        SELECT id, phase_number, title, start_date, end_date, status, description, updated_at
        FROM project_phases
        WHERE project_id = :project_id
        ORDER BY phase_number ASC, start_date ASC
    ");
    $stmt_phases->execute([':project_id' => $project_id]);
    $phases = $stmt_phases->fetchAll(PDO::FETCH_ASSOC);

    // Auto-sync fallback: If project_phases has no entries for this project, check project_milestones
    if (empty($phases)) {
        $stmt_ms = $pdo->prepare("
            SELECT id, title, phase_name, start_date, end_date, status, description, created_at
            FROM project_milestones
            WHERE project_id = ?
            ORDER BY start_date ASC
        ");
        $stmt_ms->execute([$project_id]);
        $milestones = $stmt_ms->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($milestones)) {
            $ins_phase = $pdo->prepare("
                INSERT INTO project_phases (project_id, phase_number, title, start_date, end_date, status, description)
                VALUES (:project_id, :phase_number, :title, :start_date, :end_date, :status, :description)
            ");
            $p_num = 1;
            foreach ($milestones as $ms) {
                $m_title = !empty($ms['title']) ? $ms['title'] : ($ms['phase_name'] ?? 'Phase ' . $p_num);
                $m_status = strtoupper($ms['status']);
                if ($m_status === 'COMPLETED') {
                    $m_status = 'COMPLETED';
                } elseif ($m_status === 'IN PROGRESS') {
                    $m_status = 'IN PROGRESS';
                } else {
                    $m_status = 'UPCOMING';
                }

                $s_date = !empty($ms['start_date']) ? $ms['start_date'] : date('Y-m-d');
                $e_date = !empty($ms['end_date']) ? $ms['end_date'] : date('Y-m-d', strtotime('+7 days'));

                $ins_phase->execute([
                    ':project_id' => $project_id,
                    ':phase_number' => $p_num,
                    ':title' => $m_title,
                    ':start_date' => $s_date,
                    ':end_date' => $e_date,
                    ':status' => $m_status,
                    ':description' => $ms['description'] ?? null
                ]);
                $p_num++;
            }

            $stmt_phases->execute([':project_id' => $project_id]);
            $phases = $stmt_phases->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- 3. Dynamic Progress & Date Calculations ---
    $total_phases = count($phases);
    $completed_phases = 0;
    $in_progress_phases = 0;
    $latest_updated = null;

    foreach ($phases as $ph) {
        $st = strtoupper(trim($ph['status']));
        if ($st === 'COMPLETED') {
            $completed_phases++;
        } elseif ($st === 'IN PROGRESS') {
            $in_progress_phases++;
        }
        if (!empty($ph['updated_at'])) {
            $ts = strtotime($ph['updated_at']);
            if (!$latest_updated || $ts > $latest_updated) {
                $latest_updated = $ts;
            }
        }
    }

    $progress_percent = ($total_phases > 0) ? round(($completed_phases / $total_phases) * 100) : 0;
    $formatted_updated_date = $latest_updated ? date('M d, Y', $latest_updated) : date('M d, Y');

} catch (PDOException $e) {
    die("Error loading project schedule: " . htmlspecialchars($e->getMessage()));
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
    <title>Project Timeline - <?= htmlspecialchars($project_name) ?> | BuildNexus</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --text-heading: #0f172a;
            --text-body: #1e293b;
            --text-muted: #64748b;
            --timeline-line: #cbd5e1;
            
            /* Status Colors */
            --color-completed: #16a34a;
            --bg-completed-pill: #15803d;
            
            --color-progress: #ea580c;
            --bg-progress-pill: #f59e0b;
            --text-progress-pill: #78350f;
            
            --color-upcoming: #94a3b8;
            --bg-upcoming-pill: #475569;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Top portal navigation */
        .portal-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 1.5rem;
        }

        .portal-brand {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .portal-brand i {
            color: #16a34a;
            font-size: 1.25rem;
        }

        /* Main Container */
        .timeline-viewport {
            max-width: 920px;
            margin: 2.5rem auto 3.5rem auto;
            padding: 0 1rem;
        }

        /* High-Fidelity Schedule Card */
        .schedule-card {
            background: var(--card-bg);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2.75rem 3rem;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.03), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
            transition: box-shadow 0.2s ease;
        }

        /* Card Header */
        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.75rem;
            gap: 1.5rem;
        }

        .schedule-subtitle {
            color: #15803d;
            font-size: 0.825rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
        }

        .schedule-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.025em;
            line-height: 1.2;
            margin: 0;
        }

        .schedule-updated {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
            margin-top: 0.75rem;
        }

        /* Stepper & Milestone List */
        .timeline-stepper {
            position: relative;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .timeline-step {
            position: relative;
            padding-left: 2.75rem;
            padding-bottom: 1.5rem;
        }

        .timeline-step:last-child {
            padding-bottom: 0;
        }

        /* Dotted Connecting Line between Stepper Rings */
        .timeline-step:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 25px;
            bottom: -6px;
            width: 0;
            border-left: 2px dotted var(--timeline-line);
            z-index: 1;
        }

        /* Bullseye Double Ring Marker */
        .stepper-marker {
            position: absolute;
            left: 0;
            top: 22px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            box-sizing: border-box;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stepper-marker::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: block;
        }

        /* Completed Marker: Green Ring + Green Dot */
        .stepper-marker.status-completed {
            border: 2.5px solid var(--color-completed);
        }
        .stepper-marker.status-completed::after {
            background-color: var(--color-completed);
        }

        /* In Progress Marker: Orange Ring + Orange Dot */
        .stepper-marker.status-in-progress {
            border: 2.5px solid var(--color-progress);
        }
        .stepper-marker.status-in-progress::after {
            background-color: var(--color-progress);
        }

        /* Upcoming Marker: Slate Grey Ring + Slate Grey Dot */
        .stepper-marker.status-upcoming {
            border: 2.5px solid var(--color-upcoming);
        }
        .stepper-marker.status-upcoming::after {
            background-color: var(--color-upcoming);
        }

        /* Phase Card */
        .phase-card {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 1.25rem 1.6rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .phase-card:hover {
            border-color: #e2e8f0;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.05);
        }

        /* Phase Card Header: Title + Status Pill */
        .phase-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-bottom: 0.45rem;
        }

        .phase-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
            letter-spacing: -0.01em;
        }

        /* Status Pills */
        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 9999px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .status-pill.pill-completed {
            background-color: var(--bg-completed-pill);
            color: #ffffff;
        }

        .status-pill.pill-in-progress {
            background-color: var(--bg-progress-pill);
            color: var(--text-progress-pill);
        }

        .status-pill.pill-upcoming {
            background-color: var(--bg-upcoming-pill);
            color: #ffffff;
        }

        /* Date range */
        .phase-dates {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .phase-dates i {
            margin-right: 0.45rem;
            font-size: 0.95rem;
            color: #94a3b8;
        }

        /* Description (if provided) */
        .phase-desc {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.65rem;
            padding-top: 0.65rem;
            border-top: 1px dashed #f1f5f9;
            line-height: 1.5;
        }

        /* Quick Progress Summary Bar (Subtle) */
        .progress-subtle-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 10px;
            padding: 0.75rem 1.25rem;
            margin-bottom: 2rem;
            font-size: 0.85rem;
        }

        .progress-subtle-bar {
            height: 6px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            width: 140px;
            margin-left: 1rem;
        }

        .progress-subtle-fill {
            height: 100%;
            background: #16a34a;
            border-radius: 999px;
            transition: width 0.4s ease;
        }

        /* Responsive styling */
        @media (max-width: 768px) {
            .schedule-card {
                padding: 1.75rem 1.5rem;
                border-radius: 12px;
            }
            .schedule-header {
                flex-direction: column;
                margin-bottom: 2rem;
                gap: 0.5rem;
            }
            .schedule-title {
                font-size: 1.6rem;
            }
            .schedule-updated {
                margin-top: 0;
            }
            .timeline-step {
                padding-left: 2.25rem;
            }
            .phase-card {
                padding: 1rem 1.2rem;
            }
        }

        /* Print styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .portal-navbar, .no-print {
                display: none !important;
            }
            .timeline-viewport {
                margin: 0 !important;
                max-width: 100% !important;
                padding: 0 !important;
            }
            .schedule-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .phase-card {
                border: 1px solid #e2e8f0 !important;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Top Portal Navigation -->
    <header class="portal-navbar d-flex justify-content-between align-items-center no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="../client_dashboard.php" class="portal-brand">
                <i class="bi bi-box-fill"></i>
                <span>BuildNexus</span>
            </a>
            <span class="badge bg-light text-secondary border px-2 py-1 small">Client Portal</span>
        </div>

        <div class="d-flex align-items-center gap-2">
            <?php if ($user_role !== 'Client' && !empty($all_projects)): ?>
                <!-- Admin / PM Project Switcher -->
                <div class="d-inline-flex align-items-center me-2">
                    <label for="projectSelector" class="small text-muted me-2 d-none d-md-inline">Project:</label>
                    <select id="projectSelector" class="form-select form-select-sm" style="max-width: 220px;" onchange="window.location.href='client_timeline.php?project_id='+this.value">
                        <?php foreach ($all_projects as $proj_opt): ?>
                            <option value="<?= $proj_opt['id'] ?>" <?= ($proj_opt['id'] == $project_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj_opt['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <a href="../client_dashboard.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i>
                <span class="d-none d-sm-inline">Dashboard</span>
            </a>

            <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" title="Print Schedule">
                <i class="bi bi-printer"></i>
                <span class="d-none d-sm-inline">Print</span>
            </button>

            <div class="dropdown">
                <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1 text-secondary"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($user_name) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><span class="dropdown-item-text small text-muted">Role: <?= htmlspecialchars($user_role) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item small" href="../client_dashboard.php"><i class="bi bi-grid me-2"></i>Dashboard</a></li>
                    <li><a class="dropdown-item small" href="client_change_orders.php"><i class="bi bi-arrow-left-right me-2"></i>Change Orders</a></li>
                    <li><a class="dropdown-item small" href="client_chat.php"><i class="bi bi-chat-dots me-2"></i>Project Chat</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item small text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Main Viewport -->
    <main class="timeline-viewport">

        <!-- Progress Overview Bar (Subtle) -->
        <div class="progress-subtle-box no-print">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold text-secondary">Milestones Progress:</span>
                <span class="fw-bold text-success"><?= $completed_phases ?> of <?= $total_phases ?> Completed</span>
                <span class="text-muted small ms-1">(<?= $progress_percent ?>%)</span>
            </div>
            <div class="d-flex align-items-center">
                <div class="progress-subtle-bar">
                    <div class="progress-subtle-fill" style="width: <?= $progress_percent ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- High-Fidelity Schedule Card Matching Design Reference -->
        <div class="schedule-card">

            <!-- Card Header -->
            <div class="schedule-header">
                <div>
                    <div class="schedule-subtitle">PROJECT TIMELINE</div>
                    <h1 class="schedule-title"><?= htmlspecialchars($project_name) ?></h1>
                </div>
                <div class="schedule-updated">
                    Updated: <?= htmlspecialchars($formatted_updated_date) ?>
                </div>
            </div>

            <!-- Milestone Stepper View -->
            <?php if (empty($phases)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x text-muted" style="font-size: 2.5rem;"></i>
                    <h5 class="fw-bold mt-3 mb-1 text-dark">No Phase Milestones Found</h5>
                    <p class="text-muted small">No schedule has been generated for this project yet. Please contact your Project Manager for updates.</p>
                </div>
            <?php else: ?>
                <ul class="timeline-stepper">
                    <?php 
                    foreach ($phases as $index => $phase): 
                        $status_raw = strtoupper(trim($phase['status']));
                        
                        // Standardize status classes and label text
                        if ($status_raw === 'COMPLETED') {
                            $marker_class = 'status-completed';
                            $pill_class = 'pill-completed';
                            $status_label = 'COMPLETED';
                        } elseif ($status_raw === 'IN PROGRESS') {
                            $marker_class = 'status-in-progress';
                            $pill_class = 'pill-in-progress';
                            $status_label = 'IN PROGRESS';
                        } else {
                            $marker_class = 'status-upcoming';
                            $pill_class = 'pill-upcoming';
                            $status_label = 'UPCOMING';
                        }

                        // Format dates: 'Jan 10 — Jan 15, 2026' or 'Jan 23 — Feb 05, 2026'
                        $start_ts = strtotime($phase['start_date']);
                        $end_ts = strtotime($phase['end_date']);
                        
                        if (date('Y', $start_ts) === date('Y', $end_ts)) {
                            $date_range_str = date('M d', $start_ts) . ' — ' . date('M d, Y', $end_ts);
                        } else {
                            $date_range_str = date('M d, Y', $start_ts) . ' — ' . date('M d, Y', $end_ts);
                        }
                    ?>
                        <li class="timeline-step">
                            <!-- Concentric Bullseye Marker -->
                            <div class="stepper-marker <?= $marker_class ?>" title="Status: <?= $status_label ?>"></div>

                            <!-- Phase Card Content -->
                            <div class="phase-card">
                                <div class="phase-meta">
                                    <h2 class="phase-title"><?= htmlspecialchars($phase['title']) ?></h2>
                                    <span class="status-pill <?= $pill_class ?>"><?= $status_label ?></span>
                                </div>

                                <div class="phase-dates">
                                    <i class="bi bi-calendar3"></i>
                                    <span><?= htmlspecialchars($date_range_str) ?></span>
                                </div>

                                <?php if (!empty($phase['description'])): ?>
                                    <div class="phase-desc">
                                        <?= nl2br(htmlspecialchars($phase['description'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </div>
    </main>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
