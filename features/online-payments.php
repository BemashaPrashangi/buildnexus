<?php
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/auth_check.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Client') {
    header("Location: pay_online.php");
    exit();
}
checkRole(['Admin', 'Project Manager']);

$stmt_slip_cnt = $pdo->query("SELECT COUNT(*) FROM invoice_payments WHERE status = 'Pending'");
$pending_slips_cnt = $stmt_slip_cnt ? (int)$stmt_slip_cnt->fetchColumn() : 0;

$success_msg = '';
$error_msg = '';

// Helper to recalculate invoice status based on payments
function recalculateInvoiceStatus($pdo, $invoice_id) {
    // Get invoice total
    $invStmt = $pdo->prepare("SELECT total_amount, status FROM invoices WHERE id = ?");
    $invStmt->execute([$invoice_id]);
    $inv = $invStmt->fetch(PDO::FETCH_ASSOC);
    if (!$inv) return;

    $total_amount = floatval($inv['total_amount']);

    // Sum all 'Completed' payments for this invoice
    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'Completed'");
    $sumStmt->execute([$invoice_id]);
    $completed_sum = floatval($sumStmt->fetchColumn());

    if ($completed_sum >= $total_amount && $total_amount > 0) {
        $new_status = 'Paid';
    } elseif ($completed_sum > 0) {
        $new_status = 'Partially Paid';
    } else {
        // Revert to Sent if it was previously marked Paid
        $new_status = ($inv['status'] === 'Draft') ? 'Draft' : 'Sent';
    }

    $updStmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $updStmt->execute([$new_status, $invoice_id]);
}

// 1. Handle Record Manual / Offline Payment
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_offline_payment') {
    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'Bank Transfer';
    $status = $_POST['status'] ?? 'Completed';
    $amount = floatval($_POST['amount'] ?? 0);
    $reference = trim($_POST['reference'] ?? '');

    if ($invoice_id <= 0 || $amount <= 0) {
        $error_msg = "Please select a valid invoice and enter a payment amount greater than zero.";
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch invoice metadata
            $stmt = $pdo->prepare("SELECT project_id, client_id, invoice_number FROM invoices WHERE id = ?");
            $stmt->execute([$invoice_id]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) {
                throw new Exception("Selected invoice not found.");
            }

            // Fee calculation (0 for offline methods, 1.5% for card)
            $fee_deducted = ($payment_method === 'Credit Card') ? round($amount * 0.015, 2) : 0.00;
            $net_amount = $amount - $fee_deducted;

            // Generate unique transaction ID
            $txn_id = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $gw_response = json_encode([
                'type' => 'manual_entry',
                'recorded_by' => $_SESSION['user_id'] ?? 1,
                'reference' => $reference,
                'gateway' => 'Manual Reconciliation Engine',
                'timestamp' => date('c')
            ]);

            $insStmt = $pdo->prepare("
                INSERT INTO payments 
                (transaction_id, invoice_id, project_id, client_id, payment_date, payment_method, status, amount, fee_deducted, net_amount, gateway_response)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insStmt->execute([
                $txn_id, $invoice_id, $inv['project_id'], $inv['client_id'],
                $payment_date, $payment_method, $status, $amount, $fee_deducted, $net_amount, $gw_response
            ]);

            // Synchronize parent invoice status
            recalculateInvoiceStatus($pdo, $invoice_id);

            $pdo->commit();
            $success_msg = "Transaction {$txn_id} recorded successfully with status '{$status}'.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error recording payment: " . $e->getMessage();
        }
    }
}

// 2. Handle Mark as Completed (Approve pending deposit)
if (isset($_GET['action']) && $_GET['action'] === 'mark_completed' && isset($_GET['id'])) {
    $payment_id = intval($_GET['id']);
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT invoice_id FROM payments WHERE id = ? FOR UPDATE");
        $stmt->execute([$payment_id]);
        $inv_id = $stmt->fetchColumn();

        if ($inv_id) {
            $upd = $pdo->prepare("UPDATE payments SET status = 'Completed' WHERE id = ?");
            $upd->execute([$payment_id]);

            recalculateInvoiceStatus($pdo, $inv_id);
            $pdo->commit();
            $success_msg = "Payment verified and marked as Completed. Invoice ledger updated.";
        } else {
            $pdo->rollBack();
            $error_msg = "Payment record not found.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Action failed: " . $e->getMessage();
    }
}

// 3. Handle Issue Refund
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_refund') {
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $refund_reason = trim($_POST['refund_reason'] ?? 'Customer refund requested');

    if ($payment_id > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
            $stmt->execute([$payment_id]);
            $origTx = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($origTx) {
                // Update status to Refunded
                $upd = $pdo->prepare("UPDATE payments SET status = 'Refunded' WHERE id = ?");
                $upd->execute([$payment_id]);

                // Recalculate invoice status (decreases completed payments)
                recalculateInvoiceStatus($pdo, $origTx['invoice_id']);

                $pdo->commit();
                $success_msg = "Payment {$origTx['transaction_id']} has been marked as Refunded. Invoice balance has been adjusted.";
            } else {
                $pdo->rollBack();
                $error_msg = "Original transaction not found.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Refund failed: " . $e->getMessage();
        }
    }
}

try {
    // Fetch all transactions from MySQL
    $payStmt = $pdo->query("
        SELECT p.*, 
               i.invoice_number, 
               COALESCE(pr.project_name, 'General') AS project_name, 
               COALESCE(c.full_name, NULLIF(pr.client_name, ''), 'Client') AS display_client
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN projects pr ON p.project_id = pr.id
        LEFT JOIN clients c ON p.client_id = c.id
        ORDER BY p.payment_date DESC, p.id DESC
    ");
    $transactions = $payStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch invoices for offline payment modal (showing unpaid & partially paid)
    $invOptions = $pdo->query("
        SELECT i.id, i.invoice_number, i.total_amount, i.status,
               COALESCE(pr.project_name, 'General') AS project_name,
               COALESCE(c.full_name, NULLIF(pr.client_name, ''), 'Client') AS client_name,
               COALESCE(SUM(p.amount), 0) AS total_paid
        FROM invoices i
        LEFT JOIN projects pr ON i.project_id = pr.id
        LEFT JOIN clients c ON i.client_id = c.id
        LEFT JOIN payments p ON p.invoice_id = i.id AND p.status = 'Completed'
        WHERE i.status != 'Void'
        GROUP BY i.id
        ORDER BY i.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function getPaymentPillClass($status) {
    switch ($status) {
        case 'Completed':
            return 'pill-completed';
        case 'Pending':
            return 'pill-pending';
        case 'Failed':
        case 'Refunded':
            return 'pill-failed';
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
    <title>Payment Management - BuildNexus</title>
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
        .nexus-select { padding: 8px 35px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 170px; }

        /* Table Styling */
        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.85rem; padding: 1rem; }
        .table tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        
        .inv-link { color: #10b981; text-decoration: none; font-weight: 500; }
        .inv-link:hover { text-decoration: underline; color: #059669; }

        /* Status Pills */
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; text-align: center; }
        .pill-completed { background: #f0fdf4; color: #16a34a; }
        .pill-pending { background: #fffbeb; color: #d97706; }
        .pill-failed { background: #fef2f2; color: #dc2626; }

        /* Buttons */
        .btn-export { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 16px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s; text-decoration: none; }
        .btn-export:hover { background-color: #16a34a; color: #fff; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
        .btn-offline-pay { background-color: #f8fafc; border: 1px solid #cbd5e1; color: #334155; font-weight: 600; border-radius: 8px; padding: 8px 16px; font-size: 0.875rem; display: flex; align-items: center; gap: 6px; }
        .btn-offline-pay:hover { background-color: #f1f5f9; color: #0f172a; }
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
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h3 fw-bold mb-0">Payment Management</h1>
            <div class="d-flex gap-2">
                <a href="verify_payments.php" class="btn btn-warning fw-semibold d-inline-flex align-items-center gap-1">
                    <i class="bi bi-shield-check"></i> Verify Slips
                    <?php if ($pending_slips_cnt > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?= $pending_slips_cnt ?></span>
                    <?php endif; ?>
                </a>
                <button class="btn-offline-pay" data-bs-toggle="modal" data-bs-target="#recordOfflineModal">
                    <i class="bi bi-plus-circle"></i> Record Payment
                </button>
                <a href="export_payments.php" id="btnExportReport" class="btn-export">
                    <i class="bi bi-download"></i> Export Report
                </a>
            </div>
        </div>

        <?php if ($pending_slips_cnt > 0): ?>
            <div class="alert alert-warning border-0 shadow-sm d-flex justify-content-between align-items-center mb-4 p-3 rounded-3" style="background-color: #fef9c3; border-left: 4px solid #eab308 !important;">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-hourglass-split fs-3 text-warning"></i>
                    <div>
                        <strong class="text-dark">Action Required: <?= $pending_slips_cnt ?> Pending Payment Verification(s)</strong>
                        <div class="small text-muted">Clients have submitted bank transfer slips awaiting your review and approval.</div>
                    </div>
                </div>
                <a href="verify_payments.php" class="btn btn-dark btn-sm fw-bold px-3">Review & Verify Now</a>
            </div>
        <?php endif; ?>

        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">All Transactions</h4>
                <p class="text-muted small">View and manage all payments received through the platform.</p>
            </div>

            <!-- Filter Controls -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="paySearch" class="nexus-input" placeholder="Search by Invoice, Client or Project...">
                </div>
                <div class="d-flex gap-2">
                    <select id="methodFilter" class="nexus-select" onchange="filterPayments()">
                        <option value="All">All Methods</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Direct Deposit">Direct Deposit</option>
                        <option value="Cash">Cash</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                    <select id="statusFilter" class="nexus-select" onchange="filterPayments()">
                        <option value="All">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Failed">Failed</option>
                        <option value="Refunded">Refunded</option>
                    </select>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="table-responsive">
                <table class="table" id="payTable">
                    <thead>
                        <tr>
                            <th width="15%">Invoice #</th>
                            <th width="20%">Project</th>
                            <th width="15%">Client</th>
                            <th width="12%">Date</th>
                            <th width="13%">Method</th>
                            <th width="12%">Status</th>
                            <th width="13%">Amount</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transactions) === 0): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No transactions recorded in the settlement ledger yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $tx): 
                            $pillClass = getPaymentPillClass($tx['status']);
                        ?>
                        <tr class="pay-row" 
                            data-method="<?= htmlspecialchars($tx['payment_method']) ?>" 
                            data-status="<?= htmlspecialchars($tx['status']) ?>"
                            data-search="<?= htmlspecialchars(strtolower(($tx['invoice_number'] ?? '') . ' ' . ($tx['project_name'] ?? '') . ' ' . ($tx['display_client'] ?? '') . ' ' . ($tx['transaction_id'] ?? ''))) ?>">
                            <td>
                                <a href="invoice_view.php?id=<?= $tx['invoice_id'] ?>" class="inv-link">
                                    <?= htmlspecialchars($tx['invoice_number'] ?? 'N/A') ?>
                                </a>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($tx['project_name']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($tx['display_client']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($tx['payment_date']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($tx['payment_method']) ?></td>
                            <td>
                                <span class="pill <?= $pillClass ?>">
                                    <?= htmlspecialchars($tx['status']) ?>
                                </span>
                            </td>
                            <td class="fw-semibold">RS. <?= number_format($tx['amount'], 2) ?></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <a class="dropdown-item small" href="transaction_details.php?id=<?= $tx['id'] ?>">
                                                <i class="bi bi-receipt me-2 text-muted"></i> View Transaction
                                            </a>
                                        </li>
                                        <?php if (!empty($tx['invoice_id'])): ?>
                                            <li>
                                                <a class="dropdown-item small" href="invoice_view.php?id=<?= $tx['invoice_id'] ?>">
                                                    <i class="bi bi-file-earmark-text me-2 text-muted"></i> View Invoice
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($tx['status'] === 'Pending'): ?>
                                            <li>
                                                <a class="dropdown-item small text-success fw-semibold" href="online-payments.php?action=mark_completed&id=<?= $tx['id'] ?>" onclick="return confirm('Confirm receipt of this payment?');">
                                                    <i class="bi bi-check-circle me-2"></i> Mark as Completed
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($tx['status'] === 'Completed'): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item small text-danger" href="#" 
                                                   onclick="openRefundModal(<?= $tx['id'] ?>, '<?= htmlspecialchars($tx['transaction_id']) ?>', '<?= htmlspecialchars($tx['invoice_number'] ?? '') ?>', <?= $tx['amount'] ?>)">
                                                    <i class="bi bi-arrow-counterclockwise me-2"></i> Issue Refund
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

    <!-- Modal: Record Manual / Offline Payment -->
    <div class="modal fade" id="recordOfflineModal" tabindex="-1" aria-labelledby="recordOfflineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST">
                <input type="hidden" name="action" value="record_offline_payment">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="recordOfflineModalLabel">Record Payment & Settlement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted">Select Target Invoice</label>
                            <select name="invoice_id" id="modalInvSelect" class="form-select bg-white" required onchange="handleInvSelection(this)">
                                <option value="">Choose an invoice...</option>
                                <?php foreach ($invOptions as $inv): 
                                    $bal = max(0, $inv['total_amount'] - $inv['total_paid']);
                                ?>
                                    <option value="<?= $inv['id'] ?>" 
                                            data-project="<?= htmlspecialchars($inv['project_name']) ?>"
                                            data-client="<?= htmlspecialchars($inv['client_name']) ?>"
                                            data-total="<?= $inv['total_amount'] ?>"
                                            data-balance="<?= $bal ?>">
                                        <?= htmlspecialchars($inv['invoice_number']) ?> — <?= htmlspecialchars($inv['client_name']) ?> (<?= htmlspecialchars($inv['project_name']) ?>) • Bal: RS. <?= number_format($bal, 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Project Name</label>
                            <input type="text" id="modalInvProject" class="form-control bg-light" readonly placeholder="Auto-populated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Client / Payer</label>
                            <input type="text" id="modalInvClient" class="form-control bg-light" readonly placeholder="Auto-populated">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control bg-white" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Payment Method</label>
                            <select name="payment_method" class="form-select bg-white" required>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Direct Deposit">Direct Deposit</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit Card">Credit Card (Terminal / POS)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Amount Received (RS)</label>
                            <input type="number" step="0.01" name="amount" id="modalPayAmount" class="form-control fs-5 fw-bold" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Initial Settlement Status</label>
                            <select name="status" class="form-select bg-white" required>
                                <option value="Completed" selected>Completed (Funds Cleared)</option>
                                <option value="Pending">Pending (Awaiting Deposit Verification)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold text-muted">Bank Slip / Cheque Reference (Optional)</label>
                            <input type="text" name="reference" class="form-control bg-white" placeholder="e.g. SLIP-891290 or CHQ-0045">
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-primary px-4">Save & Reconcile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Issue Refund -->
    <div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST">
                <input type="hidden" name="action" value="issue_refund">
                <input type="hidden" name="payment_id" id="refundPaymentId">

                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-danger" id="refundModalLabel"><i class="bi bi-arrow-counterclockwise me-1"></i> Issue Transaction Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning small mb-3">
                        Issuing a refund will reverse the settlement and automatically adjust the parent invoice balance back to Unpaid or Partially Paid.
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Transaction ID:</span>
                        <span class="fw-bold" id="refundTxnId">-</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Invoice #:</span>
                        <span class="fw-semibold" id="refundInvNum">-</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 small">
                        <span class="text-muted">Refund Amount:</span>
                        <span class="fw-bold text-danger fs-6" id="refundAmount">-</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Refund Reason / Audit Note</label>
                        <textarea name="refund_reason" class="form-control" rows="2" placeholder="e.g. Scope revision, duplicated transaction, client request" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Confirm Refund</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time table filter
        function filterPayments() {
            let filterText = document.getElementById('paySearch').value.toLowerCase().trim();
            let selectedMethod = document.getElementById('methodFilter').value;
            let selectedStatus = document.getElementById('statusFilter').value;

            // Update Export Report link with query parameters
            let exportUrl = new URL('export_payments.php', window.location.href);
            if (filterText) exportUrl.searchParams.set('search', filterText);
            if (selectedMethod !== 'All') exportUrl.searchParams.set('method', selectedMethod);
            if (selectedStatus !== 'All') exportUrl.searchParams.set('status', selectedStatus);
            document.getElementById('btnExportReport').setAttribute('href', exportUrl.toString());

            let rows = document.querySelectorAll(".pay-row");
            rows.forEach(row => {
                let searchData = row.getAttribute('data-search') || '';
                let method = row.getAttribute('data-method') || '';
                let status = row.getAttribute('data-status') || '';

                let matchesText = (filterText === '' || searchData.includes(filterText));
                let matchesMethod = (selectedMethod === 'All' || method === selectedMethod);
                let matchesStatus = (selectedStatus === 'All' || status === selectedStatus);

                if (matchesText && matchesMethod && matchesStatus) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        document.getElementById('paySearch').addEventListener('keyup', filterPayments);

        // Auto-populate invoice details in offline payment modal
        function handleInvSelection(select) {
            let opt = select.options[select.selectedIndex];
            let project = opt.getAttribute('data-project') || '';
            let client = opt.getAttribute('data-client') || '';
            let balance = parseFloat(opt.getAttribute('data-balance')) || 0;

            document.getElementById('modalInvProject').value = project;
            document.getElementById('modalInvClient').value = client;
            document.getElementById('modalPayAmount').value = balance > 0 ? balance.toFixed(2) : '';
        }

        // Open Refund Modal
        function openRefundModal(paymentId, txnId, invoiceNumber, amount) {
            document.getElementById('refundPaymentId').value = paymentId;
            document.getElementById('refundTxnId').innerText = txnId;
            document.getElementById('refundInvNum').innerText = invoiceNumber;
            document.getElementById('refundAmount').innerText = 'RS. ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2 });

            let refundModal = new bootstrap.Modal(document.getElementById('refundModal'));
            refundModal.show();
        }
    </script>
</body>
</html>