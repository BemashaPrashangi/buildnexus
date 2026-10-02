<?php
require_once 'db.php';
session_start();

$co_id = $_GET['id'] ?? null;

if (!$co_id || $_SESSION['role'] !== 'Client') {
    header("Location: client_dashboard.php");
    exit();
}

try {
    // Fetch Change Order and Project details
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
    <title>Review Change Order - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; padding: 4rem 0; font-family: 'Inter', sans-serif; }
        .review-card { max-width: 700px; margin: auto; background: white; padding: 3rem; border-radius: 12px; border: 1px solid #e2e8f0; }
        .signature-box { background: #f1f5f9; border: 2px dashed #cbd5e1; padding: 1.5rem; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="review-card shadow-lg">
            <h6 class="text-success fw-bold">PENDING APPROVAL</h6>
            <h2 class="fw-bold mb-4"><?= htmlspecialchars($order['title']) ?></h2>
            <p class="text-muted">Project: <strong><?= htmlspecialchars($order['project_name']) ?></strong></p>
            <hr>
            
            <div class="mb-4">
                <label class="small fw-bold text-muted">Description of Changes</label>
                <div class="mt-2 p-3 bg-light rounded border"><?= nl2br(htmlspecialchars($order['description'])) ?></div>
            </div>

            <div class="alert alert-success d-flex justify-content-between align-items-center p-4">
                <span class="fw-bold">Financial Impact</span>
                <h3 class="fw-bold mb-0">Rs. <?= number_format($order['cost_impact'], 2) ?></h3>
            </div>

            <form action="process_client_approval.php" method="POST" class="mt-5">
                <input type="hidden" name="co_id" value="<?= $co_id ?>">
                <div class="signature-box">
                    <label class="small fw-bold text-muted mb-2">Electronic Signature</label>
                    <input type="text" name="signature" class="form-control mb-3" placeholder="Type your full name to approve..." required>
                    <p class="x-small text-muted mb-0" style="font-size: 0.7rem;">By signing, you agree to the cost and scope changes listed above.</p>
                </div>
                
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-grow-1 py-3 fw-bold rounded-3">
                        Approve Change Order
                    </button>
                    <a href="client_dashboard.php" class="btn btn-light border py-3 px-4">Decline</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>