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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_grn') {
    try {
        $pdo->beginTransaction();
        
        $all_fully_received = true;
        
        if (isset($_POST['item_id']) && is_array($_POST['item_id'])) {
            $updateStmt = $pdo->prepare("UPDATE purchase_order_items SET quantity_received = ? WHERE id = ? AND po_id = ?");
            
            foreach ($_POST['item_id'] as $index => $item_id) {
                $qty_received = floatval($_POST['quantity_received'][$index] ?? 0);
                $qty_ordered = floatval($_POST['quantity_ordered'][$index] ?? 0);
                
                $updateStmt->execute([$qty_received, $item_id, $po_id]);
                
                if ($qty_received < $qty_ordered) {
                    $all_fully_received = false;
                }
            }
        }
        
        // If fully received, update PO status
        if ($all_fully_received) {
            $updPoStmt = $pdo->prepare("UPDATE purchase_orders SET status = 'Goods Received' WHERE id = ?");
            $updPoStmt->execute([$po_id]);
        } else {
            // Ensure status is at least Pending Delivery
            $updPoStmt = $pdo->prepare("UPDATE purchase_orders SET status = 'Pending Delivery' WHERE id = ? AND status = 'Draft'");
            $updPoStmt->execute([$po_id]);
        }
        
        $pdo->commit();
        header("Location: po_details.php?id=" . $po_id);
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "GRN Processing Error: " . $e->getMessage();
    }
}

try {
    // Fetch PO
    $stmt = $pdo->prepare("
        SELECT po.*, p.project_name 
        FROM purchase_orders po 
        LEFT JOIN projects p ON po.project_id = p.id 
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
    <title>Goods Receipt Note - <?= htmlspecialchars($po['po_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { max-width: 900px; margin: 0 auto; padding: 2.5rem 1rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
        
        .back-link { color: #64748b; text-decoration: none; font-weight: 500; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 1.5rem; transition: 0.2s; }
        .back-link:hover { color: #1e293b; }

        .table-items th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #64748b; font-weight: 600; font-size: 0.85rem; padding: 12px; }
        .table-items td { padding: 12px; font-size: 0.9rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        .qty-input { width: 100px; text-align: center; font-weight: 600; }
        .btn-nexus { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 10px 20px; }
        .btn-nexus:hover { background-color: #16a34a; }
        
        .received-full { background-color: #f0fdf4 !important; }
    </style>
</head>
<body>

    <div class="main-container">
        <a href="po_details.php?id=<?= $po['id'] ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to PO Details</a>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="nexus-card">
            <div class="mb-4">
                <h3 class="fw-bold mb-1">Goods Receipt Note (GRN)</h3>
                <p class="text-muted mb-0">Record actual quantities received on-site for <strong><?= htmlspecialchars($po['po_number']) ?></strong> from <?= htmlspecialchars($po['vendor_name']) ?>.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="process_grn">
                
                <div class="table-responsive mb-4">
                    <table class="table table-items border">
                        <thead>
                            <tr>
                                <th width="40%">Item Description</th>
                                <th width="20%" class="text-center">Ordered</th>
                                <th width="10%" class="text-center">Prev Received</th>
                                <th width="30%" class="text-center">Verify Received Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $isFull = ($item['quantity_received'] >= $item['quantity_ordered']);
                            ?>
                            <tr class="<?= $isFull ? 'received-full' : '' ?>">
                                <td class="fw-semibold">
                                    <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="quantity_ordered[]" value="<?= $item['quantity_ordered'] ?>">
                                    <?= htmlspecialchars($item['item_description']) ?>
                                    <?php if($isFull): ?> <i class="bi bi-check-circle-fill text-success ms-1"></i> <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold text-dark"><?= $item['quantity_ordered'] ?> <span class="fw-normal text-muted small"><?= htmlspecialchars($item['unit']) ?></span></td>
                                <td class="text-center text-muted"><?= $item['quantity_received'] ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <input type="number" step="0.01" name="quantity_received[]" class="form-control qty-input" value="<?= $item['quantity_received'] == 0 ? $item['quantity_ordered'] : $item['quantity_received'] ?>" required <?= $isFull ? 'readonly' : '' ?>>
                                        <span class="text-muted small"><?= htmlspecialchars($item['unit']) ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info py-2 d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                    <div class="small">
                        <strong>Note:</strong> If all items match or exceed their ordered quantities, the Purchase Order will automatically transition to the "Goods Received" status.
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-nexus">Save Receipt & Verify</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
