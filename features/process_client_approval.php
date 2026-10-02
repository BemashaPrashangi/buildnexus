<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'Client') {
    $co_id = $_POST['co_id'];
    $signature = $_POST['signature'];

    try {
        $pdo->beginTransaction();

        // 1. Fetch the change order details
        $stmt = $pdo->prepare("SELECT project_id, cost_impact FROM change_orders WHERE id = ?");
        $stmt->execute([$co_id]);
        $order = $stmt->fetch();

        if ($order) {
            // 2. Update status to 'Approved'
            $update_co = $pdo->prepare("UPDATE change_orders SET status = 'Approved' WHERE id = ?");
            $update_co->execute([$co_id]);

            // 3. AUTOMATICALLY UPDATE PROJECT BUDGET
            // We add the cost_impact to the existing budget
            $update_budget = $pdo->prepare("UPDATE projects SET budget = budget + ? WHERE id = ?");
            $update_budget->execute([$order['cost_impact'], $order['project_id']]);

            $pdo->commit();
            header("Location: client_dashboard.php?msg=approved");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Approval Error: " . $e->getMessage());
    }
}