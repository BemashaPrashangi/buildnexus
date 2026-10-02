<?php
require 'db.php';

$email = 'admin@buildnexus.com';
$new_password = 'password123'; // This will be your new password

// 1. Hash the password securely using YOUR server's algorithm
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// 2. Update the database
$sql = "UPDATE users SET password = :pass WHERE email = :email";
$stmt = $pdo->prepare($sql);

if ($stmt->execute([':pass' => $hashed_password, ':email' => $email])) {
    echo "<h1>✅ Success!</h1>";
    echo "<p>Admin password has been reset to: <strong>password123</strong></p>";
    echo "<a href='login.php'>Go to Login Page</a>";
} else {
    echo "<h1>❌ Error</h1>";
    echo "Could not update the database.";
}
?>