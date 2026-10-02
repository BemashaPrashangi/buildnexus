<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("<div class='alert alert-danger m-3'>Invalid contact ID.</div>");
}

$stmt = $pdo->prepare("
    SELECT c.*, 
           u.full_name AS linked_user_name, u.role AS linked_user_role,
           dp.project_name AS default_project_name
    FROM contacts c
    LEFT JOIN users u ON c.linked_user_id = u.id
    LEFT JOIN projects dp ON c.default_project_id = dp.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$contact = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contact) {
    die("<div class='alert alert-danger m-3'>Contact not found.</div>");
}

// Fetch all assigned projects
$pStmt = $pdo->prepare("
    SELECT p.id, p.project_name, p.project_code, p.location, p.status, cpa.assignment_role
    FROM contact_project_assignments cpa
    JOIN projects p ON cpa.project_id = p.id
    WHERE cpa.contact_id = ?
    ORDER BY p.project_name ASC
");
$pStmt->execute([$id]);
$assignedProjects = $pStmt->fetchAll(PDO::FETCH_ASSOC);

// Avatar initials
function getInitials($name) {
    $words = preg_split('/[\s\._-]+/', trim($name));
    $initials = '';
    if (!empty($words[0])) $initials .= strtoupper(mb_substr($words[0], 0, 1));
    if (count($words) > 1 && !empty($words[count($words) - 1])) {
        $initials .= strtoupper(mb_substr($words[count($words) - 1], 0, 1));
    } elseif (strlen($words[0]) >= 2) {
        $initials .= strtoupper(mb_substr($words[0], 1, 1));
    }
    return $initials ?: 'CN';
}

$initials = getInitials($contact['name']);

// Role badge classes
$roleStyles = [
    'Project Manager' => 'background: #f3e8ff; color: #7e22ce;',
    'Foreman' => 'background: #fef9c3; color: #a16207;',
    'Vendor' => 'background: #ffedd5; color: #c2410c;',
    'Client' => 'background: #ccfbf1; color: #0f766e;',
    'Architect' => 'background: #eff6ff; color: #2563eb;',
    'Subcontractor' => 'background: #e0e7ff; color: #4338ca;',
    'Engineer' => 'background: #cffafe; color: #0891b2;',
    'Inspector' => 'background: #ffe4e6; color: #be123c;'
];
$badgeStyle = $roleStyles[$contact['role_type']] ?? 'background: #f1f5f9; color: #475569;';

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
    <title><?= htmlspecialchars($contact['name']) ?> - Contact Details - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2.5rem 0; }
        .view-card { max-width: 700px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="mb-3 d-flex justify-content-between align-items-center" style="max-width: 700px; margin: 0 auto;">
            <a href="directory.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        </div>
        <div class="view-card">
<?php endif; ?>

        <!-- Contact Header -->
        <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
            <div style="width: 58px; height: 58px; border-radius: 50%; background: #f1f5f9; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700; color: #334155; flex-shrink: 0;">
                <?= $initials ?>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($contact['name']) ?></h4>
                    <span style="<?= $badgeStyle ?> padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block;">
                        <?= htmlspecialchars($contact['role_type']) ?>
                    </span>
                    <?php if ($contact['status'] === 'Archived'): ?>
                        <span class="badge bg-secondary">Archived</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small mt-1">
                    <?php if (!empty($contact['company_name'])): ?>
                        <i class="bi bi-buildings me-1 text-secondary"></i> <strong><?= htmlspecialchars($contact['company_name']) ?></strong>
                    <?php else: ?>
                        <i class="bi bi-person me-1 text-secondary"></i> Independent Stakeholder
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Contact Info Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Email Address</div>
                    <div class="fw-semibold text-dark text-truncate mt-1">
                        <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" class="text-decoration-none text-dark">
                            <i class="bi bi-envelope text-success me-1"></i> <?= htmlspecialchars($contact['email']) ?>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Phone Number</div>
                    <div class="fw-semibold text-dark mt-1">
                        <?php if (!empty($contact['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($contact['phone']) ?>" class="text-decoration-none text-dark">
                                <i class="bi bi-telephone text-primary me-1"></i> <?= htmlspecialchars($contact['phone']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">Not provided</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="text-muted small fw-semibold text-uppercase">Business / Mailing Address</div>
                    <div class="text-dark small mt-1">
                        <?= !empty($contact['address']) ? nl2br(htmlspecialchars($contact['address'])) : '<span class="text-muted">No physical address on file.</span>' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Projects -->
        <div class="mb-3">
            <h6 class="fw-bold text-uppercase text-secondary small mb-2">
                <i class="bi bi-folder-check text-success me-1"></i> Assigned Project Portfolios (<?= count($assignedProjects) ?>)
            </h6>
            <?php if (!empty($assignedProjects)): ?>
                <div class="list-group rounded-3 shadow-none border">
                    <?php foreach ($assignedProjects as $p): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <div>
                                <a href="project_overview.php?id=<?= $p['id'] ?>" class="fw-semibold text-success text-decoration-none">
                                    <?= htmlspecialchars($p['project_name']) ?>
                                </a>
                                <?php if (!empty($p['project_code'])): ?>
                                    <span class="text-muted small ms-1">(<?= htmlspecialchars($p['project_code']) ?>)</span>
                                <?php endif; ?>
                                <?php if (!empty($p['location'])): ?>
                                    <div class="text-muted small"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['location']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-light text-secondary border">
                                <?= htmlspecialchars($p['status'] ?? 'Active') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-3 bg-light rounded-3 text-muted text-center small border">
                    <i class="bi bi-info-circle me-1"></i> No projects assigned yet.
                </div>
            <?php endif; ?>
        </div>

        <!-- Linked System Login Account if any -->
        <?php if (!empty($contact['linked_user_name'])): ?>
            <div class="mt-3 p-3 bg-success-subtle text-success-emphasis rounded-3 border border-success-subtle d-flex align-items-center justify-content-between">
                <div>
                    <i class="bi bi-shield-lock-fill me-1"></i>
                    <strong>System User Account:</strong> <?= htmlspecialchars($contact['linked_user_name']) ?> (<?= htmlspecialchars($contact['linked_user_role']) ?>)
                </div>
                <span class="badge bg-success text-white">Active Login</span>
            </div>
        <?php endif; ?>

<?php if (!$isAjax): ?>
        </div>
    </div>
</body>
</html>
<?php endif; ?>
