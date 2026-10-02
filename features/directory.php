<?php
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$success_msg = '';
$error_msg = '';

// Handle Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'contact_created') $success_msg = 'Contact and project assignments created successfully!';
    if ($_GET['msg'] === 'contact_archived') $success_msg = 'Contact has been successfully archived.';
    if ($_GET['msg'] === 'contact_deleted') $success_msg = 'Contact removed permanently.';
    if ($_GET['msg'] === 'message_sent') $success_msg = 'Direct notification message dispatched successfully!';
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid_id') $error_msg = 'Invalid contact record selected.';
    if ($_GET['error'] === 'action_failed') $error_msg = 'Failed to update contact record.';
}

// Handle Form Submission for New Contact
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_contact') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
    $company_name = !empty($_POST['company_name']) ? trim($_POST['company_name']) : null;
    $role_type = trim($_POST['role_type'] ?? 'Subcontractor');
    $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
    $assigned_projects = $_POST['assigned_projects'] ?? [];

    $allowedRoles = ['Project Manager', 'Foreman', 'Vendor', 'Client', 'Architect', 'Subcontractor', 'Engineer', 'Inspector'];

    if (empty($name) || empty($email)) {
        $error_msg = 'Full Name and Email Address are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please provide a valid email address.';
    } elseif (!in_array($role_type, $allowedRoles)) {
        $error_msg = 'Invalid role type specified.';
    } else {
        try {
            $pdo->beginTransaction();

            // Link existing user if email matches
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $uStmt->execute([$email]);
            $uRow = $uStmt->fetch();
            $linkedUserId = $uRow ? $uRow['id'] : null;

            // Default project from first assigned project
            $defaultProjId = !empty($assigned_projects[0]) && is_numeric($assigned_projects[0]) ? intval($assigned_projects[0]) : null;

            // Check if contact already exists
            $cCheck = $pdo->prepare("SELECT id FROM contacts WHERE email = ?");
            $cCheck->execute([$email]);
            $existingC = $cCheck->fetch();

            if ($existingC) {
                $contactId = $existingC['id'];
                $upd = $pdo->prepare("
                    UPDATE contacts 
                    SET name = ?, phone = ?, company_name = ?, role_type = ?, linked_user_id = ?, default_project_id = ?, address = ?, status = 'Active'
                    WHERE id = ?
                ");
                $upd->execute([$name, $phone, $company_name, $role_type, $linkedUserId, $defaultProjId, $address, $contactId]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO contacts (name, email, phone, company_name, role_type, linked_user_id, default_project_id, address, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
                ");
                $ins->execute([$name, $email, $phone, $company_name, $role_type, $linkedUserId, $defaultProjId, $address]);
                $contactId = $pdo->lastInsertId();
            }

            // Sync assigned projects
            if (!empty($assigned_projects) && is_array($assigned_projects)) {
                // Delete previous assignments if updating
                $delAss = $pdo->prepare("DELETE FROM contact_project_assignments WHERE contact_id = ?");
                $delAss->execute([$contactId]);

                $insAss = $pdo->prepare("INSERT INTO contact_project_assignments (contact_id, project_id, assignment_role) VALUES (?, ?, ?)");
                foreach ($assigned_projects as $pId) {
                    $pId = intval($pId);
                    if ($pId > 0) {
                        $insAss->execute([$contactId, $pId, $role_type]);
                    }
                }
            }

            $pdo->commit();
            header("Location: directory.php?msg=contact_created");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Quick Direct Message Action
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $recipient_id = intval($_POST['contact_id'] ?? 0);
    $msg_content = trim($_POST['message_content'] ?? '');
    if ($recipient_id > 0 && !empty($msg_content)) {
        header("Location: directory.php?msg=message_sent");
        exit();
    }
}

// Dynamic Avatar Initials Helper Function
function getAvatarInitials($name) {
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

// Fetch Filter & Form Lists
try {
    // 1. Projects for filter and creation modal
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Filter Parameters
    $filter_role = !empty($_GET['role']) ? trim($_GET['role']) : '';
    $filter_project = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    $filter_search = !empty($_GET['search']) ? trim($_GET['search']) : '';

    // 3. Contacts Query with Project Association Aggregation
    $query = "
        SELECT c.*,
               COUNT(cpa.project_id) AS assigned_project_count,
               MIN(p.project_name) AS single_project_name,
               MIN(p.id) AS single_project_id,
               GROUP_CONCAT(p.project_name SEPARATOR ', ') AS all_project_names,
               dp.project_name AS default_project_name,
               dp.id AS default_project_id
        FROM contacts c
        LEFT JOIN contact_project_assignments cpa ON c.id = cpa.contact_id
        LEFT JOIN projects p ON cpa.project_id = p.id
        LEFT JOIN projects dp ON c.default_project_id = dp.id
        WHERE c.status = 'Active'
    ";
    $params = [];

    if (!empty($filter_role)) {
        $query .= " AND c.role_type = ?";
        $params[] = $filter_role;
    }
    if ($filter_project > 0) {
        $query .= " AND (cpa.project_id = ? OR c.default_project_id = ?)";
        $params[] = $filter_project;
        $params[] = $filter_project;
    }
    if (!empty($filter_search)) {
        $query .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.company_name LIKE ? OR c.phone LIKE ?)";
        $term = "%{$filter_search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $query .= " GROUP BY c.id ORDER BY c.id ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directory - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (BuildNexus Design Language) --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        
        /* Filter Header UI */
        .search-wrapper { position: relative; max-width: 480px; flex-grow: 1; }
        .nexus-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.95rem; }
        .nexus-input { width: 100%; padding: 8px 14px 8px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff; transition: border-color 0.15s ease; }
        .nexus-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }

        .nexus-select { padding: 8px 36px 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.88rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 200px; cursor: pointer; }
        .nexus-select:focus { outline: none; border-color: #22c55e; }

        /* Contact Visuals */
        .avatar-circle { width: 40px; height: 40px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.82rem; font-weight: 700; color: #475569; margin-right: 15px; flex-shrink: 0; }
        
        .project-link { color: #16a34a; text-decoration: none; font-weight: 500; transition: color 0.15s ease; }
        .project-link:hover { color: #15803d; text-decoration: underline; }
        .project-multiple { color: #16a34a; font-weight: 700; cursor: pointer; text-decoration: none; }
        .project-multiple:hover { color: #15803d; text-decoration: underline; }

        /* Role Badges Exact Specifications */
        .role-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .role-project-manager { background: #f3e8ff; color: #7e22ce; }
        .role-foreman { background: #fef9c3; color: #a16207; }
        .role-vendor { background: #ffedd5; color: #c2410c; }
        .role-client { background: #ccfbf1; color: #0f766e; }
        .role-architect { background: #eff6ff; color: #2563eb; }
        .role-subcontractor { background: #e0e7ff; color: #4338ca; }
        .role-engineer { background: #cffafe; color: #0891b2; }
        .role-inspector { background: #ffe4e6; color: #be123c; }

        /* Top Action Button */
        .btn-new-contact { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 18px; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: background-color 0.15s; }
        .btn-new-contact:hover { background-color: #16a34a; color: #fff; }

        /* Table Styling */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600; font-size: 0.875rem; padding: 1rem; }
        .table-custom tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tr:hover td { background-color: #fafafa; }

        .dropdown-menu { border-radius: 10px; border: 1px solid #e2e8f0; padding: 6px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07); }
        .dropdown-item { border-radius: 6px; padding: 6px 12px; font-weight: 500; font-size: 0.85rem; }
        .dropdown-item:hover { background-color: #f1f5f9; }
        .dropdown-item.text-danger:hover { background-color: #fef2f2; }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0 text-dark">Directory</h1>
            <button class="btn-new-contact" data-bs-toggle="modal" data-bs-target="#newContactModal">
                <i class="bi bi-plus-lg"></i> New Contact
            </button>
        </div>

        <!-- Main Card -->
        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1 text-dark">All Contacts</h4>
                <p class="text-muted small mb-0">Manage all company and project contacts.</p>
            </div>

            <!-- Filter Controls -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="contactSearch" class="nexus-input" placeholder="Search contacts..." value="<?= htmlspecialchars($filter_search) ?>">
                </div>
                
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Role Dropdown Filter -->
                    <select class="nexus-select" id="roleFilter" onchange="applyFilters()">
                        <option value="">All Roles</option>
                        <option value="Project Manager" <?= ($filter_role === 'Project Manager') ? 'selected' : '' ?>>Project Manager</option>
                        <option value="Foreman" <?= ($filter_role === 'Foreman') ? 'selected' : '' ?>>Foreman</option>
                        <option value="Vendor" <?= ($filter_role === 'Vendor') ? 'selected' : '' ?>>Vendor</option>
                        <option value="Client" <?= ($filter_role === 'Client') ? 'selected' : '' ?>>Client</option>
                        <option value="Architect" <?= ($filter_role === 'Architect') ? 'selected' : '' ?>>Architect</option>
                        <option value="Subcontractor" <?= ($filter_role === 'Subcontractor') ? 'selected' : '' ?>>Subcontractor</option>
                        <option value="Engineer" <?= ($filter_role === 'Engineer') ? 'selected' : '' ?>>Engineer</option>
                        <option value="Inspector" <?= ($filter_role === 'Inspector') ? 'selected' : '' ?>>Inspector</option>
                    </select>

                    <!-- Project Dropdown Filter -->
                    <select class="nexus-select" id="projectFilter" onchange="applyFilters()">
                        <option value="">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= ($filter_project == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Reset Filters Button -->
                    <?php if (!empty($filter_role) || $filter_project > 0 || !empty($filter_search)): ?>
                        <a href="directory.php" class="btn btn-sm btn-outline-secondary border rounded-3 px-3 py-2" title="Clear Filters">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contacts Table -->
            <div class="table-responsive">
                <table class="table-custom" id="directoryTable">
                    <thead>
                        <tr>
                            <th width="35%">Name</th>
                            <th width="20%">Role</th>
                            <th width="35%">Project</th>
                            <th width="10%" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($contacts)): ?>
                            <?php foreach ($contacts as $c): 
                                $initials = getAvatarInitials($c['name']);
                                $roleClass = 'role-' . strtolower(str_replace(' ', '-', $c['role_type']));
                                $projectCount = intval($c['assigned_project_count']);
                                $searchData = strtolower(htmlspecialchars($c['name'] . ' ' . $c['email'] . ' ' . ($c['company_name'] ?? '') . ' ' . ($c['phone'] ?? '') . ' ' . ($c['all_project_names'] ?? '')));
                            ?>
                            <tr class="contact-row" 
                                data-role="<?= htmlspecialchars($c['role_type']) ?>" 
                                data-project-ids="<?= $c['single_project_id'] ? $c['single_project_id'] : '' ?>" 
                                data-search="<?= $searchData ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle"><?= $initials ?></div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($c['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-pill <?= $roleClass ?>">
                                        <?= htmlspecialchars($c['role_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($projectCount > 1): ?>
                                        <span class="project-multiple" onclick="openContactDetails(<?= $c['id'] ?>)" title="<?= htmlspecialchars($c['all_project_names']) ?>">
                                            Multiple
                                        </span>
                                    <?php elseif ($projectCount === 1): ?>
                                        <a href="project_overview.php?id=<?= $c['single_project_id'] ?>" class="project-link">
                                            <?= htmlspecialchars($c['single_project_name']) ?>
                                        </a>
                                    <?php elseif (!empty($c['default_project_name'])): ?>
                                        <a href="project_overview.php?id=<?= $c['default_project_id'] ?>" class="project-link">
                                            <?= htmlspecialchars($c['default_project_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn p-0 border-0 text-muted" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openContactDetails(<?= $c['id'] ?>)">
                                                    <i class="bi bi-person-lines-fill text-primary me-2"></i> View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openSendMessage(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>', '<?= addslashes($c['email']) ?>', '<?= $c['role_type'] ?>')">
                                                    <i class="bi bi-chat-dots text-success me-2"></i> Send Message
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openContactActivity(<?= $c['id'] ?>)">
                                                    <i class="bi bi-clock-history text-secondary me-2"></i> View Activity
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="confirmDelete(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>')">
                                                    <i class="bi bi-person-x me-2"></i> Remove Contact
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noRecordsRow">
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <h6 class="fw-semibold">No contacts found</h6>
                                    <p class="small text-muted mb-0">Try clearing filters or click "+ New Contact" to register a stakeholder.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: + NEW CONTACT ================= -->
    <div class="modal fade" id="newContactModal" tabindex="-1" aria-labelledby="newContactModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-success-subtle text-success rounded-3">
                            <i class="bi bi-person-plus-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark" id="newContactModalLabel">Register New Directory Contact</h5>
                            <div class="text-muted small">Add company stakeholders, vendors, architects, and project managers.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="directory.php" method="POST" id="createContactForm">
                    <input type="hidden" name="action" value="create_contact">

                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Full Name / Contact Person <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Ruwan Weerasinghe" required>
                            </div>

                            <!-- Email Address -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control rounded-3" placeholder="e.g. ruwan@architects.lk" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Phone Number -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Phone Number</label>
                                <input type="tel" name="phone" class="form-control rounded-3" placeholder="e.g. +94 77 123 4567">
                            </div>

                            <!-- Company Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Company / Organization</label>
                                <input type="text" name="company_name" class="form-control rounded-3" placeholder="e.g. Weerasinghe Architecture Group">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Role Type -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Role Type <span class="text-danger">*</span></label>
                                <select name="role_type" class="form-select rounded-3" required>
                                    <option value="" disabled selected>Select Role Category...</option>
                                    <option value="Project Manager">Project Manager</option>
                                    <option value="Foreman">Foreman</option>
                                    <option value="Vendor">Vendor</option>
                                    <option value="Client">Client</option>
                                    <option value="Architect">Architect</option>
                                    <option value="Subcontractor">Subcontractor</option>
                                    <option value="Engineer">Engineer</option>
                                    <option value="Inspector">Inspector</option>
                                </select>
                            </div>

                            <!-- Assigned Project(s) -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">Primary Assigned Project</label>
                                <select name="assigned_projects[]" class="form-select rounded-3">
                                    <option value="">None / Floating Stakeholder</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= $p['id'] ?>">
                                            <?= htmlspecialchars($p['project_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small">Additional project assignments can be tagged below.</div>
                            </div>
                        </div>

                        <!-- Multi-Project Assignment Checkboxes -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Associated Project Portfolio(s)</label>
                            <div class="p-3 bg-light rounded-3 border" style="max-height: 140px; overflow-y: auto;">
                                <div class="row g-2">
                                    <?php foreach ($projects as $p): ?>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="assigned_projects[]" value="<?= $p['id'] ?>" id="projCheck_<?= $p['id'] ?>">
                                                <label class="form-check-label small text-dark" for="projCheck_<?= $p['id'] ?>">
                                                    <?= htmlspecialchars($p['project_name']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Business / Mailing Address -->
                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted">Office / Mailing Address</label>
                            <textarea name="address" class="form-control rounded-3" rows="2" placeholder="Street address, building level, city, postal code..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer border-top pt-3">
                        <button type="button" class="btn btn-light border px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-new-contact px-4">
                            <i class="bi bi-check-lg me-1"></i> Register Contact
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: VIEW DETAILS ================= -->
    <div class="modal fade" id="viewContactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-badge text-success me-2"></i>Contact Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="viewContactBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status"></div>
                        <div class="text-muted small mt-2">Loading contact details...</div>
                    </div>
                </div>
                <div class="modal-footer border-top pt-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: VIEW ACTIVITY ================= -->
    <div class="modal fade" id="viewActivityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Contact Activity Audit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="viewActivityBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="text-muted small mt-2">Auditing project records...</div>
                    </div>
                </div>
                <div class="modal-footer border-top pt-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: SEND MESSAGE ================= -->
    <div class="modal fade" id="sendMessageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-chat-dots-fill text-success me-2"></i>Send Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="directory.php" method="POST">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="contact_id" id="msgContactId" value="">
                    
                    <div class="modal-body p-4">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-3 border">
                            <div class="avatar-circle" id="msgAvatar">CN</div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0" id="msgContactName">Recipient Name</h6>
                                <div class="text-muted small" id="msgContactEmail">email@example.com</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Direct Message Content</label>
                            <textarea name="message_content" class="form-control rounded-3" rows="4" placeholder="Type notification or message here..." required></textarea>
                        </div>

                        <div class="p-3 bg-success-subtle text-success-emphasis rounded-3 border border-success-subtle small d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-chat-left-text me-1"></i> Alternatively, engage in live interactive channels:</span>
                            <div class="d-flex gap-1" id="chatChannelLinks">
                                <a href="team-chat.php" class="btn btn-xs btn-success py-1 px-2 text-white text-decoration-none" style="font-size: 0.75rem;">Team Chat</a>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top pt-2">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-3">
                            <i class="bi bi-send me-1"></i> Send Direct Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: REMOVE CONFIRMATION ================= -->
    <div class="modal fade" id="deleteContactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Remove Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">Are you sure you want to remove <strong id="deleteContactName"></strong> from the active company directory?</p>
                    <div class="p-3 bg-light rounded-3 border small text-muted">
                        <i class="bi bi-info-circle me-1"></i> Archiving maintains past audit logs, purchase orders, and RFI history intact.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                    <form action="contact_delete.php" method="POST" class="d-inline" id="archiveForm">
                        <input type="hidden" name="id" id="archiveContactId" value="">
                        <input type="hidden" name="action" value="archive">
                        <button type="submit" class="btn btn-warning px-3 fw-semibold">Archive Contact</button>
                    </form>
                    <form action="contact_delete.php" method="POST" class="d-inline" id="deleteForm">
                        <input type="hidden" name="id" id="deleteContactId" value="">
                        <input type="hidden" name="action" value="delete_permanently">
                        <button type="submit" class="btn btn-outline-danger px-3">Delete Permanently</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- REAL-TIME LIVE FILTERING ---
        function applyFilters() {
            const searchVal = document.getElementById('contactSearch').value.toLowerCase().trim();
            const roleVal = document.getElementById('roleFilter').value;
            const projectVal = document.getElementById('projectFilter').value;

            const rows = document.querySelectorAll(".contact-row");
            let visibleCount = 0;

            rows.forEach(row => {
                const rowRole = row.getAttribute('data-role');
                const rowProjects = row.getAttribute('data-project-ids') || '';
                const rowSearch = row.getAttribute('data-search') || '';

                const matchesSearch = !searchVal || rowSearch.includes(searchVal);
                const matchesRole = !roleVal || rowRole === roleVal;
                // If project filtered, check if row project matches
                const matchesProject = !projectVal || rowProjects.split(',').includes(projectVal) || rowSearch.includes(document.getElementById('projectFilter').options[document.getElementById('projectFilter').selectedIndex].text.toLowerCase());

                if (matchesSearch && matchesRole && matchesProject) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });

            // Handle no records row
            let noRecords = document.getElementById('noRecordsRow');
            if (visibleCount === 0) {
                if (!noRecords) {
                    const tbody = document.querySelector("#directoryTable tbody");
                    const tr = document.createElement("tr");
                    tr.id = "noRecordsRow";
                    tr.innerHTML = `
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-semibold">No matching contacts</h6>
                            <p class="small text-muted mb-0">Try clearing filters or search terms.</p>
                        </td>
                    `;
                    tbody.appendChild(tr);
                } else {
                    noRecords.style.display = "";
                }
            } else if (noRecords) {
                noRecords.style.display = "none";
            }
        }

        document.getElementById('contactSearch').addEventListener('keyup', applyFilters);

        // --- VIEW DETAILS MODAL (AJAX) ---
        function openContactDetails(contactId) {
            const modalEl = document.getElementById('viewContactModal');
            const modal = new bootstrap.Modal(modalEl);
            const body = document.getElementById('viewContactBody');

            body.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <div class="text-muted small mt-2">Loading contact details...</div>
                </div>
            `;
            modal.show();

            fetch(`contact_view.php?id=${contactId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.text())
            .then(html => {
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = `<div class="alert alert-danger">Failed to load details: ${err.message}</div>`;
            });
        }

        // --- VIEW ACTIVITY AUDIT MODAL (AJAX) ---
        function openContactActivity(contactId) {
            const modalEl = document.getElementById('viewActivityModal');
            const modal = new bootstrap.Modal(modalEl);
            const body = document.getElementById('viewActivityBody');

            body.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-muted small mt-2">Auditing project records...</div>
                </div>
            `;
            modal.show();

            fetch(`contact_activity.php?id=${contactId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.text())
            .then(html => {
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = `<div class="alert alert-danger">Failed to load activity: ${err.message}</div>`;
            });
        }

        // --- SEND MESSAGE MODAL ---
        function openSendMessage(contactId, name, email, role) {
            document.getElementById('msgContactId').value = contactId;
            document.getElementById('msgContactName').innerText = name;
            document.getElementById('msgContactEmail').innerText = email;
            
            // Compute initials
            const words = name.trim().split(/[\s\._-]+/);
            let init = words[0] ? words[0].charAt(0).toUpperCase() : 'C';
            if (words.length > 1 && words[words.length - 1]) {
                init += words[words.length - 1].charAt(0).toUpperCase();
            }
            document.getElementById('msgAvatar').innerText = init;

            // Chat link options based on role
            const links = document.getElementById('chatChannelLinks');
            if (role === 'Client') {
                links.innerHTML = `<a href="client_chat.php" class="btn btn-xs btn-success py-1 px-2 text-white text-decoration-none" style="font-size: 0.75rem;">Client Chat</a>`;
            } else {
                links.innerHTML = `<a href="team-chat.php" class="btn btn-xs btn-success py-1 px-2 text-white text-decoration-none" style="font-size: 0.75rem;">Team Chat</a>`;
            }

            const modal = new bootstrap.Modal(document.getElementById('sendMessageModal'));
            modal.show();
        }

        // --- REMOVE CONFIRMATION ---
        function confirmDelete(id, name) {
            document.getElementById('archiveContactId').value = id;
            document.getElementById('deleteContactId').value = id;
            document.getElementById('deleteContactName').innerText = `"${name}"`;
            const modal = new bootstrap.Modal(document.getElementById('deleteContactModal'));
            modal.show();
        }
    </script>
</body>
</html>