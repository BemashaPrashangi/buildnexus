<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$project_id = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$status = !empty($_GET['status']) ? trim($_GET['status']) : '';
$search = !empty($_GET['search']) ? trim($_GET['search']) : '';

$query = "
    SELECT e.*, p.project_name, p.project_code,
           COALESCE(SUM(el.hours_used), 0) AS total_hours,
           COALESCE(SUM(el.fuel_liters), 0) AS total_fuel
    FROM equipment e
    LEFT JOIN projects p ON e.current_project_id = p.id
    LEFT JOIN equipment_logs el ON e.id = el.equipment_id
    WHERE 1=1
";
$params = [];

if ($project_id > 0) {
    $query .= " AND e.current_project_id = ?";
    $params[] = $project_id;
}
if (!empty($status)) {
    $query .= " AND e.status = ?";
    $params[] = $status;
}
if (!empty($search)) {
    $query .= " AND (e.name LIKE ? OR e.equipment_code LIKE ? OR e.plate_number LIKE ? OR p.project_name LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " GROUP BY e.id ORDER BY e.id ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "equipment_fleet_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fputs($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, [
    'ID',
    'Asset Code',
    'Equipment Name',
    'Plate / Serial Number',
    'Type',
    'Assigned Project',
    'Status',
    'Next Scheduled Service',
    'Cumulative Runtime (Hrs)',
    'Cumulative Fuel (L)',
    'Hourly Rate ($)',
    'Registered Date'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['id'],
        $row['equipment_code'] ?? 'N/A',
        $row['name'],
        $row['plate_number'] ?? '',
        $row['type'],
        $row['project_name'] ?? 'Unassigned',
        $row['status'],
        $row['next_service_date'] ?? 'N/A',
        number_format($row['total_hours'], 2),
        number_format($row['total_fuel'], 2),
        number_format($row['hourly_operating_cost'], 2),
        $row['created_at']
    ]);
}

fclose($output);
exit();
