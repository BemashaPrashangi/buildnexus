<?php
// features/export_report_csv.php - Executive BI Reports CSV Exporter
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$type = $_GET['type'] ?? 'budget_actual';
$project_id = intval($_GET['project_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? 'All');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

$filename = "buildnexus_" . preg_replace('/[^a-zA-Z0-9_]/', '', $type) . "_report_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type === 'budget_actual') {
    fputcsv($output, [
        'Project Code',
        'Project Name',
        'Assigned PM',
        'Allocated Budget (LKR)',
        'Committed POs (LKR)',
        'Incurred Vendor Bills (LKR)',
        'Incurred Labor Costs (LKR)',
        'Total Actual Expenses (LKR)',
        'Variance (LKR)',
        'Variance %',
        'Status'
    ]);

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
            COALESCE(u.full_name, 'Unassigned') AS pm_name,
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

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $budget = floatval($r['baseline_budget']);
        $po = floatval($r['committed_po']);
        $bills = floatval($r['incurred_bills']);
        $labor = floatval($r['incurred_labor']);
        $actual_spend = $bills + $labor;
        $variance = $budget - $actual_spend;
        $variance_pct = ($budget > 0) ? round((($actual_spend - $budget) / $budget) * 100, 2) : 0;
        $status_tag = ($variance >= 0) ? 'Under Budget' : 'Over Budget';

        fputcsv($output, [
            $r['project_code'],
            $r['project_name'],
            $r['pm_name'],
            number_format($budget, 2, '.', ''),
            number_format($po, 2, '.', ''),
            number_format($bills, 2, '.', ''),
            number_format($labor, 2, '.', ''),
            number_format($actual_spend, 2, '.', ''),
            number_format($variance, 2, '.', ''),
            $variance_pct . '%',
            $status_tag
        ]);
    }

} elseif ($type === 'profitability') {
    fputcsv($output, [
        'Client Name',
        'Company',
        'Active Projects',
        'Invoiced Revenue (LKR)',
        'Collected Revenue (LKR)',
        'Total Incurred Cost (LKR)',
        'Gross Profit (LKR)',
        'Profit Margin %'
    ]);

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

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $invoiced = floatval($r['total_invoiced']);
        $collected = floatval($r['total_collected']);
        $cost = floatval($r['total_project_cost']);
        $profit = $invoiced - $cost;
        $margin_pct = ($invoiced > 0) ? round(($profit / $invoiced) * 100, 2) : 0;

        fputcsv($output, [
            $r['client_name'],
            $r['company'] ?: 'Individual Client',
            $r['active_projects'],
            number_format($invoiced, 2, '.', ''),
            number_format($collected, 2, '.', ''),
            number_format($cost, 2, '.', ''),
            number_format($profit, 2, '.', ''),
            $margin_pct . '%'
        ]);
    }

} elseif ($type === 'timeline') {
    fputcsv($output, [
        'Project Code',
        'Project Name',
        'Start Date',
        'Target Handover',
        'Planned Duration (Days)',
        'Elapsed Days',
        'Expected Progress %',
        'Actual Progress %',
        'Schedule Delay (Days)',
        'Adherence Status'
    ]);

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

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $exp = floatval($r['expected_progress']);
        $act = floatval($r['actual_progress']);
        $duration = max(1, intval($r['planned_duration']));
        $slip_days = max(0, round((($exp - $act) / 100) * $duration));

        if ($act >= $exp) {
            $status_pill = 'On Schedule';
        } elseif (($exp - $act) <= 15) {
            $status_pill = 'Minor Delay';
        } else {
            $status_pill = 'Critical Delay';
        }

        fputcsv($output, [
            $r['project_code'],
            $r['project_name'],
            $r['start_date'],
            $r['end_date'],
            $r['planned_duration'],
            $r['elapsed_days'],
            $exp . '%',
            $act . '%',
            $slip_days . ' days',
            $status_pill
        ]);
    }
}

fclose($output);
exit();
