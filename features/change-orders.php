<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$success_msg = '';
$error_msg = '';

// 1. Handle Create New Change Order
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_co') {
    $project_id = intval($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cost_impact = floatval($_POST['cost_impact'] ?? 0);
    $time_impact_days = intval($_POST['time_impact_days'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['Draft', 'Pending']) ? $_POST['status'] : 'Pending';
    $user_id = $_SESSION['user_id'] ?? 1;

    if ($project_id <= 0 || empty($title)) {
        $error_msg = "Please select a project and provide a title for the Change Order.";
    } else {
        try {
            $pdo->beginTransaction();

            // Auto-generate sequential CO number (e.g. CO-7)
            $maxStmt = $pdo->query("SELECT MAX(id) FROM change_orders");
            $nextId = ($maxStmt->fetchColumn() ?: 0) + 1;
            $co_number = "CO-" . $nextId;

            // Look up client_id from project or clients table
            $cStmt = $pdo->prepare("SELECT id FROM clients WHERE project_id = ? LIMIT 1");
            $cStmt->execute([$project_id]);
            $client_id = $cStmt->fetchColumn() ?: null;

            $insStmt = $pdo->prepare("
                INSERT INTO change_orders 
                (co_number, project_id, client_id, title, description, cost_impact, time_impact_days, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insStmt->execute([
                $co_number, $project_id, $client_id, $title, $description, $cost_impact, $time_impact_days, $status, $user_id
            ]);

            $pdo->commit();
            $success_msg = "Change Order {$co_number} created successfully with status '{$status}'.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to create Change Order: " . $e->getMessage();
        }
    }
}

// 2. Handle Actions (Send for Approval, Convert to Invoice, Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    try {
        if ($action === 'send_approval') {
            $upd = $pdo->prepare("UPDATE change_orders SET status = 'Pending' WHERE id = ? AND status = 'Draft'");
            $upd->execute([$id]);
            $success_msg = "Change Order submitted to client for approval.";
        } elseif ($action === 'convert_invoice') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM change_orders WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $co = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$co) {
                throw new Exception("Change Order not found.");
            }
            if ($co['status'] !== 'Approved') {
                throw new Exception("Only approved Change Orders can be converted to invoices.");
            }

            // Generate next invoice number
            $year = date('Y');
            $seqStmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
            $seqStmt->execute(["INV-{$year}-%"]);
            $lastNo = $seqStmt->fetchColumn();
            if ($lastNo && preg_match("/INV-{$year}-(\d+)/", $lastNo, $matches)) {
                $nextSeq = intval($matches[1]) + 1;
            } else {
                $maxStmt = $pdo->query("SELECT MAX(id) FROM invoices");
                $nextSeq = ($maxStmt->fetchColumn() ?: 0) + 1;
            }
            $invoice_number = sprintf("INV-%s-%03d", $year, $nextSeq);

            $amount = floatval($co['cost_impact']);
            $due_date = date('Y-m-d', strtotime('+14 days'));
            $co_num = $co['co_number'] ?: "CO-{$co['id']}";
            $notes = "Generated from Change Order: " . $co_num;

            $insInv = $pdo->prepare("
                INSERT INTO invoices (invoice_number, project_id, client_id, amount, tax_amount, total_amount, issue_date, due_date, status, notes)
                VALUES (?, ?, ?, ?, 0.00, ?, CURRENT_DATE, ?, 'Sent', ?)
            ");
            $insInv->execute([$invoice_number, $co['project_id'], $co['client_id'], $amount, $amount, $due_date, $notes]);
            $inv_id = $pdo->lastInsertId();

            $insItem = $pdo->prepare("
                INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total)
                VALUES (?, ?, 1.00, ?, ?)
            ");
            $insItem->execute([$inv_id, "Variation: " . $co_num . " - " . $co['title'], $amount, $amount]);

            $pdo->commit();
            header("Location: invoicing.php?msg=co_converted&inv=" . urlencode($invoice_number));
            exit();

        } elseif ($action === 'delete') {
            $del = $pdo->prepare("DELETE FROM change_orders WHERE id = ?");
            $del->execute([$id]);
            $success_msg = "Change Order deleted successfully.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = "Action failed: " . $e->getMessage();
    }
}

try {
    // Fetch all change orders with robust joins
    $query = "
        SELECT co.*, 
               p.project_name, 
               COALESCE(c.full_name, NULLIF(p.client_name, ''), 'Client') AS client_name 
        FROM change_orders co 
        LEFT JOIN projects p ON co.project_id = p.id 
        LEFT JOIN clients c ON (co.client_id = c.id OR (co.client_id IS NULL AND p.id = c.project_id))
        ORDER BY co.id DESC
    ";
    $change_orders = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

    // Fetch projects for modal dropdown & filter
    $projects = $pdo->query("
        SELECT p.id, p.project_name, 
               COALESCE(c.full_name, NULLIF(p.client_name, ''), 'Client') AS client_name
        FROM projects p
        LEFT JOIN clients c ON c.project_id = p.id
        ORDER BY p.project_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function getCoPillClass($status) {
    switch ($status) {
        case 'Approved':
            return 'pill-approved';
        case 'Pending':
            return 'pill-pending';
        case 'Rejected':
            return 'pill-rejected';
        case 'Draft':
            return 'pill-draft';
        default:
            return 'pill-pending';
    }
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
    <title>Change Orders - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (BuildNexus Standard) --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        
        /* Filters */
        .search-wrapper { position: relative; max-width: 400px; flex-grow: 1; }
        .nexus-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .nexus-input { width: 100%; padding: 8px 12px 8px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff; }
        .nexus-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }
        .nexus-select { padding: 8px 35px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 170px; }

        /* Table Styling */
        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.85rem; padding: 1rem; }
        .table tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        
        .co-link { color: #0f172a; text-decoration: none; font-weight: 700; }
        .co-link:hover { color: #16a34a; }

        /* Status Pills matching exact screenshots */
        .pill { padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; text-align: center; }
        .pill-approved { background: #f0fdf4; color: #16a34a; }
        .pill-pending { background: #fef9c3; color: #a16207; }
        .pill-rejected { background: #fef2f2; color: #dc2626; }
        .pill-draft { background: #f1f5f9; color: #64748b; }

        /* Amount styling */
        .amount-text { color: #10b981; font-weight: 700; font-size: 0.92rem; }

        /* Buttons */
        .btn-new-co { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 18px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s; text-decoration: none; }
        .btn-new-co:hover { background-color: #16a34a; color: #fff; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
        .btn-nexus-primary { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; }
        .btn-nexus-primary:hover { background-color: #16a34a; color: #fff; }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Notification Alerts -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($success_msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($error_msg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">Change Orders</h1>
            <button class="btn-new-co" data-bs-toggle="modal" data-bs-target="#newCoModal">
                <i class="bi bi-plus-lg"></i> New Change Order
            </button>
        </div>

        <div class="nexus-card">
            <!-- Filter Bar -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="coSearch" class="nexus-input" placeholder="Search by CO ID, title, project, or client...">
                </div>
                <div class="d-flex gap-2">
                    <select id="projectFilter" class="nexus-select" onchange="filterChangeOrders()">
                        <option value="All">All Projects</option>
                        <?php foreach($projects as $p): ?>
                            <option value="<?= htmlspecialchars($p['project_name']) ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter" class="nexus-select" onchange="filterChangeOrders()">
                        <option value="All">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>
            </div>

            <!-- Table matching user layout -->
            <div class="table-responsive">
                <table class="table align-middle" id="coTable">
                    <thead>
                        <tr class="text-muted small">
                            <th width="15%">CO ID</th>
                            <th width="25%">Project</th>
                            <th width="20%">Client</th>
                            <th width="15%">Status</th>
                            <th width="18%">Amount</th>
                            <th width="7%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($change_orders) === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No Change Orders recorded yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($change_orders as $co): 
                            $coCode = !empty($co['co_number']) ? $co['co_number'] : ('CO-' . $co['id']);
                            $pillClass = getCoPillClass($co['status']);
                        ?>
                        <tr class="co-row" 
                            data-project="<?= htmlspecialchars($co['project_name'] ?? '') ?>" 
                            data-status="<?= htmlspecialchars($co['status']) ?>"
                            data-search="<?= htmlspecialchars(strtolower($coCode . ' ' . ($co['title'] ?? '') . ' ' . ($co['project_name'] ?? '') . ' ' . ($co['client_name'] ?? ''))) ?>">
                            <td>
                                <a href="co_view.php?id=<?= $co['id'] ?>" class="co-link">
                                    <?= htmlspecialchars($coCode) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($co['project_name'] ?? 'General') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($co['client_name']) ?></td>
                            <td>
                                <span class="pill <?= $pillClass ?>">
                                    <?= htmlspecialchars($co['status']) ?>
                                </span>
                            </td>
                            <td class="amount-text">Rs. <?= number_format($co['cost_impact']) ?></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border p-1 px-2" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <a class="dropdown-item small" href="co_view.php?id=<?= $co['id'] ?>">
                                                <i class="bi bi-eye me-2 text-muted"></i> View Details
                                            </a>
                                        </li>
                                        <?php if ($co['status'] === 'Draft'): ?>
                                            <li>
                                                <a class="dropdown-item small" href="change-orders.php?action=send_approval&id=<?= $co['id'] ?>">
                                                    <i class="bi bi-send me-2 text-muted"></i> Send for Approval
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($co['status'] === 'Approved'): ?>
                                            <li>
                                                <a class="dropdown-item small text-success fw-semibold" href="change-orders.php?action=convert_invoice&id=<?= $co['id'] ?>" onclick="return confirm('Generate an official invoice from this approved change order?');">
                                                    <i class="bi bi-receipt me-2"></i> Convert to Invoice
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item small text-danger" href="change-orders.php?action=delete&id=<?= $co['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete change order <?= htmlspecialchars($coCode) ?>?');">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: + New Change Order -->
    <div class="modal fade" id="newCoModal" tabindex="-1" aria-labelledby="newCoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST">
                <input type="hidden" name="action" value="create_co">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="newCoModalLabel">Create New Change Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold text-muted">Select Project</label>
                            <select name="project_id" class="form-select bg-white" required onchange="handleProjectChange(this)">
                                <option value="">Choose Project...</option>
                                <?php foreach($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-client="<?= htmlspecialchars($p['client_name']) ?>">
                                        <?= htmlspecialchars($p['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted">Client / Owner</label>
                            <input type="text" id="modalCoClient" class="form-control bg-light" readonly placeholder="Auto-populated">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Change Order Title / Subject</label>
                        <input type="text" name="title" class="form-control bg-white" placeholder="e.g. Master Bathroom Tile Upgrade, Electrical Conduit Addition" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Scope of Change & Justification</label>
                        <textarea name="description" class="form-control bg-white" rows="3" placeholder="Provide detailed specifications, rationale, or site condition requirements for this contract variation..." required></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Cost Impact (Rs.)</label>
                            <input type="number" step="0.01" name="cost_impact" class="form-control bg-white fw-bold fs-6" value="0.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Schedule Impact (Days)</label>
                            <input type="number" name="time_impact_days" class="form-control bg-white" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Initial Status</label>
                            <select name="status" class="form-select bg-white" required>
                                <option value="Pending" selected>Pending (Submit to Client)</option>
                                <option value="Draft">Draft (Internal Review)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary px-4">Create & Notify</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time table filter
        function filterChangeOrders() {
            let filterText = document.getElementById('coSearch').value.toLowerCase().trim();
            let selectedProject = document.getElementById('projectFilter').value;
            let selectedStatus = document.getElementById('statusFilter').value;

            let rows = document.querySelectorAll(".co-row");
            rows.forEach(row => {
                let searchData = row.getAttribute('data-search') || '';
                let project = row.getAttribute('data-project') || '';
                let status = row.getAttribute('data-status') || '';

                let matchesText = (filterText === '' || searchData.includes(filterText));
                let matchesProject = (selectedProject === 'All' || project === selectedProject);
                let matchesStatus = (selectedStatus === 'All' || status === selectedStatus);

                if (matchesText && matchesProject && matchesStatus) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        document.getElementById('coSearch').addEventListener('keyup', filterChangeOrders);

        // Project selection auto-fills client name
        function handleProjectChange(select) {
            let opt = select.options[select.selectedIndex];
            let client = opt.getAttribute('data-client') || '';
            document.getElementById('modalCoClient').value = client;
        }
    </script>
</body>
</html>