<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $message = htmlspecialchars($_POST['message']);
    $user_id = $_SESSION['user_id'];
    
    // Fetch linked project
    $stmt = $pdo->prepare("SELECT project_id FROM clients WHERE id = ?");
    $stmt->execute([$user_id]);
    $project_id = $stmt->fetchColumn();

    $sql = "INSERT INTO project_notes (project_id, user_id, note_text) VALUES (?, ?, ?)";
    $pdo->prepare($sql)->execute([$project_id, $user_id, $message]);
    echo json_encode(['status' => 'sent']);
}