<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman', 'Client']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid Inspection ID.");
}

$stmt = $pdo->prepare("
    SELECT pi.*, 
           p.project_name, p.project_code, p.location,
           COALESCE(u.full_name, 'QA/QC Engineer') AS creator_name,
           pm.full_name AS pm_name
    FROM project_inspections pi
    JOIN projects p ON pi.project_id = p.id
    LEFT JOIN users u ON pi.created_by = u.id
    LEFT JOIN users pm ON p.pm_id = pm.id
    WHERE pi.id = ?
");
$stmt->execute([$id]);
$insp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$insp) {
    die("Inspection record not found.");
}

// Fetch defects if any
$defectStmt = $pdo->prepare("SELECT * FROM inspection_defects WHERE inspection_id = ? ORDER BY id ASC");
$defectStmt->execute([$id]);
$defects = $defectStmt->fetchAll(PDO::FETCH_ASSOC);

$statusClass = 'text-success';
if ($insp['status'] === 'Failed') $statusClass = 'text-danger';
if ($insp['status'] === 'Scheduled') $statusClass = 'text-primary';
if ($insp['status'] === 'Cancelled') $statusClass = 'text-secondary';
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
    <title>Inspection Certificate - <?= htmlspecialchars($insp['inspection_code']) ?> - <?= htmlspecialchars($insp['project_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2.5rem 0; }
        .cert-sheet { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 3rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .brand-badge { background-color: #22c55e; color: #fff; padding: 6px 14px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; font-size: 1.1rem; }
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
        .meta-label { font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 3px; }
        .meta-val { font-size: 0.95rem; font-weight: 600; color: #0f172a; }
        .section-title { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px; margin-bottom: 12px; }
        .sig-line { border-top: 1px solid #94a3b8; margin-top: 45px; padding-top: 8px; font-size: 0.85rem; font-weight: 600; color: #475569; text-align: center; }
        @media print {
            body { background: #fff; padding: 0; }
            .cert-sheet { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="cert-sheet">
        <!-- Action Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="inspections.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Inspections
            </a>
            <button onclick="window.print()" class="btn btn-sm btn-success fw-semibold">
                <i class="bi bi-printer me-1"></i> Print / Save as PDF
            </button>
        </div>

        <!-- Corporate Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <div class="brand-badge mb-2">
                    <i class="bi bi-building"></i> BuildNexus QA/QC
                </div>
                <h3 class="fw-bold text-dark mb-1">Building Code Compliance Certificate</h3>
                <p class="text-muted small mb-0">Structural, Architectural & MEP Inspection Verification</p>
            </div>
            <div class="text-end">
                <div class="badge bg-light text-dark border font-monospace fs-6 px-3 py-2 mb-2">
                    <?= htmlspecialchars($insp['inspection_code']) ?>
                </div>
                <div class="text-muted small">Generated on <?= date('Y-m-d H:i') ?></div>
                <div class="fs-5 fw-bold <?= $statusClass ?> mt-1">
                    STATUS: <?= strtoupper($insp['status']) ?>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="meta-box h-100">
                    <div class="meta-label">Project Name</div>
                    <div class="meta-val text-success fs-5"><?= htmlspecialchars($insp['project_name']) ?></div>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($insp['location'] ?: 'Site Location') ?>
                        <?php if (!empty($insp['project_code'])): ?>
                            &bull; Code: <?= htmlspecialchars($insp['project_code']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="meta-box h-100">
                    <div class="meta-label">Inspection Type</div>
                    <div class="meta-val"><i class="bi bi-clipboard-check me-1 text-primary"></i> <?= htmlspecialchars($insp['inspection_type']) ?></div>
                    <div class="meta-label mt-2">Scheduled Date</div>
                    <div class="meta-val"><?= htmlspecialchars($insp['scheduled_date']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="meta-box h-100">
                    <div class="meta-label">Certified Inspector</div>
                    <div class="meta-val"><i class="bi bi-person-badge me-1 text-secondary"></i> <?= htmlspecialchars($insp['inspector_name']) ?></div>
                    <div class="meta-label mt-2">Authority / Agency</div>
                    <div class="meta-val"><?= htmlspecialchars($insp['inspector_agency'] ?: 'Municipal Authority') ?></div>
                </div>
            </div>
        </div>

        <!-- Notes / Checklist Results -->
        <div class="mb-4">
            <div class="section-title"><i class="bi bi-journal-text me-2"></i>Inspection Findings & Signoff Notes</div>
            <div class="p-3 bg-light rounded-3 border" style="white-space: pre-line; line-height: 1.6; font-size: 0.95rem;">
                <?= htmlspecialchars($insp['result_notes'] ?: 'Inspection completed under official building codes and architectural specifications.') ?>
            </div>
        </div>

        <!-- Defects Punch List (if any) -->
        <?php if (!empty($defects)): ?>
            <div class="mb-4">
                <div class="section-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Observed Deficiencies & Corrective Action Notices</div>
                <div class="table-responsive border rounded-3">
                    <table class="table mb-0 table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Defect Description</th>
                                <th>Remedy Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($defects as $idx => $def): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($def['defect_description']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $def['remedy_status'] === 'Verified' ? 'success' : 'danger' ?>-subtle text-<?= $def['remedy_status'] === 'Verified' ? 'success' : 'danger' ?> border">
                                            <?= htmlspecialchars($def['remedy_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Signoff Block -->
        <div class="row mt-5 pt-3">
            <div class="col-md-6">
                <div class="sig-line">
                    Lead Inspector Signature (<?= htmlspecialchars($insp['inspector_name']) ?>)<br>
                    <small class="text-muted"><?= htmlspecialchars($insp['inspector_agency'] ?: 'Building Inspection Authority') ?></small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="sig-line">
                    Project Manager Signoff (<?= htmlspecialchars($insp['pm_name'] ?: 'Project Management Engineer') ?>)<br>
                    <small class="text-muted">BuildNexus QA/QC Verification</small>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
