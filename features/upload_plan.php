<?php
require_once '../db.php';
session_start();

// Security: Check if user is authorized Client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    die("Unauthorized access.");
}

// Fetch user/client info for the UI
$uploaded_by = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.full_name, c.project_id FROM users u LEFT JOIN clients c ON u.id = c.id WHERE u.id = ?");
$stmt->execute([$uploaded_by]);
$user_data = $stmt->fetch();
$client_name = $user_data['full_name'] ?? 'Client';
$project_id = $user_data['project_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_name = $_POST['plan_name'];
    
    if (!$project_id) {
        $msg = "Error: You are not assigned to any project yet.";
    } elseif ($_FILES["file_2d"]["size"] > 10485760) {
        // 10 MB limit (10 * 1024 * 1024 bytes)
        $msg = "Error: File size must be less than 10 MB.";
    } else {
        $target_dir = "../uploads/plans/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES["file_2d"]["name"], PATHINFO_EXTENSION);
        $unique_2d_name = time() . "_" . bin2hex(random_bytes(4)) . "." . $file_extension;
        $target_file = $target_dir . $unique_2d_name;

        if (move_uploaded_file($_FILES["file_2d"]["tmp_name"], $target_file)) {
            try {
                $sql = "INSERT INTO floor_plans (project_id, plan_name, file_2d, file_3d, uploaded_by, status) 
                        VALUES (?, ?, ?, ?, ?, 'Pending')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $project_id, 
                    $plan_name, 
                    $unique_2d_name, 
                    "", 
                    $uploaded_by
                ]);
                $msg_success = "Your house plan sketch has been uploaded successfully!";
            } catch (PDOException $e) {
                error_log("Upload Database Error: " . $e->getMessage());
                $msg = "System Error: Could not save plan data.";
            }
        } else {
            $msg = "Error: The file could not be moved. Check folder permissions.";
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
    <title>Upload Floor Plan - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #333; }
        .sidebar { width: 240px; height: 100vh; background: #fff; border-right: 1px solid #eee; position: fixed; padding: 1.25rem; font-size: 0.85rem; }
        .main-content { margin-left: 240px; padding: 2rem; background: #fcfcfc; min-height: 100vh; }
        .nav-link-client { display: flex; align-items: center; padding: 8px 12px; color: #666; text-decoration: none; border-radius: 6px; margin-bottom: 4px; }
        .nav-link-client:hover, .nav-link-client.active { background: #f8f9fa; color: #000; font-weight: 500; }
        .nav-link-client i { margin-right: 12px; font-size: 1rem; }
        .nexus-card { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 2rem; max-width: 600px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="mb-4 ps-2 fw-bold"><i class="bi bi-box-fill text-success me-2"></i>BuildNexus</div>
        <nav>
            <a href="../client_dashboard.php" class="nav-link-client"><i class="bi bi-grid"></i> Dashboard</a>
            <a href="client_timeline.php" class="nav-link-client"><i class="bi bi-calendar3"></i> Project Schedule</a>
            <a href="pay_online.php" class="nav-link-client"><i class="bi bi-credit-card"></i> Pay Online</a>
            <a href="client_change_orders.php" class="nav-link-client"><i class="bi bi-arrow-left-right"></i> Change Orders</a>
            <a href="client_chat.php" class="nav-link-client"><i class="bi bi-chat-dots"></i> Chat</a>
            <a href="client_notes.php" class="nav-link-client"><i class="bi bi-sticky"></i> Notes</a>
            <a href="client_rfis.php" class="nav-link-client"><i class="bi bi-file-earmark-text"></i> RFIs</a>
            <a href="upload_plan.php" class="nav-link-client active"><i class="bi bi-upload"></i> Upload Plans</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">Upload Your House Plan</h5>
            <div class="small text-muted">Welcome, <?= htmlspecialchars($client_name) ?> | <a href="../logout.php" class="text-dark fw-bold">Logout</a></div>
        </div>

        <?php if(isset($msg)): ?>
            <div class="alert alert-danger shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if(isset($msg_success)): ?>
            <div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg_success) ?></div>
        <?php endif; ?>

        <div class="nexus-card shadow-sm">
            <h6 class="fw-bold mb-4">Upload a Sketch or Image of your Floor Plan</h6>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Plan Name</label>
                    <input type="text" name="plan_name" class="form-control bg-light" placeholder="e.g. Ground Floor Sketch" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Upload File (Image/Sketch)</label>
                    <input type="file" name="file_2d" class="form-control bg-light" accept="image/*" required>
                    <div class="form-text text-muted small">Max file size: 10 MB</div>
                </div>
                <button type="submit" class="btn btn-success fw-bold w-100 py-2"><i class="bi bi-cloud-upload me-2"></i> Upload Plan</button>
            </form>
        </div>
    </main>
</body>
</html>