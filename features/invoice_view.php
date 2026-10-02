<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

$invoice_id = intval($_GET['id'] ?? 0);
if ($invoice_id <= 0) {
    die("Invalid Invoice ID.");
}

$success_msg = '';
$error_msg = '';

// Handle quick Mark as Sent or Send Reminder
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'mark_sent') {
        $pdo->prepare("UPDATE invoices SET status = 'Sent' WHERE id = ? AND status = 'Draft'")->execute([$invoice_id]);
        $success_msg = "Invoice marked as Sent!";
    } elseif ($_GET['action'] === 'remind') {
        $success_msg = "Payment reminder dispatched to client.";
    }
}

// Handle Record Payment from view page
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'Bank Transfer';
    $transaction_reference = trim($_POST['transaction_reference'] ?? '');

    if ($amount_paid > 0) {
        try {
            $pdo->beginTransaction();

            $invStmt = $pdo->prepare("SELECT project_id, client_id, total_amount FROM invoices WHERE id = ? FOR UPDATE");
            $invStmt->execute([$invoice_id]);
            $invRow = $invStmt->fetch(PDO::FETCH_ASSOC);
            $total_amount = floatval($invRow['total_amount'] ?? 0);

            $txn_id = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $payStmt = $pdo->prepare("
                INSERT INTO payments (transaction_id, invoice_id, project_id, client_id, payment_date, payment_method, status, amount, fee_deducted, net_amount, transaction_reference)
                VALUES (?, ?, ?, ?, CURRENT_DATE, ?, 'Completed', ?, 0.00, ?, ?)
            ");
            $payStmt->execute([$txn_id, $invoice_id, $invRow['project_id'], $invRow['client_id'], $payment_method, $amount_paid, $amount_paid, $transaction_reference]);

            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'Completed'");
            $sumStmt->execute([$invoice_id]);
            $total_paid = floatval($sumStmt->fetchColumn());

            $new_status = ($total_paid >= $total_amount) ? 'Paid' : (($total_paid > 0) ? 'Partially Paid' : 'Draft');
            $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?")->execute([$new_status, $invoice_id]);

            $pdo->commit();
            $success_msg = "Payment of RS. " . number_format($amount_paid, 2) . " recorded successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Payment error: " . $e->getMessage();
        }
    }
}

try {
    // Fetch invoice details with project & client info
    $stmt = $pdo->prepare("
        SELECT i.*, 
               p.project_name, p.client_name AS proj_client_name,
               c.full_name AS client_name, c.company AS client_company, c.email AS client_email
        FROM invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        die("Invoice not found.");
    }

    // Fetch line items
    $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $itemStmt->execute([$invoice_id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch payment ledger
    $payStmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC");
    $payStmt->execute([$invoice_id]);
    $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

    $total_paid = 0;
    foreach ($payments as $p) {
        $total_paid += floatval($p['amount']);
    }
    $balance_due = max(0, floatval($invoice['total_amount']) - $total_paid);

    // Effective status
    $displayStatus = $invoice['status'];
    if ($invoice['status'] === 'Sent' && $invoice['due_date'] < date('Y-m-d')) {
        $displayStatus = 'Overdue';
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$clientDisplayName = !empty($invoice['client_name']) ? $invoice['client_name'] : (!empty($invoice['proj_client_name']) ? $invoice['proj_client_name'] : 'Valued Client');
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
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .invoice-wrapper { max-width: 900px; margin: 2rem auto; }
        
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .invoice-paper { padding: 3rem; }
        
        .pill { padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display: inline-block; }
        .pill-paid { background: #f0fdf4; color: #16a34a; }
        .pill-sent { background: #eff6ff; color: #2563eb; }
        .pill-overdue { background: #fef2f2; color: #dc2626; }
        .pill-draft { background: #f1f5f9; color: #64748b; }
        .pill-partially-paid { background: #fffbeb; color: #d97706; }
        .pill-void { background: #f8fafc; color: #94a3b8; text-decoration: line-through; }

        .meta-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 2px; }
        .meta-val { font-size: 0.95rem; font-weight: 500; }

        .table-invoice thead th { background-color: #f8fafc; color: #475569; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding: 12px; }
        .table-invoice tbody td { padding: 14px 12px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

        .btn-nexus { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; }
        .btn-nexus:hover { background-color: #16a34a; color: #fff; }

        /* Print Specific Styles */
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .invoice-wrapper { margin: 0; max-width: 100%; }
            .nexus-card { border: none !important; box-shadow: none !important; }
            .invoice-paper { padding: 0 !important; }
        }
    </style>
</head>
<body>

    <div class="invoice-wrapper">
        <!-- Action Toolbar (Hidden in Print) -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <a href="invoicing.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Invoices
            </a>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-dark" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print / Save as PDF
                </button>
                <?php if ($displayStatus !== 'Paid' && $displayStatus !== 'Void'): ?>
                    <button class="btn btn-sm btn-nexus" data-bs-toggle="modal" data-bs-target="#payModal">
                        <i class="bi bi-cash-coin me-1"></i> Record Payment
                    </button>
                <?php endif; ?>
                <?php if ($invoice['status'] === 'Draft'): ?>
                    <a href="invoice_view.php?id=<?= $invoice['id'] ?>&action=mark_sent" class="btn btn-sm btn-primary">
                        <i class="bi bi-send me-1"></i> Mark as Sent
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print mb-3" role="alert">
                <?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Printable Invoice Canvas -->
        <div class="nexus-card invoice-paper">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                <div>
                    <h2 class="fw-bold text-success mb-1">
                        <i class="bi bi-boxes me-1"></i> BUILDNEXUS
                    </h2>
                    <p class="text-muted small mb-0">Construction Management & Project Delivery Systems</p>
                    <p class="text-muted small mb-0">742 Evergreen Avenue, Colombo 03, Sri Lanka</p>
                </div>
                <div class="text-end">
                    <h1 class="h3 fw-bold text-dark mb-1">INVOICE</h1>
                    <div class="fw-bold fs-5 text-secondary"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                    <div class="mt-2">
                        <span class="pill pill-<?= strtolower(str_replace(' ', '-', $displayStatus)) ?>">
                            <?= htmlspecialchars($displayStatus) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bill To & Project Info -->
            <div class="row mb-4">
                <div class="col-sm-6">
                    <div class="meta-label">Billed To</div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($clientDisplayName) ?></h5>
                    <?php if (!empty($invoice['client_company'])): ?>
                        <div class="text-muted small"><?= htmlspecialchars($invoice['client_company']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['client_email'])): ?>
                        <div class="text-muted small"><?= htmlspecialchars($invoice['client_email']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <div class="meta-label">Project</div>
                    <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($invoice['project_name'] ?? 'General') ?></h5>
                    
                    <div class="d-flex justify-content-sm-end gap-4 mt-2">
                        <div>
                            <div class="meta-label">Issue Date</div>
                            <div class="meta-val"><?= htmlspecialchars($invoice['issue_date']) ?></div>
                        </div>
                        <div>
                            <div class="meta-label">Due Date</div>
                            <div class="meta-val fw-bold text-danger"><?= htmlspecialchars($invoice['due_date']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Itemized Table -->
            <div class="table-responsive mb-4">
                <table class="table table-invoice">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="50%">Description / Milestone</th>
                            <th width="15%" class="text-center">Qty</th>
                            <th width="15%" class="text-end">Unit Price</th>
                            <th width="15%" class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td>1</td>
                                <td>Construction Progress & Milestone Billing</td>
                                <td class="text-center">1.00</td>
                                <td class="text-end">RS. <?= number_format($invoice['amount'], 2) ?></td>
                                <td class="text-end fw-semibold">RS. <?= number_format($invoice['amount'], 2) ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $idx => $it): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($it['description']) ?></td>
                                    <td class="text-center"><?= number_format($it['quantity'], 2) ?></td>
                                    <td class="text-end">RS. <?= number_format($it['unit_price'], 2) ?></td>
                                    <td class="text-end fw-bold">RS. <?= number_format($it['line_total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals & Notes -->
            <div class="row pt-2 mb-4 border-bottom pb-4">
                <div class="col-md-7">
                    <?php if (!empty($invoice['notes'])): ?>
                        <div class="meta-label">Terms & Conditions</div>
                        <p class="small text-muted mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($invoice['notes']) ?></p>
                    <?php else: ?>
                        <div class="meta-label">Payment Instructions</div>
                        <p class="small text-muted mb-0">Please deposit funds referencing <?= htmlspecialchars($invoice['invoice_number']) ?> to:<br>
                        Account Name: BuildNexus Construction Ltd<br>
                        Bank: Commercial Bank PLC, Colombo Branch<br>
                        A/C: 1000-8899-2341
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fw-semibold">Subtotal:</span>
                        <span class="fw-bold">RS. <?= number_format($invoice['amount'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fw-semibold">Tax Amount:</span>
                        <span class="fw-bold">RS. <?= number_format($invoice['tax_amount'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between fs-5 fw-bold border-top pt-2">
                        <span>Total Amount:</span>
                        <span class="text-dark">RS. <?= number_format($invoice['total_amount'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-success fw-semibold mt-2">
                        <span>Paid to Date:</span>
                        <span>- RS. <?= number_format($total_paid, 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between fs-5 fw-bold text-danger border-top pt-2 mt-2">
                        <span>Balance Due:</span>
                        <span>RS. <?= number_format($balance_due, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment History Ledger -->
            <div class="mb-2">
                <h6 class="fw-bold text-dark mb-2">Payment Receipts & Audit Ledger</h6>
                <?php if (empty($payments)): ?>
                    <p class="small text-muted mb-0">No payments recorded against this invoice yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered small">
                            <thead class="bg-light">
                                <tr>
                                    <th>Receipt Date</th>
                                    <th>Payment Method</th>
                                    <th>Reference / Cheque #</th>
                                    <th class="text-end">Amount Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td><?= date('Y-m-d h:i A', strtotime($pay['payment_date'])) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($pay['payment_method']) ?></span></td>
                                        <td><?= htmlspecialchars($pay['transaction_reference'] ?: 'N/A') ?></td>
                                        <td class="text-end fw-bold text-success">RS. <?= number_format($pay['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center text-muted small mt-4 pt-3 border-top">
                Thank you for your business! This is a computer-generated invoice authenticated by BuildNexus.
            </div>
        </div>
    </div>

    <!-- Quick Modal for Record Payment -->
    <div class="modal fade no-print" id="payModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" method="POST">
                <input type="hidden" name="action" value="record_payment">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border small mb-3">
                        Remaining Balance: <strong class="text-danger">RS. <?= number_format($balance_due, 2) ?></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Amount Paid (RS)</label>
                        <input type="number" step="0.01" name="amount_paid" class="form-control fw-bold fs-5" value="<?= $balance_due ?>" max="<?= $balance_due ?>" required>
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
                        <label class="form-label small fw-semibold text-muted">Reference / Cheque #</label>
                        <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. TXN-109283">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus px-4">Save Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
