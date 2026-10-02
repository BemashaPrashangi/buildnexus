<?php
require_once '../db.php';
session_start();

// Security: Ensure only clients can access this portal
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    header("Location: login.php");
    exit();
}

try {
    // 1. Get the project_id linked to this client
    $stmt = $pdo->prepare("SELECT project_id FROM clients WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $project_id = $stmt->fetchColumn();

    // 2. Fetch all estimates for this project
    $stmt_est = $pdo->prepare("SELECT * FROM estimates WHERE project_id = ? ORDER BY created_at DESC");
    $stmt_est->execute([$project_id]);
    $estimates = $stmt_est->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
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
    <title>My Proposals - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .proposal-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; transition: 0.3s; }
        .proposal-card:hover { border-color: #22c55e; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.75rem; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <h2 class="fw-bold mb-4">Project Proposals</h2>
        <div class="row g-4">
            <?php foreach ($estimates as $est): ?>
            <div class="col-md-6">
                <div class="proposal-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small fw-bold text-uppercase"><?= $est['estimate_number'] ?></span>
                        <span class="status-badge bg-light border text-dark"><?= $est['status'] ?></span>
                    </div>
                    <h4 class="fw-bold">Rs. <?= number_format($est['total_amount'], 2) ?></h4>
                    <p class="text-muted small">Sent on <?= date('M d, Y', strtotime($est['created_at'])) ?></p>
                    <a href="view_estimate_client.php?id=<?= $est['id'] ?>" class="btn btn-outline-success w-100 fw-bold mt-2">Review & Approve</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>