<?php
// File: features/process_client_action.php
require_once '../db.php';
session_start();

// 1. Security Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_SESSION['role'] !== 'Client') {
    header("Location: ../login.php");
    exit();
}

// 2. Get Form Data
$co_id = $_POST['co_id'] ?? null;
$signature = trim($_POST['signature'] ?? '');
$action = $_POST['action_type'] ?? '';

if (!$co_id || empty($signature) || $action !== 'approve') {
     die("Error: Invalid request or missing signature.");
}

try {
    // Start Transaction for data integrity
    $pdo->beginTransaction();

    // A. Verify CO exists and belongs to client's project (Security Measure)
    // Also fetch cost and project_id for budget update
    $stmt_verify = $pdo->prepare("
        SELECT co.id, co.project_id, co.cost_impact 
        FROM change_orders co
        JOIN clients c ON co.project_id = c.project_id
        WHERE co.id = ? AND c.id = ? AND co.status = 'Pending'
    ");
    $stmt_verify->execute([$co_id, $_SESSION['user_id']]);
    $co_data = $stmt_verify->fetch(PDO::FETCH_ASSOC);

    if (!$co_data) {
        throw new Exception("Change Order not found or already processed.");
    }

    // B. Update Change Order Status to Approved
    // (Optional: Add a 'client_signature' column to your DB to save the name typed)
    $stmt_update_co = $pdo->prepare("UPDATE change_orders SET status = 'Approved' WHERE id = ?");
    $stmt_update_co->execute([$co_id]);

    // C. AUTOMATICALLY UPDATE PROJECT BUDGET
    // Add the cost impact to the total project budget
    $stmt_update_budget = $pdo->prepare("UPDATE projects SET budget = budget + ? WHERE id = ?");
    $stmt_update_budget->execute([$co_data['cost_impact'], $co_data['project_id']]);

    // Commit Transaction
    $pdo->commit();

    // Redirect back with success
    header("Location: client_change_orders.php?msg=approved");
    exit();

} catch (Exception $e) {
    // Rollback on error so partial data isn't saved
    $pdo->rollBack();
    die("Processing Error: " . $e->getMessage());
}
?>