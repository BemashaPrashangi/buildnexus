<?php
// features/reports_view.php - Production-Ready Construction Executive BI & Reports View
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$type = $_GET['type'] ?? 'budget_actual';
if (!in_array($type, ['budget_actual', 'profitability', 'timeline'])) {
    $type = 'budget_actual';
}

$project_id = intval($_GET['project_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? 'All');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

// 1. Fetch Projects for filter dropdown
try {
    $projects_list = $pdo->query("SELECT id, project_name, project_code, status FROM projects WHERE status != 'Archived' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects_list = [];
}

// 2. Fetch Report-Specific Data
$report_title = '';
$kpi1_title = ''; $kpi1_val = ''; $kpi1_icon = ''; $kpi1_color = '';
$kpi2_title = ''; $kpi2_val = ''; $kpi2_icon = ''; $kpi2_color = '';
$kpi3_title = ''; $kpi3_val = ''; $kpi3_icon = ''; $kpi3_color = '';
$kpi4_title = ''; $kpi4_val = ''; $kpi4_icon = ''; $kpi4_color = '';

$chart_labels = [];
$chart_dataset1 = [];
$chart_dataset2 = [];

$rows = [];

if ($type === 'budget_actual') {
    $report_title = 'Budget vs. Actual Cost Analysis';

    $where = ["p.status != 'Archived'"];
    $params = [];
    if ($project_id > 0) {
        $where[] = "p.id = ?";
        $params[] = $project_id;
    }
    if ($status_filter !== 'All' && !empty($status_filter)) {
        $where[] = "p.status = ?";
        $params[] = $status_filter;
    }
    $where_sql = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.project_code,
            p.project_name,
            p.status,
            COALESCE(u.full_name, 'Unassigned PM') AS pm_name,
            COALESCE(b.allocated_amount, p.budget, 0) AS baseline_budget,
            (SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders WHERE project_id = p.id AND status != 'Cancelled') AS committed_po,
            (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE project_id = p.id) AS incurred_bills,
            (SELECT COALESCE(SUM(total_hours * hourly_rate), 0) FROM time_cards WHERE project_id = p.id AND approval_status = 'Approved') AS incurred_labor
        FROM projects p
        LEFT JOIN budgets b ON p.id = b.project_id
        LEFT JOIN users u ON p.pm_id = u.id
        WHERE {$where_sql}
        ORDER BY p.id DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totals for KPIs
    $sum_budget = 0; $sum_spend = 0; $sum_po = 0;
    foreach ($rows as $r) {
        $sum_budget += floatval($r['baseline_budget']);
        $sum_spend += (floatval($r['incurred_bills']) + floatval($r['incurred_labor']));
        $sum_po += floatval($r['committed_po']);

        // Chart Data (first 10)
        if (count($chart_labels) < 10) {
            $chart_labels[] = $r['project_code'];
            $chart_dataset1[] = round(floatval($r['baseline_budget']) / 1000000, 2); // In Millions
            $chart_dataset2[] = round((floatval($r['incurred_bills']) + floatval($r['incurred_labor'])) / 1000000, 2);
        }
    }
    $global_variance = $sum_budget - $sum_spend;
    $usage_pct = ($sum_budget > 0) ? round(($sum_spend / $sum_budget) * 100, 1) : 0;

    $kpi1_title = 'Total Budget Allocated'; $kpi1_val = 'RS. ' . number_format($sum_budget, 0); $kpi1_icon = 'bi-wallet2'; $kpi1_color = 'success';
    $kpi2_title = 'Total Actual Spent'; $kpi2_val = 'RS. ' . number_format($sum_spend, 0); $kpi2_icon = 'bi-cash-stack'; $kpi2_color = 'primary';
    $kpi3_title = 'Global Variance'; $kpi3_val = ($global_variance < 0 ? '-' : '+') . 'RS. ' . number_format(abs($global_variance), 0); $kpi3_icon = 'bi-scales'; $kpi3_color = ($global_variance >= 0 ? 'success' : 'danger');
    $kpi4_title = 'Budget Consumption'; $kpi4_val = $usage_pct . '%'; $kpi4_icon = 'bi-speedometer2'; $kpi4_color = ($usage_pct > 100 ? 'danger' : 'info');

} elseif ($type === 'profitability') {
    $report_title = 'Client Profitability & Margin Analysis';

    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.full_name AS client_name,
            c.company,
            (SELECT COUNT(*) FROM projects WHERE client_id = c.id AND status != 'Archived') AS active_projects,
            (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id AND status IN ('Paid', 'Sent')) AS total_invoiced,
            (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE client_id = c.id AND status = 'Completed') AS total_collected,
            (
                SELECT COALESCE(SUM(b.total_amount), 0) 
                FROM bills b 
                JOIN projects p ON b.project_id = p.id 
                WHERE p.client_id = c.id
            ) + (
                SELECT COALESCE(SUM(tc.total_hours * tc.hourly_rate), 0) 
                FROM time_cards tc 
                JOIN projects p ON tc.project_id = p.id 
                WHERE p.client_id = c.id AND tc.approval_status = 'Approved'
            ) AS total_project_cost
        FROM clients c
        ORDER BY total_invoiced DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sum_invoiced = 0; $sum_collected = 0; $sum_costs = 0;
    foreach ($rows as $r) {
        $inv = floatval($r['total_invoiced']);
        $col = floatval($r['total_collected']);
        $cost = floatval($r['total_project_cost']);
        $sum_invoiced += $inv;
        $sum_collected += $col;
        $sum_costs += $cost;

        if (count($chart_labels) < 8) {
            $chart_labels[] = $r['client_name'];
            $chart_dataset1[] = round($inv / 1000000, 2);
            $chart_dataset2[] = round($cost / 1000000, 2);
        }
    }
    $total_profit = $sum_invoiced - $sum_costs;
    $overall_margin = ($sum_invoiced > 0) ? round(($total_profit / $sum_invoiced) * 100, 1) : 0;

    $kpi1_title = 'Total Invoiced Revenue'; $kpi1_val = 'RS. ' . number_format($sum_invoiced, 0); $kpi1_icon = 'bi-receipt-cutoff'; $kpi1_color = 'success';
    $kpi2_title = 'Total Collected Revenue'; $kpi2_val = 'RS. ' . number_format($sum_collected, 0); $kpi2_icon = 'bi-bank'; $kpi2_color = 'primary';
    $kpi3_title = 'Total Incurred Cost'; $kpi3_val = 'RS. ' . number_format($sum_costs, 0); $kpi3_icon = 'bi-cash-coin'; $kpi3_color = 'danger';
    $kpi4_title = 'Overall Net Margin'; $kpi4_val = $overall_margin . '%'; $kpi4_icon = 'bi-graph-up-arrow'; $kpi4_color = ($overall_margin >= 15 ? 'success' : ($overall_margin >= 5 ? 'warning' : 'danger'));

} elseif ($type === 'timeline') {
    $report_title = 'Project Timeline & Schedule Adherence';

    $where = ["p.status != 'Archived'"];
    $params = [];
    if ($project_id > 0) {
        $where[] = "p.id = ?";
        $params[] = $project_id;
    }
    if ($status_filter !== 'All' && !empty($status_filter)) {
        $where[] = "p.status = ?";
        $params[] = $status_filter;
    }
    $where_sql = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.project_code,
            p.project_name,
            p.start_date,
            p.end_date,
            p.stage,
            p.status,
            DATEDIFF(p.end_date, p.start_date) AS planned_duration,
            DATEDIFF(CURRENT_DATE(), p.start_date) AS elapsed_days,
            LEAST(100, GREATEST(0, ROUND((DATEDIFF(CURRENT_DATE(), p.start_date) / NULLIF(DATEDIFF(p.end_date, p.start_date), 0)) * 100, 1))) AS expected_progress,
            COALESCE(
                ROUND((SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'Done') / NULLIF((SELECT COUNT(*) FROM tasks WHERE project_id = p.id), 0) * 100, 1),
                p.progress_percent,
                0
            ) AS actual_progress
        FROM projects p
        WHERE {$where_sql}
        ORDER BY p.id DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tot_projects = count($rows);
    $sum_exp = 0; $sum_act = 0; $delayed_count = 0;
    foreach ($rows as $r) {
        $exp = floatval($r['expected_progress']);
        $act = floatval($r['actual_progress']);
        $sum_exp += $exp;
        $sum_act += $act;
        if ($act < $exp) $delayed_count++;

        if (count($chart_labels) < 8) {
            $chart_labels[] = $r['project_code'];
            $chart_dataset1[] = $exp;
            $chart_dataset2[] = $act;
        }
    }
    $avg_exp = ($tot_projects > 0) ? round($sum_exp / $tot_projects, 1) : 0;
    $avg_act = ($tot_projects > 0) ? round($sum_act / $tot_projects, 1) : 0;
    $on_track_count = $tot_projects - $delayed_count;

    $kpi1_title = 'Projects Monitored'; $kpi1_val = $tot_projects; $kpi1_icon = 'bi-building-check'; $kpi1_color = 'primary';
    $kpi2_title = 'Avg Expected Progress'; $kpi2_val = $avg_exp . '%'; $kpi2_icon = 'bi-clock-history'; $kpi2_color = 'secondary';
    $kpi3_title = 'Avg Actual Progress'; $kpi3_val = $avg_act . '%'; $kpi3_icon = 'bi-check2-circle'; $kpi3_color = 'success';
    $kpi4_title = 'Schedule Health'; $kpi4_val = "{$on_track_count} On Track / {$delayed_count} Delayed"; $kpi4_icon = 'bi-activity'; $kpi4_color = ($delayed_count == 0 ? 'success' : 'warning');
}

// Current CSV download URL
$csv_query = http_build_query([
    'type' => $type,
    'project_id' => $project_id,
    'status' => $status_filter,
    'start_date' => $start_date,
    'end_date' => $end_date
]);
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
    <title><?= htmlspecialchars($report_title) ?> - BuildNexus BI</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* --- BuildNexus Standard Design Language (Zero Tailwind) --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .main-container {
            padding: 2.5rem 3rem;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Top Header & Buttons */
        .page-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .page-header-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .btn-nexus-success {
            background-color: #22c55e;
            border: 1px solid #16a34a;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 8px 16px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: background 0.15s, transform 0.15s;
        }

        .btn-nexus-success:hover {
            background-color: #16a34a;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-nexus-outline {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }

        .btn-nexus-outline:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        /* Report Switcher Tabs */
        .report-nav-tabs {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 4px;
            display: inline-flex;
            margin-bottom: 2rem;
            width: 100%;
            max-width: 680px;
            border: 1px solid #e2e8f0;
        }

        .nav-tab-btn {
            flex: 1;
            border: none;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .nav-tab-btn:hover {
            color: #1e293b;
        }

        .nav-tab-btn.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }

        /* Filter Panel */
        .filter-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        /* KPI Quick Metric Cards */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .kpi-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 1.45rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .kpi-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        /* Content Card & Tables */
        .nexus-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            margin-bottom: 2rem;
        }

        .table-nexus thead th {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 12px 14px;
            white-space: nowrap;
        }

        .table-nexus tbody td {
            padding: 14px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .table-nexus tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Pills & Status Badges */
        .pill-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }

        .pill-on-schedule  { background-color: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .pill-minor-delay  { background-color: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
        .pill-crit-delay   { background-color: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

        /* Print Media Styles */
        @media print {
            body { background: #fff !important; }
            .no-print, .report-nav-tabs, .filter-panel, .btn-nexus-success, .btn-nexus-outline { display: none !important; }
            .main-container { padding: 0 !important; width: 100% !important; max-width: 100% !important; }
            .nexus-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .print-header { display: block !important; }
        }

        .print-header {
            display: none;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #0f172a;
        }
    </style>
</head>
<body>

<div class="main-container">

    <!-- Corporate Header for Print Only -->
    <div class="print-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold mb-0">BuildNexus Construction ERP</h3>
                <small class="text-muted">Executive Business Intelligence & Project Governance Report</small>
            </div>
            <div class="text-end small text-muted">
                <div>Report: <strong><?= htmlspecialchars($report_title) ?></strong></div>
                <div>Generated: <?= date('Y-m-d H:i') ?></div>
            </div>
        </div>
    </div>

    <!-- Top Navigation & Action Buttons -->
    <div class="page-header-row no-print">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="reports.php" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Reports Hub
                </a>
                <span class="text-muted small">/</span>
                <span class="small fw-semibold text-secondary">BI Intelligence</span>
            </div>
            <h1 class="page-header-title mb-0"><?= htmlspecialchars($report_title) ?></h1>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="export_report_csv.php?<?= $csv_query ?>" class="btn-nexus-outline" title="Download CSV Spreadsheet">
                <i class="bi bi-filetype-csv text-success"></i> Export CSV
            </a>
            <button class="btn-nexus-outline" onclick="window.print()" title="Print or Save as PDF">
                <i class="bi bi-printer text-primary"></i> Print / PDF
            </button>
            <a href="reports.php" class="btn-nexus-success">
                <i class="bi bi-grid-fill"></i> All Reports
            </a>
        </div>
    </div>

    <!-- Report Type Switcher Tabs -->
    <div class="report-nav-tabs no-print">
        <a href="reports_view.php?type=budget_actual<?= ($project_id > 0 ? '&project_id=' . $project_id : '') ?>" 
           class="nav-tab-btn <?= ($type === 'budget_actual') ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-bar-graph"></i> Budget vs. Actual
        </a>
        <a href="reports_view.php?type=profitability" 
           class="nav-tab-btn <?= ($type === 'profitability') ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-person"></i> Client Profitability
        </a>
        <a href="reports_view.php?type=timeline<?= ($project_id > 0 ? '&project_id=' . $project_id : '') ?>" 
           class="nav-tab-btn <?= ($type === 'timeline') ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-check"></i> Timeline Analysis
        </a>
    </div>

    <!-- Top Filter Bar -->
    <div class="filter-panel no-print">
        <form method="GET" action="reports_view.php" class="row g-3 align-items-end">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

            <?php if ($type !== 'profitability'): ?>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">Filter by Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="0">All Projects</option>
                    <?php foreach ($projects_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($p['id'] == $project_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Project Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="All" <?= ($status_filter === 'All') ? 'selected' : '' ?>>All Statuses</option>
                    <option value="Active" <?= ($status_filter === 'Active') ? 'selected' : '' ?>>Active Only</option>
                    <option value="Completed" <?= ($status_filter === 'Completed') ? 'selected' : '' ?>>Completed</option>
                    <option value="On Hold" <?= ($status_filter === 'On Hold') ? 'selected' : '' ?>>On Hold</option>
                </select>
            </div>
            <?php else: ?>
            <div class="col-md-7">
                <label class="form-label small fw-bold text-muted mb-1">Client Analysis Scope</label>
                <input type="text" class="form-control form-control-sm" readonly value="All Active Clients with Invoices & Production Activity">
            </div>
            <?php endif; ?>

            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark fw-semibold px-3">
                    <i class="bi bi-funnel me-1"></i> Apply Filters
                </button>
                <a href="reports_view.php?type=<?= $type ?>" class="btn btn-sm btn-outline-secondary fw-semibold">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Visual KPI Summary Row -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?= $kpi1_title ?></div>
                <div class="kpi-value text-<?= $kpi1_color ?>"><?= $kpi1_val ?></div>
            </div>
            <div class="kpi-icon-wrap bg-<?= $kpi1_color ?> bg-opacity-10 text-<?= $kpi1_color ?>">
                <i class="bi <?= $kpi1_icon ?>"></i>
            </div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?= $kpi2_title ?></div>
                <div class="kpi-value text-<?= $kpi2_color ?>"><?= $kpi2_val ?></div>
            </div>
            <div class="kpi-icon-wrap bg-<?= $kpi2_color ?> bg-opacity-10 text-<?= $kpi2_color ?>">
                <i class="bi <?= $kpi2_icon ?>"></i>
            </div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?= $kpi3_title ?></div>
                <div class="kpi-value text-<?= $kpi3_color ?>"><?= $kpi3_val ?></div>
            </div>
            <div class="kpi-icon-wrap bg-<?= $kpi3_color ?> bg-opacity-10 text-<?= $kpi3_color ?>">
                <i class="bi <?= $kpi3_icon ?>"></i>
            </div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?= $kpi4_title ?></div>
                <div class="kpi-value text-<?= $kpi4_color ?>"><?= $kpi4_val ?></div>
            </div>
            <div class="kpi-icon-wrap bg-<?= $kpi4_color ?> bg-opacity-10 text-<?= $kpi4_color ?>">
                <i class="bi <?= $kpi4_icon ?>"></i>
            </div>
        </div>
    </div>

    <!-- Chart.js Visual Reporting Card -->
    <?php if (!empty($chart_labels)): ?>
    <div class="nexus-card no-print">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0">Executive Visual BI Analysis</h5>
                <small class="text-muted">
                    <?= ($type === 'budget_actual') ? 'Allocated Baseline vs. Incurred Expenses (RS. Millions)' : (($type === 'profitability') ? 'Invoiced Revenue vs. Production Costs (RS. Millions)' : 'Planned Schedule % vs. Actual Work Done %') ?>
                </small>
            </div>
            <div class="d-flex gap-3 small fw-semibold">
                <span class="d-flex align-items-center gap-1">
                    <span style="display:inline-block; width:12px; height:12px; background-color:#3b82f6; border-radius:3px;"></span>
                    <?= ($type === 'budget_actual') ? 'Budget' : (($type === 'profitability') ? 'Invoiced' : 'Planned %') ?>
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span style="display:inline-block; width:12px; height:12px; background-color:#10b981; border-radius:3px;"></span>
                    <?= ($type === 'budget_actual') ? 'Actual' : (($type === 'profitability') ? 'Costs' : 'Actual %') ?>
                </span>
            </div>
        </div>
        <div style="position: relative; height: 320px; width: 100%;">
            <canvas id="biChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dynamic Data Table Card -->
    <div class="nexus-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0"><?= htmlspecialchars($report_title) ?> Data Table</h5>
                <small class="text-muted"><?= count($rows) ?> records matching current executive governance filters</small>
            </div>
        </div>

        <div class="table-responsive">
            <?php if ($type === 'budget_actual'): ?>
                <!-- 1. BUDGET VS ACTUAL TABLE -->
                <table class="table table-nexus align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Project Name</th>
                            <th>Assigned PM</th>
                            <th class="text-end">Allocated Budget</th>
                            <th class="text-end">Committed POs</th>
                            <th class="text-end">Vendor Bills</th>
                            <th class="text-end">Labor Costs</th>
                            <th class="text-end">Total Spent</th>
                            <th class="text-end">Variance</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">No project records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): 
                                $budget = floatval($r['baseline_budget']);
                                $po = floatval($r['committed_po']);
                                $bills = floatval($r['incurred_bills']);
                                $labor = floatval($r['incurred_labor']);
                                $actual_spend = $bills + $labor;
                                $variance = $budget - $actual_spend;
                                $is_under = ($variance >= 0);
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($r['project_code']) ?></td>
                                <td>
                                    <a href="project_financials.php?project_id=<?= $r['id'] ?>" class="text-dark fw-semibold text-decoration-none" title="View Financial Breakdown">
                                        <?= htmlspecialchars($r['project_name']) ?> <i class="bi bi-box-arrow-up-right small text-muted"></i>
                                    </a>
                                </td>
                                <td class="text-secondary"><?= htmlspecialchars($r['pm_name']) ?></td>
                                <td class="text-end fw-semibold">RS. <?= number_format($budget, 2) ?></td>
                                <td class="text-end text-muted">RS. <?= number_format($po, 2) ?></td>
                                <td class="text-end text-muted">RS. <?= number_format($bills, 2) ?></td>
                                <td class="text-end text-muted">RS. <?= number_format($labor, 2) ?></td>
                                <td class="text-end fw-bold text-dark">RS. <?= number_format($actual_spend, 2) ?></td>
                                <td class="text-end fw-bold <?= $is_under ? 'text-success' : 'text-danger' ?>">
                                    <?= ($is_under ? '+' : '-') ?> RS. <?= number_format(abs($variance), 2) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $is_under ? 'bg-success' : 'bg-danger' ?> py-1 px-2" style="font-size: 0.72rem;">
                                        <?= $is_under ? 'Under Budget' : 'Over Budget' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($type === 'profitability'): ?>
                <!-- 2. CLIENT PROFITABILITY TABLE -->
                <table class="table table-nexus align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Company</th>
                            <th class="text-center">Active Projects</th>
                            <th class="text-end">Invoiced Revenue</th>
                            <th class="text-end">Collected Revenue</th>
                            <th class="text-end">Total Incurred Cost</th>
                            <th class="text-end">Net Profit</th>
                            <th class="text-center">Profit Margin %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No client financial records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): 
                                $inv = floatval($r['total_invoiced']);
                                $col = floatval($r['total_collected']);
                                $cost = floatval($r['total_project_cost']);
                                $profit = $inv - $cost;
                                $margin_pct = ($inv > 0) ? round(($profit / $inv) * 100, 1) : 0;
                                $margin_badge = ($margin_pct >= 15) ? 'bg-success' : (($margin_pct >= 5) ? 'bg-warning text-dark' : 'bg-danger');
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($r['client_name']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($r['company'] ?: 'Individual Client') ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark"><?= $r['active_projects'] ?></span></td>
                                <td class="text-end fw-semibold text-dark">RS. <?= number_format($inv, 2) ?></td>
                                <td class="text-end text-primary">RS. <?= number_format($col, 2) ?></td>
                                <td class="text-end text-danger">RS. <?= number_format($cost, 2) ?></td>
                                <td class="text-end fw-bold <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= ($profit >= 0 ? '+' : '-') ?> RS. <?= number_format(abs($profit), 2) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $margin_badge ?> py-1 px-2" style="font-size: 0.72rem;">
                                        <?= $margin_pct ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($type === 'timeline'): ?>
                <!-- 3. TIMELINE & SCHEDULE ADHERENCE TABLE -->
                <table class="table table-nexus align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Project Name</th>
                            <th>Start Date</th>
                            <th>Target Handover</th>
                            <th class="text-center">Duration</th>
                            <th class="text-center">Elapsed</th>
                            <th class="text-end">Planned %</th>
                            <th class="text-end">Actual %</th>
                            <th class="text-end">Schedule Delay</th>
                            <th class="text-center">Adherence Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">No project timeline records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): 
                                $exp = floatval($r['expected_progress']);
                                $act = floatval($r['actual_progress']);
                                $duration = max(1, intval($r['planned_duration']));
                                $slip_days = max(0, round((($exp - $act) / 100) * $duration));

                                if ($act >= $exp) {
                                    $pill_class = 'pill-on-schedule';
                                    $pill_text = 'On Schedule';
                                } elseif (($exp - $act) <= 15) {
                                    $pill_class = 'pill-minor-delay';
                                    $pill_text = 'Minor Delay';
                                } else {
                                    $pill_class = 'pill-crit-delay';
                                    $pill_text = 'Critical Delay';
                                }
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($r['project_code']) ?></td>
                                <td>
                                    <a href="projects.php" class="text-dark fw-semibold text-decoration-none">
                                        <?= htmlspecialchars($r['project_name']) ?>
                                    </a>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($r['start_date']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($r['end_date']) ?></td>
                                <td class="text-center"><?= $r['planned_duration'] ?> days</td>
                                <td class="text-center"><?= $r['elapsed_days'] ?> days</td>
                                <td class="text-end fw-semibold text-secondary"><?= $exp ?>%</td>
                                <td class="text-end fw-bold text-dark"><?= $act ?>%</td>
                                <td class="text-end <?= ($slip_days > 0 ? 'text-danger fw-bold' : 'text-success') ?>">
                                    <?= ($slip_days > 0 ? "+{$slip_days} days" : '0 days') ?>
                                </td>
                                <td class="text-center">
                                    <span class="pill-badge <?= $pill_class ?>"><?= $pill_text ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js Visualization Script -->
<?php if (!empty($chart_labels)): ?>
<script>
    const ctx = document.getElementById('biChart').getContext('2d');
    const isTimeline = <?= ($type === 'timeline') ? 'true' : 'false' ?>;

    new Chart(ctx, {
        type: isTimeline ? 'bar' : 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: '<?= ($type === 'budget_actual') ? 'Budget (M)' : (($type === 'profitability') ? 'Invoiced (M)' : 'Planned Progress %') ?>',
                    data: <?= json_encode($chart_dataset1) ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6
                },
                {
                    label: '<?= ($type === 'budget_actual') ? 'Actual (M)' : (($type === 'profitability') ? 'Cost (M)' : 'Actual Progress %') ?>',
                    data: <?= json_encode($chart_dataset2) ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + ctx.raw + (isTimeline ? '%' : 'M');
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: "'Inter', sans-serif", size: 11 }, color: '#64748b' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { family: "'Inter', sans-serif", size: 11 },
                        color: '#64748b',
                        callback: function(v) { return v + (isTimeline ? '%' : 'M'); }
                    }
                }
            }
        }
    });
</script>
<?php endif; ?>

</body>
</html>
