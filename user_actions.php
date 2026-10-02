<?php
require 'db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('Active', 'Inactive', 'Invited') DEFAULT 'Active'");
    echo "Added status column";
} catch (Exception $e) {
    // If it already exists, it will throw an error, which is fine
}
session_start();

$action = $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? 0;

if (!$action) {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: user_management.php');
    exit;
}

try {
    if ($action === 'create_user') {
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = trim($_POST['role']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        
        // Check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $_SESSION['error'] = 'A user with this email already exists.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'Active', NOW())");
            $stmt->execute([$name, $email, $password, $role]);
            $_SESSION['success'] = 'New user created successfully.';
        }
    }
    elseif ($action === 'edit_user') {
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $user_id]);
        $_SESSION['success'] = 'User details updated successfully.';
    } 
    elseif ($action === 'change_role') {
        $role = trim($_POST['role']);
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);
        $_SESSION['success'] = 'User role updated successfully.';
    }
    elseif ($action === 'resend_invite') {
        // Mock email sending
        $_SESSION['success'] = 'Invitation email has been resent to the user.';
    }
    elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'Inactive' WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success'] = 'User has been deactivated.';
    }
    elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'Active' WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success'] = 'User has been activated.';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
}

header('Location: user_management.php');
exit;
