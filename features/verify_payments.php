<?php
// features/verify_payments.php - Admin Payment Slip Verification Module
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';

// Only Admin and Project Manager can verify payments
checkRole(['Admin', 'Project Manager']);

$admin_id = $_SESSION['user_id'] ?? null;
$admin_name = $_SESSION['user_name'] ?? 'Admin';
$success_msg = '';
$error_msg = '';

// Handle Verification / Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $action = $_POST['action'];

    if ($payment_id <= 0) {
        $error_msg = "Invalid payment record selected.";
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch payment details along with invoice & project
            $stmt = $pdo->prepare("
                SELECT ip.*, i.id as invoice_id, i.invoice_number, i.project_id, i.client_id as inv_client_id, 
                       COALESCE(i.total_amount, i.amount) as invoice_amount, p.project_name
                FROM invoice_payments ip
                JOIN invoices i ON ip.invoice_id = i.id
                JOIN projects p ON i.project_id = p.id
                WHERE ip.id = ?
            ");
            $stmt->execute([$payment_id]);
            $pay = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pay) {
                throw new Exception("Payment record not found.");
            }

            if ($action === 'approve_payment') {
                // 1. Mark invoice_payments as Verified
                $updPay = $pdo->prepare("
                    UPDATE invoice_payments 
                    SET status = 'Verified', verified_by = ?, verified_at = NOW() 
                    WHERE id = ?
                ");
                $updPay->execute([$admin_id, $payment_id]);

                // 2. Mark invoice as Paid
                $updInv = $pdo->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ?");
                $updInv->execute([$pay['invoice_id']]);

                // 3. Sync with payments table for automated project financials update
                $txn_ref = !empty($pay['transaction_reference']) ? $pay['transaction_reference'] : ('TXN-BANK-' . $payment_id . '-' . date('Ymd'));
                $slip_url = $pay['receipt_slip_url'] ?? '';

                $stmt_sync = $pdo->prepare("
                    INSERT INTO payments (
                        transaction_id, invoice_id, project_id, client_id, 
                        amount, amount_paid, fee_deducted, net_amount, 
                        payment_method, status, receipt_url, transaction_reference, payment_date
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, ?, 0.00, ?, 
                        'Bank Transfer', 'Completed', ?, ?, NOW()
                    )
                ");
                $stmt_sync->execute([
                    $txn_ref, $pay['invoice_id'], $pay['project_id'], $pay['client_id'],
                    $pay['amount_paid'], $pay['amount_paid'], $pay['amount_paid'],
                    $slip_url, $txn_ref
                ]);

                $pdo->commit();
                $success_msg = "Payment of RS. " . number_format($pay['amount_paid'], 2) . " for Invoice " . htmlspecialchars($pay['invoice_number']) . " has been successfully verified! Project financials have been updated.";

            } elseif ($action === 'reject_payment') {
                $reason = trim($_POST['rejection_reason'] ?? 'Payment slip could not be verified.');

                // 1. Mark invoice_payments as Rejected
                $updPay = $pdo->prepare("
                    UPDATE invoice_payments 
                    SET status = 'Rejected', verified_by = ?, verified_at = NOW() 
                    WHERE id = ?
                ");
                $updPay->execute([$admin_id, $payment_id]);

                // 2. Revert invoice status to 'Sent' and record rejection reason in notes
                $updInv = $pdo->prepare("
                    UPDATE invoices 
                    SET status = 'Sent', 
                        notes = CONCAT(COALESCE(notes, ''), '\n[Payment Rejected on " . date('Y-m-d H:i') . ": ', ?, ']')
                    WHERE id = ?
                ");
                $updInv->execute([$reason, $pay['invoice_id']]);

                $pdo->commit();
                $success_msg = "Payment for Invoice " . htmlspecialchars($pay['invoice_number']) . " has been rejected. The invoice status has reverted to 'Sent'.";
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error processing verification: " . $e->getMessage();
        }
    }
}

// Fetch Pending Payments
try {
    $stmt_pending = $pdo->query("
        SELECT ip.*, i.invoice_number, COALESCE(i.total_amount, i.amount) as invoice_amount, 
               p.project_name, u.full_name as client_name, u.email as client_email
        FROM invoice_payments ip
        JOIN invoices i ON ip.invoice_id = i.id
        JOIN projects p ON i.project_id = p.id
        LEFT JOIN users u ON ip.client_id = u.id
        WHERE ip.status = 'Pending'
        ORDER BY ip.created_at ASC
    ");
    $pending_payments = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Recent History (Verified & Rejected)
    $stmt_history = $pdo->query("
        SELECT ip.*, i.invoice_number, COALESCE(i.total_amount, i.amount) as invoice_amount, 
               p.project_name, u.full_name as client_name, admin.full_name as verifier_name
        FROM invoice_payments ip
        JOIN invoices i ON ip.invoice_id = i.id
        JOIN projects p ON i.project_id = p.id
        LEFT JOIN users u ON ip.client_id = u.id
        LEFT JOIN users admin ON ip.verified_by = admin.id
        WHERE ip.status IN ('Verified', 'Rejected')
        ORDER BY ip.created_at DESC
        LIMIT 25
    ");
    $history_payments = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $total_pending_count = count($pending_payments);
    $total_pending_amount = array_sum(array_column($pending_payments, 'amount_paid'));

} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
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
    <title>Payment Verification - BuildNexus</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding-bottom: 3rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .table thead th { background: #f8fafc; color: #64748b; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .badge-pending { background-color: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
        .badge-verified { background-color: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .badge-rejected { background-color: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
        .slip-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer; transition: transform 0.2s; }
        .slip-thumb:hover { transform: scale(1.08); }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-white bg-white border-bottom mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold text-dark d-flex align-items-center gap-2" href="../admin_dashboard.php">
                <i class="bi bi-box-fill text-success fs-5"></i> BuildNexus Admin
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="invoicing.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt me-1"></i> Invoicing</a>
                <a href="online-payments.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-credit-card me-1"></i> Payments</a>
                <a href="../admin_dashboard.php" class="btn btn-sm btn-dark"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">Bank Transfer & Payment Slip Verification</h3>
                <p class="text-muted small mb-0">Review submitted bank deposit slips, verify client payments, and update project financials.</p>
            </div>
            <div>
                <a href="../pay_online.php" target="_blank" class="btn btn-outline-success btn-sm fw-semibold">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Preview Client Payment Portal
                </a>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="nexus-card p-3 mb-0 d-flex align-items-center gap-3">
                    <div class="p-3 bg-warning-subtle text-warning rounded-circle">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Verifications</div>
                        <h4 class="fw-bold mb-0 text-dark"><?= $total_pending_count ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="nexus-card p-3 mb-0 d-flex align-items-center gap-3">
                    <div class="p-3 bg-success-subtle text-success rounded-circle">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending Amount</div>
                        <h4 class="fw-bold mb-0 text-success">RS. <?= number_format($total_pending_amount, 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="nexus-card p-3 mb-0 d-flex align-items-center gap-3">
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Verification Policy</div>
                        <div class="fw-semibold text-dark small">Verified payments credit financials immediately</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Verifications Table -->
        <div class="nexus-card">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-hourglass-split text-warning"></i> 
                <span>Pending Approvals</span>
                <span class="badge bg-warning text-dark rounded-pill"><?= $total_pending_count ?></span>
            </h5>

            <?php if (empty($pending_payments)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check2-all text-success fs-1"></i>
                    <h6 class="fw-bold mt-2">All Caught Up!</h6>
                    <p class="small mb-0">There are no pending payment slip verifications right now.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Submission Date</th>
                                <th>Invoice #</th>
                                <th>Project & Client</th>
                                <th>Amount Paid</th>
                                <th>Method / Ref</th>
                                <th>Deposit Slip</th>
                                <th class="text-end">Verification Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_payments as $pay): ?>
                                <tr>
                                    <td class="small text-muted">
                                        <?= date('M d, Y', strtotime($pay['created_at'])) ?><br>
                                        <span class="text-secondary" style="font-size: 0.75rem;"><?= date('H:i A', strtotime($pay['created_at'])) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($pay['invoice_number']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($pay['project_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($pay['client_name'] ?? 'Client') ?></div>
                                    </td>
                                    <td class="fw-bold text-success fs-6">
                                        RS. <?= number_format($pay['amount_paid'], 2) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($pay['payment_method']) ?></span>
                                        <?php if (!empty($pay['transaction_reference'])): ?>
                                            <div class="small text-muted font-monospace mt-1"><?= htmlspecialchars($pay['transaction_reference']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $slip = $pay['receipt_slip_url'];
                                            $is_pdf = strtolower(pathinfo($slip, PATHINFO_EXTENSION)) === 'pdf';
                                        ?>
                                        <?php if ($slip): ?>
                                            <?php if ($is_pdf): ?>
                                                <a href="../<?= htmlspecialchars($slip) ?>" target="_blank" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-file-earmark-pdf-fill"></i> View PDF Slip
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn p-0 border-0" onclick="showSlipModal('../<?= htmlspecialchars($slip) ?>', '<?= htmlspecialchars($pay['invoice_number']) ?>')">
                                                    <img src="../<?= htmlspecialchars($slip) ?>" class="slip-thumb" alt="Slip" title="Click to enlarge">
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">No file uploaded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <!-- Approve Form -->
                                            <form method="POST" onsubmit="return confirm('Confirm and approve this payment of RS. <?= number_format($pay['amount_paid'], 2) ?>? This will mark the invoice as Paid.');">
                                                <input type="hidden" name="action" value="approve_payment">
                                                <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm px-3 fw-bold">
                                                    <i class="bi bi-check-lg me-1"></i> Approve
                                                </button>
                                            </form>

                                            <!-- Reject Button -->
                                            <button type="button" class="btn btn-outline-danger btn-sm px-3 fw-semibold" 
                                                    onclick="openRejectModal(<?= $pay['id'] ?>, '<?= htmlspecialchars($pay['invoice_number']) ?>')">
                                                <i class="bi bi-x-lg me-1"></i> Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- History of Verified & Rejected Payments -->
        <div class="nexus-card">
            <h5 class="fw-bold mb-3"><i class="bi bi-journal-check text-success me-2"></i>Recent Verification History</h5>
            <?php if (empty($history_payments)): ?>
                <div class="text-muted small py-3">No historical verifications yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Slip</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_payments as $h): ?>
                                <tr>
                                    <td class="small text-muted"><?= date('M d, Y', strtotime($h['created_at'])) ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($h['invoice_number']) ?></td>
                                    <td class="small"><?= htmlspecialchars($h['project_name']) ?></td>
                                    <td class="small"><?= htmlspecialchars($h['client_name'] ?? 'Client') ?></td>
                                    <td class="fw-semibold">RS. <?= number_format($h['amount_paid'], 2) ?></td>
                                    <td>
                                        <?php if ($h['status'] === 'Verified'): ?>
                                            <span class="badge badge-verified px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Verified</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($h['verifier_name'] ?? 'Admin') ?></td>
                                    <td>
                                        <?php if (!empty($h['receipt_slip_url'])): ?>
                                            <a href="../<?= htmlspecialchars($h['receipt_slip_url']) ?>" target="_blank" class="small text-primary">
                                                <i class="bi bi-paperclip"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Slip Preview Modal -->
    <div class="modal fade" id="slipModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom">
                    <h6 class="modal-title fw-bold">Deposit Slip - <span id="slip_inv_title"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-3">
                    <img id="slip_img" src="" class="img-fluid rounded border shadow-sm" style="max-height: 550px;" alt="Receipt Slip">
                </div>
                <div class="modal-footer border-top">
                    <a id="slip_download" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Open Original Image
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content border-0 shadow">
                <input type="hidden" name="action" value="reject_payment">
                <input type="hidden" name="payment_id" id="reject_payment_id">
                <div class="modal-header border-bottom">
                    <h6 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Reject Payment Slip</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">You are rejecting the payment for <strong id="reject_inv_num"></strong>. Please provide a clear explanation for the client:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reason for Rejection</label>
                        <select class="form-select form-select-sm mb-2" onchange="document.getElementById('reason_text').value = this.value">
                            <option value="">-- Choose Common Reason --</option>
                            <option value="Deposit slip is illegible or blurry. Please upload a clear photo or PDF scan.">Deposit slip is illegible or blurry</option>
                            <option value="Transaction reference or bank transfer not found in company account.">Payment reference not found in account</option>
                            <option value="Amount deposited does not match invoice total.">Amount deposited does not match invoice</option>
                            <option value="Duplicate slip upload.">Duplicate slip upload</option>
                        </select>
                        <textarea name="rejection_reason" id="reason_text" class="form-control" rows="3" placeholder="Enter custom message to client..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSlipModal(src, invNum) {
            document.getElementById('slip_img').src = src;
            document.getElementById('slip_download').href = src;
            document.getElementById('slip_inv_title').innerText = invNum;
            new bootstrap.Modal(document.getElementById('slipModal')).show();
        }

        function openRejectModal(payId, invNum) {
            document.getElementById('reject_payment_id').value = payId;
            document.getElementById('reject_inv_num').innerText = invNum;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
    </script>
</body>
</html>
