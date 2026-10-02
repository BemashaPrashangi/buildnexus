<?php
require_once '../db.php';
session_start();

$estimate_id = $_GET['id'] ?? null;

if (!$estimate_id) {
    die("Error: No Estimate ID provided.");
}

try {
    $pdo->beginTransaction();

    // 1. Fetch Estimate and associated Project ID
    $stmt = $pdo->prepare("SELECT project_id, status FROM estimates WHERE id = ?");
    $stmt->execute([$estimate_id]);
    $estimate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estimate) die("Estimate not found.");

    // 2. Fetch line items for this estimate
    $stmt_items = $pdo->prepare("SELECT item_name FROM estimate_items WHERE estimate_id = ?");
    $stmt_items->execute([$estimate_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // 3. Automated Milestone Scheduling Algorithm
    $schedule_map = [
        'Foundation Phase' => ['cement', 'concrete', 'excavation', 'iron', 'sand'],
        'Structure Phase'  => ['brick', 'block', 'wall', 'pillar', 'slab'],
        'Roofing Phase'    => ['truss', 'tile', 'wood', 'sheet', 'asbestos'],
        'Finishing Phase'  => ['paint', 'putty', 'tile', 'floor', 'door', 'window']
    ];

    $created_phases = [];
    $start_date = date('Y-m-d');

    foreach ($items as $row) {
        $item_name = strtolower($row['item_name']);
        $assigned_phase = 'General Construction'; // Default

        foreach ($schedule_map as $phase => $keywords) {
            foreach ($keywords as $key) {
                if (strpos($item_name, $key) !== false) {
                    $assigned_phase = $phase;
                    break 2;
                }
            }
        }

        // 4. Create Milestone if new
        if (!isset($created_phases[$assigned_phase])) {
            $end_date = date('Y-m-d', strtotime($start_date . ' + 14 days'));
            $sql_ms = "INSERT INTO project_milestones (project_id, phase_name, start_date, end_date, status) VALUES (?, ?, ?, ?, 'Upcoming')";
            $pdo->prepare($sql_ms)->execute([$estimate['project_id'], $assigned_phase, $start_date, $end_date]);
            
            $created_phases[$assigned_phase] = true;
            $start_date = $end_date; // Sequence phases
        }

        // 5. Create specific Task
        $sql_task = "INSERT INTO tasks (project_id, task_title, due_date, status) VALUES (?, ?, ?, 'To Do')";
        $pdo->prepare($sql_task)->execute([$estimate['project_id'], "Install: " . $row['item_name'], $start_date]);
    }

    $pdo->commit();
    header("Location: ../project_schedule.php?msg=schedule_ready");
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Database Error: " . $e->getMessage());
}