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

// Fetch defects
$defectStmt = $pdo->prepare("SELECT * FROM inspection_defects WHERE inspection_id = ? ORDER BY id ASC");
$defectStmt->execute([$id]);
$defects = $defectStmt->fetchAll(PDO::FETCH_ASSOC);

// Pill style
$statusClass = 'pill-scheduled';
if ($insp['status'] === 'Passed') $statusClass = 'pill-passed';
if ($insp['status'] === 'Failed') $statusClass = 'pill-failed';
if ($insp['status'] === 'Cancelled') $statusClass = 'pill-cancelled';
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
    <title>Inspection <?= htmlspecialchars($insp['inspection_code']) ?> - <?= htmlspecialchars($insp['project_name']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2.5rem 0; }
        .view-wrapper { max-width: 1040px; margin: 0 auto; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
        .meta-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
        .meta-title { font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 4px; }
        .meta-value { font-size: 1.05rem; font-weight: 600; color: #0f172a; }

        .pill { padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; letter-spacing: 0.02em; }
        .pill-passed { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .pill-scheduled { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .pill-failed { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
        .pill-cancelled { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>

    <div class="view-wrapper">
        <!-- Action Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="inspections.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Inspections
            </a>
            <div class="d-flex gap-2">
                <a href="generate_inspection_report.php?id=<?= $insp['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-printer me-1"></i> Inspection Certificate
                </a>
                <a href="project_overview.php?id=<?= $insp['project_id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-building me-1"></i> Project Overview
                </a>
            </div>
        </div>

        <!-- Main Inspection Details -->
        <div class="nexus-card">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($insp['inspection_code']) ?></span>
                        <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($insp['inspection_type']) ?> Inspection</h3>
                    </div>
                    <div class="text-muted small">
                        Project: <a href="project_overview.php?id=<?= $insp['project_id'] ?>" class="text-success fw-semibold text-decoration-none"><?= htmlspecialchars($insp['project_name']) ?></a>
                        &bull; <?= htmlspecialchars($insp['location'] ?: 'Site Location') ?>
                    </div>
                </div>
                <div class="text-end">
                    <span class="pill <?= $statusClass ?> fs-6 mb-1">
                        <?= htmlspecialchars($insp['status']) ?>
                    </span>
                    <div class="text-muted small">Scheduled: <?= htmlspecialchars($insp['scheduled_date']) ?></div>
                </div>
            </div>

            <!-- Key Metadata Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-title">Inspector Name</div>
                        <div class="meta-value"><i class="bi bi-person-badge text-secondary me-1"></i> <?= htmlspecialchars($insp['inspector_name']) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-title">Authority / Agency</div>
                        <div class="meta-value"><i class="bi bi-building text-primary me-1"></i> <?= htmlspecialchars($insp['inspector_agency'] ?: 'City Council') ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-title">Scheduled Date</div>
                        <div class="meta-value"><i class="bi bi-calendar-event text-warning me-1"></i> <?= htmlspecialchars($insp['scheduled_date']) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-title">Completion Date</div>
                        <div class="meta-value"><i class="bi bi-calendar-check text-success me-1"></i> <?= htmlspecialchars($insp['completed_date'] ?: 'Pending Review') ?></div>
                    </div>
                </div>
            </div>

            <!-- Findings / Inspector Notes -->
            <div class="mb-4">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-journal-text me-2 text-success"></i>Inspector Remarks & Compliance Notes</h6>
                <div class="p-3 bg-light rounded-3 border" style="line-height: 1.7; font-size: 0.95rem;">
                    <?= nl2br(htmlspecialchars($insp['result_notes'] ?: 'No detailed inspector notes entered yet.')) ?>
                </div>
            </div>

            <!-- Attached Scanned Report / Certificate -->
            <?php if (!empty($insp['report_file_url'])): 
                $fileUrl = $insp['report_file_url'];
                if (!str_starts_with($fileUrl, 'http') && !str_starts_with($fileUrl, '/')) {
                    $fileUrl = '../' . $fileUrl;
                }
            ?>
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-paperclip me-2 text-primary"></i>Attached Compliance Documentation</h6>
                    <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-file-earmark-pdf fs-2 text-danger"></i>
                            <div>
                                <div class="fw-semibold text-dark">Signed Inspection Report / Certificate</div>
                                <div class="text-muted small"><?= htmlspecialchars(basename($insp['report_file_url'])) ?></div>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i> View / Download
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Defects / Deficiency Log -->
            <div class="mb-3">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Deficiencies & Corrective Action Items (<?= count($defects) ?>)</h6>
                <?php if (empty($defects)): ?>
                    <p class="text-muted small mb-0">No code deficiencies or corrective actions noted on this inspection.</p>
                <?php else: ?>
                    <div class="table-responsive border rounded-3">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Deficiency Description</th>
                                    <th>Remedy Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($defects as $idx => $def): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($def['defect_description']) ?></td>
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
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
