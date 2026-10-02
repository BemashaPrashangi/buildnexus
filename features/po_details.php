<?php
require_once '../db.php'; 
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Invalid PO ID.");
}

$po_id = $_GET['id'];

try {
    // Fetch PO
    $stmt = $pdo->prepare("
        SELECT po.*, p.project_name, u.full_name 
        FROM purchase_orders po 
        LEFT JOIN projects p ON po.project_id = p.id 
        LEFT JOIN users u ON po.created_by = u.id 
        WHERE po.id = ?
    ");
    $stmt->execute([$po_id]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        die("Purchase Order not found.");
    }

    // Fetch Items
    $itemStmt = $pdo->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id ASC");
    $itemStmt->execute([$po_id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function getStatusPillClass($status) {
    switch ($status) {
        case 'Goods Received': return 'pill-received';
        case 'Pending Delivery': return 'pill-pending';
        case 'Draft': return 'pill-draft';
        case 'Converted to Bill':
        case 'Paid': return 'pill-paid';
        case 'Cancelled': return 'pill-cancelled';
        default: return 'pill-draft';
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
    <title><?= htmlspecialchars($po['po_number']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { max-width: 1000px; margin: 0 auto; padding: 2.5rem 1rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
        
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .pill-received { background: #fef9c3; color: #a16207; }
        .pill-pending { background: #eff6ff; color: #1e40af; }
        .pill-draft { background: #f1f5f9; color: #64748b; }
        .pill-paid { background: #f0fdf4; color: #16a34a; }
        .pill-cancelled { background: #fef2f2; color: #dc2626; }

        .meta-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 4px; }
        .meta-value { font-size: 0.95rem; font-weight: 500; }
        
        .back-link { color: #64748b; text-decoration: none; font-weight: 500; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 1.5rem; transition: 0.2s; }
        .back-link:hover { color: #1e293b; }

        .table-items th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 0.85rem; padding: 12px; }
        .table-items td { padding: 12px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    </style>
</head>
<body>

    <div class="main-container">
        <a href="procurement.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Procurement</a>
        
        <div class="nexus-card">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="h4 fw-bold mb-1">Purchase Order: <?= htmlspecialchars($po['po_number']) ?></h2>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($po['project_name']) ?> • Vendor: <strong><?= htmlspecialchars($po['vendor_name']) ?></strong></p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="pill <?= getStatusPillClass($po['status']) ?>">
                        <?= htmlspecialchars($po['status']) ?>
                    </span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                </div>
            </div>

            <div class="row g-4 mb-5 pb-4 border-bottom">
                <div class="col-sm-3">
                    <div class="meta-label">PO Date</div>
                    <div class="meta-value"><?= htmlspecialchars($po['po_date']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="meta-label">Expected Delivery</div>
                    <div class="meta-value"><?= $po['expected_delivery_date'] ? htmlspecialchars($po['expected_delivery_date']) : 'N/A' ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="meta-label">Created By</div>
                    <div class="meta-value"><?= htmlspecialchars($po['full_name'] ?: 'Project Manager') ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="meta-label">Total Amount</div>
                    <div class="meta-value fw-bold text-success">RS. <?= number_format($po['total_amount'], 2) ?></div>
                </div>
            </div>

            <h5 class="fw-bold mb-3">Line Items</h5>
            <div class="table-responsive mb-4">
                <table class="table table-items">
                    <thead>
                        <tr>
                            <th width="40%">Description</th>
                            <th width="15%" class="text-center">Qty Ordered</th>
                            <th width="15%" class="text-center">Qty Received</th>
                            <th width="15%" class="text-end">Unit Price</th>
                            <th width="15%" class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($item['item_description']) ?></td>
                            <td class="text-center"><?= $item['quantity_ordered'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                            <td class="text-center">
                                <?php if ($item['quantity_received'] >= $item['quantity_ordered']): ?>
                                    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> <?= $item['quantity_received'] ?></span>
                                <?php elseif ($item['quantity_received'] > 0): ?>
                                    <span class="text-warning fw-bold"><?= $item['quantity_received'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">RS. <?= number_format($item['unit_price'], 2) ?></td>
                            <td class="text-end fw-bold">RS. <?= number_format($item['line_total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-7">
                    <?php if ($po['notes']): ?>
                        <div class="meta-label">Notes / Terms</div>
                        <p class="small text-muted" style="white-space: pre-wrap;"><?= htmlspecialchars($po['notes']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-semibold text-muted">Subtotal</span>
                        <span class="fw-bold">RS. <?= number_format($po['subtotal'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                        <span class="fw-semibold text-muted">Tax (7.25%)</span>
                        <span class="fw-bold">RS. <?= number_format($po['tax_amount'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold fs-5">Grand Total</span>
                        <span class="fw-bold fs-5 text-success">RS. <?= number_format($po['total_amount'], 2) ?></span>
                    </div>
                </div>
            </div>

            <?php if (in_array($po['status'], ['Pending Delivery', 'Draft'])): ?>
                <div class="mt-5 text-end">
                    <a href="process_grn.php?id=<?= $po['id'] ?>" class="btn btn-warning fw-bold text-dark px-4"><i class="bi bi-box-seam me-2"></i> Receive Goods (GRN)</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
