<?php
require 'db.php'; 
session_start();

// Security: Allow only Admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_general'])) {
        // Logic to update company settings (Simulated for this implementation)
        $success_msg = "General settings updated successfully.";
    } elseif (isset($_POST['update_password'])) {
        $current_pw = $_POST['current_password'];
        $new_pw = $_POST['new_password'];
        $confirm_pw = $_POST['confirm_password'];

        if ($new_pw !== $confirm_pw) {
            $error_msg = "New passwords do not match.";
        } else {
            // Real Logic: Update the user's password in the database
            $hashed_pw = password_hash($new_pw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_pw, $user_id]);
            $success_msg = "Password updated successfully.";
        }
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
    <title>System Settings - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (Zero Tailwind) --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; overflow: hidden; }
        
        .sidebar { width: 260px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; padding: 1.5rem 1rem; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 2rem; text-decoration: none; color: #1e293b; font-weight: 700; font-size: 1.25rem; }
        .nav-link-custom { display: flex; align-items: center; gap: 12px; padding: 10px 12px; font-size: 0.875rem; color: #64748b; text-decoration: none; border-radius: 8px; transition: 0.2s; }
        .nav-link-custom:hover { background: #f1f5f9; color: #1e293b; }
        .nav-link-custom.active { background: #f1f5f9; color: #2563eb; font-weight: 600; }
        .nav-category { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 1.5rem 0 0.5rem 10px; letter-spacing: 0.05em; }

        .main-content { margin-left: 260px; padding: 3rem; height: 100vh; overflow-y: auto; }
        
        /* Tab Controls */
        .settings-nav { background: #f1f5f9; padding: 5px; border-radius: 10px; display: inline-flex; margin-bottom: 2rem; width: 100%; max-width: 900px; }
        .nav-tab { flex: 1; text-align: center; padding: 10px; cursor: pointer; border-radius: 8px; font-size: 0.9rem; font-weight: 500; color: #64748b; transition: 0.2s; }
        .nav-tab.active { background: #fff; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

        /* Form Container */
        .settings-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem; width: 100%; max-width: 900px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .form-label { font-weight: 600; font-size: 0.9rem; color: #1e293b; margin-bottom: 0.75rem; }
        .form-control { border-radius: 8px; border: 1px solid #e2e8f0; padding: 12px; font-size: 0.95rem; background-color: #fcfcfc; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.05); background-color: #fff; }

        .btn-nexus { background-color: #22c55e; color: #fff; font-weight: 600; border: none; border-radius: 8px; padding: 12px 24px; transition: 0.2s; }
        .btn-nexus:hover { background-color: #16a34a; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="index.php" class="sidebar-brand">
            <img src="images/logo.png" alt="BuildNexus" style="width: 32px;" onerror="this.src='https://via.placeholder.com/32?text=B'">
            <span>BuildNexus</span>
        </a>
        <nav>
            <a href="admin_dashboard.php" class="nav-link-custom"><i class="bi bi-grid"></i> Dashboard</a>
            <p class="nav-category">Administration</p>
            <a href="user_management.php" class="nav-link-custom"><i class="bi bi-people"></i> User Management</a>
            <a href="access_levels.php" class="nav-link-custom"><i class="bi bi-shield-lock"></i> Access Levels</a>
            <a href="system_settings.php" class="nav-link-custom active"><i class="bi bi-gear"></i> System Settings</a>
        </nav>
        <a href="logout.php" class="mt-auto nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </aside>

    <main class="main-content">
        <h1 class="h3 fw-bold mb-4">System Settings</h1>

        <div class="settings-nav">
            <div class="nav-tab active" id="tab-general" onclick="switchTab('general')">General</div>
            <div class="nav-tab" id="tab-password" onclick="switchTab('password')">Password</div>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success border-0 rounded-3 small mb-4" style="max-width: 900px;"><?= $success_msg ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger border-0 rounded-3 small mb-4" style="max-width: 900px;"><?= $error_msg ?></div>
        <?php endif; ?>

        <div id="section-general" class="settings-card">
            <h4 class="fw-bold mb-1">General Settings</h4>
            <p class="text-muted small mb-4">Update your company's information.</p>
            
            <form action="system_settings.php" method="POST">
                <input type="hidden" name="update_general" value="1">
                <div class="mb-3">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="BuildNexus Construction">
                </div>
                <div class="mb-3">
                    <label class="form-label">Company Email</label>
                    <input type="email" name="company_email" class="form-control" value="contact@buildnexus.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Company Phone</label>
                    <input type="text" name="company_phone" class="form-control" value="+94 11 234 5678">
                </div>
                <div class="mb-4">
                    <label class="form-label">Company Address</label>
                    <input type="text" name="company_address" class="form-control" value="123 Main Street, Colombo, Sri Lanka">
                </div>
                <button type="submit" class="btn-nexus">Save Changes</button>
            </form>
        </div>

        <div id="section-password" class="settings-card d-none">
            <h4 class="fw-bold mb-1">Change Password</h4>
            <p class="text-muted small mb-4">Update your account password. It's a good practice to use a strong, unique password.</p>
            
            <form action="system_settings.php" method="POST">
                <input type="hidden" name="update_password" value="1">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn-nexus">Update Password</button>
            </form>
        </div>
    </main>

    <script>
        function switchTab(tab) {
            // Sections
            document.getElementById('section-general').classList.add('d-none');
            document.getElementById('section-password').classList.add('d-none');
            // Tabs
            document.getElementById('tab-general').classList.remove('active');
            document.getElementById('tab-password').classList.remove('active');

            if (tab === 'general') {
                document.getElementById('section-general').classList.remove('d-none');
                document.getElementById('tab-general').classList.add('active');
            } else {
                document.getElementById('section-password').classList.remove('d-none');
                document.getElementById('tab-password').classList.add('active');
            }
        }
    </script>
</body>
</html>