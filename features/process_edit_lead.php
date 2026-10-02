<?php
require_once '../db.php';
session_start();

// Security: Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'];
    $name = $_POST['customer_name'];
    $phone = $_POST['phone'];
    $type = $_POST['project_type'];

    try {
        // Update the database
        $sql = "UPDATE leads SET 
                customer_name = ?, 
                phone = ?, 
                project_type = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$name, $phone, $type, $lead_id]);

        if ($result) {
            header("Location: lead_details.php?id=$lead_id&msg=updated");
        } else {
            header("Location: lead_details.php?id=$lead_id&msg=error");
        }
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: lead-generation.php");
    exit();
}