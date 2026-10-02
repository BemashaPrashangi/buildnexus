<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$method = $_GET['method'] ?? 'All';
$status = $_GET['status'] ?? 'All';
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT p.*, 
           i.invoice_number, 
           COALESCE(pr.project_name, 'General') AS project_name, 
           COALESCE(c.full_name, NULLIF(pr.client_name, ''), 'Client') AS display_client
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.id
    LEFT JOIN projects pr ON p.project_id = pr.id
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE 1=1
";
$params = [];

if ($method !== 'All' && !empty($method)) {
    $query .= " AND p.payment_method = ?";
    $params[] = $method;
}
if ($status !== 'All' && !empty($status)) {
    $query .= " AND p.status = ?";
    $params[] = $status;
}
if (!empty($search)) {
    $query .= " AND (i.invoice_number LIKE ? OR p.transaction_id LIKE ? OR pr.project_name LIKE ? OR c.full_name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY p.payment_date DESC, p.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'BuildNexus_Payments_Report_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, [
    'Transaction ID',
    'Invoice #',
    'Project',
    'Client',
    'Date',
    'Payment Method',
    'Status',
    'Gross Amount (RS)',
    'Fee Deducted (RS)',
    'Net Amount (RS)'
]);

foreach ($rows as $r) {
    fputcsv($output, [
        $r['transaction_id'],
        $r['invoice_number'],
        $r['project_name'],
        $r['display_client'],
        $r['payment_date'],
        $r['payment_method'],
        $r['status'],
        number_format($r['amount'], 2, '.', ''),
        number_format($r['fee_deducted'], 2, '.', ''),
        number_format($r['net_amount'], 2, '.', '')
    ]);
}

fclose($output);
exit();
