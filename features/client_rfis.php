<?php
require_once '../db.php';
session_start();

// Security: Client access check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    header("Location: ../login.php");
    exit();
}

try {
    // 1. Fetch Project details for the client
    $stmt = $pdo->prepare("SELECT c.project_id, p.project_name FROM clients c JOIN projects p ON c.project_id = p.id WHERE c.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    $project_id = $project['project_id'] ?? 1;

    // 2. Fetch live RFIs
    $stmt_rfis = $pdo->prepare("SELECT * FROM project_rfis WHERE project_id = ? ORDER BY date_sent DESC");
    $stmt_rfis->execute([$project_id]);
    $db_rfis = $stmt_rfis->fetchAll(PDO::FETCH_ASSOC);

    // Initial Mock Data to match your screenshot
    if (empty($db_rfis)) {
        $db_rfis = [
            ['subject' => 'Clarification on Window Specifications', 'status' => 'Answered', 'date_sent' => '2024-10-22'],
            ['subject' => 'Pool Deck Material Approval', 'status' => 'Overdue', 'date_sent' => '2024-10-15']
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
    <title>RFIs - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- PURE CSS CUSTOM STYLING --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 2rem; }
        
        .table thead th { background: #fff; border-bottom: 1px solid #eee; color: #64748b; font-size: 0.85rem; font-weight: 500; padding-bottom: 1.5rem; }
        .table tbody td { padding: 1.5rem 0.75rem; vertical-align: middle; border-bottom: 1px solid #f8fafc; }

        /* Status Pills */
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; }
        .pill-answered { background: #f0fdf4; color: #16a34a; }
        .pill-overdue { background: #fef2f2; color: #dc2626; }

        .btn-respond { background: #fff; border: 1px solid #eee; color: #1e293b; font-weight: 500; padding: 6px 20px; border-radius: 6px; font-size: 0.85rem; transition: 0.2s; }
        .btn-respond:hover { background: #f8fafc; border-color: #cbd5e1; }
    </style>
</head>
<body>

    <h1 class="fw-bold mb-5">RFIs</h1>

    <div class="nexus-card">
        <h4 class="fw-bold mb-1">My Requests for Information</h4>
        <p class="text-muted small mb-4">Items needing your review or response.</p>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="45%">Subject</th>
                        <th width="20%" class="text-center">Status</th>
                        <th width="20%" class="text-center">Date Sent</th>
                        <th width="15%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($db_rfis as $rfi): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($rfi['subject']) ?></div>
                            <div class="text-muted small"><?= $project['project_name'] ?? 'Luxury Villa in Kandy' ?></div>
                        </td>
                        <td class="text-center">
                            <?php 
                                $status = $rfi['status'];
                                $class = ($status == 'Answered') ? 'pill-answered' : 'pill-overdue';
                            ?>
                            <span class="pill <?= $class ?>"><?= $status ?></span>
                        </td>
                        <td class="text-center text-muted"><?= $rfi['date_sent'] ?></td>
                        <td class="text-end">
                            <button class="btn-respond">Respond</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>