<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

function jsonResponse($success, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

try {
    if ($action === 'create') {
        $customer_name = trim($_POST['name'] ?? $_POST['customer_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        $project_type = trim($_POST['project'] ?? $_POST['project_type'] ?? 'Residential');
        $estimated_budget = floatval($_POST['budget'] ?? $_POST['estimated_budget'] ?? 0.00);
        $site_location = !empty($_POST['location'] ?? $_POST['site_location'] ?? '') ? trim($_POST['location'] ?? $_POST['site_location']) : null;
        $assigned_to = !empty($_POST['assigned_to']) && is_numeric($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        $status = in_array($_POST['stage'] ?? $_POST['status'] ?? '', ['New', 'Follow-up', 'Quoted', 'Converted', 'Lost']) ? ($_POST['stage'] ?? $_POST['status']) : 'New';
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
        $source = !empty($_POST['source']) ? trim($_POST['source']) : '';

        if (!empty($source) && empty($notes)) {
            $notes = "Source: " . $source;
        } elseif (!empty($source)) {
            $notes = "Source: {$source} | " . $notes;
        }

        if (empty($customer_name) || empty($email)) {
            if ($isAjax) jsonResponse(false, 'Customer name and email are required.');
            header("Location: lead-generation.php?msg=error");
            exit();
        }

        $sql = "
            INSERT INTO leads (customer_name, email, phone, project_type, estimated_budget, site_location, status, assigned_to, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $customer_name,
            $email,
            $phone,
            $project_type,
            $estimated_budget,
            $site_location,
            $status,
            $assigned_to,
            $notes
        ]);

        $newId = $pdo->lastInsertId();

        if ($isAjax) jsonResponse(true, 'Lead registered successfully!', ['lead_id' => $newId]);
        header("Location: lead-generation.php?msg=lead_created");
        exit();

    } elseif ($action === 'assign') {
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $user_id = !empty($_POST['user_id']) && is_numeric($_POST['user_id']) ? intval($_POST['user_id']) : null;

        if ($lead_id <= 0) {
            if ($isAjax) jsonResponse(false, 'Invalid Lead ID.');
            header("Location: lead-generation.php?msg=error");
            exit();
        }

        $sql = "UPDATE leads SET assigned_to = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $lead_id]);

        $agentName = 'Unassigned';
        if ($user_id) {
            $uStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $uStmt->execute([$user_id]);
            $agentName = $uStmt->fetchColumn() ?: 'Unassigned';
        }

        if ($isAjax) jsonResponse(true, 'Lead assigned successfully.', ['agent_name' => $agentName, 'user_id' => $user_id]);
        header("Location: lead-generation.php?msg=user_assigned");
        exit();

    } elseif ($action === 'change_stage' || $action === 'update_status') {
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $new_stage = trim($_POST['new_stage'] ?? $_POST['status'] ?? '');

        if ($lead_id <= 0 || !in_array($new_stage, ['New', 'Follow-up', 'Quoted', 'Converted', 'Lost'])) {
            if ($isAjax) jsonResponse(false, 'Invalid stage or lead ID.');
            header("Location: lead-generation.php?msg=error");
            exit();
        }

        $sql = "UPDATE leads SET status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_stage, $lead_id]);

        if ($isAjax) jsonResponse(true, 'Lead status updated.', ['status' => $new_stage]);
        header("Location: lead-generation.php?msg=stage_updated");
        exit();

    } elseif ($action === 'delete') {
        $lead_id = intval($_POST['lead_id'] ?? $_GET['lead_id'] ?? 0);
        if ($lead_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->execute([$lead_id]);
            if ($isAjax) jsonResponse(true, 'Lead removed successfully.');
            header("Location: lead-generation.php?msg=lead_deleted");
            exit();
        }
    }
} catch (PDOException $e) {
    if ($isAjax) jsonResponse(false, 'Database Error: ' . $e->getMessage());
    die("Database Error: " . $e->getMessage());
}

if ($isAjax) jsonResponse(false, 'Unknown action.');
header("Location: lead-generation.php");
exit();
