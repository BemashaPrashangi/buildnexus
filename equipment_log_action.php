<?php
// Root proxy / fallback for equipment_log_action.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/features/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $equipment_id = intval($_POST['equipment_id'] ?? 0);
    $project_id = intval($_POST['project_id'] ?? 0);
    $foreman_id = intval($_SESSION['user_id'] ?? 1);
    $hours = floatval($_POST['hours_used'] ?? 0);
    $fuel = floatval($_POST['fuel_liters'] ?? 0);
    $log_date = !empty($_POST['log_date']) ? trim($_POST['log_date']) : date('Y-m-d');
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;

    if ($equipment_id <= 0 || $project_id <= 0) {
        header("Location: foreman_logs.php?error=invalid_input");
        exit();
    }

    try {
        $sql = "INSERT INTO equipment_logs (equipment_id, project_id, foreman_id, hours_used, fuel_liters, log_date, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$equipment_id, $project_id, $foreman_id, $hours, $fuel, $log_date, $notes]);

        $updEq = $pdo->prepare("UPDATE equipment SET current_project_id = ?, status = 'In Use' WHERE id = ?");
        $updEq->execute([$project_id, $equipment_id]);

        header("Location: foreman_logs.php?msg=equipment_logged");
        exit();
    } catch (PDOException $e) {
        error_log("Equipment Action Root Error: " . $e->getMessage());
        header("Location: foreman_logs.php?error=db_error");
        exit();
    }
} else {
    header("Location: foreman_logs.php");
    exit();
}