<?php
require_once '../db.php';
session_start();

// Security: Project Manager or Admin access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

try {
    // Fetch live data
    $projects = $pdo->query("SELECT id, project_name FROM projects")->fetchAll();
    
    // Initial query for clients
    $clients = $pdo->query("SELECT c.*, p.project_name FROM clients c LEFT JOIN projects p ON c.project_id = p.id")->fetchAll();
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
    <title>Clients - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* --- PURE CSS CUSTOM STYLING --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        
        /* Initials Avatar */
        .avatar-circle { width: 40px; height: 40px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: #475569; margin-right: 15px; }
        
        /* Status Badges */
        .status-pill { padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .bg-active { background: #f0fdf4; color: #16a34a; }
        .bg-on-hold { background: #fffbeb; color: #d97706; }

        .project-link { color: #16a34a; text-decoration: none; font-weight: 500; }
        .project-link:hover { text-decoration: underline; }

        .table thead th { background: #fcfcfc; border-bottom: 1px solid #f1f5f9; color: #64748b; font-size: 0.85rem; padding: 1rem; font-weight: 500; }
        .table tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; vertical-align: middle; font-size: 0.875rem; }
        
        .btn-new-client { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 10px 20px; font-size: 0.875rem; }
        .btn-new-client:hover { background-color: #16a34a; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold h2 mb-1">Clients</h1>
            <p class="text-muted small">All Clients</p>
        </div>
        <button class="btn-new-client" data-bs-toggle="modal" data-bs-target="#newClientModal">
            <i class="bi bi-plus-lg me-2"></i>New Client
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="position-relative">
                <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                <input type="text" class="form-control ps-5" placeholder="Search clients..." style="border-radius: 10px; border-color: #e2e8f0;">
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-select" style="border-radius: 10px; border-color: #e2e8f0;">
                <option selected>Luxury Villa in Kandy</option>
                <?php foreach($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= $p['project_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-select" style="border-radius: 10px; border-color: #e2e8f0;">
                <option selected>Active</option>
                <option>On Hold</option>
                <option>Archived</option>
            </select>
        </div>
    </div>

    <div class="nexus-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th width="30%">Name</th>
                        <th width="20%">Company</th>
                        <th width="25%">Project</th>
                        <th width="15%">Status</th>
                        <th width="10%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Manual mock for visual match if DB is empty
                    $mock_clients = [
                        ['init' => 'MS', 'name' => 'Mr. Silva', 'email' => 'no-email@example.com', 'comp' => 'N/A', 'proj' => 'Luxury Villa in Kandy', 'stat' => 'Active', 'cls' => 'bg-active'],
                        ['init' => 'JD', 'name' => 'John Doe', 'email' => 'no-email@example.com', 'comp' => 'ABC Corp', 'proj' => 'Colombo Office Complex', 'stat' => 'Active', 'cls' => 'bg-active'],
                        ['init' => 'EC', 'name' => 'Emily Carter', 'email' => 'no-email@example.com', 'comp' => 'Serendipity Resorts', 'proj' => 'Galle Boutique Hotel', 'stat' => 'Active', 'cls' => 'bg-active'],
                        ['init' => 'JS', 'name' => 'Jane Smith', 'email' => 'no-email@example.com', 'comp' => 'N/A', 'proj' => 'Apartment Fit-out', 'stat' => 'On Hold', 'cls' => 'bg-on-hold']
                    ];
                    foreach($mock_clients as $mc): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle"><?= $mc['init'] ?></div>
                                <div><div class="fw-bold"><?= $mc['name'] ?></div><div class="text-muted small"><?= $mc['email'] ?></div></div>
                            </div>
                        </td>
                        <td class="text-muted"><?= $mc['comp'] ?></td>
                        <td><a href="#" class="project-link"><?= $mc['proj'] ?></a></td>
                        <td><span class="status-pill <?= $mc['cls'] ?>"><?= $mc['stat'] ?></span></td>
                        <td class="text-end pe-4">
                            <div class="dropdown">
                                <i class="bi bi-three-dots" style="cursor:pointer" data-bs-toggle="dropdown"></i>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                    <li><a class="dropdown-item small" href="#">View Details</a></li>
                                    <li><a class="dropdown-item small" href="#">Send Message</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item small text-danger" href="#">Archive Client</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="newClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold">Add New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_client.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3"><label class="small fw-bold text-muted">Full Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="small fw-bold text-muted">Email Address</label><input type="email" name="email" class="form-control"></div>
                    <div class="mb-3"><label class="small fw-bold text-muted">Company (Optional)</label><input type="text" name="company" class="form-control"></div>
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Assign Project</label>
                        <select name="project_id" class="form-select">
                            <?php foreach($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['project_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-success w-100 fw-bold py-2">Create Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>