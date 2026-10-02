<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

function jsonResp($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

if ($action === 'assign_project') {
    $equipment_id = intval($_POST['equipment_id'] ?? 0);
    $project_id = !empty($_POST['project_id']) && is_numeric($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $status = $project_id ? 'In Use' : 'Available';

    if ($equipment_id <= 0) {
        if ($isAjax) jsonResp(false, 'Invalid Equipment ID.');
        header("Location: equipment-logs.php?error=invalid_id");
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE equipment SET current_project_id = ?, status = ? WHERE id = ?");
        $stmt->execute([$project_id, $status, $equipment_id]);

        if ($isAjax) jsonResp(true, 'Equipment successfully reallocated.', ['status' => $status]);
        header("Location: equipment-logs.php?msg=reallocated");
        exit();
    } catch (PDOException $e) {
        if ($isAjax) jsonResp(false, 'Database error: ' . $e->getMessage());
        header("Location: equipment-logs.php?error=db_error");
        exit();
    }
}

if ($action === 'schedule_maintenance') {
    $equipment_id = intval($_POST['equipment_id'] ?? 0);
    $next_service_date = !empty($_POST['next_service_date']) ? trim($_POST['next_service_date']) : null;
    $set_maintenance = isset($_POST['set_maintenance']) && $_POST['set_maintenance'] == '1';

    if ($equipment_id <= 0 || empty($next_service_date)) {
        if ($isAjax) jsonResp(false, 'Please specify a valid service date.');
        header("Location: equipment-logs.php?error=invalid_input");
        exit();
    }

    try {
        if ($set_maintenance) {
            $stmt = $pdo->prepare("UPDATE equipment SET next_service_date = ?, status = 'Maintenance' WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE equipment SET next_service_date = ? WHERE id = ?");
        }
        $stmt->execute([$next_service_date, $equipment_id]);

        if ($isAjax) jsonResp(true, 'Maintenance scheduled successfully.');
        header("Location: equipment-logs.php?msg=maintenance_scheduled");
        exit();
    } catch (PDOException $e) {
        if ($isAjax) jsonResp(false, 'Database error: ' . $e->getMessage());
        header("Location: equipment-logs.php?error=db_error");
        exit();
    }
}

if ($action === 'remove_equipment') {
    $equipment_id = intval($_POST['equipment_id'] ?? $_GET['equipment_id'] ?? 0);
    $mode = $_POST['mode'] ?? $_GET['mode'] ?? 'decommission'; // decommission or delete

    if ($equipment_id <= 0) {
        if ($isAjax) jsonResp(false, 'Invalid Equipment ID.');
        header("Location: equipment-logs.php?error=invalid_id");
        exit();
    }

    try {
        if ($mode === 'delete_permanently') {
            $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
            $stmt->execute([$equipment_id]);
            $msg = 'deleted';
        } else {
            $stmt = $pdo->prepare("UPDATE equipment SET status = 'Decommissioned', current_project_id = NULL WHERE id = ?");
            $stmt->execute([$equipment_id]);
            $msg = 'decommissioned';
        }

        if ($isAjax) jsonResp(true, 'Equipment status updated.');
        header("Location: equipment-logs.php?msg={$msg}");
        exit();
    } catch (PDOException $e) {
        if ($isAjax) jsonResp(false, 'Database error: ' . $e->getMessage());
        header("Location: equipment-logs.php?error=db_error");
        exit();
    }
}

if ($isAjax) jsonResp(false, 'Unsupported action.');
header("Location: equipment-logs.php");
exit();
