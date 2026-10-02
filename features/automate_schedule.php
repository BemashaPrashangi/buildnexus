<?php
require_once '../db.php';

$estimate_id = $_GET['id'];

// 1. Get the items from the estimate
$stmt = $pdo->prepare("SELECT item_name FROM estimate_items WHERE estimate_id = ?");
$stmt->execute([$estimate_id]);
$items = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Simple logic: Group items into logical construction phases
$phases = [
    'Site Prep' => ['clearing', 'excavation', 'foundation'],
    'Structural' => ['framing', 'roofing', 'wooden'],
    'Finishing' => ['paint', 'cabinet', 'tile']
];

foreach ($phases as $phaseName => $keywords) {
    foreach ($items as $item) {
        foreach ($keywords as $kw) {
            if (strpos(strtolower($item), $kw) !== false) {
                // 3. Automatically insert into project_milestones
                $sql = "INSERT INTO project_milestones (project_id, phase_name, start_date, status) 
                        SELECT project_id, ?, CURDATE(), 'Upcoming' FROM estimates WHERE id = ?";
                $pdo->prepare($sql)->execute([$phaseName, $estimate_id]);
                break 2; // Move to next phase
            }
        }
    }
}

header("Location: ../master_schedule.php?msg=AutoScheduled");
?>