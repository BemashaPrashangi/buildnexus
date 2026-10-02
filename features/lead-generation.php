<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$message_map = [
    'lead_created'      => ['text' => 'New lead inquiry registered successfully!', 'type' => 'success'],
    'stage_updated'     => ['text' => 'Lead pipeline status updated.', 'type' => 'info'],
    'user_assigned'     => ['text' => 'Lead assigned to estimator successfully.', 'type' => 'primary'],
    'converted_success' => ['text' => 'Lead successfully converted to an Active Project & Client portfolio!', 'type' => 'success'],
    'lead_deleted'      => ['text' => 'Lead record removed from directory.', 'type' => 'warning'],
    'error'             => ['text' => 'An error occurred while processing the lead action.', 'type' => 'danger']
];

$display_alert = null;
if (isset($_GET['msg']) && array_key_exists($_GET['msg'], $message_map)) {
    $display_alert = $message_map[$_GET['msg']];
}

// Fetch Staff for Assignment
try {
    $users_stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('Project Manager', 'Foreman', 'Admin') ORDER BY full_name ASC");
    $all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Leads with Filters
    $filter_status = !empty($_GET['status']) ? trim($_GET['status']) : '';
    $filter_agent = isset($_GET['agent_id']) && is_numeric($_GET['agent_id']) ? intval($_GET['agent_id']) : -1;
    $filter_search = !empty($_GET['search']) ? trim($_GET['search']) : '';

    $query = "
        SELECT l.*, u.full_name as agent_name 
        FROM leads l 
        LEFT JOIN users u ON l.assigned_to = u.id 
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filter_status)) {
        $query .= " AND l.status = ?";
        $params[] = $filter_status;
    }
    if ($filter_agent >= 0) {
        if ($filter_agent === 0) {
            $query .= " AND l.assigned_to IS NULL";
        } else {
            $query .= " AND l.assigned_to = ?";
            $params[] = $filter_agent;
        }
    }
    if (!empty($filter_search)) {
        $query .= " AND (l.customer_name LIKE ? OR l.email LIKE ? OR l.project_type LIKE ? OR l.site_location LIKE ?)";
        $term = "%{$filter_search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $query .= " ORDER BY l.created_at DESC, l.id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Helper for Avatar Initials
function getLeadInitials($name) {
    $words = explode(" ", trim($name));
    $initials = "";
    foreach ($words as $w) { 
        if (!empty($w)) $initials .= strtoupper(mb_substr($w, 0, 1)); 
    }
    return mb_substr($initials, 0, 2) ?: 'L';
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
    <title>Leads - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (BuildNexus Design Language) --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03); }
        
        /* Table UI */
        .avatar-circle { width: 40px; height: 40px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-right: 14px; flex-shrink: 0; }
        
        /* Status Badges Exact Match */
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .status-converted { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .status-new { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .status-follow-up { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
        .status-quoted { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
        .status-lost { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

        /* Top Action Button */
        .btn-new-lead { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 18px; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: background-color 0.15s; }
        .btn-new-lead:hover { background-color: #16a34a; color: #fff; }

        /* Inputs & Filters */
        .search-wrapper { position: relative; max-width: 450px; flex-grow: 1; }
        .nexus-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.95rem; }
        .nexus-input { width: 100%; padding: 8px 14px 8px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff; transition: border-color 0.15s ease; }
        .nexus-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }

        .nexus-select { padding: 8px 36px 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.88rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 190px; cursor: pointer; }
        .nexus-select:focus { outline: none; border-color: #22c55e; }

        /* Table */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-custom thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600; font-size: 0.875rem; padding: 1rem 0.85rem; }
        .table-custom tbody td { padding: 1.25rem 0.85rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tr:hover td { background-color: #fafafa; }

        .dropdown-menu { border-radius: 10px; border: 1px solid #e2e8f0; padding: 6px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07); }
        .dropdown-item { border-radius: 6px; padding: 6px 12px; font-weight: 500; font-size: 0.85rem; }
        .dropdown-item:hover { background-color: #f1f5f9; }
        .dropdown-item.text-success:hover { background-color: #f0fdf4; color: #15803d !important; }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Alerts -->
        <?php if ($display_alert): ?>
            <div class="alert alert-<?= $display_alert['type'] ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?= $display_alert['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0 text-dark">Leads</h1>
            <button class="btn-new-lead" data-bs-toggle="modal" data-bs-target="#newLeadModal">
                <i class="bi bi-plus-lg"></i> New Lead
            </button>
        </div>

        <!-- Main Card -->
        <div class="nexus-card">
            <!-- Filter Controls -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="leadSearch" class="nexus-input" placeholder="Search by contact name, email, or project type..." value="<?= htmlspecialchars($filter_search) ?>">
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Status Filter -->
                    <select class="nexus-select" id="statusFilter" onchange="applyFilters()">
                        <option value="">All Statuses</option>
                        <option value="New" <?= ($filter_status === 'New') ? 'selected' : '' ?>>New</option>
                        <option value="Follow-up" <?= ($filter_status === 'Follow-up') ? 'selected' : '' ?>>Follow-up</option>
                        <option value="Quoted" <?= ($filter_status === 'Quoted') ? 'selected' : '' ?>>Quoted</option>
                        <option value="Converted" <?= ($filter_status === 'Converted') ? 'selected' : '' ?>>Converted</option>
                        <option value="Lost" <?= ($filter_status === 'Lost') ? 'selected' : '' ?>>Lost</option>
                    </select>

                    <!-- Assigned Staff Filter -->
                    <select class="nexus-select" id="agentFilter" onchange="applyFilters()">
                        <option value="-1">All Estimators</option>
                        <option value="0" <?= ($filter_agent === 0) ? 'selected' : '' ?>>Unassigned</option>
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($filter_agent === intval($u['id'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Reset Filters Button -->
                    <?php if (!empty($filter_status) || $filter_agent >= 0 || !empty($filter_search)): ?>
                        <a href="lead-generation.php" class="btn btn-sm btn-outline-secondary border rounded-3 px-3 py-2" title="Clear Filters">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leads Table -->
            <div class="table-responsive">
                <table class="table-custom" id="leadsTable">
                    <thead>
                        <tr>
                            <th width="28%">Contact</th>
                            <th width="20%">Project Type</th>
                            <th width="15%">Status</th>
                            <th width="17%">Assigned To</th> 
                            <th width="12%">Created</th>
                            <th width="8%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leads)): ?>
                            <?php foreach ($leads as $l): 
                                $statusSlug = strtolower(str_replace(' ', '-', $l['status']));
                                $searchString = strtolower(htmlspecialchars($l['customer_name'] . ' ' . $l['email'] . ' ' . $l['project_type'] . ' ' . ($l['agent_name'] ?? '') . ' ' . ($l['site_location'] ?? '')));
                            ?>
                                <tr class="lead-row" 
                                    data-status="<?= htmlspecialchars($l['status']) ?>"
                                    data-agent-id="<?= $l['assigned_to'] ? $l['assigned_to'] : '0' ?>"
                                    data-search="<?= $searchString ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle"><?= getLeadInitials($l['customer_name']) ?></div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($l['customer_name']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($l['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-dark fw-medium"><?= htmlspecialchars($l['project_type']) ?></td>
                                    <td>
                                        <span class="status-pill status-<?= $statusSlug ?>">
                                            <?= htmlspecialchars($l['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <i class="bi bi-person me-1"></i>
                                        <?= htmlspecialchars($l['agent_name'] ?? 'Unassigned') ?>
                                    </td>
                                    <td class="text-muted small"><?= date('M d, Y', strtotime($l['created_at'])) ?></td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn p-0 border-0 text-muted" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots fs-5"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openQuickReplyModal(<?= $l['id'] ?>, '<?= addslashes($l['customer_name']) ?>', '<?= addslashes($l['project_type']) ?>', '<?= addslashes($l['notes'] ?? '') ?>')">
                                                        <i class="bi bi-chat-text text-primary me-2"></i>Quick Reply
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openAssignModal(<?= $l['id'] ?>, '<?= addslashes($l['customer_name']) ?>', '<?= $l['assigned_to'] ?? '' ?>')">
                                                        <i class="bi bi-person-plus me-2"></i>Assign To
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openLeadDetails(<?= $l['id'] ?>)">
                                                        <i class="bi bi-eye me-2"></i>View Details
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <?php if ($l['status'] !== 'Converted'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-success fw-bold" href="javascript:void(0)" onclick="confirmConvert(<?= $l['id'] ?>, '<?= addslashes($l['customer_name']) ?>', '<?= addslashes($l['project_type']) ?>')">
                                                            <i class="bi bi-node-plus me-2"></i>Convert to Project
                                                        </a>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <span class="dropdown-item small text-muted disabled">
                                                            <i class="bi bi-check-circle-fill text-success me-2"></i>Already Converted
                                                        </span>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noRecordsRow">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <h6 class="fw-semibold">No pre-construction leads found</h6>
                                    <p class="small text-muted mb-0">Try clearing filters or click "+ New Lead" to record an inquiry.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: + NEW LEAD ================= -->
    <div class="modal fade" id="newLeadModal" tabindex="-1" aria-labelledby="newLeadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-success-subtle text-success rounded-3">
                            <i class="bi bi-person-plus-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark" id="newLeadModalLabel">Register New Pre-Construction Lead</h5>
                            <div class="text-muted small">Record prospective client inquiries, project types, and budget estimates.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="process_lead_actions.php?action=create" method="POST" id="createLeadForm">
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Customer Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control rounded-3" placeholder="e.g. Kasun Jayawardena" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control rounded-3" placeholder="e.g. kasun@example.com" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Phone Number</label>
                                <input type="tel" name="phone" class="form-control rounded-3" placeholder="e.g. +94 77 123 4567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Project Type <span class="text-danger">*</span></label>
                                <input type="text" name="project_type" class="form-control rounded-3" list="projectTypeSuggestions" placeholder="e.g. Residential Luxury Villa" required>
                                <datalist id="projectTypeSuggestions">
                                    <option value="Residential Luxury Villa">
                                    <option value="Commercial Office Complex">
                                    <option value="Office Renovation">
                                    <option value="Industrial Warehouse">
                                    <option value="Hospitality / Resort Expansion">
                                </datalist>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Estimated Budget (RS.)</label>
                                <input type="number" step="0.01" name="estimated_budget" class="form-control rounded-3" placeholder="e.g. 50000000.00" value="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Inquiry Channel / Source</label>
                                <select name="source" class="form-select rounded-3">
                                    <option value="Website">Website</option>
                                    <option value="Referral">Client Referral</option>
                                    <option value="LinkedIn">LinkedIn</option>
                                    <option value="Facebook">Facebook</option>
                                    <option value="Walk-in">Walk-in Inquiry</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-muted">Assigned Estimator</label>
                                <select name="assigned_to" class="form-select rounded-3">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($all_users as $user): ?>
                                        <option value="<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['role']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Site Location / Address</label>
                            <input type="text" name="site_location" class="form-control rounded-3" placeholder="e.g. Kandy Road, Katugastota">
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Customer Requirements & Inquiry Notes</label>
                            <textarea name="notes" class="form-control rounded-3" rows="3" placeholder="Detailed architectural preferences, target timeline, or special engineering scope..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer border-top pt-3">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-new-lead px-4">
                            <i class="bi bi-check-lg me-1"></i> Register Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: QUICK REPLY ================= -->
    <div class="modal fade" id="quickReplyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary-subtle text-primary rounded-3">
                            <i class="bi bi-chat-dots fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark">Pre-Construction Quick Reply Assistant</h5>
                            <div class="text-muted small">Draft professional client consultation proposals and inquiry replies.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Prospective Client:</span>
                            <strong class="text-dark ms-1" id="quickLeadName">Customer</strong>
                            &bull; <span class="text-muted small text-uppercase fw-semibold">Scope:</span>
                            <span class="text-success fw-medium ms-1" id="quickLeadProject">Project Type</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="generateQuickDraft()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Regenerate
                        </button>
                    </div>

                    <div id="quickLoading" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="text-muted small mt-2">Drafting pre-construction proposal response...</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Editable Response Draft</label>
                        <textarea id="quickGeneratedReply" class="form-control rounded-3 font-monospace" rows="10" style="font-size: 0.88rem; line-height: 1.6;"></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-success w-100 fw-semibold" onclick="sendToWhatsApp()">
                            <i class="bi bi-whatsapp me-2"></i> Open WhatsApp
                        </button>
                        <button class="btn btn-primary w-100 fw-semibold" onclick="sendToEmail()">
                            <i class="bi bi-envelope me-2"></i> Open Email Client
                        </button>
                        <button class="btn btn-light border w-50 fw-semibold" onclick="copyReply()">
                            <i class="bi bi-clipboard me-2"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: ASSIGN STAFF ================= -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus text-primary me-2"></i>Assign Lead to Estimator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_lead_actions.php?action=assign" method="POST" id="assignForm">
                    <input type="hidden" name="lead_id" id="assignLeadId" value="">
                    <div class="modal-body p-4">
                        <p class="mb-3 text-secondary small">Assign inquiry from <strong id="assignLeadCustomer" class="text-dark"></strong> to a project manager or sales estimator:</p>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Assigned Team Member</label>
                            <select name="user_id" id="assignUserId" class="form-select rounded-3" required>
                                <option value="">Select Staff...</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top pt-2">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: VIEW DETAILS ================= -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Lead Specifications & History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="detailsModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="text-muted small mt-2">Loading lead details...</div>
                    </div>
                </div>
                <div class="modal-footer border-top pt-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: CONVERT TO PROJECT CONFIRMATION ================= -->
    <div class="modal fade" id="convertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold text-success"><i class="bi bi-node-plus-fill me-2"></i>Convert Lead to Active Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">Are you ready to convert <strong id="convertCustomerName" class="text-dark"></strong> (<span id="convertProjectType" class="text-success fw-medium"></span>) into an Active Project?</p>
                    <div class="p-3 bg-light rounded-3 border small text-muted">
                        <i class="bi bi-check2-circle text-success me-1"></i> Automatically registers the client into the <strong>Clients</strong> portfolio.<br>
                        <i class="bi bi-check2-circle text-success me-1"></i> Creates a new <strong>Active Project</strong> with milestone tracking.<br>
                        <i class="bi bi-check2-circle text-success me-1"></i> Marks this lead status as <strong>Converted</strong>.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmConvertBtn" class="btn btn-success px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Convert Now
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- REAL-TIME LIVE FILTERING ---
        function applyFilters() {
            const searchVal = document.getElementById('leadSearch').value.toLowerCase().trim();
            const statusVal = document.getElementById('statusFilter').value;
            const agentVal = document.getElementById('agentFilter').value;

            const rows = document.querySelectorAll(".lead-row");
            let visibleCount = 0;

            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const rowAgent = row.getAttribute('data-agent-id');
                const rowSearch = row.getAttribute('data-search') || '';

                const matchesSearch = !searchVal || rowSearch.includes(searchVal);
                const matchesStatus = !statusVal || rowStatus === statusVal;
                const matchesAgent = (agentVal === "-1") || (rowAgent === agentVal);

                if (matchesSearch && matchesStatus && matchesAgent) {
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
                    const tbody = document.querySelector("#leadsTable tbody");
                    const tr = document.createElement("tr");
                    tr.id = "noRecordsRow";
                    tr.innerHTML = `
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-semibold">No matching leads found</h6>
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

        document.getElementById('leadSearch').addEventListener('keyup', applyFilters);

        // --- QUICK REPLY MODAL LOGIC ---
        let currentQuickLeadId = 0;
        let currentQuickLeadName = '';
        let currentQuickProject = '';
        let currentQuickNotes = '';
        let currentQuickEmail = '';

        function openQuickReplyModal(leadId, leadName, project, notes) {
            currentQuickLeadId = leadId;
            currentQuickLeadName = leadName;
            currentQuickProject = project;
            currentQuickNotes = notes;

            document.getElementById('quickLeadName').innerText = leadName;
            document.getElementById('quickLeadProject').innerText = project;
            
            const modal = new bootstrap.Modal(document.getElementById('quickReplyModal'));
            modal.show();

            generateQuickDraft();
        }

        function generateQuickDraft() {
            document.getElementById('quickLoading').classList.remove('d-none');
            document.getElementById('quickGeneratedReply').value = '';

            fetch('generate_reply.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `lead_id=${currentQuickLeadId}&customer_name=${encodeURIComponent(currentQuickLeadName)}&project_type=${encodeURIComponent(currentQuickProject)}&notes=${encodeURIComponent(currentQuickNotes)}`
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('quickLoading').classList.add('d-none');
                document.getElementById('quickGeneratedReply').value = data.suggestion;
                currentQuickEmail = data.email || '';
            })
            .catch(err => {
                document.getElementById('quickLoading').classList.add('d-none');
                document.getElementById('quickGeneratedReply').value = 'Failed to generate response: ' + err.message;
            });
        }

        function sendToWhatsApp() {
            const msg = document.getElementById("quickGeneratedReply").value;
            window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
        }

        function sendToEmail() {
            const msg = document.getElementById("quickGeneratedReply").value;
            const recipient = currentQuickEmail ? encodeURIComponent(currentQuickEmail) : '';
            window.location.href = `mailto:${recipient}?subject=${encodeURIComponent('Tharaka Construction Inquiry Follow-up - ' + currentQuickProject)}&body=${encodeURIComponent(msg)}`;
        }

        function copyReply() {
            const textarea = document.getElementById("quickGeneratedReply");
            textarea.select();
            navigator.clipboard.writeText(textarea.value);
            alert("Response copied to clipboard!");
        }

        // --- ASSIGN LEAD MODAL ---
        function openAssignModal(leadId, customerName, currentUserId) {
            document.getElementById('assignLeadId').value = leadId;
            document.getElementById('assignLeadCustomer').innerText = `"${customerName}"`;
            document.getElementById('assignUserId').value = currentUserId || '';
            const modal = new bootstrap.Modal(document.getElementById('assignModal'));
            modal.show();
        }

        // --- VIEW LEAD DETAILS MODAL (AJAX) ---
        function openLeadDetails(leadId) {
            const modalEl = document.getElementById('detailsModal');
            const modal = new bootstrap.Modal(modalEl);
            const body = document.getElementById('detailsModalBody');

            body.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-muted small mt-2">Loading lead specifications...</div>
                </div>
            `;
            modal.show();

            fetch(`lead_details.php?id=${leadId}`, {
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

        // --- CONVERT TO PROJECT CONFIRMATION ---
        function confirmConvert(leadId, customerName, projectType) {
            document.getElementById('convertCustomerName').innerText = customerName;
            document.getElementById('convertProjectType').innerText = projectType;
            document.getElementById('confirmConvertBtn').href = `convert_lead.php?id=${leadId}`;
            const modal = new bootstrap.Modal(document.getElementById('convertModal'));
            modal.show();
        }
    </script>
</body>
</html>