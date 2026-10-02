<?php
require '../db.php';
try {
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS bids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bid_no VARCHAR(50) NOT NULL,
        project_id INT NULL,
        project_name VARCHAR(100) NOT NULL,
        subcontractor VARCHAR(100) NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        status VARCHAR(50) DEFAULT 'Submitted',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Check if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM bids")->fetchColumn();
    
    if ($count == 0) {
        // Insert dummy data
        $dummy = [
            ['BID-2024-001', 'Luxury Villa in Kandy', 'Lanka Construction', 5000000, 'Submitted'],
            ['BID-2024-002', 'Colombo Office Complex', 'Metro Builders', 12500000, 'Submitted'],
            ['BID-2024-003', 'Galle Boutique Hotel', 'Coastal Contractors', 8200000, 'Submitted'],
            ['BID-2024-004', 'Luxury Villa in Kandy', 'Kandy Homes', 4800000, 'Submitted'],
            ['BID-2024-005', 'Highway Expansion E01', 'Mega Engineering', 250000000, 'Submitted']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO bids (bid_no, project_name, subcontractor, amount, status) VALUES (?, ?, ?, ?, ?)");
        foreach ($dummy as $row) {
            $stmt->execute($row);
        }
    }
} catch (Exception $e) {
    // Ignore error
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$action = $_POST['action'] ?? '';

if (!$action) {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: proposals.php');
    exit;
}

try {
    if ($action === 'create_bid') {
        $bid_no = 'BID-' . date('Y') . '-' . rand(100, 999);
        $project_name = trim($_POST['project_name']);
        $subcontractor = trim($_POST['subcontractor']);
        $amount = (float) $_POST['amount'];
        
        $stmt = $pdo->prepare("INSERT INTO bids (bid_no, project_name, subcontractor, amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$bid_no, $project_name, $subcontractor, $amount]);
        $_SESSION['success'] = "New bid request ($bid_no) created successfully.";
    }
    elseif ($action === 'award_bid') {
        $bid_id = $_POST['bid_id'];
        $stmt = $pdo->prepare("UPDATE bids SET status = 'Awarded' WHERE id = ?");
        $stmt->execute([$bid_id]);
        $_SESSION['success'] = 'Bid has been successfully awarded!';
    }
    elseif ($action === 'send_message') {
        // Mock sending message
        $_SESSION['success'] = 'Message sent to subcontractor successfully.';
    }
    elseif ($action === 'archive_bid') {
        $bid_id = $_POST['bid_id'];
        $stmt = $pdo->prepare("UPDATE bids SET status = 'Archived' WHERE id = ?");
        $stmt->execute([$bid_id]);
        $_SESSION['success'] = 'Bid archived.';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Database Error: ' . $e->getMessage();
}

header('Location: proposals.php');
exit;
