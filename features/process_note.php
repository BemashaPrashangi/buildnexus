<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['note_text'])) {
    $note = htmlspecialchars($_POST['note_text']);
    $user_id = $_SESSION['user_id'];
    
    // Fetch project ID
    $stmt = $pdo->prepare("SELECT project_id FROM clients WHERE id = ?");
    $stmt->execute([$user_id]);
    $project_id = $stmt->fetchColumn();

    try {
        $sql = "INSERT INTO project_notes (project_id, user_id, note_text) VALUES (?, ?, ?)";
        $pdo->prepare($sql)->execute([$project_id, $user_id, $note]);
        header("Location: client_notes.php?success=1");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>