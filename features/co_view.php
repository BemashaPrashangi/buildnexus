<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

$co_id = intval($_GET['id'] ?? 0);
if ($co_id <= 0) {
    die("Invalid Change Order ID.");
}

$success_msg = '';
$error_msg = '';

// Handle Convert to Invoice from view page
if (isset($_GET['action']) && $_GET['action'] === 'convert_invoice') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM change_orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$co_id]);
        $co = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$co) {
            throw new Exception("Change Order not found.");
        }
        if ($co['status'] !== 'Approved') {
            throw new Exception("Only approved change orders can be converted to invoices.");
        }

        // Generate sequential invoice number
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
        $tax_amount = 0.00;
        $total_amount = $amount;
        $due_date = date('Y-m-d', strtotime('+14 days'));
        $notes = "Generated from Change Order: " . ($co['co_number'] ?: "CO-{$co['id']}");

        $insInv = $pdo->prepare("
            INSERT INTO invoices (invoice_number, project_id, client_id, amount, tax_amount, total_amount, issue_date, due_date, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE, ?, 'Sent', ?)
        ");
        $insInv->execute([
            $invoice_number,
            $co['project_id'],
            $co['client_id'],
            $amount,
            $tax_amount,
            $total_amount,
            $due_date,
            $notes
        ]);
        $invoice_id = $pdo->lastInsertId();

        // Insert invoice item
        $insItem = $pdo->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total)
            VALUES (?, ?, 1.00, ?, ?)
        ");
        $insItem->execute([
            $invoice_id,
            "Change Order " . ($co['co_number'] ?: "CO-{$co['id']}") . ": " . $co['title'],
            $amount,
            $amount
        ]);

        $pdo->commit();
        header("Location: invoicing.php?msg=co_converted&inv=" . urlencode($invoice_number));
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Conversion failed: " . $e->getMessage();
    }
}

// Handle Send for Approval
if (isset($_GET['action']) && $_GET['action'] === 'send_approval') {
    $upd = $pdo->prepare("UPDATE change_orders SET status = 'Pending' WHERE id = ? AND status = 'Draft'");
    $upd->execute([$co_id]);
    $success_msg = "Change Order submitted to client for approval.";
}

try {
    $stmt = $pdo->prepare("
        SELECT co.*, 
               p.project_name, 
               COALESCE(c.full_name, NULLIF(p.client_name, ''), 'Client') AS display_client,
               c.email AS client_email, c.company AS client_company,
               u.full_name AS creator_name, u.role AS creator_role
        FROM change_orders co
        LEFT JOIN projects p ON co.project_id = p.id
        LEFT JOIN clients c ON co.client_id = c.id
        LEFT JOIN users u ON co.created_by = u.id
        WHERE co.id = ?
    ");
    $stmt->execute([$co_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Change Order not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$statusClass = 'pill-pending';
if ($order['status'] === 'Approved') $statusClass = 'pill-approved';
elseif ($order['status'] === 'Rejected') $statusClass = 'pill-rejected';
elseif ($order['status'] === 'Draft') $statusClass = 'pill-draft';
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
    <title><?= htmlspecialchars($order['co_number'] ?? 'CO-' . $order['id']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; padding: 2rem 0; }
        .details-wrapper { max-width: 800px; margin: 0 auto; }
        .details-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        
        .pill { padding: 4px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display: inline-block; }
        .pill-approved { background: #f0fdf4; color: #16a34a; }
        .pill-pending { background: #fefce8; color: #a16207; }
        .pill-rejected { background: #fef2f2; color: #dc2626; }
        .pill-draft { background: #f1f5f9; color: #64748b; }

        .meta-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 2px; }
        .impact-card { border-radius: 10px; padding: 1.25rem; text-align: center; }
        .impact-cost { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .impact-time { background: #eff6ff; border: 1px solid #bfdbfe; }
        
        .btn-nexus-primary { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; }
        .btn-nexus-primary:hover { background-color: #16a34a; color: #fff; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .details-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="details-wrapper">
        <!-- Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <a href="change-orders.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Change Orders
            </a>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-dark" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print / PDF
                </button>
                <?php if ($order['status'] === 'Draft'): ?>
                    <a href="co_view.php?id=<?= $order['id'] ?>&action=send_approval" class="btn btn-sm btn-primary">
                        <i class="bi bi-send me-1"></i> Send for Approval
                    </a>
                <?php elseif ($order['status'] === 'Approved'): ?>
                    <a href="co_view.php?id=<?= $order['id'] ?>&action=convert_invoice" class="btn btn-sm btn-nexus-primary">
                        <i class="bi bi-receipt me-1"></i> Convert to Invoice
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
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print mb-3" role="alert">
                <?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="details-card">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                <div>
                    <h3 class="fw-bold text-success mb-1">
                        <i class="bi bi-boxes me-1"></i> BUILDNEXUS
                    </h3>
                    <p class="text-muted small mb-0">Contract Variation & Change Order Specification</p>
                </div>
                <div class="text-end">
                    <span class="pill <?= $statusClass ?> mb-1"><?= htmlspecialchars($order['status']) ?></span>
                    <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($order['co_number'] ?? 'CO-' . $order['id']) ?></div>
                    <div class="text-muted small">Created: <?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                </div>
            </div>

            <!-- Subject & Details -->
            <div class="mb-4">
                <div class="meta-label">Change Order Subject</div>
                <h4 class="fw-bold text-dark"><?= htmlspecialchars($order['title']) ?></h4>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="meta-label">Project</div>
                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($order['project_name']) ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="meta-label">Client</div>
                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($order['display_client']) ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="meta-label">Author / PM</div>
                    <div class="text-muted">
                        <?= htmlspecialchars($order['creator_name'] ?: 'Project Manager') ?>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="meta-label">Approval Status</div>
                    <div>
                        <?php if ($order['status'] === 'Approved'): ?>
                            <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Approved on <?= date('M d, Y', strtotime($order['approved_at'] ?: $order['created_at'])) ?></span>
                        <?php elseif ($order['status'] === 'Rejected'): ?>
                            <span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i> Declined by Client</span>
                        <?php else: ?>
                            <span class="text-warning fw-semibold"><i class="bi bi-clock-history me-1"></i> Awaiting Client Sign-off</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Scope Justification -->
            <div class="mb-4 bg-light p-3 rounded-3 border">
                <div class="meta-label mb-2">Scope of Change & Justification</div>
                <div class="small text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($order['description']) ?></div>
            </div>

            <!-- Impact Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="impact-card impact-cost">
                        <div class="meta-label text-success">Cost Impact (Amount Added)</div>
                        <h3 class="fw-bold text-success mb-0">Rs. <?= number_format($order['cost_impact'], 2) ?></h3>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="impact-card impact-time">
                        <div class="meta-label text-primary">Schedule Impact</div>
                        <h3 class="fw-bold text-primary mb-0">+<?= intval($order['time_impact_days']) ?> Days</h3>
                    </div>
                </div>
            </div>

            <?php if (!empty($order['rejection_reason'])): ?>
                <div class="alert alert-danger mb-4">
                    <div class="fw-bold small mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i> Client Rejection Reason:</div>
                    <div class="small"><?= htmlspecialchars($order['rejection_reason']) ?></div>
                </div>
            <?php endif; ?>

            <div class="text-center text-muted small mt-4 pt-3 border-top">
                This document constitutes an official contract variation and adjustment to the Master Construction Agreement.
            </div>
        </div>
    </div>
</body>
</html>
