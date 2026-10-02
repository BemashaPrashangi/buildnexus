<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("<div class='alert alert-danger m-3'>Invalid Equipment ID.</div>");
}

$stmt = $pdo->prepare("
    SELECT e.*, p.project_name, p.project_code
    FROM equipment e
    LEFT JOIN projects p ON e.current_project_id = p.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$eq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eq) {
    die("<div class='alert alert-danger m-3'>Equipment record not found.</div>");
}

// Fetch logs
$logStmt = $pdo->prepare("
    SELECT el.*, p.project_name, p.project_code, u.full_name AS foreman_name
    FROM equipment_logs el
    LEFT JOIN projects p ON el.project_id = p.id
    LEFT JOIN users u ON el.foreman_id = u.id
    WHERE el.equipment_id = ?
    ORDER BY el.log_date DESC, el.id DESC
");
$logStmt->execute([$id]);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totalHours = 0;
$totalFuel = 0;
foreach ($logs as $l) {
    $totalHours += floatval($l['hours_used']);
    $totalFuel += floatval($l['fuel_liters']);
}
$hourlyRate = floatval($eq['hourly_operating_cost']);
$estimatedCost = $totalHours * $hourlyRate;

$statusStyles = [
    'In Use' => 'background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe;',
    'Available' => 'background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7;',
    'Maintenance' => 'background: #fef9c3; color: #a16207; border: 1px solid #fef08a;',
    'Decommissioned' => 'background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2;'
];
$statusPill = $statusStyles[$eq['status']] ?? 'background: #f1f5f9; color: #475569;';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
?>
<?php if (!$isAjax): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- BuildNexus Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/buildnexus/images/logo.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/buildnexus/images/logo.png?v=2">
    <link rel="shortcut icon" href="/buildnexus/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="/buildnexus/images/logo.png?v=2">
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($eq['name']) ?> - Log History - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2.5rem 0; }
        .view-card { max-width: 850px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="mb-3" style="max-width: 850px; margin: 0 auto;">
            <a href="equipment-logs.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Equipment Logs
            </a>
        </div>
        <div class="view-card">
<?php endif; ?>

        <!-- Machine Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-light text-secondary border font-monospace"><?= htmlspecialchars($eq['equipment_code'] ?? 'EQ-N/A') ?></span>
                    <span style="<?= $statusPill ?> padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600;">
                        <?= htmlspecialchars($eq['status']) ?>
                    </span>
                    <span class="badge bg-light text-muted border"><?= htmlspecialchars($eq['type']) ?></span>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($eq['name']) ?></h4>
                <div class="text-muted small">
                    <?php if (!empty($eq['plate_number'])): ?>
                        <span class="me-3"><i class="bi bi-card-heading text-secondary me-1"></i> Plate/Serial: <strong><?= htmlspecialchars($eq['plate_number']) ?></strong></span>
                    <?php endif; ?>
                    <span><i class="bi bi-geo-alt text-success me-1"></i> Location: <strong class="text-success"><?= htmlspecialchars($eq['project_name'] ?? 'Unassigned (Yard)') ?></strong></span>
                </div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Next Scheduled Service</div>
                <div class="fw-bold <?= (!empty($eq['next_service_date']) && $eq['next_service_date'] < date('Y-m-d')) ? 'text-danger' : 'text-dark' ?>">
                    <i class="bi bi-wrench-adjustable me-1"></i> <?= htmlspecialchars($eq['next_service_date'] ?? 'Not scheduled') ?>
                </div>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Total Runtime</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($totalHours, 1) ?> <span class="fs-6 fw-normal text-muted">Hours</span></div>
                    <div class="text-muted small"><?= count($logs) ?> logged shift(s)</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Total Fuel Logged</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($totalFuel, 1) ?> <span class="fs-6 fw-normal text-muted">Liters</span></div>
                    <div class="text-muted small">Diesel / Petrol</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Operating Cost Est.</div>
                    <div class="fs-4 fw-bold text-success mt-1">$<?= number_format($estimatedCost, 2) ?></div>
                    <div class="text-muted small">Rate: $<?= number_format($hourlyRate, 2) ?>/hr</div>
                </div>
            </div>
        </div>

        <!-- Shift History Table -->
        <div class="mb-3">
            <h6 class="fw-bold text-uppercase text-secondary small mb-2">
                <i class="bi bi-clock-history text-primary me-1"></i> Shift & Runtime Operation Logs (<?= count($logs) ?>)
            </h6>
            <?php if (!empty($logs)): ?>
                <div class="table-responsive border rounded-3">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light">
                            <tr class="small text-uppercase text-muted">
                                <th width="15%">Date</th>
                                <th width="25%">Project Site</th>
                                <th width="15%" class="text-center">Runtime</th>
                                <th width="15%" class="text-center">Fuel</th>
                                <th width="30%">Notes / Observed Defects</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="fw-semibold text-muted"><?= htmlspecialchars($l['log_date']) ?></td>
                                <td>
                                    <span class="text-success fw-medium"><?= htmlspecialchars($l['project_name'] ?? 'N/A') ?></span>
                                </td>
                                <td class="text-center fw-bold text-dark"><?= number_format($l['hours_used'], 1) ?> hrs</td>
                                <td class="text-center text-muted"><?= number_format($l['fuel_liters'], 1) ?> L</td>
                                <td class="text-secondary small">
                                    <?= !empty($l['notes']) ? htmlspecialchars($l['notes']) : '<span class="text-muted fst-italic">Routine shift</span>' ?>
                                    <?php if (!empty($l['foreman_name'])): ?>
                                        <div class="text-muted" style="font-size: 0.75rem;">By: <?= htmlspecialchars($l['foreman_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 bg-light rounded-3 text-center border text-muted">
                    <i class="bi bi-speedometer2 fs-2 text-secondary opacity-50 d-block mb-2"></i>
                    <h6 class="fw-semibold">No operational shift logs recorded yet</h6>
                    <p class="small text-muted mb-0">Use "+ New Log" to record runtime hours and fuel consumption.</p>
                </div>
            <?php endif; ?>
        </div>

<?php if (!$isAjax): ?>
        </div>
    </div>
</body>
</html>
<?php endif; ?>
