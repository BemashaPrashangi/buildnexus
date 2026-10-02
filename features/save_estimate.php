<?php
// FIX: Path to db.php must be correct relative to this folder
require_once '../db.php'; 
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? null;
    $total_amount = $_POST['total_amount'] ?? 0;
    $items = json_decode($_POST['items'] ?? '[]', true);
    
    // Generate a unique reference code
    $estimate_no = "EST-" . time(); 

    if (!$project_id) {
        echo json_encode(['success' => false, 'error' => 'No Project Selected']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert the Master Estimate
        $sql = "INSERT INTO estimates (project_id, estimate_number, total_amount, status) VALUES (?, ?, ?, 'Sent')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$project_id, $estimate_no, $total_amount]);
        $estimate_id = $pdo->lastInsertId();

        // 2. Insert line items linked to this estimate
        $sql_item = "INSERT INTO estimate_items (estimate_id, item_name, unit, unit_cost) VALUES (?, ?, ?, ?)";
        $stmt_item = $pdo->prepare($sql_item);

        foreach ($items as $item) {
            $stmt_item->execute([
                $estimate_id, 
                $item['name'], 
                $item['category'], // Using category as unit name
                $item['cost']
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid Request']);
}