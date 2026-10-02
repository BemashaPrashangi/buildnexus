<?php
// Root proxy / fallback for safety_toolbox_action.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/features/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = intval($_POST['project_id'] ?? 0);
    $topic_id = !empty($_POST['topic_id']) ? intval($_POST['topic_id']) : null;
    $custom_topic = !empty($_POST['custom_topic']) ? trim($_POST['custom_topic']) : null;
    $foreman_id = intval($_SESSION['user_id']);
    $attendees = intval($_POST['attendees_count'] ?? 0);
    $meeting_date = !empty($_POST['meeting_date']) ? trim($_POST['meeting_date']) : date('Y-m-d');
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;

    if ($project_id <= 0) {
        header("Location: foreman_logs.php?error=invalid_project");
        exit();
    }

    try {
        $sql = "INSERT INTO safety_meeting_logs (topic_id, custom_topic, project_id, foreman_id, attendees_count, meeting_date, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$topic_id, $custom_topic, $project_id, $foreman_id, $attendees, $meeting_date, $notes]);

        header("Location: foreman_logs.php?msg=safety_logged");
        exit();
    } catch (PDOException $e) {
        error_log("Safety Action Root Error: " . $e->getMessage());
        header("Location: foreman_logs.php?error=db_error");
        exit();
    }
} else {
    header("Location: foreman_logs.php");
    exit();
}