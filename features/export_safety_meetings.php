<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$project_id = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$date = !empty($_GET['date']) ? trim($_GET['date']) : '';
$search = !empty($_GET['search']) ? trim($_GET['search']) : '';

$query = "
    SELECT sm.id, sm.meeting_date, sm.attendees_count, sm.notes, sm.signed_roster_file, sm.created_at,
           p.project_name, p.project_code,
           COALESCE(st.title, sm.custom_topic, 'General Safety Meeting') AS topic_title,
           COALESCE(st.category, 'General Safety') AS topic_category,
           COALESCE(u.full_name, 'Site Foreman') AS lead_name,
           (SELECT COUNT(*) FROM safety_meeting_attendees sma WHERE sma.meeting_id = sm.id) AS roster_count,
           (SELECT GROUP_CONCAT(CONCAT(sma.worker_name, ' (', sma.trade_role, ')') SEPARATOR '; ') 
            FROM safety_meeting_attendees sma WHERE sma.meeting_id = sm.id) AS attendee_names
    FROM safety_meeting_logs sm
    JOIN projects p ON sm.project_id = p.id
    LEFT JOIN safety_topics st ON sm.topic_id = st.id
    LEFT JOIN users u ON sm.foreman_id = u.id
    WHERE 1=1
";
$params = [];

if ($project_id > 0) {
    $query .= " AND sm.project_id = ?";
    $params[] = $project_id;
}
if (!empty($date)) {
    $query .= " AND sm.meeting_date = ?";
    $params[] = $date;
}
if (!empty($search)) {
    $query .= " AND (p.project_name LIKE ? OR u.full_name LIKE ? OR st.title LIKE ? OR sm.custom_topic LIKE ? OR sm.notes LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY sm.meeting_date DESC, sm.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "safety_meetings_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fputs($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, [
    'Meeting ID',
    'Date',
    'Project Code',
    'Project Name',
    'Safety Topic',
    'Topic Category',
    'Meeting Lead / Conductor',
    'Total Attendees',
    'Roster Registered',
    'Roster File Attached',
    'Worker Roster Details',
    'Safety Notes / Discussion',
    'Logged Timestamp'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['id'],
        $row['meeting_date'],
        $row['project_code'] ?? 'N/A',
        $row['project_name'],
        $row['topic_title'],
        $row['topic_category'],
        $row['lead_name'],
        $row['attendees_count'],
        $row['roster_count'],
        !empty($row['signed_roster_file']) ? 'Yes' : 'No',
        $row['attendee_names'] ?? '',
        $row['notes'] ?? '',
        $row['created_at']
    ]);
}

fclose($output);
exit();
