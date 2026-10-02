<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

// Role-based access control: Project Manager or Admin only
checkRole(['Admin', 'Project Manager']);

$user_id   = (int)($_SESSION['user_id'] ?? 1);
$user_role = $_SESSION['role'] ?? 'Project Manager';
$is_admin  = ($user_role === 'Admin') ? 1 : 0;
$pm_id     = $user_id;
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Project Manager');

// -------------------------------------------------------------
// POST Request Handlers: Actions & Modals
// -------------------------------------------------------------
$flash_success = '';
$flash_error   = '';

if (isset($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. Process Pending GRN Approval / Rejection
        if ($action === 'process_grn') {
            $grn_id   = (int)($_POST['grn_id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            $notes    = trim($_POST['decision_notes'] ?? '');

            if ($grn_id > 0 && in_array($decision, ['approve', 'reject'])) {
                $new_status = ($decision === 'approve') ? 'Approved' : 'Rejected';
                
                $stmt = $pdo->prepare("
                    UPDATE grn 
                    SET status = :status, 
                        notes = CONCAT(COALESCE(notes, ''), :append_note)
                    WHERE id = :id
                ");
                $append = " | PM Sign-off: " . ucfirst($new_status) . " on " . date('Y-m-d H:i') . ($notes ? " ($notes)" : "");
                $stmt->execute([
                    ':status'      => $new_status,
                    ':append_note' => $append,
                    ':id'          => $grn_id
                ]);

                // If approved, update associated PO status to 'Goods Received'
                if ($new_status === 'Approved') {
                    $po_stmt = $pdo->prepare("SELECT po_id FROM grn WHERE id = ?");
                    $po_stmt->execute([$grn_id]);
                    $po_id = $po_stmt->fetchColumn();
                    if ($po_id) {
                        $update_po = $pdo->prepare("UPDATE purchase_orders SET status = 'Goods Received' WHERE id = ?");
                        $update_po->execute([$po_id]);
                    }
                }

                $_SESSION['flash_success'] = "GRN #{$grn_id} has been successfully {$new_status}!";
            } else {
                $_SESSION['flash_error'] = "Invalid action parameters for GRN review.";
            }
            header("Location: dashboard.php");
            exit();
        }

        // 2. Create New Project
        if ($action === 'create_project') {
            $p_name   = trim($_POST['project_name'] ?? '');
            $c_name   = trim($_POST['client_name'] ?? '');
            $budget   = floatval($_POST['budget'] ?? 0);
            $start_dt = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
            $stage    = $_POST['stage'] ?? 'Planning';
            $p_code   = 'PRJ-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $p_name), 0, 4)) . '-' . rand(100, 999);

            if (!empty($p_name)) {
                $stmt = $pdo->prepare("
                    INSERT INTO projects (project_code, project_name, client_name, budget, start_date, stage, status, pm_id, progress_percent)
                    VALUES (:p_code, :p_name, :c_name, :budget, :start_dt, :stage, 'Active', :pm_id, 0)
                ");
                $stmt->execute([
                    ':p_code'   => $p_code,
                    ':p_name'   => $p_name,
                    ':c_name'   => $c_name,
                    ':budget'   => $budget,
                    ':start_dt' => $start_dt,
                    ':stage'    => $stage,
                    ':pm_id'    => $pm_id
                ]);
                $_SESSION['flash_success'] = "Project '{$p_name}' successfully created and marked Active.";
            } else {
                $_SESSION['flash_error'] = "Project name cannot be empty.";
            }
            header("Location: dashboard.php");
            exit();
        }

        // 3. Quick Task Modal
        if ($action === 'quick_task') {
            $task_title = trim($_POST['task_title'] ?? '');
            $project_id = (int)($_POST['project_id'] ?? 0);
            $assigned   = (int)($_POST['assigned_to'] ?? $user_id);
            $due_date   = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+3 days'));
            $priority   = $_POST['priority'] ?? 'Medium';

            if (!empty($task_title) && $project_id > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (project_id, assigned_to, title, priority, due_date, status, created_by)
                    VALUES (:project_id, :assigned_to, :title, :priority, :due_date, 'To Do', :created_by)
                ");
                $stmt->execute([
                    ':project_id'  => $project_id,
                    ':assigned_to' => $assigned,
                    ':title'       => $task_title,
                    ':priority'    => $priority,
                    ':due_date'    => $due_date,
                    ':created_by'  => $user_id
                ]);
                $_SESSION['flash_success'] = "New task '{$task_title}' successfully created!";
            } else {
                $_SESSION['flash_error'] = "Task title and project selection are required.";
            }
            header("Location: dashboard.php");
            exit();
        }

        // 4. Quick Contract / Quote Modal
        if ($action === 'quick_contract') {
            $project_id   = (int)($_POST['project_id'] ?? 0);
            $total_amount = floatval($_POST['total_amount'] ?? 0);
            $quote_code   = 'Q-' . rand(1000, 9999);

            if ($project_id > 0 && $total_amount > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO quotes (quote_code, project_id, total_amount, status, is_approved)
                    VALUES (:quote_code, :project_id, :total_amount, 'Sent', 0)
                ");
                $stmt->execute([
                    ':quote_code'   => $quote_code,
                    ':project_id'   => $project_id,
                    ':total_amount' => $total_amount
                ]);
                $_SESSION['flash_success'] = "Quote #{$quote_code} generated and sent for client review.";
            } else {
                $_SESSION['flash_error'] = "Valid project and amount are required for contract drafting.";
            }
            header("Location: dashboard.php");
            exit();
        }

        // 5. Quick Drawing / Plan File Upload
        if ($action === 'quick_upload') {
            $project_id = (int)($_POST['project_id'] ?? 0);
            $plan_title = trim($_POST['title'] ?? 'Architectural Blueprint Layout');
            $discipline = $_POST['discipline'] ?? 'Architectural';
            $plan_code  = 'A-' . rand(100, 999);

            if ($project_id > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO project_floor_plans (project_id, plan_code, title, discipline, revision, status)
                    VALUES (:project_id, :plan_code, :title, :discipline, 'v1.0', 'Active')
                ");
                $stmt->execute([
                    ':project_id' => $project_id,
                    ':plan_code'  => $plan_code,
                    ':title'      => $plan_title,
                    ':discipline' => $discipline
                ]);
                $_SESSION['flash_success'] = "Plan drawing '{$plan_title}' uploaded to Project Floor Plans!";
            } else {
                $_SESSION['flash_error'] = "Please select a target project for the upload.";
            }
            header("Location: dashboard.php");
            exit();
        }

    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "System Database Exception: " . $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
}

// -------------------------------------------------------------
// Database Schema & Aggregation Queries (MySQL)
// -------------------------------------------------------------

// Helper for relative timestamps
function format_time_elapsed($datetime) {
    if (empty($datetime)) return 'Just now';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'Just now';
    $difference = time() - $timestamp;

    if ($difference < 60) {
        return 'Just now';
    } elseif ($difference < 3600) {
        $minutes = max(1, round($difference / 60));
        return $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes') . ' ago';
    } elseif ($difference < 86400) {
        $hours = max(1, round($difference / 3600));
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
    } elseif ($difference < 604800) {
        $days = max(1, round($difference / 86400));
        return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

try {
    // 1. KPI 1: Active Projects
    $stmt_active = $pdo->prepare("
        SELECT COUNT(*) 
        FROM projects 
        WHERE status = 'Active' 
          AND (pm_id = :pm_id OR :is_admin = 1)
    ");
    $stmt_active->execute([':pm_id' => $pm_id, ':is_admin' => $is_admin]);
    $kpi_active_projects = (int)$stmt_active->fetchColumn();

    // 2. KPI 2: Pending Quotes
    $stmt_quotes = $pdo->prepare("
        SELECT COUNT(*) 
        FROM quotes 
        WHERE is_approved = 0 
          AND status = 'Sent'
    ");
    $stmt_quotes->execute();
    $kpi_pending_quotes = (int)$stmt_quotes->fetchColumn();

    // 3. KPI 3: Tasks Overdue
    $stmt_tasks = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tasks 
        WHERE due_date < CURRENT_DATE() 
          AND status != 'Done' 
          AND (
              assigned_to = :user_id 
              OR project_id IN (SELECT id FROM projects WHERE pm_id = :pm_id OR :is_admin = 1)
          )
    ");
    $stmt_tasks->execute([':user_id' => $user_id, ':pm_id' => $pm_id, ':is_admin' => $is_admin]);
    $kpi_tasks_overdue = (int)$stmt_tasks->fetchColumn();

    // 4. KPI 4: Budget Alert
    $stmt_budget = $pdo->prepare("
        SELECT COUNT(*) 
        FROM projects p 
        JOIN (
            SELECT project_id, SUM(total_amount) AS spent 
            FROM bills 
            GROUP BY project_id
        ) b ON p.id = b.project_id 
        WHERE b.spent > p.budget 
          AND p.status = 'Active' 
          AND (p.pm_id = :pm_id OR :is_admin = 1)
    ");
    $stmt_budget->execute([':pm_id' => $pm_id, ':is_admin' => $is_admin]);
    $kpi_budget_alert = (int)$stmt_budget->fetchColumn();

    // 5. My Pending Actions (GRNs requiring PM sign-off)
    $stmt_actions = $pdo->prepare("
        SELECT 
            'GRN' AS action_type, 
            grn.id, 
            grn.grn_number, 
            po.po_number, 
            p.project_name, 
            grn.received_date, 
            grn.notes, 
            COALESCE(u.full_name, 'Site Warehouse') AS received_by_name
        FROM grn 
        JOIN purchase_orders po ON grn.po_id = po.id 
        JOIN projects p ON po.project_id = p.id 
        LEFT JOIN users u ON grn.received_by = u.id 
        WHERE grn.status = 'Pending Approval' 
          AND (p.pm_id = :pm_id OR :is_admin = 1) 
        ORDER BY grn.id DESC 
        LIMIT 3
    ");
    $stmt_actions->execute([':pm_id' => $pm_id, ':is_admin' => $is_admin]);
    $pending_actions = $stmt_actions->fetchAll(PDO::FETCH_ASSOC);

    // 6. My Active Projects Query
    $stmt_projects = $pdo->prepare("
        SELECT 
            p.id, 
            p.project_name, 
            p.client_name, 
            p.stage, 
            p.budget, 
            COALESCE(SUM(b.total_amount), 0) AS actual_spend 
        FROM projects p 
        LEFT JOIN bills b ON p.id = b.project_id 
        WHERE p.status = 'Active' 
          AND (p.pm_id = :pm_id OR :is_admin = 1) 
        GROUP BY p.id 
        ORDER BY p.id ASC 
        LIMIT 5
    ");
    $stmt_projects->execute([':pm_id' => $pm_id, ':is_admin' => $is_admin]);
    $active_projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

    // 7. Sales Pipeline (New Leads)
    $stmt_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'New'");
    $kpi_new_leads = (int)$stmt_leads->fetchColumn();
    $total_leads   = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn() ?: 1;
    $pipeline_pct  = min(100, max(20, round(($kpi_new_leads / $total_leads) * 100)));

    // 8. Recent Project Activity Audit Log
    $stmt_activity = $pdo->prepare("
        SELECT 
            CONCAT('Foreman updated Task \'', t.title, '\' to Done on Project \'', p.project_name, '\'') AS activity_text, 
            t.completed_at AS event_time, 
            'task' AS activity_type
        FROM tasks t 
        JOIN projects p ON t.project_id = p.id 
        WHERE t.status = 'Done' AND t.completed_at IS NOT NULL AND (p.pm_id = :pm_id OR :is_admin = 1)
        
        UNION ALL
        
        SELECT 
            CONCAT('You created Quote #', q.quote_code, ' for Project \'', p.project_name, '\'') AS activity_text, 
            q.created_at AS event_time, 
            'quote' AS activity_type
        FROM quotes q 
        JOIN projects p ON q.project_id = p.id 
        WHERE (p.pm_id = :pm_id OR :is_admin = 1)
        
        UNION ALL
        
        SELECT 
            CONCAT('Daily Report submitted for Project \'', p.project_name, '\'') AS activity_text, 
            dr.created_at AS event_time, 
            'report' AS activity_type
        FROM daily_reports dr 
        JOIN projects p ON dr.project_id = p.id 
        WHERE (p.pm_id = :pm_id OR :is_admin = 1)

        UNION ALL

        SELECT 
            CONCAT(u.full_name, ' posted note on \'', p.project_name, '\': \"', SUBSTRING(n.note_text, 1, 40), IF(LENGTH(n.note_text)>40, '...', ''), '\"') AS activity_text,
            n.created_at AS event_time,
            'note' AS activity_type
        FROM project_notes n
        JOIN users u ON n.user_id = u.id
        JOIN projects p ON n.project_id = p.id
        WHERE (p.pm_id = :pm_id OR :is_admin = 1)
        
        ORDER BY event_time DESC 
        LIMIT 6
    ");
    $stmt_activity->execute([':pm_id' => $pm_id, ':is_admin' => $is_admin]);
    $recent_activities = $stmt_activity->fetchAll(PDO::FETCH_ASSOC);

    // 9. Recent Client Notes & Inquiries for PM
    $stmt_c_notes = $pdo->prepare("
        SELECT n.id, n.project_id, n.note_text, n.created_at, u.full_name, u.role, p.project_name
        FROM project_notes n
        JOIN users u ON n.user_id = u.id
        JOIN projects p ON n.project_id = p.id
        WHERE (p.pm_id = :pm_id OR :is_admin = 1)
        ORDER BY n.created_at DESC
        LIMIT 4
    ");
    $stmt_c_notes->execute([':pm_id' => $pm_id, ':is_admin' => $is_admin]);
    $recent_client_notes = $stmt_c_notes->fetchAll(PDO::FETCH_ASSOC);

    // Dropdown helpers for Modals
    $all_active_projects = $pdo->query("SELECT id, project_name, client_name FROM projects WHERE status = 'Active' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $assignees = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('Project Manager', 'Foreman', 'Worker', 'Admin') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div class='alert alert-danger m-4'>Database Aggregation Error: " . htmlspecialchars($e->getMessage()) . "</div>");
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
    <title>Dashboard - BuildNexus PM Command Center</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --nexus-green: #15803d;
            --nexus-green-hover: #166534;
            --nexus-border: #e2e8f0;
            --nexus-text-main: #0f172a;
            --nexus-text-muted: #64748b;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: var(--nexus-text-main);
            margin: 0;
            padding: 0;
        }

        /* Standardized Left Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid var(--nexus-border);
            position: fixed;
            top: 0;
            left: 0;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            z-index: 1030;
            box-sizing: border-box;
        }

        .sidebar-brand {
            padding-bottom: 0.25rem;
        }

        .btn-create-project {
            background-color: #10753a;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-create-project:hover {
            background-color: #0b582b;
            color: #ffffff;
        }

        .sidebar-nav-container {
            overflow-y: auto;
            margin-right: -6px;
            padding-right: 6px;
        }

        .sidebar-nav-link {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }
        .sidebar-nav-link:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }
        .sidebar-nav-link.active {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 600;
        }

        .sidebar-sub-link {
            padding: 5px 10px;
            font-size: 0.84rem;
            color: #64748b;
        }

        .sidebar-logout-link {
            padding: 8px 12px;
            color: #dc2626 !important;
            text-decoration: none;
            font-size: 0.88rem;
            border-radius: 8px;
            transition: all 0.15s ease;
        }
        .sidebar-logout-link:hover {
            background-color: #fef2f2;
            color: #b91c1c !important;
        }

        /* Main Workspace Container */
        .main-workspace {
            margin-left: 250px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
        }

        /* Top Header Search Bar */
        .dashboard-header {
            margin-bottom: 2rem;
        }
        .dashboard-title {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #0f172a;
        }
        .search-container {
            position: relative;
            width: 320px;
        }
        .search-input {
            border: 1px solid var(--nexus-border);
            border-radius: 8px;
            padding: 0.45rem 0.85rem;
            font-size: 0.88rem;
            width: 100%;
            background-color: #ffffff;
            color: #334155;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .search-input:focus {
            border-color: #94a3b8;
            outline: none;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.15);
        }
        .search-dropdown {
            position: absolute;
            top: 105%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid var(--nexus-border);
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08);
            z-index: 1050;
            display: none;
            max-height: 250px;
            overflow-y: auto;
        }
        .search-dropdown-item {
            padding: 8px 12px;
            font-size: 0.84rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            text-decoration: none;
            display: block;
            color: #1e293b;
        }
        .search-dropdown-item:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }

        .avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #475569;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }

        /* KPI Metric Cards */
        .kpi-card {
            background: #ffffff;
            border: 1px solid var(--nexus-border);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .kpi-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--nexus-text-muted);
            margin-bottom: 0.5rem;
        }
        .kpi-value {
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--nexus-text-main);
            line-height: 1;
        }

        /* Budget Alert Highlight Card */
        .kpi-card-danger {
            background: #fef2f2 !important;
            border: 1px solid #fee2e2 !important;
        }
        .kpi-card-danger .kpi-title {
            color: #dc2626 !important;
        }
        .kpi-card-danger .kpi-value {
            color: #dc2626 !important;
        }

        /* General Nexus Card */
        .nexus-card {
            background: #ffffff;
            border: 1px solid var(--nexus-border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            margin-bottom: 1.5rem;
        }
        .nexus-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid transparent;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nexus-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--nexus-text-main);
            margin: 0;
        }

        /* Badges */
        .badge-stage {
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 9999px;
            display: inline-block;
        }
        .badge-over {
            background: #fee2e2;
            color: #dc2626;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 9999px;
            display: inline-block;
        }
        .badge-ontrack {
            background: #dcfce7;
            color: #16a34a;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 9999px;
            display: inline-block;
        }

        /* Quick Create Grid */
        .quick-action-btn {
            background: #ffffff;
            border: 1px solid var(--nexus-border);
            border-radius: 10px;
            padding: 1.25rem 0.75rem;
            text-align: center;
            color: #475569;
            text-decoration: none;
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .quick-action-btn i {
            font-size: 1.35rem;
            margin-bottom: 0.35rem;
            display: block;
            color: #64748b;
        }
        .quick-action-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        .quick-action-btn:hover i {
            color: #0f172a;
        }

        /* Tables */
        .projects-table th {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            background-color: #ffffff;
            border-bottom: 1px solid var(--nexus-border);
            padding: 0.75rem 1rem;
        }
        .projects-table td {
            font-size: 0.875rem;
            padding: 0.85rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        .projects-table tr:last-child td {
            border-bottom: none;
        }

        /* Action Menu Dropdown */
        .dropdown-toggle::after {
            display: none;
        }

        /* Mobile Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-workspace {
                margin-left: 0;
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>

    <!-- Standardized Sidebar Navigation -->
    <?php 
    $current_page = 'dashboard';
    require_once __DIR__ . '/sidebar.php'; 
    ?>

    <!-- Main Operational Workspace -->
    <main class="main-workspace">

        <!-- Flash Message Banners -->
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 border-0 shadow-sm" role="alert" style="background-color: #ecfdf5; color: #065f46; border-left: 4px solid #10b981 !important;">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div class="fw-medium small"><?= htmlspecialchars($flash_success) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 border-0 shadow-sm" role="alert" style="background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444 !important;">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div class="fw-medium small"><?= htmlspecialchars($flash_error) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Top Search & Notification Bar -->
        <header class="d-flex justify-content-between align-items-center dashboard-header">
            <div>
                <h2 class="dashboard-title mb-0">Dashboard</h2>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Search Projects Input with real-time dropdown filtering -->
                <div class="search-container">
                    <input type="text" id="projectSearchInput" class="search-input" placeholder="Search projects..." autocomplete="off">
                    <div id="projectSearchDropdown" class="search-dropdown">
                        <?php foreach($all_active_projects as $prj): ?>
                            <a href="features/project_financials.php?project_id=<?= $prj['id'] ?>" class="search-dropdown-item d-flex justify-content-between align-items-center" data-name="<?= strtolower(htmlspecialchars($prj['project_name'])) ?>">
                                <span class="fw-semibold"><?= htmlspecialchars($prj['project_name']) ?></span>
                                <span class="text-muted small"><?= htmlspecialchars($prj['client_name'] ?? 'Active') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Notification Bell with Pending Indicator & Dropdown -->
                <div class="dropdown position-relative">
                    <button type="button" class="btn btn-link text-secondary p-1 border-0 dropdown-toggle" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if (!empty($recent_client_notes) || count($pending_actions) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 8px; height: 8px;"></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2" style="width: 320px; max-height: 420px; overflow-y: auto;">
                        <li class="dropdown-header fw-bold text-dark d-flex justify-content-between align-items-center">
                            <span>Client Notes & Alerts</span>
                            <span class="badge bg-success small"><?= count($recent_client_notes) ?> New</span>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <?php if (!empty($recent_client_notes)): ?>
                            <?php foreach($recent_client_notes as $cn): ?>
                                <li>
                                    <a class="dropdown-item py-2 px-3 text-wrap" href="client_notes.php?project_id=<?= $cn['project_id'] ?>">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($cn['full_name']) ?></span>
                                            <span class="text-muted small" style="font-size: 0.7rem;"><?= format_time_elapsed($cn['created_at']) ?></span>
                                        </div>
                                        <div class="small fw-semibold text-dark mb-1"><?= htmlspecialchars($cn['project_name']) ?></div>
                                        <div class="small text-secondary text-truncate"><?= htmlspecialchars($cn['note_text']) ?></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="px-3 py-2 text-muted small text-center">No new notifications.</li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-center small text-primary fw-semibold" href="client_notes.php">
                                Open All Project Notes &rarr;
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- User Avatar Circle -->
                <div class="avatar-circle" data-bs-toggle="tooltip" title="<?= $user_name ?> (<?= htmlspecialchars($user_role) ?>)">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
            </div>
        </header>

        <!-- Top Metric KPI Row (4 Cards) -->
        <section class="row g-3 mb-4">
            <!-- Card 1: Active Projects -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-title">Active Projects</span>
                    <span class="kpi-value"><?= $kpi_active_projects ?></span>
                </div>
            </div>

            <!-- Card 2: Pending Quotes -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-title">Pending Quotes</span>
                    <span class="kpi-value"><?= $kpi_pending_quotes ?></span>
                </div>
            </div>

            <!-- Card 3: Tasks Overdue -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-title">Tasks Overdue</span>
                    <span class="kpi-value"><?= $kpi_tasks_overdue ?></span>
                </div>
            </div>

            <!-- Card 4: Budget Alert (Highlighted soft red) -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="kpi-card kpi-card-danger">
                    <span class="kpi-title">Budget Alert</span>
                    <span class="kpi-value"><?= $kpi_budget_alert ?></span>
                </div>
            </div>
        </section>

        <!-- Two-Column Middle Grid -->
        <div class="row g-4">
            
            <!-- Left Column: Pending Actions & Active Projects (col-lg-8) -->
            <div class="col-lg-8">
                
                <!-- My Pending Actions Card -->
                <div class="nexus-card">
                    <div class="nexus-card-header">
                        <h6 class="nexus-card-title">My Pending Actions</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($pending_actions)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach($pending_actions as $action_item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3 border-0 border-bottom">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="small text-dark fw-bold">
                                                Approve <?= htmlspecialchars($action_item['action_type']) ?> for <?= htmlspecialchars($action_item['po_number']) ?> (<?= htmlspecialchars($action_item['project_name']) ?>)
                                            </span>
                                        </div>
                                        <button type="button" 
                                                class="btn btn-light btn-sm fw-bold border shadow-sm px-3" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewGrnModal<?= $action_item['id'] ?>">
                                            View
                                        </button>
                                    </li>

                                    <!-- Review GRN Approval Modal -->
                                    <div class="modal fade" id="viewGrnModal<?= $action_item['id'] ?>" tabindex="-1" aria-labelledby="grnModalLabel<?= $action_item['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header border-bottom pb-3">
                                                    <h5 class="modal-title fw-bold fs-6" id="grnModalLabel<?= $action_item['id'] ?>">
                                                        Review Goods Received Note (<?= htmlspecialchars($action_item['grn_number']) ?>)
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="dashboard.php" method="POST">
                                                    <input type="hidden" name="action" value="process_grn">
                                                    <input type="hidden" name="grn_id" value="<?= $action_item['id'] ?>">

                                                    <div class="modal-body p-4">
                                                        <div class="mb-3 p-3 bg-light rounded-3 small">
                                                            <div class="row g-2">
                                                                <div class="col-6"><span class="text-muted d-block">PO Reference:</span> <strong><?= htmlspecialchars($action_item['po_number']) ?></strong></div>
                                                                <div class="col-6"><span class="text-muted d-block">Project:</span> <strong><?= htmlspecialchars($action_item['project_name']) ?></strong></div>
                                                                <div class="col-6"><span class="text-muted d-block">Received Date:</span> <strong><?= htmlspecialchars($action_item['received_date']) ?></strong></div>
                                                                <div class="col-6"><span class="text-muted d-block">Received By:</span> <strong><?= htmlspecialchars($action_item['received_by_name']) ?></strong></div>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold text-muted">Site Warehouse Verification Notes</label>
                                                            <div class="p-2 border rounded bg-white small text-secondary">
                                                                <?= nl2br(htmlspecialchars($action_item['notes'] ?: 'All goods verified against packing slip and inspected for physical damage.')) ?>
                                                            </div>
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold">PM Approval Comment (Optional)</label>
                                                            <input type="text" name="decision_notes" class="form-control form-control-sm" placeholder="e.g. Approved for warehouse inventory entry">
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer border-top bg-light p-3 d-flex justify-content-between">
                                                        <button type="submit" name="decision" value="reject" class="btn btn-outline-danger btn-sm fw-semibold px-3">
                                                            Reject GRN
                                                        </button>
                                                        <div class="d-flex gap-2">
                                                            <button type="button" class="btn btn-light btn-sm fw-semibold border px-3" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="decision" value="approve" class="btn btn-success btn-sm fw-semibold px-4" style="background-color: #15803d; border-color: #15803d;">
                                                                Approve GRN
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted small">
                                <i class="bi bi-shield-check text-success fs-4 d-block mb-1"></i>
                                All pending approvals and change orders are fully up-to-date!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Active Projects Card -->
                <div class="nexus-card">
                    <div class="nexus-card-header">
                        <h6 class="nexus-card-title">My Active Projects</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table projects-table align-middle mb-0" id="activeProjectsTable">
                            <thead>
                                <tr>
                                    <th class="ps-4">Project Title</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Budget</th>
                                    <th class="text-end pe-4"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($active_projects)): ?>
                                    <?php foreach($active_projects as $proj): ?>
                                        <?php
                                            $b_val = floatval($proj['budget']);
                                            $s_val = floatval($proj['actual_spend']);
                                            $is_over = ($b_val > 0 && $s_val > $b_val);
                                            $pct_over = $is_over ? round((($s_val - $b_val) / $b_val) * 100) : 0;
                                            $stage_name = !empty($proj['stage']) ? $proj['stage'] : 'Framing';
                                        ?>
                                        <tr class="project-row" data-title="<?= strtolower(htmlspecialchars($proj['project_name'])) ?>" data-client="<?= strtolower(htmlspecialchars($proj['client_name'] ?? '')) ?>">
                                            <td class="ps-4 fw-semibold text-dark">
                                                <a href="features/project_financials.php?project_id=<?= $proj['id'] ?>" class="text-dark text-decoration-none hover-primary">
                                                    <?= htmlspecialchars($proj['project_name']) ?>
                                                </a>
                                            </td>
                                            <td class="text-muted">
                                                <?= htmlspecialchars($proj['client_name'] ?: '—') ?>
                                            </td>
                                            <td>
                                                <span class="badge-stage"><?= htmlspecialchars($stage_name) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($is_over): ?>
                                                    <span class="badge-over"><?= $pct_over ?>% Over</span>
                                                <?php else: ?>
                                                    <span class="badge-ontrack">On Track</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="dropdown">
                                                    <button class="btn btn-link text-muted p-0 border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-three-dots fs-5"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 small">
                                                        <li><a class="dropdown-item py-2" href="client_notes.php?project_id=<?= $proj['id'] ?>"><i class="bi bi-chat-left-text me-2 text-info"></i>Project Notes & Client Log</a></li>
                                                        <li><a class="dropdown-item py-2" href="features/project_financials.php?project_id=<?= $proj['id'] ?>"><i class="bi bi-cash-stack me-2 text-primary"></i>Financials & Budget</a></li>
                                                        <li><a class="dropdown-item py-2" href="features/tasks.php?project_id=<?= $proj['id'] ?>"><i class="bi bi-check2-square me-2 text-success"></i>Project Tasks</a></li>
                                                        <li><a class="dropdown-item py-2" href="features/projects.php"><i class="bi bi-eye me-2 text-secondary"></i>View Details</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4 small">
                                            No active projects assigned to your portfolio.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Right Column: Quick Actions & Sales Pipeline (col-lg-4) -->
            <div class="col-lg-4">
                
                <!-- Create New Quick Actions Card -->
                <div class="nexus-card p-4">
                    <h6 class="nexus-card-title mb-3">Create New</h6>
                    <div class="row g-2">
                        <!-- File Upload Modal Trigger -->
                        <div class="col-6">
                            <a href="#" class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#fileUploadModal">
                                <i class="bi bi-file-earmark-arrow-up"></i>
                                <span>File Upload</span>
                            </a>
                        </div>
                        <!-- Contract Modal Trigger -->
                        <div class="col-6">
                            <a href="#" class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#contractModal">
                                <i class="bi bi-file-text"></i>
                                <span>Contract</span>
                            </a>
                        </div>
                        <!-- Task Modal Trigger -->
                        <div class="col-6">
                            <a href="#" class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#quickTaskModal">
                                <i class="bi bi-check2-square"></i>
                                <span>Task</span>
                            </a>
                        </div>
                        <!-- Selections Modal Trigger -->
                        <div class="col-6">
                            <a href="features/client_selections.php" class="quick-action-btn">
                                <i class="bi bi-grid-3x3-gap"></i>
                                <span>Selections</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Sales Pipeline Card -->
                <div class="nexus-card p-4">
                    <h6 class="nexus-card-title mb-3">Sales Pipeline</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold text-secondary">New Leads</span>
                        <span class="small fw-bold text-dark fs-6"><?= $kpi_new_leads ?></span>
                    </div>
                    <div class="progress mb-2" style="height: 6px; background-color: #e2e8f0; border-radius: 9999px;">
                        <div class="progress-bar" role="progressbar" style="width: <?= $pipeline_pct ?>%; background-color: #2563eb;" aria-valuenow="<?= $pipeline_pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <small class="text-muted" style="font-size: 0.75rem;">Funnel Conversion Active</small>
                        <a href="features/lead-generation.php" class="small fw-bold text-decoration-none" style="color: #2563eb; font-size: 0.78rem;">CRM Funnel &rarr;</a>
                    </div>
                </div>

                <!-- Recent Client Notes & Inquiries Card -->
                <div class="nexus-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="nexus-card-title mb-0">Client Notes & Comms</h6>
                        <a href="client_notes.php" class="small fw-semibold text-decoration-none" style="color: #15803d; font-size: 0.78rem;">View All &rarr;</a>
                    </div>
                    <?php if (!empty($recent_client_notes)): ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach(array_slice($recent_client_notes, 0, 3) as $cn): ?>
                                <div class="d-flex align-items-start gap-2 border-bottom border-light pb-2">
                                    <div class="avatar-circle-sm bg-light text-dark fw-bold border rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width: 32px; font-size: 0.75rem;">
                                        <?= strtoupper(substr($cn['full_name'], 0, 2)) ?>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small text-dark"><?= htmlspecialchars($cn['full_name']) ?></span>
                                            <span class="text-muted" style="font-size: 0.7rem;"><?= format_time_elapsed($cn['created_at']) ?></span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($cn['project_name']) ?></div>
                                        <p class="text-secondary small mb-1 text-truncate" style="font-size: 0.8rem;"><?= htmlspecialchars($cn['note_text']) ?></p>
                                        <a href="client_notes.php?project_id=<?= $cn['project_id'] ?>" class="text-decoration-none small fw-semibold" style="color: #15803d; font-size: 0.75rem;">
                                            <i class="bi bi-reply-fill me-1"></i>View / Reply
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No client notes recorded yet.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- Bottom Row: Recent Project Activity Audit Log -->
        <section class="nexus-card mt-2">
            <div class="nexus-card-header">
                <h6 class="nexus-card-title">Recent Project Activity</h6>
                <button type="button" class="btn btn-light btn-sm fw-bold border shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#allActivityModal">
                    View All
                </button>
            </div>
            <div class="p-4 pt-2">
                <?php if (!empty($recent_activities)): ?>
                    <div class="activity-stream">
                        <?php foreach($recent_activities as $act): ?>
                            <div class="d-flex align-items-start gap-3 py-2 border-bottom border-light">
                                <div class="mt-1">
                                    <?php if ($act['activity_type'] === 'task'): ?>
                                        <i class="bi bi-check-circle-fill text-success fs-6"></i>
                                    <?php elseif ($act['activity_type'] === 'quote'): ?>
                                        <i class="bi bi-receipt text-primary fs-6"></i>
                                    <?php elseif ($act['activity_type'] === 'note'): ?>
                                        <i class="bi bi-chat-quote-fill text-info fs-6"></i>
                                    <?php else: ?>
                                        <i class="bi bi-journal-text text-secondary fs-6"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-0 small fw-bold text-dark"><?= htmlspecialchars($act['activity_text']) ?></p>
                                    <small class="text-muted" style="font-size: 0.78rem;"><?= format_time_elapsed($act['event_time']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No recent project events recorded today.</p>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- ========================================== -->
    <!-- ACTION MODALS                              -->
    <!-- ========================================== -->

    <!-- 1. Create New Project Modal -->
    <div class="modal fade" id="newProjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold fs-5 mb-0">New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="dashboard.php" method="POST">
                    <input type="hidden" name="action" value="create_project">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Project Title <span class="text-danger">*</span></label>
                            <input type="text" name="project_name" class="form-control" placeholder="e.g. Skyline Residence" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Client Name <span class="text-danger">*</span></label>
                            <input type="text" name="client_name" class="form-control" placeholder="e.g. Mr. Perera" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-semibold mb-1">Budget ($ / LKR) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="budget" class="form-control" placeholder="850000" required>
                            </div>
                            <div class="col-6">
                                <label class="small fw-semibold mb-1">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-semibold mb-1">Initial Stage</label>
                            <select name="stage" class="form-select">
                                <option value="Planning">Planning</option>
                                <option value="Earthwork">Earthwork</option>
                                <option value="Framing" selected>Framing</option>
                                <option value="Roofing">Roofing</option>
                                <option value="Finishing">Finishing</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-success w-100 fw-bold py-2" style="background-color: #10753a; border-color: #10753a;">
                            Create Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. File Upload Modal -->
    <div class="modal fade" id="fileUploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="fw-bold fs-6 mb-0">Upload Drawing / Architectural Blueprint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="quick_upload">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Target Project <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-select" required>
                                <option value="">-- Choose Project --</option>
                                <?php foreach($all_active_projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Drawing / Plan Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Ground Floor Architectural Layout" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Discipline</label>
                            <select name="discipline" class="form-select">
                                <option value="Architectural">Architectural</option>
                                <option value="Structural">Structural</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="HVAC">HVAC</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-semibold mb-1">Drawing File (PDF, PNG, DWG)</label>
                            <input type="file" name="plan_file" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light p-3">
                        <button type="button" class="btn btn-light btn-sm fw-semibold border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold px-4">Upload Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. Contract / Quote Generator Modal -->
    <div class="modal fade" id="contractModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="fw-bold fs-6 mb-0">Generate Contract / Quote</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="dashboard.php" method="POST">
                    <input type="hidden" name="action" value="quick_contract">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Project <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-select" required>
                                <option value="">-- Choose Project --</option>
                                <?php foreach($all_active_projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Contract / Total Quote Amount ($ / LKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="total_amount" class="form-control" placeholder="450000.00" required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-semibold mb-1">Status</label>
                            <input type="text" class="form-control" value="Sent (Awaiting Client Approval)" readonly>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light p-3">
                        <button type="button" class="btn btn-light btn-sm fw-semibold border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm fw-semibold px-4" style="background-color: #15803d; border-color: #15803d;">Generate Quote</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 4. Quick Task Modal -->
    <div class="modal fade" id="quickTaskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="fw-bold fs-6 mb-0">Create Quick Action Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="dashboard.php" method="POST">
                    <input type="hidden" name="action" value="quick_task">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Target Project <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-select" required>
                                <option value="">-- Choose Project --</option>
                                <?php foreach($all_active_projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-semibold mb-1">Task Title <span class="text-danger">*</span></label>
                            <input type="text" name="task_title" class="form-control" placeholder="e.g. Inspect Foundation Rebar" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="small fw-semibold mb-1">Assignee</label>
                                <select name="assigned_to" class="form-select">
                                    <?php foreach($assignees as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= ($u['id'] == $user_id) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="small fw-semibold mb-1">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-semibold mb-1">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light p-3">
                        <button type="button" class="btn btn-light btn-sm fw-semibold border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm fw-semibold px-4" style="background-color: #10753a; border-color: #10753a;">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 5. View All Activity Modal -->
    <div class="modal fade" id="allActivityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="fw-bold fs-6 mb-0">Recent Operational Activity Stream</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="list-group list-group-flush">
                        <?php foreach($recent_activities as $act): ?>
                            <div class="list-group-item d-flex align-items-start gap-3 py-3 border-0 border-bottom">
                                <div class="mt-1">
                                    <?php if ($act['activity_type'] === 'task'): ?>
                                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                    <?php elseif ($act['activity_type'] === 'quote'): ?>
                                        <i class="bi bi-receipt text-primary fs-5"></i>
                                    <?php else: ?>
                                        <i class="bi bi-journal-text text-secondary fs-5"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold text-dark small"><?= htmlspecialchars($act['activity_text']) ?></h6>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?= format_time_elapsed($act['event_time']) ?> &bull; <?= htmlspecialchars($act['event_time'] ?? '') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm fw-semibold" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Client-side Interactive Search & Real-time Filtering -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Enable Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Project Search Bar Real-time table filter and dropdown
            const searchInput = document.getElementById('projectSearchInput');
            const searchDropdown = document.getElementById('projectSearchDropdown');
            const dropdownItems = document.querySelectorAll('.search-dropdown-item');
            const projectRows = document.querySelectorAll('.project-row');

            searchInput.addEventListener('input', function() {
                const query = this.value.trim().toLowerCase();

                // 1. Filter table rows
                projectRows.forEach(row => {
                    const title = row.getAttribute('data-title') || '';
                    const client = row.getAttribute('data-client') || '';
                    if (query === '' || title.includes(query) || client.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // 2. Filter dropdown auto-suggest
                if (query.length > 0) {
                    let matches = 0;
                    dropdownItems.forEach(item => {
                        const name = item.getAttribute('data-name') || '';
                        if (name.includes(query)) {
                            item.style.display = 'flex';
                            matches++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    searchDropdown.style.display = matches > 0 ? 'block' : 'none';
                } else {
                    searchDropdown.style.display = 'none';
                }
            });

            // Close search dropdown on click outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                    searchDropdown.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>