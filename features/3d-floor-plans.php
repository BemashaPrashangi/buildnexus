<?php
require_once '../db.php';
require_once 'auth_check.php';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $_POST['plan_id'] ?? null;
    if ($plan_id) {
        if (isset($_POST['action']) && $_POST['action'] === 'send_to_team') {
            // Update to Processing when sent to team
            $stmt = $pdo->prepare("UPDATE floor_plans SET status = 'Processing' WHERE id = ?");
            $stmt->execute([$plan_id]);
            $msg = "Plan sent to Project Manager and Foreman successfully!";
        } elseif (isset($_POST['action']) && $_POST['action'] === 'notify_client') {
            // Update to Completed to signify it has been received and reviewed
            $stmt = $pdo->prepare("UPDATE floor_plans SET status = 'Completed' WHERE id = ?");
            $stmt->execute([$plan_id]);
            $msg = "Client notified that their plan has been received by admin!";
        }
    }
}

try {
    // 1. Fetch plans for the dashboard
    $stmt = $pdo->query("SELECT fp.*, p.project_name, p.client_name FROM floor_plans fp JOIN projects p ON fp.project_id = p.id ORDER BY fp.created_at DESC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Floor Plans - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }

        .page-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }

        .rounded-4 {
            border-radius: 1.5rem !important;
        }
    </style>
</head>

<body>

    <header class="page-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <a href="../admin_dashboard.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
                <h1 class="h3 fw-bold mb-0">Client Floor Plans</h1>
            </div>
        </div>
    </header>

    <main class="container mb-5">
        <?php if(isset($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
        <?php foreach ($plans as $plan): ?>
            <div class="col-md-6 plan-item">
                <div class="card h-100 p-4 border-0 shadow-sm bg-white rounded-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="fw-bold text-dark mb-1">
                                <i class="bi bi-house-door-fill text-primary me-2"></i>
                                <?= htmlspecialchars($plan['plan_name']); ?>
                            </h4>
                            <p class="text-muted small mb-0">Project: <strong><?= htmlspecialchars($plan['project_name']); ?></strong></p>
                            <p class="text-muted small mb-0">Client: <strong><?= htmlspecialchars($plan['client_name'] ?? 'Unknown'); ?></strong></p>
                        </div>
                    </div>
                    
                    <div class="text-center p-3 border rounded-3 bg-light mb-4 flex-grow-1 d-flex flex-column justify-content-center align-items-center">
                        <p class="small fw-bold text-muted mb-2 text-uppercase">Client Uploaded Sketch</p>
                        <?php if ($plan['file_2d']): ?>
                            <img src="../uploads/plans/<?= htmlspecialchars($plan['file_2d']); ?>" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;">
                        <?php else: ?>
                            <div class="py-5 text-muted">
                                <i class="bi bi-image text-secondary fs-1"></i>
                                <p class="mt-2 mb-0">No sketch available</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-column gap-2 mt-auto">
                        <form method="POST" class="m-0 p-0 w-100">
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <button type="submit" name="action" value="send_to_team" class="btn btn-primary w-100 fw-bold shadow-sm">
                                <i class="bi bi-send-fill me-2"></i> Send to Project Manager / Foreman
                            </button>
                        </form>
                        <form method="POST" class="m-0 p-0 w-100">
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <button type="submit" name="action" value="notify_client" class="btn btn-outline-success w-100 fw-bold shadow-sm">
                                <i class="bi bi-envelope-check-fill me-2"></i> Notify Client: Plans Received
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($plans)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-folder2-open display-4 text-muted mb-3"></i>
                <h5 class="text-muted">No client floor plans found</h5>
                <p class="text-muted small">When a client uploads their sketch, it will appear here.</p>
            </div>
        <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>