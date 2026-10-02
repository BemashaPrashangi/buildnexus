<?php
require 'db.php'; 
session_start();


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['user_name'];

try {
    
    $total_users    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $properties_listed = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();

    
    $total_revenue = $pdo->query("SELECT SUM(total_amount) FROM purchase_orders WHERE status = 'Approved'")->fetchColumn() ?: 0;

   
    $active_agents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Project Manager'")->fetchColumn();

    
    $properties_sold = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Completed'")->fetchColumn();

    
    $recent_listings = $pdo->query("SELECT project_name, budget, status FROM projects ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
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
    <title>Admin Dashboard - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            overflow: hidden;
        }

       
        .sidebar {
            width: 260px;
            height: 100vh;
            background-color: #ffffff;
           
            border-right: 1px solid #e2e8f0;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 2rem 10px;
            text-decoration: none;
            color: #1e293b;
        }

        .sidebar-brand:hover {
            color: #1e293b;
            opacity: 0.85;
        }

        .btn-create {
            background-color: #22c55e;
            color: white;
            font-weight: 700;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: background 0.2s;
        }

        .btn-create:hover {
            background-color: #16a34a;
            color: white;
        }

        .nav-category {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 1.5rem 0 0.5rem 10px;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            font-size: 0.875rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-link-custom:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }

        .nav-link-custom.active {
            background-color: #f1f5f9;
            color: #2563eb;
            font-weight: 600;
        }

       
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            height: 100vh;
            overflow-y: auto;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .stat-icon {
            padding: 0.75rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .table-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .logout-link {
            margin-top: auto;
            padding: 1rem 12px;
            color: #ef4444;
            text-decoration: none;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logout-link:hover {
            color: #b91c1c;
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <a href="index.php" class="sidebar-brand">
            <img src="images/logo.png" alt="BuildNexus" style="width: 40px; height: 40px; object-fit: contain;">
            <span style="font-weight: 700; font-size: 1.25rem;">BuildNexus</span>
        </a>

        <a href="#" class="btn-create">+ Create New</a>

        <a href="admin_dashboard.php" class="nav-link-custom active">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <p class="nav-category">Administration</p>
        <a href="user_management.php" class="nav-link-custom"><i class="bi bi-people"></i> User Management</a>
        <a href="access_levels.php" class="nav-link-custom"><i class="bi bi-shield-lock"></i> Access Level</a>
        <a href="system_settings.php" class="nav-link-custom"><i class="bi bi-gear"></i> System Settings</a>
        

        <p class="nav-category">Planning</p>
        <a href="features/floor-plans.php" class="nav-link-custom">
            <i class="bi bi-layers"></i> Floor Plans & Blueprints
        </a>
        <a href="features/crm.php" class="nav-link-custom"><i class="bi bi-person-lines-fill"></i> CRM & Auto-Quoting</a>
        <a href="features/proposals.php" class="nav-link-custom"><i class="bi bi-file-earmark-text"></i> Proposals</a>
        <a href="features/interactive-estimates.php" class="nav-link-custom"><i class="bi bi-calculator"></i> Interactive Estimates</a>
        <a href="features/takeoffs.php" class="nav-link-custom"><i class="bi bi-rulers"></i> Take offs</a>
        <a href="features/selections.php" class="nav-link-custom"><i class="bi bi-check2-square"></i> Selections</a>
        <a href="features/rfi.php" class="nav-link-custom"><i class="bi bi-question-circle"></i> RFIs</a>
        <a href="features/submittals.php" class="nav-link-custom active"><i class="bi bi-file-earmark-check"></i> Submittals</a>

        <p class="nav-category">Financials</p>
        <a href="features/procurement.php" class="nav-link-custom"><i class="bi bi-cart3"></i> Purchase Orders</a>
        <a href="features/invoicing.php" class="nav-link-custom"><i class="bi bi-receipt"></i> Invoicing</a>
        <a href="features/online-payments.php" class="nav-link-custom"><i class="bi bi-credit-card"></i> Payments</a>
        <a href="features/change-orders.php" class="nav-link-custom"><i class="bi bi-arrow-repeat"></i> Change Orders</a>
        <a href="features/reports.php" class="nav-link-custom"><i class="bi bi-bar-chart"></i> Reports</a>

        <p class="nav-category">Project Management</p>
        <a href="features/projects.php" class="nav-link-custom"><i class="bi bi-building"></i> All Projects</a>
        <a href="features/master_schedule.php" class="nav-link-custom active"><i class="bi bi-calendar3"></i> Schedule</a>
        <a href="features/daily-logs.php" class="nav-link-custom"><i class="bi bi-journal-text"></i> Daily Logs</a>
        <a href="features/time-cards.php" class="nav-link-custom"><i class="bi bi-clock-history"></i> Time Cards</a>
        <a href="features/inspections.php" class="nav-link-custom"><i class="bi bi-clipboard-check"></i> Inspections</a>
        <a href="features/safety-meetings.php" class="nav-link-custom"><i class="bi bi-cone-striped"></i> Safety</a>
        <a href="features/directory.php" class="nav-link-custom active"><i class="bi bi-person-lines-fill"></i> Directory</a>
        <a href="features/equipment-logs.php" class="nav-link-custom"><i class="bi bi-truck"></i> Equipment</a>

        <p class="nav-category">Marketing</p>
        <a href="features/lead-generation.php" class="nav-link-custom"><i class="bi bi-funnel"></i> Lead Generation</a>
        <a href="features/email-marketing.php" class="nav-link-custom"><i class="bi bi-envelope-paper"></i> Email Marketing</a>

        <a href="logout.php" class="logout-link">
            <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
    </aside>

    <main class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 class="h3 fw-bold text-dark">BuildNexus Overview</h1>
                <p class="text-muted small">Real-time system performance and project metrics.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm fw-semibold">Export CSV</button>
                <a href="features/projects.php" class="btn btn-primary btn-sm fw-semibold">+ New Project</a>
            </div>
        </header>

        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-currency-dollar fs-4"></i>
                    </div>
                    <p class="text-muted fw-bold small text-uppercase mb-1">Total Revenue</p>
                    <h3 class="fw-bold text-dark mb-0">Rs. <?= number_format($total_revenue, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-building fs-4"></i>
                    </div>
                    <p class="text-muted fw-bold small text-uppercase mb-1">Properties Listed</p>
                    <h3 class="fw-bold text-dark mb-0"><?= $properties_listed; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <p class="text-muted fw-bold small text-uppercase mb-1">Active Agents</p>
                    <h3 class="fw-bold text-dark mb-0"><?= $active_agents; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                    <p class="text-muted fw-bold small text-uppercase mb-1">Properties Sold</p>
                    <h3 class="fw-bold text-dark mb-0"><?= $properties_sold; ?></h3>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="table-card">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
                        <h5 class="mb-0 fw-bold">Recent Property Listings</h5>
                        <a href="features/projects.php" class="text-primary small fw-bold text-decoration-none">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light text-muted small text-uppercase fw-bold">
                                <tr>
                                    <th class="px-4 py-3">Property Name</th>
                                    <th class="px-4 py-3">Budget</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_listings as $list): ?>
                                    <tr style="border-bottom: 1px solid #f8fafc;">
                                        <td class="px-4 py-3 fw-semibold"><?= htmlspecialchars($list['project_name']); ?></td>
                                        <td class="px-4 py-3 text-muted">Rs. <?= number_format($list['budget'], 2); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="badge <?= $list['status'] == 'Active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?> px-3">
                                                <?= $list['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="table-card p-4">
                    <h5 class="fw-bold mb-4">Top Performing Agents</h5>
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary fw-bold rounded-circle d-flex align-items-center justify-center" style="width: 40px; height: 40px; font-size: 0.8rem;">DP</div>
                            <div>
                                <p class="mb-0 fw-bold small">Dilshan Perera</p>
                                <p class="mb-0 text-muted" style="font-size: 0.75rem;">Project Manager</p>
                            </div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary">12 Sales</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>