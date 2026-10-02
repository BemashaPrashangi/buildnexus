<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$lead_id = intval($_GET['id'] ?? 0);
if ($lead_id <= 0) {
    die("<div class='alert alert-danger m-3'>Error: No valid Lead ID provided.</div>");
}

try {
    $stmt = $pdo->prepare("
        SELECT l.*, u.full_name AS agent_name, u.role AS agent_role 
        FROM leads l 
        LEFT JOIN users u ON l.assigned_to = u.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        die("<div class='alert alert-danger m-3'>Error: Lead record not found.</div>");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Avatar initials
function getInitials($name) {
    $words = explode(" ", trim($name));
    $initials = "";
    foreach ($words as $w) { if(!empty($w)) $initials .= strtoupper($w[0]); }
    return substr($initials, 0, 2) ?: 'L';
}

$statusStyles = [
    'Converted' => 'background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7;',
    'New' => 'background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe;',
    'Follow-up' => 'background: #fef9c3; color: #a16207; border: 1px solid #fef08a;',
    'Quoted' => 'background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff;',
    'Lost' => 'background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2;'
];
$pillStyle = $statusStyles[$lead['status']] ?? 'background: #f1f5f9; color: #475569;';

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
    <title>Lead Details - <?= htmlspecialchars($lead['customer_name']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; color: #1e293b; padding: 2.5rem 0; }
        .details-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 2rem; max-width: 800px; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04); }
    </style>
</head>
<body>
    <div class="container">
        <div class="mb-3 d-flex justify-content-between align-items-center" style="max-width: 800px; margin: 0 auto;">
            <a href="lead-generation.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Leads
            </a>
            <?php if ($lead['status'] !== 'Converted'): ?>
                <a href="convert_lead.php?id=<?= $lead['id'] ?>" class="btn btn-success btn-sm fw-bold">
                    <i class="bi bi-node-plus me-1"></i> Convert to Project
                </a>
            <?php endif; ?>
        </div>
        <div class="details-card">
<?php endif; ?>

        <!-- Lead Header -->
        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: #f1f5f9; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; font-weight: 700; color: #475569;">
                    <?= getInitials($lead['customer_name']) ?>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($lead['customer_name']) ?></h4>
                    <div class="text-muted small">
                        <i class="bi bi-building text-success me-1"></i> Proposed: <strong><?= htmlspecialchars($lead['project_type']) ?></strong>
                    </div>
                </div>
            </div>
            <div class="text-end">
                <span style="<?= $pillStyle ?> padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display: inline-block;">
                    <?= htmlspecialchars($lead['status']) ?>
                </span>
                <div class="text-muted small mt-1">Logged: <?= date('M d, Y', strtotime($lead['created_at'])) ?></div>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Email Address</div>
                    <div class="fw-semibold text-dark text-truncate mt-1">
                        <a href="mailto:<?= htmlspecialchars($lead['email']) ?>" class="text-decoration-none text-dark">
                            <i class="bi bi-envelope text-success me-1"></i> <?= htmlspecialchars($lead['email']) ?>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Phone Number</div>
                    <div class="fw-semibold text-dark mt-1">
                        <?php if (!empty($lead['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($lead['phone']) ?>" class="text-decoration-none text-dark">
                                <i class="bi bi-telephone text-primary me-1"></i> <?= htmlspecialchars($lead['phone']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">Not provided</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Estimated Budget</div>
                    <div class="fw-bold text-success fs-5 mt-1">
                        <?= (!empty($lead['estimated_budget']) && $lead['estimated_budget'] > 0) ? 'RS. ' . number_format($lead['estimated_budget'], 2) : '<span class="text-muted fs-6 fw-normal">Undisclosed</span>' ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Assigned Estimator</div>
                    <div class="fw-semibold text-dark mt-1">
                        <i class="bi bi-person-badge text-secondary me-1"></i> <?= htmlspecialchars($lead['agent_name'] ?? 'Unassigned') ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Site / Property Location</div>
                    <div class="text-dark mt-1">
                        <i class="bi bi-geo-alt text-danger me-1"></i> <?= !empty($lead['site_location']) ? htmlspecialchars($lead['site_location']) : '<span class="text-muted">Not specified</span>' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Requirements Notes -->
        <div class="mb-4">
            <h6 class="fw-bold text-uppercase text-secondary small mb-2">
                <i class="bi bi-card-text text-primary me-1"></i> Inquiry Details & Requirements
            </h6>
            <div class="p-3 bg-light rounded-3 border" style="font-size: 0.92rem; line-height: 1.6;">
                <?= !empty($lead['notes']) ? nl2br(htmlspecialchars($lead['notes'])) : '<span class="text-muted">No specific requirements entered for this inquiry.</span>' ?>
            </div>
        </div>

        <!-- Cached Automated Reply Draft -->
        <?php if (!empty($lead['ai_response_draft'])): ?>
            <div class="mb-3">
                <h6 class="fw-bold text-uppercase text-secondary small mb-2">
                    <i class="bi bi-chat-left-text text-primary me-1"></i> Pre-Sales Response Draft
                </h6>
                <div class="p-3 bg-primary-subtle text-dark rounded-3 border border-primary-subtle font-monospace" style="font-size: 0.85rem; line-height: 1.6; white-space: pre-wrap;">
<?= htmlspecialchars($lead['ai_response_draft']) ?>
                </div>
            </div>
        <?php endif; ?>

<?php if (!$isAjax): ?>
        </div>
    </div>
</body>
</html>
<?php endif; ?>