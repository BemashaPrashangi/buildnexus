<?php
require '../db.php';
try {
    // Create takeoffs table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS takeoffs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        takeoff_no VARCHAR(50) NOT NULL,
        project_name VARCHAR(100) NOT NULL,
        creator VARCHAR(100) NOT NULL,
        status VARCHAR(50) DEFAULT 'In Progress',
        img_url VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if empty
    $count = $pdo->query("SELECT COUNT(*) FROM takeoffs")->fetchColumn();
    
    if ($count == 0) {
        // Insert dummy data
        $dummy = [
            ['T-001', 'Luxury Villa in Kandy', 'John Doe', 'Completed', 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=100&h=100&fit=crop'],
            ['T-002', 'Colombo Office Complex', 'Jane Smith', 'In Progress', 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=100&h=100&fit=crop'],
            ['T-003', 'Galle Boutique Hotel', 'John Doe', 'Completed', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=100&h=100&fit=crop']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO takeoffs (takeoff_no, project_name, creator, status, img_url) VALUES (?, ?, ?, ?, ?)");
        foreach ($dummy as $row) {
            $stmt->execute($row);
        }
    }
} catch (Exception $e) {
    // Ignore error
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$action = $_POST['action'] ?? '';

if (!$action) {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: takeoffs.php');
    exit;
}

try {
    if ($action === 'create_takeoff') {
        $takeoff_no = 'T-' . rand(100, 999);
        $project_name = trim($_POST['project_name']);
        $creator = $_SESSION['full_name'] ?? 'Admin User'; // Default if not set
        $status = 'In Progress';
        $img = 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=100&h=100&fit=crop';
        
        $stmt = $pdo->prepare("INSERT INTO takeoffs (takeoff_no, project_name, creator, status, img_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$takeoff_no, $project_name, $creator, $status, $img]);
        $_SESSION['success'] = "New Takeoff ($takeoff_no) created successfully.";
    }
    elseif ($action === 'generate_estimate') {
        // Just mocking the generation action
        $takeoff_id = $_POST['takeoff_id'];
        $_SESSION['success'] = 'Estimate is being calculated in the background. You will be notified when it is complete.';
    }
    elseif ($action === 'share') {
        $_SESSION['success'] = 'Takeoff sharing link sent to recipient via email.';
    }
    elseif ($action === 'archive') {
        $takeoff_id = $_POST['takeoff_id'];
        $stmt = $pdo->prepare("UPDATE takeoffs SET status = 'Archived' WHERE id = ?");
        $stmt->execute([$takeoff_id]);
        $_SESSION['success'] = 'Takeoff archived successfully.';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Database Error: ' . $e->getMessage();
}

header('Location: takeoffs.php');
exit;
