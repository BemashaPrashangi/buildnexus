<?php
// features/selections_action.php - Handles AJAX updates, voting, budget recalcs, and conversions
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

function getRoomBudgetMetrics(PDO $pdo, int $room_id): array {
    $rStmt = $pdo->prepare("SELECT * FROM project_selection_rooms WHERE id = ?");
    $rStmt->execute([$room_id]);
    $room = $rStmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        return [
            'budget_allowance' => 0.00,
            'used_amount' => 0.00,
            'progress_percent' => 0.0,
            'gauge_color' => '#22c55e',
            'is_over_budget' => false,
            'variance' => 0.00
        ];
    }

    $allowance = floatval($room['budget_allowance']);

    // Sum total_price of items where include_in_budget = 1
    $sumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(unit_price * quantity), 0.00) 
        FROM project_selections 
        WHERE room_id = ? AND include_in_budget = 1
    ");
    $sumStmt->execute([$room_id]);
    $used = floatval($sumStmt->fetchColumn() ?: 0.00);

    $percent = $allowance > 0 ? ($used / $allowance) * 100 : 0;
    
    // Gauge color logic: Green <= 90%, Amber 90-100%, Red > 100%
    if ($percent > 100) {
        $color = '#ef4444'; // Red
    } elseif ($percent >= 90) {
        $color = '#f59e0b'; // Amber
    } else {
        $color = '#22c55e'; // Green
    }

    return [
        'budget_allowance' => $allowance,
        'used_amount' => $used,
        'progress_percent' => min(100, round($percent, 2)),
        'raw_percent' => round($percent, 2),
        'gauge_color' => $color,
        'is_over_budget' => ($used > $allowance),
        'variance' => round($used - $allowance, 2)
    ];
}

try {
    if ($action === 'change_status') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $notes = trim($_POST['client_notes'] ?? '');

        if (!in_array($status, ['Approved', 'Declined', 'Pending'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid approval status.']);
            exit();
        }

        // Fetch item to get room_id
        $iStmt = $pdo->prepare("SELECT room_id FROM project_selections WHERE id = ?");
        $iStmt->execute([$item_id]);
        $room_id = $iStmt->fetchColumn();

        if (!$room_id) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit();
        }

        if (!empty($notes)) {
            $upd = $pdo->prepare("UPDATE project_selections SET approval_status = ?, client_notes = ? WHERE id = ?");
            $upd->execute([$status, $notes, $item_id]);
        } else {
            $upd = $pdo->prepare("UPDATE project_selections SET approval_status = ? WHERE id = ?");
            $upd->execute([$status, $item_id]);
        }

        $metrics = getRoomBudgetMetrics($pdo, intval($room_id));

        echo json_encode([
            'success' => true,
            'message' => "Item status updated to {$status}.",
            'item_id' => $item_id,
            'new_status' => $status,
            'metrics' => $metrics
        ]);
        exit();

    } elseif ($action === 'toggle_include') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $included = (isset($_POST['included']) && ($_POST['included'] === 'true' || $_POST['included'] == '1')) ? 1 : 0;

        $iStmt = $pdo->prepare("SELECT room_id FROM project_selections WHERE id = ?");
        $iStmt->execute([$item_id]);
        $room_id = $iStmt->fetchColumn();

        if (!$room_id) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit();
        }

        $upd = $pdo->prepare("UPDATE project_selections SET include_in_budget = ? WHERE id = ?");
        $upd->execute([$included, $item_id]);

        $metrics = getRoomBudgetMetrics($pdo, intval($room_id));

        echo json_encode([
            'success' => true,
            'message' => $included ? "Item included in budget allowance." : "Item excluded from budget allowance.",
            'item_id' => $item_id,
            'included' => $included,
            'metrics' => $metrics
        ]);
        exit();

    } elseif ($action === 'create_item') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $room_id = intval($_POST['room_id'] ?? 0);
        $item_name = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'Fixtures');
        $unit_price = floatval($_POST['unit_price'] ?? 0);
        $quantity = floatval($_POST['quantity'] ?? 1);
        $include_in_budget = isset($_POST['include_in_budget']) ? 1 : 1;
        $photo_url = trim($_POST['photo_url'] ?? '');

        // Handle uploaded image file if present
        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/selections/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $filename = 'sel_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $upload_dir . $filename)) {
                    $photo_url = '../uploads/selections/' . $filename;
                }
            }
        }

        if (empty($photo_url)) {
            // Default placeholder modern fixture image
            $photo_url = 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600&h=450&fit=crop';
        }

        if ($room_id <= 0 || empty($item_name)) {
            echo json_encode(['success' => false, 'message' => 'Please provide item name and select a valid room.']);
            exit();
        }

        // Verify project_id from room if not passed
        if ($project_id <= 0) {
            $rStmt = $pdo->prepare("SELECT project_id FROM project_selection_rooms WHERE id = ?");
            $rStmt->execute([$room_id]);
            $project_id = intval($rStmt->fetchColumn() ?: 1);
        }

        $ins = $pdo->prepare("
            INSERT INTO project_selections 
            (room_id, project_id, item_name, category, photo_url, unit_price, quantity, include_in_budget, approval_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        $ins->execute([
            $room_id,
            $project_id,
            $item_name,
            $category,
            $photo_url,
            $unit_price,
            $quantity,
            $include_in_budget
        ]);
        $new_id = $pdo->lastInsertId();

        $metrics = getRoomBudgetMetrics($pdo, $room_id);

        echo json_encode([
            'success' => true,
            'message' => "Finish '{$item_name}' added to room successfully!",
            'item' => [
                'id' => $new_id,
                'name' => $item_name,
                'category' => $category,
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                'photo_url' => $photo_url,
                'status' => 'Pending',
                'include_in_budget' => $include_in_budget
            ],
            'metrics' => $metrics
        ]);
        exit();

    } elseif ($action === 'delete_item') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $iStmt = $pdo->prepare("SELECT room_id FROM project_selections WHERE id = ?");
        $iStmt->execute([$item_id]);
        $room_id = $iStmt->fetchColumn();

        if (!$room_id) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit();
        }

        $del = $pdo->prepare("DELETE FROM project_selections WHERE id = ?");
        $del->execute([$item_id]);

        $metrics = getRoomBudgetMetrics($pdo, intval($room_id));

        echo json_encode([
            'success' => true,
            'message' => 'Item deleted from selections.',
            'item_id' => $item_id,
            'metrics' => $metrics
        ]);
        exit();

    } elseif ($action === 'convert_po') {
        // Convert Approved items into a Purchase Order in purchase_orders table
        $room_id = intval($_POST['room_id'] ?? 0);
        $vendor_name = trim($_POST['vendor_name'] ?? 'Premium Finishes Supplier');

        // Fetch room and project
        $rStmt = $pdo->prepare("
            SELECT r.*, p.project_name 
            FROM project_selection_rooms r 
            JOIN projects p ON r.project_id = p.id 
            WHERE r.id = ?
        ");
        $rStmt->execute([$room_id]);
        $room = $rStmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            echo json_encode(['success' => false, 'message' => 'Room not found.']);
            exit();
        }

        // Fetch approved items
        $iStmt = $pdo->prepare("SELECT * FROM project_selections WHERE room_id = ? AND approval_status = 'Approved'");
        $iStmt->execute([$room_id]);
        $approved_items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($approved_items)) {
            echo json_encode(['success' => false, 'message' => 'No approved items found in this room to generate a Purchase Order.']);
            exit();
        }

        $subtotal = 0;
        foreach ($approved_items as $item) {
            $subtotal += floatval($item['unit_price']) * floatval($item['quantity']);
        }
        $tax_amount = round($subtotal * 0.08, 2);
        $total_amount = $subtotal + $tax_amount;

        // Auto-generate PO number
        $year = date('Y');
        $maxStmt = $pdo->query("SELECT MAX(id) FROM purchase_orders");
        $nextId = ($maxStmt->fetchColumn() ?: 0) + 1;
        $po_number = sprintf("PO-%s-%04d", $year, $nextId);

        $notes = "Auto-generated from approved client finishes for Room: " . $room['room_name'] . " (" . count($approved_items) . " items).";

        $insPO = $pdo->prepare("
            INSERT INTO purchase_orders 
            (po_number, project_id, vendor_name, po_date, expected_delivery_date, subtotal, tax_amount, total_amount, notes, status, created_by)
            VALUES (?, ?, ?, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY), ?, ?, ?, ?, 'Draft', ?)
        ");
        $user_id = $_SESSION['user_id'] ?? 1;
        $insPO->execute([
            $po_number,
            $room['project_id'],
            $vendor_name,
            $subtotal,
            $tax_amount,
            $total_amount,
            $notes,
            $user_id
        ]);
        $po_id = $pdo->lastInsertId();

        // Insert into purchase_order_items if table exists
        try {
            $insItem = $pdo->prepare("
                INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, line_total)
                VALUES (?, ?, ?, 'EA', ?, ?)
            ");
            foreach ($approved_items as $item) {
                $line_total = floatval($item['unit_price']) * floatval($item['quantity']);
                $insItem->execute([$po_id, $item['item_name'], $item['quantity'], $item['unit_price'], $line_total]);
            }
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'message' => "Purchase Order {$po_number} successfully generated for RS. " . number_format($total_amount, 2) . "!",
            'po_number' => $po_number,
            'redirect' => 'procurement.php'
        ]);
        exit();

    } elseif ($action === 'convert_co') {
        // Create Change Order from selections variance
        $room_id = intval($_POST['room_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $cost_impact = floatval($_POST['cost_impact'] ?? 0);

        $rStmt = $pdo->prepare("SELECT r.*, p.id AS p_id FROM project_selection_rooms r JOIN projects p ON r.project_id = p.id WHERE r.id = ?");
        $rStmt->execute([$room_id]);
        $room = $rStmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            echo json_encode(['success' => false, 'message' => 'Room not found.']);
            exit();
        }

        $project_id = $room['p_id'];

        if (empty($title)) {
            $title = "Finish Selections Variance: " . $room['room_name'];
        }

        if ($cost_impact <= 0) {
            $metrics = getRoomBudgetMetrics($pdo, $room_id);
            $cost_impact = max(0, $metrics['variance']);
        }

        // Generate CO number
        $maxStmt = $pdo->query("SELECT MAX(id) FROM change_orders");
        $nextId = ($maxStmt->fetchColumn() ?: 0) + 1;
        $co_number = "CO-" . $nextId;

        // Fetch client_id
        $cStmt = $pdo->prepare("SELECT id FROM clients WHERE project_id = ? LIMIT 1");
        $cStmt->execute([$project_id]);
        $client_id = $cStmt->fetchColumn() ?: null;

        $insCO = $pdo->prepare("
            INSERT INTO change_orders 
            (co_number, project_id, client_id, title, description, cost_impact, time_impact_days, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 3, 'Pending', ?)
        ");
        $user_id = $_SESSION['user_id'] ?? 1;
        $insCO->execute([
            $co_number,
            $project_id,
            $client_id,
            $title,
            $description ?: "Budget allowance variance generated from {$room['room_name']} finish selections.",
            $cost_impact,
            $user_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Change Order {$co_number} created with cost impact RS. " . number_format($cost_impact, 2) . "!",
            'co_number' => $co_number,
            'redirect' => 'change-orders.php'
        ]);
        exit();

    } elseif ($action === 'create_room') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $room_name = trim($_POST['room_name'] ?? '');
        $allowance = floatval($_POST['budget_allowance'] ?? 0);

        if ($project_id <= 0 || empty($room_name)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid room name.']);
            exit();
        }

        $insR = $pdo->prepare("
            INSERT INTO project_selection_rooms (project_id, room_name, budget_allowance, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insR->execute([$project_id, $room_name, $allowance]);
        $new_room_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => "Room '{$room_name}' created successfully!",
            'room_id' => $new_room_id,
            'redirect' => "client_selections.php?room_id={$new_room_id}"
        ]);
        exit();

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
