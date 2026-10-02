<?php
require_once '../db.php';
session_start();

// 1. Security & Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['project_file'])) {
    $file = $_FILES['project_file'];
    $user_id = $_SESSION['user_id'];
    
    // 2. Fetch Project ID linked to this user
    $stmt = $pdo->prepare("SELECT project_id FROM clients WHERE id = ?");
    $stmt->execute([$user_id]);
    $project_id = $stmt->fetchColumn();

    // If no project is linked, fallback to a default or error
    if (!$project_id) {
        die("Error: No project linked to your account.");
    }

    // 3. File Configuration
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $original_name = basename($file['name']);
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    // Generate a unique name to avoid conflicts: e.g., 173528_document.pdf
    $unique_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $original_name);
    $target_path = $upload_dir . $unique_name;

    // 4. Validation: Size and Type
    $max_size = 25 * 1024 * 1024; // 25MB
    $allowed_types = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx'];

    if ($file['size'] > $max_size) {
        die("Error: File is too large. Max size is 25MB.");
    }

    if (!in_array($file_extension, $allowed_types)) {
        die("Error: File type not allowed.");
    }

    // 5. Physical Upload & Database Entry
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Calculate human-readable size
        $size_kb = $file['size'] / 1024;
        $file_size_display = ($size_kb > 1024) ? number_format($size_kb / 1024, 1) . ' MB' : number_format($size_kb, 0) . ' KB';

        try {
            $sql = "INSERT INTO project_files (project_id, file_name, file_path, file_size, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([
                $project_id, 
                $original_name, 
                $target_path, 
                $file_size_display, 
                $user_id
            ]);

            header("Location: file_sharing.php?upload=success");
            exit();
        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    } else {
        die("Error: Failed to move uploaded file.");
    }
}
?>