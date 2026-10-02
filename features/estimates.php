<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

// --- NEW: MESSAGE NOTIFICATION LOGIC ---
$message_map = [
    'estimate_sent' => ['text' => 'Estimate Saved and Sent to Client Portal!', 'type' => 'success'],
    'error'         => ['text' => 'An error occurred while saving.', 'type' => 'danger']
];

$display_alert = null;
if (isset($_GET['msg']) && array_key_exists($_GET['msg'], $message_map)) {
    $display_alert = $message_map[$_GET['msg']];
}

try {
    $stmt = $pdo->query("SELECT e.*, p.project_name FROM estimates e LEFT JOIN projects p ON e.project_id = p.id ORDER BY e.created_at DESC");
    $estimates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_budget = $pdo->query("SELECT SUM(total_amount) FROM estimates WHERE status = 'Approved'")->fetchColumn() ?: 0;
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
    <title>Estimates - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03); margin-bottom: 2rem; }
        .btn-nexus-success { background-color: #22c55e; color: #fff; border: none; font-weight: 600; border-radius: 8px; padding: 10px 18px; font-size: 0.9rem; }
        .nexus-control { width: 100%; padding: 10px 12px 10px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .nexus-input-group { position: relative; flex-grow: 1; }
        .nexus-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    </style>
</head>
<body>

    <div class="main-container">
        <?php if ($display_alert): ?>
            <div class="alert alert-<?= $display_alert['type'] ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="background-color: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6;">
                <i class="bi bi-info-circle-fill me-2"></i> <?= $display_alert['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">Estimates</h1>
            <a href="estimates_new.php" class="btn-nexus-success text-decoration-none">
                <i class="bi bi-plus-lg me-1"></i> New Estimate
            </a>
        </div>

        <div class="nexus-card">
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <div class="nexus-input-group">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="estSearch" class="nexus-control" placeholder="Search estimates...">
                </div>
            </div>

            <div class="table-responsive mt-4">
                <table class="table align-middle" id="estTable">
                    <thead>
                        <tr class="text-muted small text-uppercase">
                            <th>Project Title</th>
                            <th>Estimate #</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($estimates)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No estimates found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($estimates as $est): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($est['project_name'] ?? 'Unassigned') ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($est['estimate_number']) ?></td>
                                    <td class="fw-semibold">Rs. <?= number_format($est['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge rounded-pill bg-light text-dark border px-3">
                                            <?= $est['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_estimate.php?id=<?= $est['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('estSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#estTable tbody tr");
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });
    </script>
</body>
</html>