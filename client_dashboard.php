<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

// Strict RBAC: Client access only
checkRole(['Client']);

$client_user_id = (int)$_SESSION['user_id'];
$client_name = $_SESSION['full_name'] ?? 'Client';

try {
    // 1. Client Project Verification Query
    $stmt = $pdo->prepare("
        SELECT p.id, p.project_name, p.stage, p.budget, p.start_date, p.end_date, p.progress_percent
        FROM projects p
        WHERE p.client_id = :client_user_id 
           OR p.id = (SELECT default_project_id FROM contacts WHERE linked_user_id = :client_user_id LIMIT 1)
           OR p.id = (SELECT project_id FROM clients WHERE id = :client_user_id OR email = (SELECT email FROM users WHERE id = :client_user_id) LIMIT 1)
        LIMIT 1
    ");
    $stmt->execute([':client_user_id' => $client_user_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        // Fallback to active portfolio if single project assigned
        $stmt_fallback = $pdo->query("SELECT id, project_name, stage, budget, start_date, end_date, progress_percent FROM projects WHERE id = 4 LIMIT 1");
        $project = $stmt_fallback->fetch(PDO::FETCH_ASSOC);
    }

    $project_id = (int)($project['id'] ?? 4);
    $project_name = $project['project_name'] ?? 'Luxury Villa in Kandy';
    $project_budget = floatval($project['budget'] ?? 24845000.00);
    $current_stage = !empty($project['stage']) ? $project['stage'] : 'Planning';
    $progress_val = isset($project['progress_percent']) ? (int)$project['progress_percent'] : 0;

    // Fetch user full name if not in session
    if (empty($_SESSION['full_name'])) {
        $stmt_u = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt_u->execute([$client_user_id]);
        $client_name = $stmt_u->fetchColumn() ?: 'Mrs. Silva';
    }

    // 2. Urgent Action Banner Hook: Pending Change Orders
    $stmt_co = $pdo->prepare("SELECT COUNT(*) FROM change_orders WHERE project_id = ? AND status = 'Pending'");
    $stmt_co->execute([$project_id]);
    $pending_co_count = (int)$stmt_co->fetchColumn();

    // 3. Plans Received Banner Hook: Completed floor plans
    $stmt_fp = $pdo->prepare("
        SELECT COUNT(*) FROM floor_plans WHERE project_id = ? AND status = 'Completed'
    ");
    $stmt_fp->execute([$project_id]);
    $received_plans_count = (int)$stmt_fp->fetchColumn();

    // If floor_plans has records or project_floor_plans exists, flag plans received
    if ($received_plans_count === 0) {
        $stmt_pfp = $pdo->prepare("SELECT COUNT(*) FROM project_floor_plans WHERE project_id = ?");
        $stmt_pfp->execute([$project_id]);
        $received_plans_count = (int)$stmt_pfp->fetchColumn();
    }

} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project_name) ?> - Client Portal | BuildNexus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --nexus-green: #16a34a;
            --nexus-green-hover: #15803d;
            --nexus-dark: #0f172a;
            --nexus-muted: #64748b;
            --nexus-border: #f1f5f9;
            --nexus-bg: #fcfcfc;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--nexus-bg);
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        /* Persistent Left Sidebar */
        .sidebar {
            width: 240px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #f1f5f9;
            position: fixed;
            top: 0;
            left: 0;
            padding: 1.5rem 1.25rem;
            display: flex;
            flex-direction: column;
            z-index: 1030;
            box-sizing: border-box;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            margin-bottom: 2rem;
            padding-left: 0.25rem;
        }

        .sidebar-brand img {
            width: 26px;
            height: 26px;
            object-fit: contain;
            margin-right: 0.65rem;
        }

        .sidebar-brand-text {
            font-weight: 700;
            font-size: 1.05rem;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        /* Navigation Links */
        .nav-link-client {
            display: flex;
            align-items: center;
            padding: 9px 12px;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 4px;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .nav-link-client:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }

        .nav-link-client.active {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 600;
        }

        .nav-link-client i {
            margin-right: 14px;
            font-size: 1rem;
            color: #64748b;
        }

        .nav-link-client.active i {
            color: #0f172a;
        }

        /* Main Workspace */
        .main-content {
            margin-left: 240px;
            padding: 2.25rem 3rem;
            min-height: 100vh;
            background-color: var(--nexus-bg);
        }

        /* Top Header */
        .header-title {
            font-size: 1.45rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        .user-greeting {
            font-size: 0.86rem;
            color: #64748b;
        }

        .logout-link {
            color: #0f172a;
            font-weight: 600;
            text-decoration: underline;
            margin-left: 0.25rem;
        }
        .logout-link:hover {
            color: #dc2626;
        }

        /* Content Cards */
        .nexus-card {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 10px;
            padding: 1.5rem 1.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            height: 100%;
        }

        .card-heading {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1.25rem;
        }

        /* Progress Bar */
        .progress-track {
            height: 6px;
            background-color: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 8px;
            margin-bottom: 18px;
        }

        .progress-bar-fill {
            height: 100%;
            background-color: var(--nexus-green);
            transition: width 0.4s ease;
        }

        /* Urgent Action Banners */
        .banner-warning {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 1.15rem 1.4rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .banner-info {
            background-color: #e0f2fe;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1.15rem 1.4rem;
            display: flex;
            align-items: center;
        }

        .btn-review-now {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.82rem;
            padding: 7px 18px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            transition: background-color 0.15s ease;
            white-space: nowrap;
        }
        .btn-review-now:hover {
            background-color: #0f172a;
            color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- Persistent Left Sidebar Navigation -->
    <aside class="sidebar">
        <!-- Brand Header with Official Clover Logo -->
        <a href="client_dashboard.php" class="sidebar-brand">
            <img src="images/logo.png" alt="BuildNexus Logo">
            <span class="sidebar-brand-text">BuildNexus</span>
        </a>

        <!-- Navigation Menu -->
        <nav class="d-flex flex-column flex-grow-1">
            <a href="client_dashboard.php" class="nav-link-client active">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            <a href="client_schedule.php" class="nav-link-client">
                <i class="bi bi-bar-chart"></i> Project Schedule
            </a>
            <a href="pay_online.php" class="nav-link-client">
                <i class="bi bi-credit-card"></i> Pay Online
            </a>
            <a href="client_change_orders.php" class="nav-link-client">
                <i class="bi bi-arrow-left-right"></i> Change Orders
            </a>
            <a href="client_chat.php" class="nav-link-client">
                <i class="bi bi-chat-dots"></i> Chat
            </a>
            <a href="client_notes.php" class="nav-link-client">
                <i class="bi bi-sticky"></i> Notes
            </a>
            <a href="client_rfis.php" class="nav-link-client">
                <i class="bi bi-file-earmark-text"></i> RFIs
            </a>
            <a href="upload_plan.php" class="nav-link-client">
                <i class="bi bi-upload"></i> Upload Plans
            </a>
        </nav>
    </aside>

    <!-- Main Operational Workspace -->
    <main class="main-content">
        <!-- Top Bar Header -->
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="header-title mb-0"><?= htmlspecialchars($project_name) ?></h1>
            <div class="user-greeting">
                Welcome, <?= htmlspecialchars($client_name) ?> <span class="mx-1 text-muted">|</span> 
                <a href="logout.php" class="logout-link">Logout</a>
            </div>
        </header>

        <!-- KPI Grid Row -->
        <div class="row g-4 mb-4">
            <!-- Project Progress Card (Left Column) -->
            <div class="col-lg-8">
                <div class="nexus-card">
                    <div class="card-heading">Project Progress</div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-secondary small">Overall Progress</span>
                        <span class="fw-bold small" style="color: var(--nexus-green);"><?= $progress_val ?>%</span>
                    </div>
                    
                    <!-- Progress Bar Track -->
                    <div class="progress-track">
                        <div class="progress-bar-fill" style="width: <?= $progress_val ?>%;"></div>
                    </div>

                    <div class="small text-secondary">
                        Current Stage: <strong class="text-dark"><?= htmlspecialchars($current_stage) ?></strong>
                    </div>
                </div>
            </div>

            <!-- Financial Overview Card (Right Column) -->
            <div class="col-lg-4">
                <div class="nexus-card">
                    <div class="card-heading">Financial Overview</div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="text-secondary small">Current Budget</span>
                        <strong class="text-dark fs-6">RS. <?= number_format($project_budget) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Banners (Positioned under the Progress Card) -->
        <div class="row">
            <div class="col-lg-8">
                <!-- Action Required: Change Orders Banner -->
                <?php if ($pending_co_count > 0): ?>
                    <div class="banner-warning">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3" style="color: #92400e;"></i>
                            <div>
                                <h6 class="fw-bold mb-1" style="color: #854d0e; font-size: 0.95rem;">Action Required: Change Orders</h6>
                                <p class="small mb-0" style="color: #78350f;">You have <?= $pending_co_count ?> change order(s) awaiting your review.</p>
                            </div>
                        </div>
                        <a href="client_change_orders.php" class="btn-review-now">Review Now</a>
                    </div>
                <?php endif; ?>

                <!-- Plans Received Notification Banner -->
                <?php if ($received_plans_count > 0): ?>
                    <div class="banner-info">
                        <i class="bi bi-check-circle-fill fs-4 me-3" style="color: #0284c7;"></i>
                        <div>
                            <h6 class="fw-bold mb-1" style="color: #0369a1; font-size: 0.95rem;">Plans Received</h6>
                            <p class="small mb-0 text-dark">The Admin has successfully received and reviewed your house plan upload.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>