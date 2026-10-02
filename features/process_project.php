<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['project_name'];
    $client = $_POST['client_name'];
    $budget = $_POST['budget'];
    $start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO projects (project_name, client_name, budget, start_date, status) VALUES (?, ?, ?, ?, 'Active')");
        $stmt->execute([$name, $client, $budget, $start]);
        header("Location: ../dashboard.php?msg=success");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>