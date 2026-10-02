<?php
require_once '../db.php';
session_start();

// Security: Client access check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    header("Location: ../login.php");
    exit();
}

try {
    // 1. Fetch Project ID linked to this client
    $stmt = $pdo->prepare("SELECT project_id FROM clients WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $project_id = $stmt->fetchColumn();

    // 2. Fetch Daily Logs for this project
    $stmt_logs = $pdo->prepare("
        SELECT dr.*, u.full_name as logged_by 
        FROM daily_reports dr 
        JOIN users u ON dr.foreman_id = u.id 
        WHERE dr.project_id = ? 
        ORDER BY dr.report_date DESC
    ");
    $stmt_logs->execute([$project_id]);
    $db_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

    // Initial Mock Data to match your exact screenshot
    if (empty($db_logs)) {
        $db_logs = [
            [
                'report_date' => '2024-10-28',
                'logged_by' => 'Sunil Perera',
                'work_summary' => 'Framing for the first floor is complete. Electrical rough-in has started. All materials for plumbing have been delivered and stored securely on site.',
                'weather' => 'Sunny, 32°C',
                'personnel' => 'Framing Crew: 5, Electricians: 2'
            ],
            [
                'report_date' => '2024-10-27',
                'logged_by' => 'Sunil Perera',
                'work_summary' => 'Continued work on first-floor framing. Received delivery of windows. Site cleanup performed at the end of the day.',
                'weather' => 'Partly Cloudy, 31°C',
                'personnel' => 'Framing Crew: 5'
            ]
        ];
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
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
    <title>Daily Logs - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- PURE CSS CUSTOM STYLING --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; padding: 2rem; }
        .log-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        
        .weather-badge { background: #fff; border: 1px solid #eee; border-radius: 20px; padding: 4px 12px; font-size: 0.7rem; font-weight: 600; }
        .section-title { font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-top: 1.5rem; margin-bottom: 0.5rem; }
        .log-text { font-size: 0.9rem; color: #64748b; line-height: 1.6; }
        
        /* Personnel Tags */
        .personnel-tag { background: #f8fafc; border: 1px solid #eee; border-radius: 15px; padding: 4px 12px; font-size: 0.7rem; font-weight: 500; margin-right: 8px; }
        
        /* Photos */
        .log-photo { width: 230px; height: 140px; border-radius: 8px; object-fit: cover; margin-right: 15px; }
    </style>
</head>
<body>

    <h2 class="fw-bold mb-4">Daily Logs</h2>

    <?php foreach ($db_logs as $log): ?>
    <div class="log-card">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h4 class="fw-bold mb-0"><?= date('l, F j, Y', strtotime($log['report_date'])) ?></h4>
                <p class="text-muted small">Logged by <?= htmlspecialchars($log['logged_by']) ?></p>
            </div>
            <div class="weather-badge"><?= $log['weather'] ?? 'Sunny, 32°C' ?></div>
        </div>

        <div class="section-title">Progress Notes</div>
        <p class="log-text"><?= htmlspecialchars($log['work_summary']) ?></p>

        <div class="section-title">Photos</div>
        <div class="d-flex flex-wrap">
            <img src="https://via.placeholder.com/230x140" class="log-photo">
            <img src="https://via.placeholder.com/230x140" class="log-photo">
        </div>

        <div class="section-title">Personnel on Site</div>
        <div class="d-flex">
            <?php 
            $personnel = explode(',', $log['personnel'] ?? 'Framing Crew: 5');
            foreach ($personnel as $p): 
            ?>
            <div class="personnel-tag"><?= trim($p) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>