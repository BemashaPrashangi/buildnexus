<?php
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$success_msg = '';
$error_msg = '';

// Handle Create New Invoice
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_invoice') {
    $project_id = intval($_POST['project_id'] ?? 0);
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $issue_date = !empty($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+14 days'));
    $tax_rate = floatval($_POST['tax_rate'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    // Collect line items
    $items = [];
    $subtotal = 0;
    if (isset($_POST['item_desc']) && is_array($_POST['item_desc'])) {
        foreach ($_POST['item_desc'] as $idx => $desc) {
            $desc = trim($desc);
            if ($desc === '') continue;
            $qty = floatval($_POST['item_qty'][$idx] ?? 1);
            $price = floatval($_POST['item_price'][$idx] ?? 0);
            $line_total = $qty * $price;
            $subtotal += $line_total;
            $items[] = [
                'desc' => $desc,
                'qty' => $qty,
                'price' => $price,
                'total' => $line_total
            ];
        }
    }

    if ($project_id <= 0) {
        $error_msg = "Please select a valid project.";
    } elseif (empty($items)) {
        $error_msg = "Please add at least one line item with a description.";
    } else {
        try {
            $pdo->beginTransaction();

            // Auto-generate invoice number: INV-YYYY-XXX
            $year = date('Y', strtotime($issue_date));
            $seqStmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
            $seqStmt->execute(["INV-{$year}-%"]);
            $lastNo = $seqStmt->fetchColumn();

            if ($lastNo && preg_match("/INV-{$year}-(\d+)/", $lastNo, $matches)) {
                $nextSeq = intval($matches[1]) + 1;
            } else {
                // Check overall max to prevent collision
                $maxStmt = $pdo->query("SELECT MAX(id) FROM invoices");
                $nextSeq = ($maxStmt->fetchColumn() ?: 0) + 1;
            }
            $invoice_number = sprintf("INV-%s-%03d", $year, $nextSeq);

            $tax_amount = round($subtotal * ($tax_rate / 100), 2);
            $total_amount = round($subtotal + $tax_amount, 2);

            // If client_id is null, look up from clients table by project_id
            if (!$client_id) {
                $cStmt = $pdo->prepare("SELECT id FROM clients WHERE project_id = ? LIMIT 1");
                $cStmt->execute([$project_id]);
                $client_id = $cStmt->fetchColumn() ?: null;
            }

            $insStmt = $pdo->prepare("
                INSERT INTO invoices (invoice_number, project_id, client_id, amount, tax_amount, total_amount, issue_date, due_date, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)
            ");
            $insStmt->execute([
                $invoice_number,
                $project_id,
                $client_id,
                $subtotal,
                $tax_amount,
                $total_amount,
                $issue_date,
                $due_date,
                $notes
            ]);
            $invoice_id = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $itemStmt->execute([
                    $invoice_id,
                    $item['desc'],
                    $item['qty'],
                    $item['price'],
                    $item['total']
                ]);
            }

            $pdo->commit();
            $success_msg = "Invoice {$invoice_number} created successfully as Draft!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to create invoice: " . $e->getMessage();
        }
    }
}

// Handle Record Payment
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'Bank Transfer';
    $transaction_reference = trim($_POST['transaction_reference'] ?? '');

    if ($invoice_id <= 0 || $amount_paid <= 0) {
        $error_msg = "Invalid invoice or payment amount.";
    } else {
        try {
            $pdo->beginTransaction();

            $invStmt = $pdo->prepare("SELECT project_id, client_id, total_amount, status FROM invoices WHERE id = ? FOR UPDATE");
            $invStmt->execute([$invoice_id]);
            $inv = $invStmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) {
                throw new Exception("Invoice not found.");
            }

            $txn_id = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            // Insert into payments
            $payStmt = $pdo->prepare("
                INSERT INTO payments (transaction_id, invoice_id, project_id, client_id, payment_date, payment_method, status, amount, fee_deducted, net_amount, transaction_reference)
                VALUES (?, ?, ?, ?, CURRENT_DATE, ?, 'Completed', ?, 0.00, ?, ?)
            ");
            $payStmt->execute([$txn_id, $invoice_id, $inv['project_id'], $inv['client_id'], $payment_method, $amount_paid, $amount_paid, $transaction_reference]);

            // Calculate sum of completed payments for this invoice
            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'Completed'");
            $sumStmt->execute([$invoice_id]);
            $total_paid = floatval($sumStmt->fetchColumn());

            if ($total_paid >= $inv['total_amount']) {
                $new_status = 'Paid';
            } elseif ($total_paid > 0) {
                $new_status = 'Partially Paid';
            } else {
                $new_status = $inv['status'];
            }

            $updStmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
            $updStmt->execute([$new_status, $invoice_id]);

            $pdo->commit();
            $success_msg = "Payment of RS. " . number_format($amount_paid, 2) . " recorded successfully. Status updated to '{$new_status}'.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to record payment: " . $e->getMessage();
        }
    }
}

// Handle Status Actions (Mark as Sent, Void)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    try {
        if ($action === 'mark_sent') {
            $upd = $pdo->prepare("UPDATE invoices SET status = 'Sent' WHERE id = ? AND status IN ('Draft', 'Overdue')");
            $upd->execute([$id]);
            $success_msg = "Invoice marked as Sent to client.";
        } elseif ($action === 'void') {
            $upd = $pdo->prepare("UPDATE invoices SET status = 'Void' WHERE id = ?");
            $upd->execute([$id]);
            $success_msg = "Invoice marked as Void.";
        }
    } catch (Exception $e) {
        $error_msg = "Action failed: " . $e->getMessage();
    }
}

try {
    // Fetch projects for filters and new invoice form
    $projects = $pdo->query("
        SELECT p.id, p.project_name, 
               COALESCE(c.id, NULL) AS client_id,
               COALESCE(c.full_name, NULLIF(p.client_name, ''), 'Client') AS client_name
        FROM projects p 
        LEFT JOIN clients c ON c.project_id = p.id
        ORDER BY p.project_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch All Invoices with joined projects and clients + payments sum
    $invoicesStmt = $pdo->query("
        SELECT i.*, 
               p.project_name, 
               COALESCE(c.full_name, NULLIF(p.client_name, ''), 'Client') AS display_client,
               COALESCE(SUM(CASE WHEN pay.status = 'Completed' THEN pay.amount ELSE 0 END), 0) AS total_paid
        FROM invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        LEFT JOIN clients c ON i.client_id = c.id
        LEFT JOIN payments pay ON pay.invoice_id = i.id
        GROUP BY i.id
        ORDER BY i.created_at DESC
    ");
    $invoices = $invoicesStmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt_p_cnt = $pdo->query("SELECT COUNT(*) FROM invoice_payments WHERE status = 'Pending'");
    $pending_invoicing_slips = $stmt_p_cnt ? (int)$stmt_p_cnt->fetchColumn() : 0;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Status badge helper function
function getInvoiceStatusPill($status, $due_date) {
    $currentDate = date('Y-m-d');
    // If status == 'Sent' and due_date < CURRENT_DATE(), automatically display as Overdue
    $displayStatus = $status;
    if ($status === 'Sent' && $due_date < $currentDate) {
        $displayStatus = 'Overdue';
    }

    $class = 'pill-draft';
    switch ($displayStatus) {
        case 'Paid':
            $class = 'pill-paid';
            break;
        case 'Pending Verification':
            $class = 'pill-pending';
            break;
        case 'Sent':
            $class = 'pill-sent';
            break;
        case 'Overdue':
            $class = 'pill-overdue';
            break;
        case 'Draft':
            $class = 'pill-draft';
            break;
        case 'Partially Paid':
            $class = 'pill-partially-paid';
            break;
        case 'Void':
            $class = 'pill-void';
            break;
    }

    return [
        'status' => $displayStatus,
        'class' => $class
    ];
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
    <title>Global Invoicing - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (BuildNexus Standard) --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        
        /* Inputs & Filters */
        .search-wrapper { position: relative; max-width: 460px; flex-grow: 1; }
        .nexus-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .nexus-input { width: 100%; padding: 8px 12px 8px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff; }
        .nexus-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }
        .nexus-select { padding: 8px 35px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 210px; }

        /* Table Styling */
        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.85rem; padding: 1rem; }
        .table tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        
        .project-link { color: #10b981; text-decoration: none; font-weight: 500; }
        .project-link:hover { text-decoration: underline; color: #059669; }

        /* Status Pills */
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; text-align: center; }
        .pill-paid { background: #f0fdf4; color: #16a34a; }
        .pill-sent { background: #eff6ff; color: #2563eb; }
        .pill-overdue { background: #fef2f2; color: #dc2626; }
        .pill-draft { background: #f1f5f9; color: #64748b; }
        .pill-partially-paid { background: #fffbeb; color: #d97706; }
        .pill-void { background: #f8fafc; color: #94a3b8; text-decoration: line-through; }

        /* Buttons */
        .btn-new-invoice { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 16px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s; text-decoration: none; }
        .btn-new-invoice:hover { background-color: #16a34a; color: #fff; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
        .btn-nexus-primary { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; }
        .btn-nexus-primary:hover { background-color: #16a34a; color: #fff; }

        /* Modal Table */
        .item-row td { padding: 6px 4px; vertical-align: middle; }
        .item-input { border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px; font-size: 0.85rem; width: 100%; }
        .item-input:focus { outline: none; border-color: #22c55e; }
        .btn-remove-row { color: #dc2626; background: none; border: none; font-size: 1.1rem; padding: 0 4px; }
        .btn-remove-row:hover { color: #991b1b; }
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">Global Invoicing</h1>
            <button class="btn-new-invoice" data-bs-toggle="modal" data-bs-target="#newInvoiceModal">
                <i class="bi bi-plus-lg"></i> New Invoice
            </button>
        </div>

        <?php if ($pending_invoicing_slips > 0): ?>
            <div class="alert alert-warning border-0 shadow-sm d-flex justify-content-between align-items-center mb-4 p-3 rounded-3" style="background-color: #fef9c3; border-left: 4px solid #eab308 !important;">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-hourglass-split fs-3 text-warning"></i>
                    <div>
                        <strong class="text-dark">Action Required: <?= $pending_invoicing_slips ?> Pending Payment Verification(s)</strong>
                        <div class="small text-muted">Bank deposit slips have been submitted by clients and require admin verification.</div>
                    </div>
                </div>
                <a href="verify_payments.php" class="btn btn-dark btn-sm fw-bold px-3">Review & Verify Slips</a>
            </div>
        <?php endif; ?>

        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">All Invoices</h4>
                <p class="text-muted small">Manage and track all invoices across all projects.</p>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="invSearch" class="nexus-input" placeholder="Search invoices by number, project, or client...">
                </div>
                <div class="d-flex gap-2">
                    <select id="projectFilter" class="nexus-select" onchange="filterInvoices()">
                        <option value="All">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= htmlspecialchars($proj['project_name']) ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter" class="nexus-select" onchange="filterInvoices()">
                        <option value="All">All Statuses</option>
                        <option value="Paid">Paid</option>
                        <option value="Pending Verification">Pending Verification</option>
                        <option value="Sent">Sent</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Draft">Draft</option>
                        <option value="Partially Paid">Partially Paid</option>
                        <option value="Void">Void</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="invTable">
                    <thead>
                        <tr>
                            <th width="15%">Invoice #</th>
                            <th width="20%">Project</th>
                            <th width="15%">Client</th>
                            <th width="15%">Amount</th>
                            <th width="15%">Due Date</th>
                            <th width="15%">Status</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($invoices) === 0): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No invoices recorded in the system yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($invoices as $inv): 
                            $statusInfo = getInvoiceStatusPill($inv['status'], $inv['due_date']);
                            $displayStatus = $statusInfo['status'];
                            $pillClass = $statusInfo['class'];
                            $balance = max(0, $inv['total_amount'] - $inv['total_paid']);
                        ?>
                        <tr class="inv-row" 
                            data-project="<?= htmlspecialchars($inv['project_name'] ?? '') ?>" 
                            data-status="<?= htmlspecialchars($displayStatus) ?>">
                            <td class="fw-semibold">
                                <a href="invoice_view.php?id=<?= $inv['id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($inv['invoice_number']) ?>
                                </a>
                            </td>
                            <td><a href="invoice_view.php?id=<?= $inv['id'] ?>" class="project-link"><?= htmlspecialchars($inv['project_name'] ?? 'General') ?></a></td>
                            <td class="text-muted"><?= htmlspecialchars($inv['display_client']) ?></td>
                            <td class="fw-semibold">RS. <?= number_format($inv['total_amount'], 2) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($inv['due_date']) ?></td>
                            <td>
                                <span class="pill <?= $pillClass ?>">
                                    <?= htmlspecialchars($displayStatus) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <a class="dropdown-item small" href="invoice_view.php?id=<?= $inv['id'] ?>">
                                                <i class="bi bi-eye me-2 text-muted"></i> View Details
                                            </a>
                                        </li>
                                        <?php if ($inv['status'] === 'Draft'): ?>
                                            <li>
                                                <a class="dropdown-item small" href="invoicing.php?action=mark_sent&id=<?= $inv['id'] ?>">
                                                    <i class="bi bi-send me-2 text-muted"></i> Mark as Sent
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li>
                                                <a class="dropdown-item small" href="invoice_view.php?id=<?= $inv['id'] ?>&action=remind">
                                                    <i class="bi bi-bell me-2 text-muted"></i> Send Reminder
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($displayStatus !== 'Paid' && $displayStatus !== 'Void'): ?>
                                            <li>
                                                <a class="dropdown-item small text-success fw-semibold" href="#" 
                                                   onclick="openPaymentModal(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['invoice_number']) ?>', '<?= htmlspecialchars($inv['display_client']) ?>', <?= $inv['total_amount'] ?>, <?= $balance ?>)">
                                                    <i class="bi bi-cash-coin me-2"></i> Record Payment
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($displayStatus !== 'Void'): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item small text-danger" href="invoicing.php?action=void&id=<?= $inv['id'] ?>" 
                                                   onclick="return confirm('Are you sure you want to void invoice <?= htmlspecialchars($inv['invoice_number']) ?>?');">
                                                    <i class="bi bi-x-circle me-2"></i> Void Invoice
                                                </a>
                                            </li>
                                        <?php endif; ?>
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

    <!-- Modal for + New Invoice -->
    <div class="modal fade" id="newInvoiceModal" tabindex="-1" aria-labelledby="newInvoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST" id="newInvoiceForm">
                <input type="hidden" name="action" value="create_invoice">
                <input type="hidden" name="client_id" id="modalClientId" value="">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="newInvoiceModalLabel">Create New Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <!-- Project & Client Metadata -->
                    <div class="row g-3 mb-4 bg-light p-3 rounded-3 border">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted">Project</label>
                            <select name="project_id" id="modalProjectSelect" class="form-select bg-white" required onchange="handleProjectChange(this)">
                                <option value="">Select Project...</option>
                                <?php foreach($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>" 
                                            data-client="<?= htmlspecialchars($p['client_name']) ?>" 
                                            data-client-id="<?= $p['client_id'] ?? '' ?>">
                                        <?= htmlspecialchars($p['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Client Name</label>
                            <input type="text" id="modalClientName" class="form-control bg-white" placeholder="Auto-populated from project" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Tax Rate</label>
                            <select name="tax_rate" id="modalTaxRate" class="form-select bg-white" onchange="calculateInvoiceTotals()">
                                <option value="0">0% (Tax Exempt)</option>
                                <option value="7.25">7.25% (Standard)</option>
                                <option value="8.0" selected>8.0% (VAT)</option>
                                <option value="12.0">12.0%</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control bg-white" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Due Date</label>
                            <input type="date" name="due_date" class="form-control bg-white" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                        </div>
                    </div>

                    <!-- Line Items Table -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">Itemized Milestones & Scope</h6>
                            <button type="button" class="btn btn-sm btn-outline-success fw-semibold" onclick="addItemRow()">
                                <i class="bi bi-plus-circle me-1"></i> Add Row
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-2">
                                <thead class="text-muted small border-bottom">
                                    <tr>
                                        <th width="50%">Description / Milestone</th>
                                        <th width="15%">Qty</th>
                                        <th width="15%">Unit Price (RS)</th>
                                        <th width="15%">Line Total (RS)</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="invoiceItemsBody">
                                    <tr class="item-row">
                                        <td>
                                            <input type="text" name="item_desc[]" class="item-input" placeholder="e.g. Phase 1: Foundation Completion (100%)" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="item_qty[]" class="item-input row-qty text-center" value="1.00" required oninput="calculateInvoiceTotals()">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="item_price[]" class="item-input row-price text-end" value="0.00" required oninput="calculateInvoiceTotals()">
                                        </td>
                                        <td>
                                            <input type="text" class="item-input row-total text-end bg-light" readonly value="0.00">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Financial Summary & Notes -->
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold text-muted">Notes / Payment Terms</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Payment due within 14 days of invoice issue. Please transfer funds to Commercial Bank A/C #123456789."></textarea>
                        </div>
                        <div class="col-md-5">
                            <div class="bg-light p-3 rounded-3 border">
                                <div class="d-flex justify-content-between mb-2 small">
                                    <span class="text-muted fw-semibold">Subtotal:</span>
                                    <span class="fw-bold" id="displaySubtotal">RS. 0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 small">
                                    <span class="text-muted fw-semibold" id="displayTaxLabel">Tax (8%):</span>
                                    <span class="fw-bold" id="displayTax">RS. 0.00</span>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between fs-5 fw-bold">
                                    <span>Grand Total:</span>
                                    <span class="text-success" id="displayGrandTotal">RS. 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary px-4">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal for Record Payment -->
    <div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="invoice_id" id="payModalInvoiceId">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="recordPaymentModalLabel">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Invoice #:</span>
                            <span class="fw-bold" id="payModalInvNumber">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Client:</span>
                            <span class="fw-semibold" id="payModalClient">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Total Due:</span>
                            <span class="fw-semibold" id="payModalTotal">-</span>
                        </div>
                        <div class="d-flex justify-content-between small text-danger fw-bold border-top pt-1 mt-1">
                            <span>Remaining Balance:</span>
                            <span id="payModalBalance">-</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Amount Paid (RS)</label>
                        <input type="number" step="0.01" name="amount_paid" id="payModalAmount" class="form-control fs-5 fw-bold" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Transaction / Cheque Reference (Optional)</label>
                        <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. TXN-894829 or CHQ-0021">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary px-4">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time table search & filter
        function filterInvoices() {
            let filterText = document.getElementById('invSearch').value.toLowerCase();
            let selectedProject = document.getElementById('projectFilter').value;
            let selectedStatus = document.getElementById('statusFilter').value;

            let rows = document.querySelectorAll(".inv-row");
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                let project = row.getAttribute('data-project');
                let status = row.getAttribute('data-status');

                let matchesText = text.includes(filterText);
                let matchesProject = (selectedProject === 'All' || project === selectedProject);
                let matchesStatus = (selectedStatus === 'All' || status === selectedStatus);

                if (matchesText && matchesProject && matchesStatus) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        document.getElementById('invSearch').addEventListener('keyup', filterInvoices);

        // Project selection auto-fills client name and client ID
        function handleProjectChange(select) {
            let selectedOption = select.options[select.selectedIndex];
            let clientName = selectedOption.getAttribute('data-client') || '';
            let clientId = selectedOption.getAttribute('data-client-id') || '';

            document.getElementById('modalClientName').value = clientName;
            document.getElementById('modalClientId').value = clientId;
        }

        // Dynamic Line Item Rows Calculation
        function calculateInvoiceTotals() {
            let subtotal = 0;
            let rows = document.querySelectorAll('#invoiceItemsBody tr');

            rows.forEach(row => {
                let qty = parseFloat(row.querySelector('.row-qty').value) || 0;
                let price = parseFloat(row.querySelector('.row-price').value) || 0;
                let lineTotal = qty * price;
                row.querySelector('.row-total').value = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                subtotal += lineTotal;
            });

            let taxRate = parseFloat(document.getElementById('modalTaxRate').value) || 0;
            let taxAmount = subtotal * (taxRate / 100);
            let grandTotal = subtotal + taxAmount;

            document.getElementById('displayTaxLabel').innerText = `Tax (${taxRate}%):`;
            document.getElementById('displaySubtotal').innerText = 'RS. ' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('displayTax').innerText = 'RS. ' + taxAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('displayGrandTotal').innerText = 'RS. ' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function addItemRow() {
            let tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
                <td>
                    <input type="text" name="item_desc[]" class="item-input" placeholder="e.g. Scope milestone description" required>
                </td>
                <td>
                    <input type="number" step="0.01" name="item_qty[]" class="item-input row-qty text-center" value="1.00" required oninput="calculateInvoiceTotals()">
                </td>
                <td>
                    <input type="number" step="0.01" name="item_price[]" class="item-input row-price text-end" value="0.00" required oninput="calculateInvoiceTotals()">
                </td>
                <td>
                    <input type="text" class="item-input row-total text-end bg-light" readonly value="0.00">
                </td>
                <td class="text-center">
                    <button type="button" class="btn-remove-row" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                </td>
            `;
            document.getElementById('invoiceItemsBody').appendChild(tr);
        }

        function removeRow(btn) {
            let row = btn.closest('tr');
            let tbody = document.getElementById('invoiceItemsBody');
            if (tbody.querySelectorAll('tr').length > 1) {
                row.remove();
                calculateInvoiceTotals();
            } else {
                alert("An invoice must contain at least one line item.");
            }
        }

        // Open Record Payment Modal
        function openPaymentModal(id, invoiceNumber, clientName, totalAmount, balance) {
            document.getElementById('payModalInvoiceId').value = id;
            document.getElementById('payModalInvNumber').innerText = invoiceNumber;
            document.getElementById('payModalClient').innerText = clientName;
            document.getElementById('payModalTotal').innerText = 'RS. ' + totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('payModalBalance').innerText = 'RS. ' + balance.toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('payModalAmount').value = balance.toFixed(2);
            document.getElementById('payModalAmount').max = balance.toFixed(2);

            let paymentModal = new bootstrap.Modal(document.getElementById('recordPaymentModal'));
            paymentModal.show();
        }
    </script>
</body>
</html>