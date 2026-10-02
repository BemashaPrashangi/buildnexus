<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo "<script>alert('Please enter both email and password.'); window.history.back();</script>";
        exit();
    }

    try {
        // 1. Fetch user by email (case-insensitive & trimmed)
        $stmt = $pdo->prepare("SELECT id, full_name, email, password, role FROM users WHERE LOWER(email) = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Verify password (supports bcrypt hash and plain-text fallback for seed data)
        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            
            // 3. Prevent session fixation attacks
            session_regenerate_id(true);

            // 4. Set session variables directly from database record
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['user_name'] = !empty($user['full_name']) ? $user['full_name'] : 'User';
            $_SESSION['email']     = $user['email'];

            // 5. Automatic role-based routing
            switch ($user['role']) {
                case 'Admin':
                    header("Location: admin_dashboard.php");
                    break;
                case 'Project Manager':
                    header("Location: dashboard.php");
                    break;
                case 'Foreman':
                    header("Location: foreman_logs.php");
                    break;
                case 'Client':
                    header("Location: client_dashboard.php");
                    break;
                default:
                    echo "<script>alert('Error: Assigned user role is invalid.'); window.location.href = 'login.php';</script>";
                    break;
            }
            exit();

        } else {
            // Invalid credentials
            echo "<script>alert('Invalid Email or Password'); window.history.back();</script>";
            exit();
        }

    } catch (PDOException $e) {
        echo "<script>alert('Database error occurred. Please try again later.'); window.history.back();</script>";
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>