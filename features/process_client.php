<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'] ?: 'no-email@example.com';
    $company = $_POST['company'] ?: 'N/A';
    $project = $_POST['project_id'];

    try {
        $sql = "INSERT INTO clients (full_name, email, company, project_id, status) VALUES (?, ?, ?, ?, 'Active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $email, $company, $project]);

        header("Location: clients.php?msg=success");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>