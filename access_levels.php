<?php
require 'db.php'; 
session_start();

// Security: Allow only Admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Define the permission matrix based on your screenshot
$permissions = [
    'PLANNING' => [
        ['feature' => '3D Floor Plans', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'View Only', 'client' => 'Full'],
        ['feature' => 'Takeoffs', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'Restricted', 'client' => 'No Access'],
        ['feature' => 'Bid Management', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'No Access', 'client' => 'No Access'],
        ['feature' => 'Selections', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'View Only', 'client' => 'Approve'],
    ],
    'FINANCIALS' => [
        ['feature' => 'Estimates / Proposals', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'No Access', 'client' => 'No Access'],
        ['feature' => 'Invoicing', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'No Access', 'client' => 'View/Pay'],
        ['feature' => 'Online Payments', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'No Access', 'client' => 'Pay Only'],
        ['feature' => 'Reports', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'No Access', 'client' => 'No Access'],
    ],
    'PROJECT MGMT' => [
        ['feature' => 'Projects / Schedule', 'admin' => 'Full', 'pm' => 'Full', 'foreman' => 'View/Edit', 'client' => 'View Only'],
        ['feature' => 'Time Cards', 'admin' => 'Full', 'pm' => 'View', 'foreman' => 'Manage Site', 'client' => 'No Access'],
        ['feature' => 'Safety Meetings', 'admin' => 'Full', 'pm' => 'View', 'foreman' => 'Execute', 'client' => 'No Access'],
        ['feature' => 'Daily Logs', 'admin' => 'Full', 'pm' => 'View', 'foreman' => 'Create', 'client' => 'No Access'],
    ]
];

// Helper function for color-coded pills
function getPillClass($status) {
    return match ($status) {
        'Full', 'Approve' => 'pill-full',
        'View Only', 'View', 'View/Pay', 'View/Edit' => 'pill-view',
        'Restricted', 'Manage Site', 'Execute', 'Create' => 'pill-restricted',
        'No Access', 'Restricted' => 'pill-none',
        default => 'pill-custom'
    };
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
    <title>Feature Access Levels - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; overflow: hidden; }
        
        /* Sidebar */
        .sidebar { width: 260px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; padding: 1.5rem 1rem; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 2rem; text-decoration: none; color: #1e293b; font-weight: 700; font-size: 1.25rem; }
        .nav-link-custom { display: flex; align-items: center; gap: 12px; padding: 10px 12px; font-size: 0.875rem; color: #64748b; text-decoration: none; border-radius: 8px; transition: 0.2s; }
        .nav-link-custom:hover { background: #f1f5f9; color: #1e293b; }
        .nav-link-custom.active { background: #f1f5f9; color: #2563eb; font-weight: 600; }
        .nav-category-side { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 1.5rem 0 0.5rem 10px; letter-spacing: 0.05em; }

        /* Main Content */
        .main-content { margin-left: 260px; padding: 2.5rem; height: 100vh; overflow-y: auto; }
        .access-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0; box-shadow: 0 1px 2px rgba(0,0,0,0.03); overflow: hidden; }
        
        /* Table Styling */
        .table { margin-bottom: 0; border-collapse: separate; border-spacing: 0; }
        .table thead th { background: #fcfcfd; border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.8rem; padding: 1rem 1.5rem; text-align: center; }
        .table thead th:first-child, .table thead th:nth-child(2) { text-align: left; }
        
        .category-row { background-color: #fcfcfd !important; font-weight: 700; font-size: 0.75rem; color: #1e293b; letter-spacing: 0.05em; }
        .category-row td { padding: 0.75rem 1.5rem !important; border-bottom: 1px solid #f1f5f9; }
        
        .feature-cell { padding: 1rem 1.5rem !important; font-size: 0.85rem; color: #475569; border-bottom: 1px solid #f8fafc; }
        .access-cell { text-align: center; padding: 1rem !important; border-bottom: 1px solid #f8fafc; }

        /* Access Pills */
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; display: inline-block; min-width: 85px; }
        .pill-full { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .pill-view { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .pill-restricted { background: #fefce8; color: #ca8a04; border: 1px solid #fef08a; }
        .pill-none { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
        .pill-custom { background: #f5f3ff; color: #7c3aed; border: 1px solid #ede9fe; }
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
            <p class="nav-category-side">Administration</p>
            <a href="user_management.php" class="nav-link-custom"><i class="bi bi-people"></i> User Management</a>
            <a href="access_levels.php" class="nav-link-custom active"><i class="bi bi-shield-lock"></i> Access Level</a>
            <a href="#" class="nav-link-custom"><i class="bi bi-gear"></i> System Settings</a>
        </nav>
        <a href="logout.php" class="mt-auto nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </aside>

    <main class="main-content">
        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">Feature Access Levels</h2>
            <p class="text-muted small">Define what each user role can view or manage across the platform.</p>
        </div>

        <div class="access-card">
            <table class="table">
                <thead>
                    <tr>
                        <th width="15%">Category</th>
                        <th width="25%">Feature</th>
                        <th width="15%">Admin</th>
                        <th width="15%">Project Manager</th>
                        <th width="15%">Foreman</th>
                        <th width="15%">Client</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissions as $category => $items): ?>
                    <tr class="category-row">
                        <td colspan="6"><?= $category ?></td>
                    </tr>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="feature-cell"></td>
                        <td class="feature-cell fw-medium"><?= $item['feature'] ?></td>
                        <td class="access-cell"><span class="status-pill <?= getPillClass($item['admin']) ?>"><?= $item['admin'] ?></span></td>
                        <td class="access-cell"><span class="status-pill <?= getPillClass($item['pm']) ?>"><?= $item['pm'] ?></span></td>
                        <td class="access-cell"><span class="status-pill <?= getPillClass($item['foreman']) ?>"><?= $item['foreman'] ?></span></td>
                        <td class="access-cell"><span class="status-pill <?= getPillClass($item['client']) ?>"><?= $item['client'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>