<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid Safety Meeting ID.");
}

$stmt = $pdo->prepare("
    SELECT sm.*, 
           p.project_name, p.project_code, p.location,
           COALESCE(st.title, sm.custom_topic, 'Toolbox Safety Talk') AS topic_title,
           COALESCE(st.category, 'General Safety') AS topic_category,
           st.content AS topic_content,
           COALESCE(u.full_name, 'Site Safety Conductor') AS lead_name,
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
    die("Safety meeting record not found.");
}

// Fetch attendee roster
$attStmt = $pdo->prepare("SELECT * FROM safety_meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
$attStmt->execute([$id]);
$attendees = $attStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Toolbox Talk Attendance Record - #<?= htmlspecialchars($meeting['id']) ?> - <?= htmlspecialchars($meeting['project_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2.5rem 0; }
        .record-sheet { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 3rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.06); }
        .brand-badge { background-color: #22c55e; color: #fff; padding: 6px 14px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; font-size: 1.1rem; }
        .doc-badge { background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; font-weight: 700; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; }
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; height: 100%; }
        .meta-label { font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 4px; }
        .meta-val { font-size: 0.95rem; font-weight: 600; color: #0f172a; }
        .section-header { font-size: 0.88rem; text-transform: uppercase; letter-spacing: 0.06em; color: #334155; font-weight: 700; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 14px; }
        .topic-summary-box { background: #f0fdf4; border-left: 4px solid #22c55e; border-radius: 0 8px 8px 0; padding: 1.25rem; }
        .roster-table th { background: #f8fafc; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; font-weight: 700; border-bottom: 2px solid #e2e8f0; }
        .roster-table td { font-size: 0.9rem; vertical-align: middle; padding: 10px 12px; }
        .signature-cell { border-bottom: 1px dashed #cbd5e1; min-width: 140px; font-family: 'Courier New', monospace; font-size: 0.85rem; color: #047857; }
        .sig-box { border-top: 1.5px solid #64748b; margin-top: 50px; padding-top: 8px; font-size: 0.85rem; font-weight: 600; color: #475569; }
        @media print {
            body { background: #fff; padding: 0; }
            .record-sheet { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="record-sheet">
        <!-- Print / Navigation Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="safety-meetings.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Safety Meetings
            </a>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-sm text-white fw-bold px-3" style="background-color: #22c55e;">
                    <i class="bi bi-printer me-1"></i> Print / Save as PDF
                </button>
            </div>
        </div>

        <!-- Official Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <div class="brand-badge mb-2">
                    <i class="bi bi-cone-striped"></i> BuildNexus HSE
                </div>
                <h2 class="h4 fw-bold mb-1 text-dark">Toolbox Talk Attendance Record</h2>
                <div class="text-muted small">Environmental, Health & Safety (EHS) Site Compliance Verification</div>
            </div>
            <div class="text-end">
                <span class="doc-badge mb-2 d-inline-block">TBT-<?= date('Y', strtotime($meeting['meeting_date'])) ?>-<?= str_pad($meeting['id'], 4, '0', STR_PAD_LEFT) ?></span>
                <div class="text-muted small">Date: <strong class="text-dark"><?= htmlspecialchars($meeting['meeting_date']) ?></strong></div>
                <div class="text-muted small">OSHA / HSE Standard Record</div>
            </div>
        </div>

        <!-- Project & Meeting Meta -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="meta-box">
                    <div class="meta-label">Project Name & Code</div>
                    <div class="meta-val text-success fw-bold"><?= htmlspecialchars($meeting['project_name']) ?></div>
                    <div class="text-muted small mt-1"><i class="bi bi-hash"></i> <?= htmlspecialchars($meeting['project_code'] ?? 'PRJ-N/A') ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="meta-box">
                    <div class="meta-label">Meeting Conductor / Lead</div>
                    <div class="meta-val"><?= htmlspecialchars($meeting['lead_name']) ?></div>
                    <div class="text-muted small mt-1"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($meeting['lead_role'] ?? 'Foreman') ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="meta-box">
                    <div class="meta-label">Site Attendance Total</div>
                    <div class="meta-val text-dark fs-5 fw-bold">
                        <?= intval($meeting['attendees_count']) ?> Workers
                    </div>
                    <div class="text-muted small mt-1"><i class="bi bi-people-fill"></i> <?= count($attendees) ?> Individually Roster Verified</div>
                </div>
            </div>
        </div>

        <!-- Safety Topic & Talking Points -->
        <div class="mb-4">
            <div class="section-header d-flex justify-content-between">
                <span>1. Safety Topic & Hazard Prevention Curriculum</span>
                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($meeting['topic_category']) ?></span>
            </div>
            <div class="topic-summary-box">
                <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($meeting['topic_title']) ?></h5>
                <p class="mb-0 text-secondary" style="font-size: 0.92rem; line-height: 1.6;">
                    <?= !empty($meeting['topic_content']) ? nl2br(htmlspecialchars($meeting['topic_content'])) : 'Standard field safety procedures and task-specific hazard mitigations reviewed with the site team prior to commencing operations.' ?>
                </p>
            </div>
        </div>

        <!-- Meeting Notes / Worker Raised Items -->
        <?php if (!empty($meeting['notes'])): ?>
        <div class="mb-4">
            <div class="section-header">2. Site Observations & Specific Concerns Raised</div>
            <div class="p-3 bg-light rounded-3 border" style="font-size: 0.92rem; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($meeting['notes'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Worker Attendance Roster -->
        <div class="mb-4">
            <div class="section-header">3. Worker Sign-In Attendance Roster</div>
            <div class="table-responsive">
                <table class="table table-bordered roster-table mb-0">
                    <thead>
                        <tr>
                            <th width="8%" class="text-center">#</th>
                            <th width="35%">Worker Full Name</th>
                            <th width="27%">Trade / Role</th>
                            <th width="15%" class="text-center">Status</th>
                            <th width="15%">Signature</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($attendees)): ?>
                            <?php foreach ($attendees as $idx => $att): ?>
                            <tr>
                                <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($att['worker_name']) ?></td>
                                <td class="text-secondary"><?= htmlspecialchars($att['trade_role']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <?= htmlspecialchars($att['signature_status']) ?>
                                    </span>
                                </td>
                                <td class="signature-cell">
                                    <span style="font-style: italic;"><?= htmlspecialchars($att['worker_name']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Fallback empty signature rows for physical documentation -->
                            <?php for ($i = 1; $i <= max(5, intval($meeting['attendees_count'])); $i++): ?>
                            <tr>
                                <td class="text-center text-muted"><?= $i ?></td>
                                <td class="text-muted" style="height: 35px;"></td>
                                <td class="text-muted"></td>
                                <td class="text-center text-muted">Signed</td>
                                <td class="signature-cell"></td>
                            </tr>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Attached Physical Sign-in Sheet if any -->
        <?php if (!empty($meeting['signed_roster_file'])): ?>
        <div class="mb-4 no-print">
            <div class="section-header">Attached Physical Sign-in Roster</div>
            <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                <div>
                    <i class="bi bi-file-earmark-check text-success fs-4 me-2"></i>
                    <span class="fw-semibold">Physical Roster Scanned Document</span>
                </div>
                <a href="../uploads/safety/<?= htmlspecialchars($meeting['signed_roster_file']) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-box-arrow-up-right me-1"></i> View Attachment
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Signatures & HSE Sign-off -->
        <div class="row pt-3 mt-4">
            <div class="col-6">
                <div class="sig-box text-center">
                    <div><strong><?= htmlspecialchars($meeting['lead_name']) ?></strong></div>
                    <div class="text-muted small">Meeting Conductor / Site Foreman</div>
                    <div class="text-muted small mt-1">Date: <?= htmlspecialchars($meeting['meeting_date']) ?></div>
                </div>
            </div>
            <div class="col-6">
                <div class="sig-box text-center">
                    <div><strong>HSE Site Safety Officer</strong></div>
                    <div class="text-muted small">BuildNexus Compliance Audit Verification</div>
                    <div class="text-muted small mt-1">Date: <?= date('Y-m-d') ?></div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
