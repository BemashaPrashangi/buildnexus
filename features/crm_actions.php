<?php
// features/crm_actions.php - Companion handler for CRM actions
require_once __DIR__ . '/../db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$action = $_POST['action'] ?? '';

if (!$action) {
    $_SESSION['error'] = 'Invalid action requested.';
    header('Location: lead-generation.php');
    exit;
}

try {
    if ($action === 'create_lead') {
        $name = trim($_POST['customer_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $project_type = trim($_POST['project_type'] ?? 'Residential');
        $budget = floatval($_POST['estimated_budget'] ?? 0);
        $location = trim($_POST['site_location'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        $status = 'New';
        
        $stmt = $pdo->prepare("
            INSERT INTO leads (customer_name, email, phone, project_type, estimated_budget, site_location, status, assigned_to, notes, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $email, $phone, $project_type, $budget, $location, $status, $assigned_to, $notes]);
        
        $_SESSION['success'] = 'New lead registered successfully.';
    }
    elseif ($action === 'change_stage') {
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $stage = trim($_POST['stage'] ?? '');
        
        $validStatuses = ['New', 'Follow-up', 'Quoted', 'Converted', 'Lost'];
        if (in_array($stage, $validStatuses)) {
            $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
            $stmt->execute([$stage, $lead_id]);
            $_SESSION['success'] = 'Lead stage updated to ' . htmlspecialchars($stage) . '.';
        } else {
            $_SESSION['error'] = 'Invalid status specified.';
        }
    }
    elseif ($action === 'assign_lead') {
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        
        $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id = ?");
        $stmt->execute([$assigned_to, $lead_id]);
        
        $_SESSION['success'] = 'Lead successfully assigned to team member.';
    }
    elseif ($action === 'convert_project') {
        $lead_id = intval($_POST['lead_id'] ?? 0);
        // Delegate to unified convert_lead.php logic
        header("Location: convert_lead.php?id=" . $lead_id);
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Database Error: ' . $e->getMessage();
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'lead-generation.php';
header('Location: ' . $referer);
exit;
