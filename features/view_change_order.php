<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

$co_id = $_GET['id'] ?? null;

try {
    $stmt = $pdo->prepare("SELECT co.*, p.project_name FROM change_orders co JOIN projects p ON co.project_id = p.id WHERE co.id = ?");
    $stmt->execute([$co_id]);
    $order = $stmt->fetch();
} catch (PDOException $e) { die($e->getMessage()); }
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
    <title>Change Order Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; padding: 4rem 0; }
        .details-card { max-width: 650px; margin: auto; background: white; padding: 2.5rem; border-radius: 12px; border: 1px solid #e2e8f0; }
        .impact-box { background: #f0fdf4; border: 1px solid #22c55e; color: #16a34a; padding: 1.5rem; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="details-card shadow-sm">
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($order['title']) ?></h4>
            <p class="text-muted small mb-4">Project: <?= htmlspecialchars($order['project_name']) ?></p>
            <div class="mb-4">
                <label class="small fw-bold text-muted">Scope of Change</label>
                <p class="mt-1 small"><?= nl2br(htmlspecialchars($order['description'])) ?></p>
            </div>
            <div class="impact-box mb-4">
                <h6 class="fw-bold mb-1">Financial Impact</h6>
                <h3 class="fw-bold mb-0">Rs. <?= number_format($order['cost_impact'], 2) ?></h3>
            </div>
            <a href="change-orders.php" class="btn btn-light border w-100 fw-bold">Back to List</a>
        </div>
    </div>
</body>
</html>