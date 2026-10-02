<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman', 'Client']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid Daily Report ID.");
}

$stmt = $pdo->prepare("
    SELECT dr.*, 
           p.project_name, p.project_code, p.location, p.budget,
           COALESCE(u.full_name, 'Site Foreman') AS author_name,
           u.email AS author_email, u.phone_number AS author_phone,
           pm.full_name AS pm_name
    FROM daily_reports dr
    JOIN projects p ON dr.project_id = p.id
    LEFT JOIN users u ON dr.foreman_id = u.id
    LEFT JOIN users pm ON p.pm_id = pm.id
    WHERE dr.id = ?
");
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    die("Daily Report not found.");
}

// Fetch photos
$photoStmt = $pdo->prepare("SELECT * FROM daily_report_photos WHERE report_id = ? ORDER BY id ASC");
$photoStmt->execute([$id]);
$photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Daily Progress Report - DSR-<?= sprintf('%05d', $report['id']) ?> - <?= htmlspecialchars($report['project_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2rem 0; }
        .report-sheet { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 3rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .brand-badge { background-color: #22c55e; color: #fff; padding: 6px 14px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; font-size: 1.1rem; }
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
        .meta-label { font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 3px; }
        .meta-val { font-size: 0.95rem; font-weight: 600; color: #0f172a; }
        .section-title { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px; margin-bottom: 12px; }
        .photo-thumb { width: 100%; height: 180px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; }
        .sig-line { border-top: 1px solid #94a3b8; margin-top: 40px; padding-top: 8px; font-size: 0.85rem; font-weight: 600; color: #475569; text-align: center; }
        @media print {
            body { background: #fff; padding: 0; }
            .report-sheet { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="report-sheet">
        <!-- Action Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="daily-logs.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Daily Logs
            </a>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-printer me-1"></i> Print / Save as PDF
                </button>
            </div>
        </div>

        <!-- Corporate Report Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <div class="brand-badge mb-2">
                    <i class="bi bi-building"></i> BuildNexus
                </div>
                <h3 class="fw-bold text-dark mb-1">Daily Site Progress Report</h3>
                <p class="text-muted small mb-0">Project Execution & Site Operations Ledger</p>
            </div>
            <div class="text-end">
                <div class="badge bg-light text-dark border font-monospace fs-6 px-3 py-2 mb-2">
                    REPORT #DSR-<?= sprintf('%05d', $report['id']) ?>
                </div>
                <div class="text-muted small">Generated on <?= date('Y-m-d H:i') ?></div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 font-monospace mt-1">
                    STATUS: <?= strtoupper($report['status']) ?>
                </span>
            </div>
        </div>

        <!-- Project & Operational Metadata -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="meta-box h-100">
                    <div class="meta-label">Project Name</div>
                    <div class="meta-val text-success fs-5"><?= htmlspecialchars($report['project_name']) ?></div>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($report['location'] ?: 'On-Site Location') ?>
                        <?php if (!empty($report['project_code'])): ?>
                            &bull; Code: <?= htmlspecialchars($report['project_code']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="meta-box h-100">
                    <div class="meta-label">Report Date</div>
                    <div class="meta-val"><i class="bi bi-calendar-event me-1 text-primary"></i> <?= htmlspecialchars($report['report_date']) ?></div>
                    <div class="meta-label mt-2">Weather Condition</div>
                    <div class="meta-val"><i class="bi bi-cloud-sun me-1 text-warning"></i> <?= htmlspecialchars($report['weather_condition'] ?: 'Sunny, 30°C') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="meta-box h-100">
                    <div class="meta-label">Site Author / Foreman</div>
                    <div class="meta-val"><i class="bi bi-person-badge me-1 text-secondary"></i> <?= htmlspecialchars($report['author_name']) ?></div>
                    <div class="meta-label mt-2">Total On-Site Crew</div>
                    <div class="meta-val"><i class="bi bi-people-fill me-1 text-info"></i> <?= intval($report['crew_count']) ?> Workers</div>
                </div>
            </div>
        </div>

        <!-- Work Summary Section -->
        <div class="mb-4">
            <div class="section-title"><i class="bi bi-journal-text me-2"></i>Daily Work Progress & Operations Summary</div>
            <div class="p-3 bg-light rounded-3 border" style="white-space: pre-line; line-height: 1.6; font-size: 0.95rem;">
                <?= htmlspecialchars($report['work_summary']) ?>
            </div>
        </div>

        <!-- Subcontractor Notes -->
        <?php if (!empty($report['subcontractor_notes'])): ?>
            <div class="mb-4">
                <div class="section-title"><i class="bi bi-tools me-2"></i>Subcontractors, Trades & Materials Received</div>
                <div class="p-3 bg-light rounded-3 border" style="white-space: pre-line; line-height: 1.6; font-size: 0.9rem;">
                    <?= htmlspecialchars($report['subcontractor_notes']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Photographic Evidence Gallery -->
        <div class="mb-4">
            <div class="section-title"><i class="bi bi-camera me-2"></i>Photographic Site Evidence & Inspection Records</div>
            <?php if (empty($photos) && empty($report['site_image'])): ?>
                <div class="p-3 bg-light rounded text-muted text-center small border">
                    No photographic evidence attached to this daily report entry.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php if (!empty($report['site_image'])): 
                        $mainImg = $report['site_image'];
                        if (!str_starts_with($mainImg, 'http') && !str_starts_with($mainImg, '/')) {
                            $mainImg = '../' . $mainImg;
                        }
                    ?>
                        <div class="col-md-4">
                            <img src="<?= htmlspecialchars($mainImg) ?>" class="photo-thumb" alt="Site Photo">
                            <div class="small text-muted mt-1">Main Site Activity Photo</div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($photos as $ph): 
                        $img = $ph['photo_url'];
                        if (!str_starts_with($img, 'http') && !str_starts_with($img, '/')) {
                            $img = '../' . $img;
                        }
                    ?>
                        <div class="col-md-4">
                            <img src="<?= htmlspecialchars($img) ?>" class="photo-thumb" alt="Site Photo">
                            <div class="small text-muted mt-1"><?= htmlspecialchars($ph['caption'] ?: 'Field progress evidence') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Signoff Block -->
        <div class="row mt-5 pt-3">
            <div class="col-md-6">
                <div class="sig-line">
                    Site Foreman Signature (<?= htmlspecialchars($report['author_name']) ?>)
                </div>
            </div>
            <div class="col-md-6">
                <div class="sig-line">
                    Project Manager Signoff (<?= htmlspecialchars($report['pm_name'] ?: 'Superintending Engineer') ?>)
                </div>
            </div>
        </div>
    </div>

</body>
</html>
