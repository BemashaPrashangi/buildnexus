<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$project_id = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$date = !empty($_GET['date']) ? trim($_GET['date']) : '';
$status = !empty($_GET['status']) ? trim($_GET['status']) : '';
$search = !empty($_GET['search']) ? trim($_GET['search']) : '';

$query = "
    SELECT tc.*, 
           u.full_name AS employee_name, u.email AS employee_email,
           p.project_name, p.project_code,
           rev.full_name AS reviewer_name
    FROM time_cards tc
    JOIN users u ON tc.user_id = u.id
    JOIN projects p ON tc.project_id = p.id
    LEFT JOIN users rev ON tc.reviewed_by = rev.id
    WHERE 1=1
";
$params = [];

if ($project_id > 0) {
    $query .= " AND tc.project_id = ?";
    $params[] = $project_id;
}
if (!empty($date)) {
    $query .= " AND tc.work_date = ?";
    $params[] = $date;
}
if (!empty($status) && in_array($status, ['Pending', 'Approved', 'Rejected'])) {
    $query .= " AND tc.approval_status = ?";
    $params[] = $status;
}
if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR p.project_name LIKE ? OR tc.work_notes LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY tc.work_date DESC, tc.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "timecards_export_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fputs($output, "\xEF\xBB\xBF");

// Column Headers
fputcsv($output, [
    'Time Card ID', 'Employee Name', 'Email', 'Project Name', 'Project Code',
    'Work Date', 'Clock In', 'Clock Out', 'Break (Mins)', 'Total Hours',
    'Hourly Rate (RS.)', 'Estimated Labor Cost (RS.)', 'Approval Status', 'Shift Status',
    'Reviewed By', 'Reviewed At', 'Work Notes'
]);

foreach ($rows as $row) {
    $rate = floatval($row['hourly_rate']);
    $hours = floatval($row['total_hours']);
    $cost = round($rate * $hours, 2);

    fputcsv($output, [
        $row['id'],
        $row['employee_name'],
        $row['employee_email'] ?? '',
        $row['project_name'],
        $row['project_code'] ?? 'N/A',
        $row['work_date'],
        $row['clock_in'] ? date('H:i', strtotime($row['clock_in'])) : 'N/A',
        $row['clock_out'] ? date('H:i', strtotime($row['clock_out'])) : 'N/A',
        $row['break_minutes'],
        number_format($hours, 1),
        number_format($rate, 2),
        number_format($cost, 2),
        $row['approval_status'],
        $row['status'],
        $row['reviewer_name'] ?? 'Pending Review',
        $row['reviewed_at'] ?? '',
        $row['work_notes'] ?? ''
    ]);
}

fclose($output);
exit();
