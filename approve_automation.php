<?php
require_once 'db.php';
session_start();

// Security: Check if user is logged in and is a Client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    header("Location: login.php");
    exit();
}

$estimate_id = $_GET['id'] ?? null;

if ($estimate_id) {
    try {
        // Find the client's assigned project
        $stmt_client = $pdo->prepare("SELECT project_id FROM clients WHERE id = ?");
        $stmt_client->execute([$_SESSION['user_id']]);
        $client = $stmt_client->fetch();

        if (!$client || empty($client['project_id'])) {
            die("Error: No project assigned to this client.");
        }

        $client_project_id = $client['project_id'];

        // Verify the estimate exists and belongs to the client's project
        $stmt_est = $pdo->prepare("SELECT project_id, total_amount FROM estimates WHERE id = ?");
        $stmt_est->execute([$estimate_id]);
        $data = $stmt_est->fetch();

        if (!$data || $data['project_id'] != $client_project_id) {
            die("Error: Invalid estimate or unauthorized access.");
        }

        $pdo->beginTransaction();

        // 1. Update Estimate Status
        $pdo->prepare("UPDATE estimates SET status = 'Approved' WHERE id = ?")->execute([$estimate_id]);

        // 2. Update Project Budget and Status
        $pdo->prepare("UPDATE projects SET budget = ?, status = 'Active' WHERE id = ?")->execute([$data['total_amount'], $data['project_id']]);

        // 3. Automation: Generate Milestone
        $pdo->prepare("INSERT INTO project_milestones (project_id, phase_name, start_date, end_date, status) 
                       VALUES (?, 'Mobilization & Site Prep', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'In Progress')")
            ->execute([$data['project_id']]);

        $pdo->commit();
        header("Location: client_dashboard.php?msg=success");
        exit();
    } catch (PDOException $e) { 
        $pdo->rollBack(); 
        die("Database Error: " . $e->getMessage()); 
    }
} else {
    // If no ID is passed, redirect back
    header("Location: client_dashboard.php");
    exit();
}