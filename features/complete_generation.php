<?php
require_once '../db.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

$plan_id = $_GET['id'] ?? null;

if (!$plan_id) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit();
}

try {
   
    // In a live system, this GLB file would be generated from a 3D Geometry CAD service.
    // We are assigning a standard spatial mesh name that our Three.js viewer will recognize.
    $generated_model_path = "spatial_mesh_" . $plan_id . ".glb";

    // Update both the status and the actual 3D model reference
    $stmt = $pdo->prepare("UPDATE floor_plans SET 
        status = 'Completed', 
        file_3d = ? 
        WHERE id = ?");
    
    $result = $stmt->execute([$generated_model_path, $plan_id]);

    if ($result) {
        // Return success and the new path to the frontend
        echo json_encode([
            'success' => true, 
            'model_path' => $generated_model_path,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
    }

} catch (PDOException $e) {
    // Handle SQL errors specifically for the floor_plans table
    echo json_encode(['success' => false, 'error' => 'SQL Error: ' . $e->getMessage()]);
}