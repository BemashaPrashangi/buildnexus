<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("<div class='alert alert-danger m-3'>Invalid contact ID.</div>");
}

$stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
$stmt->execute([$id]);
$contact = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contact) {
    die("<div class='alert alert-danger m-3'>Contact not found.</div>");
}

$name = $contact['name'];
$role = $contact['role_type'];
$userId = $contact['linked_user_id'];
$company = $contact['company_name'];

$activities = [];

// 1. Check Purchase Orders (for Vendors or any company match)
try {
    $poStmt = $pdo->prepare("
        SELECT po.id, po.po_number, po.po_date, po.total_amount, po.status, p.project_name
        FROM purchase_orders po
        JOIN projects p ON po.project_id = p.id
        WHERE po.vendor_name LIKE ? OR po.vendor_name LIKE ?
        ORDER BY po.po_date DESC LIMIT 10
    ");
    $poStmt->execute(["%{$name}%", "%{$company}%"]);
    $pos = $poStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pos as $po) {
        $activities[] = [
            'type' => 'Purchase Order',
            'icon' => 'bi-cart-check text-warning',
            'code' => $po['po_number'],
            'title' => 'Purchase Order issued to ' . htmlspecialchars($contact['name']),
            'project' => $po['project_name'],
            'date' => $po['po_date'],
            'badge' => '$' . number_format($po['total_amount'], 2) . ' (' . ($po['status'] ?? 'Issued') . ')',
            'link' => 'procurement.php'
        ];
    }
} catch (Exception $e) {}

// 2. Check RFIs (for Architects, Engineers, Subcontractors)
try {
    $rfiStmt = $pdo->prepare("
        SELECT rfi.id, rfi.rfi_number, rfi.subject, rfi.due_date, rfi.status, p.project_name
        FROM project_rfis rfi
        JOIN projects p ON rfi.project_id = p.id
        WHERE rfi.assigned_to_name LIKE ?
        ORDER BY rfi.id DESC LIMIT 10
    ");
    $rfiStmt->execute(["%{$name}%"]);
    $rfis = $rfiStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rfis as $r) {
        $activities[] = [
            'type' => 'RFI Assignment',
            'icon' => 'bi-question-circle text-primary',
            'code' => $r['rfi_number'],
            'title' => $r['subject'],
            'project' => $r['project_name'],
            'date' => $r['due_date'],
            'badge' => $r['status'] ?? 'Open',
            'link' => 'rfi.php'
        ];
    }
} catch (Exception $e) {}

// 3. Check Submittals (for Reviewers / Architects)
try {
    $subStmt = $pdo->prepare("
        SELECT s.id, s.submittal_number, s.title, s.submittal_type, s.status, p.project_name, s.review_due_date
        FROM project_submittals s
        JOIN projects p ON s.project_id = p.id
        WHERE s.submitted_to_name LIKE ?
        ORDER BY s.id DESC LIMIT 10
    ");
    $subStmt->execute(["%{$name}%"]);
    $submittals = $subStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($submittals as $s) {
        $activities[] = [
            'type' => 'Submittal Review',
            'icon' => 'bi-file-earmark-check text-info',
            'code' => $s['submittal_number'],
            'title' => $s['title'],
            'project' => $s['project_name'],
            'date' => $s['review_due_date'] ?? date('Y-m-d'),
            'badge' => $s['status'] ?? 'Pending',
            'link' => 'submittals.php'
        ];
    }
} catch (Exception $e) {}

// 4. Check Safety Meetings (for Foremen)
if ($userId || in_array($role, ['Foreman', 'Project Manager'])) {
    try {
        $smStmt = $pdo->prepare("
            SELECT sm.id, sm.meeting_date, sm.attendees_count, p.project_name,
                   COALESCE(st.title, sm.custom_topic, 'Toolbox Talk') as topic_title
            FROM safety_meeting_logs sm
            JOIN projects p ON sm.project_id = p.id
            LEFT JOIN safety_topics st ON sm.topic_id = st.id
            LEFT JOIN users u ON sm.foreman_id = u.id
            WHERE sm.foreman_id = ? OR u.full_name LIKE ?
            ORDER BY sm.meeting_date DESC LIMIT 10
        ");
        $smStmt->execute([$userId ?: 0, "%{$name}%"]);
        $safety = $smStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($safety as $sm) {
            $activities[] = [
                'type' => 'Safety Toolbox Talk',
                'icon' => 'bi-cone-striped text-success',
                'code' => 'TBT-#' . $sm['id'],
                'title' => $sm['topic_title'],
                'project' => $sm['project_name'],
                'date' => $sm['meeting_date'],
                'badge' => $sm['attendees_count'] . ' Attendees',
                'link' => 'safety-meetings.php'
            ];
        }
    } catch (Exception $e) {}
}

// 5. Check Daily Site Logs
if ($userId || in_array($role, ['Foreman', 'Project Manager'])) {
    try {
        $dlStmt = $pdo->prepare("
            SELECT dr.id, dr.report_date, dr.crew_count, dr.status, p.project_name, dr.weather_condition
            FROM daily_reports dr
            JOIN projects p ON dr.project_id = p.id
            LEFT JOIN users u ON dr.foreman_id = u.id
            WHERE dr.foreman_id = ? OR u.full_name LIKE ?
            ORDER BY dr.report_date DESC LIMIT 10
        ");
        $dlStmt->execute([$userId ?: 0, "%{$name}%"]);
        $daily = $dlStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($daily as $dr) {
            $activities[] = [
                'type' => 'Daily Site Log',
                'icon' => 'bi-journal-check text-success',
                'code' => 'DLOG-#' . $dr['id'],
                'title' => 'Daily Report (' . ($dr['weather_condition'] ?? 'Site operations') . ')',
                'project' => $dr['project_name'],
                'date' => $dr['report_date'],
                'badge' => ($dr['crew_count'] ?? 0) . ' Crew',
                'link' => 'daily-logs.php'
            ];
        }
    } catch (Exception $e) {}
}

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
    <title>Contact Activity - <?= htmlspecialchars($contact['name']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2.5rem 0; }
        .view-card { max-width: 800px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="mb-3" style="max-width: 800px; margin: 0 auto;">
            <a href="directory.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        </div>
        <div class="view-card">
<?php endif; ?>

        <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-clock-history text-primary me-2"></i>Activity Audit: <?= htmlspecialchars($contact['name']) ?>
                </h5>
                <div class="text-muted small">
                    Role: <strong><?= htmlspecialchars($contact['role_type']) ?></strong>
                    <?php if (!empty($contact['company_name'])): ?>
                        &bull; <?= htmlspecialchars($contact['company_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge bg-light text-dark border px-3 py-2"><?= count($activities) ?> Record(s) Linked</span>
        </div>

        <?php if (!empty($activities)): ?>
            <div class="list-group rounded-3 shadow-none border">
                <?php foreach ($activities as $act): ?>
                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="fs-4"><i class="bi <?= $act['icon'] ?>"></i></div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-light text-secondary border font-monospace"><?= htmlspecialchars($act['code']) ?></span>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($act['title']) ?></span>
                                </div>
                                <div class="text-muted small mt-1">
                                    <i class="bi bi-building text-success"></i> <?= htmlspecialchars($act['project']) ?>
                                    &bull; <i class="bi bi-calendar"></i> <?= htmlspecialchars($act['date']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success-subtle text-success border border-success-subtle mb-1 d-block">
                                <?= htmlspecialchars($act['badge']) ?>
                            </span>
                            <a href="<?= $act['link'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.78rem;">
                                View <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-4 bg-light rounded-3 text-center border text-muted">
                <i class="bi bi-inbox fs-2 text-secondary opacity-50 d-block mb-2"></i>
                <h6 class="fw-semibold">No recent activity linked</h6>
                <p class="small text-muted mb-0">No purchase orders, RFIs, submittals, or logs are currently tied to this contact.</p>
            </div>
        <?php endif; ?>

<?php if (!$isAjax): ?>
        </div>
    </div>
</body>
</html>
<?php endif; ?>
