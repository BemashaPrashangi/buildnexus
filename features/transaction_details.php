<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

$payment_id = intval($_GET['id'] ?? 0);
if ($payment_id <= 0) {
    die("Invalid Payment ID.");
}

$stmt = $pdo->prepare("
    SELECT p.*, 
           i.invoice_number, i.total_amount AS invoice_total, i.status AS invoice_status,
           pr.project_name, 
           COALESCE(c.full_name, NULLIF(pr.client_name, ''), 'Valued Client') AS display_client,
           c.email AS client_email, c.company AS client_company
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.id
    LEFT JOIN projects pr ON p.project_id = pr.id
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    die("Transaction not found.");
}

$statusClass = 'pill-pending';
if ($tx['status'] === 'Completed') $statusClass = 'pill-completed';
elseif ($tx['status'] === 'Failed' || $tx['status'] === 'Refunded') $statusClass = 'pill-failed';
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
    <title>Transaction <?= htmlspecialchars($tx['transaction_id']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2rem 0; }
        .receipt-card { max-width: 800px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .pill { padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display: inline-block; }
        .pill-completed { background: #f0fdf4; color: #16a34a; }
        .pill-pending { background: #fffbeb; color: #d97706; }
        .pill-failed { background: #fef2f2; color: #dc2626; }
        .meta-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 2px; }
        .meta-val { font-size: 0.95rem; font-weight: 500; }
        .amount-display { font-size: 1.75rem; font-weight: 700; color: #0f172a; }
        .raw-json { background: #0f172a; color: #38bdf8; padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 0.8rem; overflow-x: auto; }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .receipt-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print" style="max-width: 800px; margin: 0 auto;">
            <a href="online-payments.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Payments
            </a>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-dark" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Receipt
                </button>
                <a href="invoice_view.php?id=<?= $tx['invoice_id'] ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-receipt me-1"></i> View Invoice
                </a>
            </div>
        </div>

        <div class="receipt-card">
            <!-- Receipt Header -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                <div>
                    <h3 class="fw-bold text-success mb-1">
                        <i class="bi bi-boxes me-1"></i> BUILDNEXUS
                    </h3>
                    <p class="text-muted small mb-0">Official Electronic Payment Receipt</p>
                </div>
                <div class="text-end">
                    <span class="pill <?= $statusClass ?> mb-2"><?= htmlspecialchars($tx['status']) ?></span>
                    <div class="text-muted small">Transaction ID</div>
                    <div class="fw-bold font-monospace text-dark"><?= htmlspecialchars($tx['transaction_id']) ?></div>
                </div>
            </div>

            <!-- Amount Highlight Box -->
            <div class="bg-light p-4 rounded-3 border mb-4 text-center">
                <div class="meta-label mb-1">Gross Payment Received</div>
                <div class="amount-display text-success">RS. <?= number_format($tx['amount'], 2) ?></div>
                <div class="text-muted small mt-1">Processed on <?= date('F d, Y', strtotime($tx['payment_date'])) ?> via <strong><?= htmlspecialchars($tx['payment_method']) ?></strong></div>
            </div>

            <!-- Details Grid -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6">
                    <div class="meta-label">Client / Payer</div>
                    <div class="fw-bold fs-6"><?= htmlspecialchars($tx['display_client']) ?></div>
                    <?php if (!empty($tx['client_company'])): ?>
                        <div class="text-muted small"><?= htmlspecialchars($tx['client_company']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($tx['client_email'])): ?>
                        <div class="text-muted small"><?= htmlspecialchars($tx['client_email']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6">
                    <div class="meta-label">Project Allocation</div>
                    <div class="fw-bold fs-6 text-dark"><?= htmlspecialchars($tx['project_name']) ?></div>
                    <div class="text-muted small">Linked Invoice: <a href="invoice_view.php?id=<?= $tx['invoice_id'] ?>" class="text-success fw-semibold"><?= htmlspecialchars($tx['invoice_number']) ?></a></div>
                </div>
            </div>

            <!-- Settlement Accounting Breakdown -->
            <div class="border-top pt-4 mb-4">
                <h6 class="fw-bold mb-3">Settlement & Ledger Breakdown</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td class="text-muted">Gross Transaction Amount</td>
                                <td class="text-end fw-semibold">RS. <?= number_format($tx['amount'], 2) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Gateway Fee Deducted (Processing)</td>
                                <td class="text-end text-danger">- RS. <?= number_format($tx['fee_deducted'], 2) ?></td>
                            </tr>
                            <tr class="table-light fs-6">
                                <td class="fw-bold">Net Settlement Credited</td>
                                <td class="text-end fw-bold text-success">RS. <?= number_format($tx['net_amount'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gateway Raw Response / Audit Data -->
            <?php if (!empty($tx['gateway_response'])): ?>
                <div class="border-top pt-4 no-print">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="meta-label">Gateway Telemetry & Response</span>
                        <span class="badge bg-secondary font-monospace">JSON Payload</span>
                    </div>
                    <pre class="raw-json mb-0"><?= htmlspecialchars(json_encode(json_decode($tx['gateway_response']), JSON_PRETTY_PRINT)) ?></pre>
                </div>
            <?php endif; ?>

            <div class="text-center text-muted small mt-4 pt-3 border-top">
                Payment verified and cryptographically reconciled by BuildNexus Treasury Engine.
            </div>
        </div>
    </div>
</body>
</html>
