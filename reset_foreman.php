<?php
require 'db.php';

// Resetting Sunil Gamage's (Foreman) password to: password123
$email = 'foreman@buildnexus.com';
$new_password = 'password123'; 
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = ? WHERE email = ?";
$stmt = $pdo->prepare($sql);

if ($stmt->execute([$hashed_password, $email])) {
    echo "<h1>Foreman Password Reset Successful!</h1>";
    echo "<p>Foreman Email: foreman@buildnexus.com</p>";
    echo "<p>New Password: password123</p>";
    echo "<a href='login.php'>Go to Login</a>";
} else {
    echo "Error updating password.";
}
?>