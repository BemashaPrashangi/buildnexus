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

// Fetch any equipment logs on this project and date
$equipStmt = $pdo->prepare("
    SELECT el.*, e.name AS equipment_name, e.plate_number
    FROM equipment_logs el
    JOIN equipment e ON el.equipment_id = e.id
    WHERE el.project_id = ? AND el.log_date = ?
");
$equipStmt->execute([$report['project_id'], $report['report_date']]);
$equipmentLogs = $equipStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Daily Log #<?= $report['id'] ?> - <?= htmlspecialchars($report['project_name']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2.5rem 0; }
        .view-wrapper { max-width: 1040px; margin: 0 auto; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
        .meta-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
        .meta-title { font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 4px; }
        .meta-value { font-size: 1.05rem; font-weight: 600; color: #0f172a; }
        
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .pill-submitted { background: #f0fdf4; color: #16a34a; }
        .pill-draft { background: #f1f5f9; color: #64748b; }
        .pill-reviewed { background: #eff6ff; color: #2563eb; }
        
        .site-photo-card { border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; transition: transform 0.2s; cursor: pointer; }
        .site-photo-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08); }
        .site-img { width: 100%; height: 200px; object-fit: cover; }
        .btn-nexus-primary { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; }
        .btn-nexus-primary:hover { background-color: #16a34a; color: #fff; }
    </style>
</head>
<body>

    <div class="view-wrapper">
        <!-- Back Navigation & Action Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="daily-logs.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Daily Logs
            </a>
            <div class="d-flex gap-2">
                <a href="generate_daily_log_pdf.php?id=<?= $report['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                </a>
                <a href="project_overview.php?id=<?= $report['project_id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-building me-1"></i> Project Overview
                </a>
            </div>
        </div>

        <!-- Main Report Card -->
        <div class="nexus-card">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($report['project_name']) ?></h3>
                        <?php if (!empty($report['project_code'])): ?>
                            <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($report['project_code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-0"><i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($report['location'] ?: 'On-Site Location') ?></p>
                </div>
                <div class="text-end">
                    <span class="pill pill-<?= strtolower($report['status']) ?> mb-1">
                        <?= htmlspecialchars($report['status']) ?>
                    </span>
                    <div class="text-muted small">Log ID #DSR-<?= sprintf('%05d', $report['id']) ?></div>
                </div>
            </div>

            <!-- Key Indicators Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-title">Date</div>
                        <div class="meta-value"><i class="bi bi-calendar3 me-1 text-primary"></i> <?= htmlspecialchars($report['report_date']) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-title">Author / Foreman</div>
                        <div class="meta-value"><i class="bi bi-person-fill me-1 text-success"></i> <?= htmlspecialchars($report['author_name']) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-title">Weather</div>
                        <div class="meta-value"><i class="bi bi-sun-fill me-1 text-warning"></i> <?= htmlspecialchars($report['weather_condition'] ?: 'Sunny, 30°C') ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-title">Total Crew On-Site</div>
                        <div class="meta-value"><i class="bi bi-people-fill me-1 text-info"></i> <?= intval($report['crew_count']) ?> Workers</div>
                    </div>
                </div>
            </div>

            <!-- Work Progress Summary -->
            <div class="mb-4">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-journal-text me-2 text-success"></i>Work Summary & Operations Completed</h6>
                <div class="p-3 bg-light rounded-3 border" style="line-height: 1.7; font-size: 0.95rem;">
                    <?= nl2br(htmlspecialchars($report['work_summary'])) ?>
                </div>
            </div>

            <!-- Subcontractor Activity Notes -->
            <?php if (!empty($report['subcontractor_notes'])): ?>
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-tools me-2 text-warning"></i>Subcontractor Notes & Trades On-Site</h6>
                    <div class="p-3 bg-light rounded-3 border" style="line-height: 1.6; font-size: 0.9rem;">
                        <?= nl2br(htmlspecialchars($report['subcontractor_notes'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Linked Equipment Logs (if any) -->
            <?php if (!empty($equipmentLogs)): ?>
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-truck me-2 text-primary"></i>Equipment Utilized on Date</h6>
                    <div class="table-responsive border rounded-3">
                        <table class="table mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Equipment</th>
                                    <th>Plate / Serial</th>
                                    <th>Hours Used</th>
                                    <th>Fuel (L)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipmentLogs as $eq): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($eq['equipment_name']) ?></td>
                                        <td class="text-muted font-monospace"><?= htmlspecialchars($eq['plate_number']) ?></td>
                                        <td><?= htmlspecialchars($eq['hours_used']) ?> hrs</td>
                                        <td><?= htmlspecialchars($eq['fuel_liters']) ?> L</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Photo Gallery Audit Trail -->
            <div>
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-images me-2 text-primary"></i>Photographic Evidence & Site Records</h6>
                <?php if (empty($photos) && empty($report['site_image'])): ?>
                    <p class="text-muted small">No photo evidence uploaded with this daily log.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php if (!empty($report['site_image'])): 
                            $mImg = $report['site_image'];
                            if (!str_starts_with($mImg, 'http') && !str_starts_with($mImg, '/')) {
                                $mImg = '../' . $mImg;
                            }
                        ?>
                            <div class="col-md-4">
                                <div class="site-photo-card" onclick="previewImage('<?= htmlspecialchars($mImg) ?>', 'Main Site Photo')">
                                    <img src="<?= htmlspecialchars($mImg) ?>" class="site-img" alt="Site Photo">
                                    <div class="p-2 small text-muted text-truncate">Main Site Activity Photo</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($photos as $ph): 
                            $img = $ph['photo_url'];
                            if (!str_starts_with($img, 'http') && !str_starts_with($img, '/')) {
                                $img = '../' . $img;
                            }
                        ?>
                            <div class="col-md-4">
                                <div class="site-photo-card" onclick="previewImage('<?= htmlspecialchars($img) ?>', '<?= htmlspecialchars(addslashes($ph['caption'] ?? 'Field Photo')) ?>')">
                                    <img src="<?= htmlspecialchars($img) ?>" class="site-img" alt="Site Photo">
                                    <div class="p-2 small text-muted text-truncate"><?= htmlspecialchars($ph['caption'] ?: 'Field photo') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-body p-0 position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3 bg-white" data-bs-dismiss="modal"></button>
                    <img id="previewModalImg" src="" class="w-100 rounded-3" alt="Preview" style="max-height: 80vh; object-fit: contain; background: #000;">
                    <div id="previewModalCaption" class="p-3 text-center text-muted small bg-white rounded-bottom"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(url, caption) {
            document.getElementById('previewModalImg').src = url;
            document.getElementById('previewModalCaption').innerText = caption;
            let myModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
            myModal.show();
        }
    </script>
</body>
</html>
