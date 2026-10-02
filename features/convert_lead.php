<?php
require_once __DIR__ . '/../db.php'; 
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

$lead_id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($lead_id <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid Lead ID.']);
        exit();
    }
    header("Location: lead-generation.php?msg=error_no_id");
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Fetch Lead Data
    $stmt_fetch = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt_fetch->execute([$lead_id]);
    $lead = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        $pdo->rollBack();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Lead not found.']);
            exit();
        }
        header("Location: lead-generation.php?msg=error_not_found");
        exit();
    }

    // 2. Insert or locate Client in clients table
    $clientId = null;
    if (!empty($lead['email'])) {
        $cStmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
        $cStmt->execute([$lead['email']]);
        $existingClient = $cStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingClient) {
            $clientId = $existingClient['id'];
        }
    }

    if (!$clientId) {
        $insClient = $pdo->prepare("
            INSERT INTO clients (full_name, email, company, status)
            VALUES (?, ?, ?, 'Active')
        ");
        $companyName = !empty($lead['company_name']) ? $lead['company_name'] : $lead['customer_name'] . ' Holdings';
        $insClient->execute([$lead['customer_name'], $lead['email'], $companyName]);
        $clientId = $pdo->lastInsertId();
    }

    // 3. Generate Project Code (e.g. PRJ-2026-001)
    $year = date('Y');
    $stmtCode = $pdo->query("SELECT MAX(id) as max_id FROM projects");
    $maxRow = $stmtCode->fetch();
    $nextPrjId = ($maxRow['max_id'] ?? 0) + 1;
    $project_code = sprintf("PRJ-%s-%03d", $year, $nextPrjId);

    // 4. Create Project
    $projectName = trim($lead['project_type']) . " - " . trim($lead['customer_name']);
    $budget = floatval($lead['estimated_budget'] ?? 0.00);
    $location = !empty($lead['site_location']) ? $lead['site_location'] : 'Colombo, Sri Lanka';
    $pmId = !empty($lead['assigned_to']) ? intval($lead['assigned_to']) : intval($_SESSION['user_id'] ?? 1);

    $sql_project = "
        INSERT INTO projects (project_name, project_code, client_id, client_name, pm_id, budget, location, start_date, end_date, stage, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'Planning', 'Active', NOW())
    ";
    $stmt_proj = $pdo->prepare($sql_project);
    $stmt_proj->execute([
        $projectName,
        $project_code,
        $clientId,
        $lead['customer_name'],
        $pmId,
        $budget,
        $location
    ]);

    $new_project_id = $pdo->lastInsertId();

    // 5. Update client's project_id
    if ($clientId && $new_project_id) {
        $updClient = $pdo->prepare("UPDATE clients SET project_id = ? WHERE id = ?");
        $updClient->execute([$new_project_id, $clientId]);
    }

    // 6. Create Initial Milestone in project_milestones if table exists
    try {
        $sql_milestone = "
            INSERT INTO project_milestones (project_id, title, event_type, start_date, end_date, status) 
            VALUES (?, 'Initial Project Kickoff & Planning', 'Milestone', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'In Progress')
        ";
        $stmt_ms = $pdo->prepare($sql_milestone);
        $stmt_ms->execute([$new_project_id]);
    } catch (Exception $e) {
        // Fallback for different milestone column schema
        try {
            $sql_ms2 = "
                INSERT INTO project_milestones (project_id, phase_name, start_date, end_date, status) 
                VALUES (?, 'Initial Project Kickoff & Planning', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'In Progress')
            ";
            $pdo->prepare($sql_ms2)->execute([$new_project_id]);
        } catch (Exception $e2) {}
    }

    // 7. Update Lead Status to 'Converted'
    $stmt_update = $pdo->prepare("UPDATE leads SET status = 'Converted' WHERE id = ?");
    $stmt_update->execute([$lead_id]);

    $pdo->commit();

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'project_id' => $new_project_id,
            'project_name' => $projectName,
            'project_code' => $project_code,
            'message' => "Lead successfully converted to Project '{$projectName}'!"
        ]);
        exit();
    }

    header("Location: projects.php?msg=converted_success&project_id=" . $new_project_id . "&lead_id=" . $lead_id);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Conversion failed: ' . $e->getMessage()]);
        exit();
    }
    header("Location: lead-generation.php?msg=error&error_details=" . urlencode($e->getMessage()));
    exit();
}