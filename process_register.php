<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Capture data matching your HTML form names
    // We use 'full_name' because that is what we set in the HTML form
    $full_name    = $_POST['full_name']; 
    $email        = $_POST['email'];
    $phone        = $_POST['phone_number'];
    $address      = $_POST['address'];
    $role         = $_POST['role'];
    $password     = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    // 2. Validate Password Match
    if ($password !== $confirm_pass) {
        // Javascript alert to show error and go back
        echo "<script>alert('Error: Passwords do not match!'); window.history.back();</script>";
        exit();
    }

    // 3. Check if Email Already Exists
    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        echo "<script>alert('Error: Email is already registered!'); window.history.back();</script>";
        exit();
    }

    // 4. Hash Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        // 5. Insert into Database
        $sql = "INSERT INTO users (full_name, email, phone_number, password, address, role) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$full_name, $email, $phone, $hashed_password, $address, $role]);

        // Success!
        echo "<script>alert('Account created successfully! Please Login.'); window.location.href='login.php';</script>";
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>