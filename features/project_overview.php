<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

$project_id = intval($_GET['id'] ?? 0);
if ($project_id <= 0) {
    die("Invalid Project ID.");
}

try {
    // 1. Project Master Details with PM and Client
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE(c.full_name, NULLIF(p.client_name, ''), 'Client') AS display_client,
               c.company AS client_company, c.email AS client_email,
               u.full_name AS pm_name, u.email AS pm_email, u.phone_number AS pm_phone
        FROM projects p
        LEFT JOIN clients c ON p.client_id = c.id
        LEFT JOIN users u ON p.pm_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        die("Project not found.");
    }

    // 2. Financial Metrics from Bills
    $billStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total_spend FROM bills WHERE project_id = ?");
    $billStmt->execute([$project_id]);
    $actual_spend = floatval($billStmt->fetchColumn());

    $budget = floatval($project['budget']);
    $remaining_budget = $budget - $actual_spend;

    if ($budget > 0) {
        $spend_percent = round(($actual_spend / $budget) * 100, 1);
        if ($actual_spend > $budget) {
            $budget_tag = 'Over Budget';
            $budget_tag_class = 'text-danger';
        } elseif ($spend_percent >= 90) {
            $budget_tag = 'On Track';
            $budget_tag_class = 'text-success';
        } else {
            $under_percent = round(100 - $spend_percent);
            $budget_tag = "{$under_percent}% Under";
            $budget_tag_class = 'text-success';
        }
    } else {
        $spend_percent = 0;
        $budget_tag = 'On Track';
        $budget_tag_class = 'text-success';
    }

    // 3. Task Progress Metrics
    $taskStmt = $pdo->prepare("
        SELECT COUNT(*) AS total_tasks,
               SUM(CASE WHEN status = 'Done' THEN 1 ELSE 0 END) AS done_tasks
        FROM tasks 
        WHERE project_id = ?
    ");
    $taskStmt->execute([$project_id]);
    $taskStats = $taskStmt->fetch(PDO::FETCH_ASSOC);
    $total_tasks = intval($taskStats['total_tasks'] ?? 0);
    $done_tasks = intval($taskStats['done_tasks'] ?? 0);

    if ($total_tasks > 0) {
        $progress_percent = round(($done_tasks / $total_tasks) * 100);
    } else {
        // Stage-based fallback
        $stageDefaults = [
            'Planning' => 10,
            'Earthwork' => 20,
            'Framing' => 45,
            'Roofing' => 60,
            'Finishing' => 85,
            'Handover' => 100
        ];
        $progress_percent = $stageDefaults[$project['stage']] ?? 15;
    }

    // 4. Linked Counts: RFIs, Submittals, Change Orders, Invoices
    $rfiCount = $pdo->prepare("SELECT COUNT(*) FROM project_rfis WHERE project_id = ?");
    $rfiCount->execute([$project_id]);
    $total_rfis = $rfiCount->fetchColumn();

    $subCount = $pdo->prepare("SELECT COUNT(*) FROM project_submittals WHERE project_id = ?");
    $subCount->execute([$project_id]);
    $total_submittals = $subCount->fetchColumn();

    $coCount = $pdo->prepare("SELECT COUNT(*) FROM change_orders WHERE project_id = ?");
    $coCount->execute([$project_id]);
    $total_cos = $coCount->fetchColumn();

    $invCount = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE project_id = ?");
    $invCount->execute([$project_id]);
    $total_invs = $invCount->fetchColumn();

    // 5. Recent RFIs
    $recentRfisStmt = $pdo->prepare("SELECT id, rfi_number, subject, status, due_date FROM project_rfis WHERE project_id = ? ORDER BY id DESC LIMIT 5");
    $recentRfisStmt->execute([$project_id]);
    $recent_rfis = $recentRfisStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Recent Submittals
    $recentSubsStmt = $pdo->prepare("SELECT id, submittal_number, title, status, review_due_date FROM project_submittals WHERE project_id = ? ORDER BY id DESC LIMIT 5");
    $recentSubsStmt->execute([$project_id]);
    $recent_subs = $recentSubsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Recent Change Orders
    $recentCosStmt = $pdo->prepare("SELECT id, co_number, title, cost_impact, status FROM change_orders WHERE project_id = ? ORDER BY id DESC LIMIT 5");
    $recentCosStmt->execute([$project_id]);
    $recent_cos = $recentCosStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Stage pill color
function getStageBadge($stage) {
    switch ($stage) {
        case 'Earthwork': return ['bg' => '#ffedd5', 'color' => '#c2410c'];
        case 'Framing': return ['bg' => '#fef9c3', 'color' => '#a16207'];
        case 'Roofing': return ['bg' => '#eff6ff', 'color' => '#1d4ed8'];
        case 'Finishing': return ['bg' => '#f3e8ff', 'color' => '#7e22ce'];
        case 'Planning':
        case 'Handover':
        default: return ['bg' => '#f0fdf4', 'color' => '#16a34a'];
    }
}
$stageBadge = getStageBadge($project['stage']);
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
    <title><?= htmlspecialchars($project['project_name']) ?> - BuildNexus Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2rem 0; }
        .overview-wrapper { max-width: 1100px; margin: 0 auto; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
        
        .pill { padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display: inline-block; }
        .meta-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 2px; }
        .hero-thumb { width: 100%; height: 220px; border-radius: 10px; object-fit: cover; }
        
        .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; text-align: center; }
        .kpi-val { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
        
        .progress-nexus { height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: #22c55e; border-radius: 10px; }

        .btn-nexus-primary { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; }
        .btn-nexus-primary:hover { background-color: #16a34a; color: #fff; }
    </style>
</head>
<body>
    <div class="overview-wrapper">
        <!-- Top Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="projects.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Projects
            </a>
            <div class="d-flex gap-2">
                <a href="rfi.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-question-circle me-1"></i> RFIs (<?= $total_rfis ?>)</a>
                <a href="submittals.php" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-check me-1"></i> Submittals (<?= $total_submittals ?>)</a>
                <a href="change-orders.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-repeat me-1"></i> Change Orders (<?= $total_cos ?>)</a>
                <a href="invoicing.php" class="btn btn-sm btn-outline-dark"><i class="bi bi-receipt me-1"></i> Invoices (<?= $total_invs ?>)</a>
            </div>
        </div>

        <!-- Project Hero Card -->
        <div class="nexus-card">
            <div class="row g-4 align-items-center">
                <div class="col-md-4">
                    <?php 
                        $overviewThumb = !empty($project['thumbnail_url']) ? $project['thumbnail_url'] : '';
                        if (!empty($overviewThumb) && !str_starts_with($overviewThumb, 'http') && !str_starts_with($overviewThumb, '/')) {
                            $overviewThumb = '../' . $overviewThumb;
                        }
                    ?>
                    <?php if (!empty($overviewThumb)): ?>
                        <img src="<?= htmlspecialchars($overviewThumb) ?>" class="hero-thumb" alt="Project Thumbnail">
                    <?php else: ?>
                        <div class="hero-thumb bg-light d-flex align-items-center justify-content-center text-muted border">
                            <i class="bi bi-building fs-1"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-8">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge bg-light text-muted border font-monospace mb-1"><?= htmlspecialchars($project['project_code'] ?? 'PRJ-' . $project['id']) ?></span>
                            <h2 class="fw-bold mb-1"><?= htmlspecialchars($project['project_name']) ?></h2>
                            <p class="text-muted small mb-0"><i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($project['location'] ?: 'Colombo, Sri Lanka') ?></p>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <span class="pill" style="background: <?= $stageBadge['bg'] ?>; color: <?= $stageBadge['color'] ?>;">
                                <?= htmlspecialchars($project['stage']) ?>
                            </span>
                            <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($project['status']) ?></span>
                        </div>
                    </div>

                    <div class="row g-3 my-2 pt-2 border-top">
                        <div class="col-sm-4">
                            <div class="meta-label">Client</div>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($project['display_client']) ?></div>
                        </div>
                        <div class="col-sm-4">
                            <div class="meta-label">Project Manager</div>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($project['pm_name'] ?: 'Unassigned') ?></div>
                        </div>
                        <div class="col-sm-4">
                            <div class="meta-label">Target Handover</div>
                            <div class="fw-semibold text-dark"><?= $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : 'In Progress' ?></div>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-1 small">
                            <span class="fw-semibold text-muted">Overall Milestone Progress</span>
                            <span class="fw-bold text-success"><?= $progress_percent ?>% Completed</span>
                        </div>
                        <div class="progress-nexus">
                            <div class="progress-fill" style="width: <?= $progress_percent ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial & Variance Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="nexus-card mb-0 text-center">
                    <div class="meta-label">Contract Budget</div>
                    <div class="kpi-val text-dark">RS. <?= number_format($budget, 2) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="nexus-card mb-0 text-center">
                    <div class="meta-label">Actual Expenditure</div>
                    <div class="kpi-val text-primary">RS. <?= number_format($actual_spend, 2) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="nexus-card mb-0 text-center">
                    <div class="meta-label">Remaining Contingency</div>
                    <div class="kpi-val <?= $remaining_budget >= 0 ? 'text-success' : 'text-danger' ?>">
                        RS. <?= number_format($remaining_budget, 2) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="nexus-card mb-0 text-center">
                    <div class="meta-label">Budget Variance</div>
                    <div class="kpi-val <?= $budget_tag_class ?>"><?= $budget_tag ?></div>
                </div>
            </div>
        </div>

        <!-- Recent Records Split Grid -->
        <div class="row g-4">
            <!-- Recent RFIs -->
            <div class="col-md-6">
                <div class="nexus-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Project RFIs</h6>
                        <a href="rfi.php" class="small text-success text-decoration-none">View All</a>
                    </div>
                    <?php if (empty($recent_rfis)): ?>
                        <p class="text-muted small">No active RFIs for this project.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_rfis as $r): ?>
                                <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold small"><?= htmlspecialchars($r['rfi_number']) ?>: <?= htmlspecialchars($r['subject']) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;">Due: <?= htmlspecialchars($r['due_date']) ?></div>
                                    </div>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Submittals -->
            <div class="col-md-6">
                <div class="nexus-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Architectural Submittals</h6>
                        <a href="submittals.php" class="small text-success text-decoration-none">View All</a>
                    </div>
                    <?php if (empty($recent_subs)): ?>
                        <p class="text-muted small">No submittals registered yet.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_subs as $s): ?>
                                <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold small"><?= htmlspecialchars($s['submittal_number']) ?>: <?= htmlspecialchars($s['title']) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;">Due: <?= htmlspecialchars($s['review_due_date'] ?: 'N/A') ?></div>
                                    </div>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($s['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
