<?php
require 'db.php'; 
session_start();


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$message = "";


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_user'])) {
    $full_name = $_POST['full_name'];
    $email     = $_POST['email'];
    $role      = $_POST['role'];
    $phone     = $_POST['phone'];
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT); // Password Encryption

    try {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, role, phone_number, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $email, $role, $phone, $password]);
        $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>User created successfully!</div>";
    } catch (PDOException $e) {
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Error: Email already exists!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- BuildNexus Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/buildnexus/images/logo.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/buildnexus/images/logo.png?v=2">
    <link rel="shortcut icon" href="/buildnexus/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="/buildnexus/images/logo.png?v=2">
    <meta charset="UTF-8">
    <title>Add User - BuildNexus Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 flex h-screen">

    <aside class="w-64 bg-slate-900 text-slate-300 p-6">
        <h2 class="text-white font-bold text-xl mb-10">BuildNexus</h2>
        <nav class="space-y-4">
            <a href="admin_dashboard.php" class="block py-2 px-3 rounded hover:bg-slate-800">Dashboard</a>
            <a href="admin_add_user.php" class="block py-2 px-3 rounded bg-slate-800 text-white font-bold">Add New User</a>
        </nav>
    </aside>

    <main class="flex-1 p-12 overflow-y-auto">
        <div class="max-w-2xl bg-white p-8 rounded-2xl shadow-lg border border-slate-200">
            <h1 class="text-2xl font-bold text-slate-800 mb-6">Create New Staff/Client Account</h1>
            
            <?php echo $message; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Sunil Perera" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Email Address</label>
                        <input type="email" name="email" class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-indigo-500" placeholder="email@buildnexus.com" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-indigo-500" placeholder="+94 7X XXX XXXX">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">System Role</label>
                    <select name="role" class="w-full border rounded-lg p-3 bg-slate-50" required>
                        <option value="Project Manager">Project Manager</option>
                        <option value="Foreman">Foreman</option>
                        <option value="Client">Client</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Assign Password</label>
                    <input type="password" name="password" class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-indigo-500" placeholder="••••••••" required>
                </div>

                <div class="pt-4 flex gap-4">
                    <button type="submit" name="create_user" class="flex-1 bg-indigo-600 text-white py-3 rounded-lg font-bold hover:bg-indigo-700 transition">
                        Create Account
                    </button>
                    <a href="admin_dashboard.php" class="flex-1 bg-slate-100 text-center py-3 rounded-lg font-bold text-slate-600 hover:bg-slate-200 transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>