<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$project_id = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$date = !empty($_GET['date']) ? trim($_GET['date']) : '';
$search = !empty($_GET['search']) ? trim($_GET['search']) : '';

$query = "
    SELECT dr.id, dr.report_date, p.project_name, p.project_code,
           COALESCE(u.full_name, 'Foreman') AS author_name,
           dr.weather_condition, dr.crew_count, dr.status,
           dr.work_summary, dr.subcontractor_notes, dr.created_at
    FROM daily_reports dr
    JOIN projects p ON dr.project_id = p.id
    LEFT JOIN users u ON dr.foreman_id = u.id
    WHERE 1=1
";
$params = [];

if ($project_id > 0) {
    $query .= " AND dr.project_id = ?";
    $params[] = $project_id;
}
if (!empty($date)) {
    $query .= " AND dr.report_date = ?";
    $params[] = $date;
}
if (!empty($search)) {
    $query .= " AND (p.project_name LIKE ? OR u.full_name LIKE ? OR dr.work_summary LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY dr.report_date DESC, dr.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "daily_logs_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fputs($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, ['Log ID', 'Report Date', 'Project Name', 'Project Code', 'Author', 'Weather', 'Crew Count', 'Status', 'Work Summary', 'Subcontractor Notes', 'Created At']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['id'],
        $row['report_date'],
        $row['project_name'],
        $row['project_code'] ?? 'N/A',
        $row['author_name'],
        $row['weather_condition'] ?? 'N/A',
        $row['crew_count'],
        $row['status'],
        $row['work_summary'],
        $row['subcontractor_notes'] ?? '',
        $row['created_at']
    ]);
}

fclose($output);
exit();
