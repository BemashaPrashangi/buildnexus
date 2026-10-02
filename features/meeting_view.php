<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman', 'Client']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid Safety Meeting ID.");
}

$stmt = $pdo->prepare("
    SELECT sm.*, 
           p.project_name, p.project_code, p.location,
           COALESCE(st.title, sm.custom_topic, 'General Safety Meeting') AS topic_title,
           COALESCE(st.category, 'General Safety') AS topic_category,
           st.content AS topic_content,
           COALESCE(u.full_name, 'Meeting Conductor') AS lead_name,
           u.role AS lead_role,
           u.email AS lead_email
    FROM safety_meeting_logs sm
    JOIN projects p ON sm.project_id = p.id
    LEFT JOIN safety_topics st ON sm.topic_id = st.id
    LEFT JOIN users u ON sm.foreman_id = u.id
    WHERE sm.id = ?
");
$stmt->execute([$id]);
$meeting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
    die("<div class='alert alert-danger m-3'>Safety meeting record not found.</div>");
}

// Fetch attendee list
$attStmt = $pdo->prepare("SELECT * FROM safety_meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
$attStmt->execute([$id]);
$attendees = $attStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Meeting Details - #<?= $meeting['id'] ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2rem 0; }
        .view-container { max-width: 850px; margin: 0 auto; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04); }
    </style>
</head>
<body>
    <div class="view-container">
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <a href="safety-meetings.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Safety Meetings
            </a>
            <a href="generate_meeting_pdf.php?id=<?= $meeting['id'] ?>" target="_blank" class="btn btn-sm text-white fw-bold" style="background-color: #22c55e;">
                <i class="bi bi-file-earmark-pdf me-1"></i> Download Attendance PDF
            </a>
        </div>
        <div class="nexus-card">
<?php endif; ?>

        <!-- Meeting Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
            <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 mb-2">
                    <i class="bi bi-shield-check me-1"></i> <?= htmlspecialchars($meeting['topic_category']) ?>
                </span>
                <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($meeting['topic_title']) ?></h4>
                <div class="text-muted small">
                    <i class="bi bi-building text-success me-1"></i> <strong class="text-success"><?= htmlspecialchars($meeting['project_name']) ?></strong>
                    <?php if (!empty($meeting['project_code'])): ?>
                        <span class="ms-1 text-muted">(<?= htmlspecialchars($meeting['project_code']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($meeting['meeting_date']) ?></div>
                <div class="text-muted small">Conducted by: <strong><?= htmlspecialchars($meeting['lead_name']) ?></strong></div>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Total Attendees</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= intval($meeting['attendees_count']) ?></div>
                    <div class="text-muted small"><?= count($attendees) ?> workers registered</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Meeting Conductor</div>
                    <div class="fs-6 fw-bold text-dark mt-1 text-truncate"><?= htmlspecialchars($meeting['lead_name']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($meeting['lead_role'] ?? 'Foreman') ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Signed Roster Document</div>
                    <div class="mt-1">
                        <?php if (!empty($meeting['signed_roster_file'])): ?>
                            <a href="../uploads/safety/<?= htmlspecialchars($meeting['signed_roster_file']) ?>" target="_blank" class="btn btn-sm btn-outline-success py-1">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> View Attachment
                            </a>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border">Digital Record Only</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Safety Guidelines / Talking Points -->
        <div class="mb-4">
            <h6 class="fw-bold text-uppercase text-secondary small mb-2"><i class="bi bi-chat-left-quote text-success me-1"></i> Safety Talking Points & Hazard Guidelines</h6>
            <div class="p-3 bg-light rounded-3 border" style="font-size: 0.92rem; line-height: 1.6;">
                <?= !empty($meeting['topic_content']) ? nl2br(htmlspecialchars($meeting['topic_content'])) : 'General safety protocols and daily task hazards discussed with workers on site.' ?>
            </div>
        </div>

        <!-- Foreman Notes / Hazards Raised -->
        <?php if (!empty($meeting['notes'])): ?>
        <div class="mb-4">
            <h6 class="fw-bold text-uppercase text-secondary small mb-2"><i class="bi bi-exclamation-triangle text-warning me-1"></i> Field Observations & Worker Feedback</h6>
            <div class="p-3 bg-warning-subtle text-dark rounded-3 border border-warning-subtle" style="font-size: 0.92rem; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($meeting['notes'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Worker Attendance Roster -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-uppercase text-secondary small mb-0"><i class="bi bi-people-fill text-success me-1"></i> Worker Attendance Roster (<?= count($attendees) ?>)</h6>
            </div>
            <?php if (!empty($attendees)): ?>
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th width="8%" class="text-center">#</th>
                            <th width="45%">Worker Name</th>
                            <th width="32%">Trade / Role</th>
                            <th width="15%" class="text-center">Verification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendees as $idx => $att): ?>
                        <tr>
                            <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($att['worker_name']) ?></td>
                            <td class="text-secondary"><?= htmlspecialchars($att['trade_role']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($att['signature_status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="p-3 bg-light rounded-3 text-muted text-center small border">
                    <i class="bi bi-info-circle me-1"></i> No individual worker names itemized. Total attendance count logged: <strong><?= intval($meeting['attendees_count']) ?></strong>.
                </div>
            <?php endif; ?>
        </div>

        <!-- Scanned Roster Preview if Image -->
        <?php if (!empty($meeting['signed_roster_file'])): 
            $fileExt = strtolower(pathinfo($meeting['signed_roster_file'], PATHINFO_EXTENSION));
            $filePath = '../uploads/safety/' . htmlspecialchars($meeting['signed_roster_file']);
        ?>
        <div class="mb-3">
            <h6 class="fw-bold text-uppercase text-secondary small mb-2"><i class="bi bi-image text-primary me-1"></i> Physical Sign-in Sheet Preview</h6>
            <?php if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'])): ?>
                <div class="border rounded-3 p-2 bg-light text-center">
                    <img src="<?= $filePath ?>" alt="Signed Attendance Sheet" class="img-fluid rounded" style="max-height: 400px; object-fit: contain;">
                </div>
            <?php else: ?>
                <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-file-earmark-pdf text-danger fs-3 me-2"></i>
                        <span class="fw-semibold"><?= htmlspecialchars($meeting['signed_roster_file']) ?></span>
                    </div>
                    <a href="<?= $filePath ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Open Document
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

<?php if (!$isAjax): ?>
        </div>
    </div>
</body>
</html>
<?php endif; ?>
