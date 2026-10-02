<?php
require_once '../db.php';
session_start();

$co_id = $_GET['id'] ?? null;

if ($co_id) {
    try {
        // Ensure status is set to 'Pending' to trigger visibility in Client Portal
        $stmt = $pdo->prepare("UPDATE change_orders SET status = 'Pending' WHERE id = ?");
        $stmt->execute([$co_id]);

        // Redirect back with success message
        header("Location: change-orders.php?msg=sent_success");
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}