<?php
require_once 'db.php';
session_start();

$estimate_id = $_GET['id'] ?? null;

// Security: Ensure only the client can view this
if (!$estimate_id || $_SESSION['role'] !== 'Client') {
    header("Location: client_dashboard.php");
    exit();
}

try {
    // 1. Fetch Estimate Master Data
    $stmt = $pdo->prepare("SELECT * FROM estimates WHERE id = ?");
    $stmt->execute([$estimate_id]);
    $estimate = $stmt->fetch();

    // 2. Fetch Line Items
    $stmt_items = $pdo->prepare("SELECT * FROM estimate_items WHERE estimate_id = ?");
    $stmt_items->execute([$estimate_id]);
    $items = $stmt_items->fetchAll();
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }
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
    <title>Review Proposal - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; padding: 4rem 0; }
        .proposal-container { max-width: 850px; background: white; padding: 3rem; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); margin: auto; }
        .header-line { border-bottom: 3px solid #22c55e; padding-bottom: 1.5rem; margin-bottom: 2rem; }
    </style>
</head>
<body>
    <div class="container proposal-container">
        <div class="header-line d-flex justify-content-between align-items-end">
            <div>
                <h6 class="text-success fw-bold">PROPOSAL</h6>
                <h2 class="fw-bold mb-0">BuildNexus Construction</h2>
            </div>
            <div class="text-end small text-muted">
                <div>Estimate: <?= htmlspecialchars($estimate['estimate_number']) ?></div>
                <div>Date: <?= date('M d, Y', strtotime($estimate['created_at'])) ?></div>
            </div>
        </div>

        <table class="table align-middle">
            <thead class="table-light">
                <tr><th>Item Description</th><th class="text-end">Total (RS.)</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="py-3"><div class="fw-bold small"><?= htmlspecialchars($item['item_name']) ?></div></td>
                    <td class="text-end fw-bold small"><?= number_format($item['unit_cost'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fs-4 fw-bold">
                    <td class="text-end py-4">Grand Total</td>
                    <td class="text-end py-4 text-success">RS. <?= number_format($estimate['total_amount'], 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="text-center mt-5">
            <a href="approve_automation.php?id=<?= $estimate['id'] ?>" class="btn btn-success px-5 fw-bold py-3 rounded-3 shadow">
                Approve & Start Project
            </a>
        </div>
    </div>
</body>
</html>