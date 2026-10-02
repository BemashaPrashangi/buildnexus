<?php
require 'db.php'; 
session_start();

// Security: Allow only Admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

try {
    // Fetch all users with dynamic project counts
    $query = "
        SELECT 
            u.*, 
            (SELECT COUNT(DISTINCT project_id) FROM time_cards WHERE user_id = u.id AND status = 'On-Site') AS active_pm_projects,
            (SELECT COUNT(*) FROM projects WHERE client_name = u.full_name AND status = 'Active') AS active_client_projects
        FROM users u
        ORDER BY u.created_at DESC
    ";
    $users = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
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
    <title>User Management - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (Zero Tailwind) --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; overflow: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: 260px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; padding: 1.5rem 1rem; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 2rem; text-decoration: none; color: #1e293b; font-weight: 700; font-size: 1.25rem; }
        .nav-link-custom { display: flex; align-items: center; gap: 12px; padding: 10px 12px; font-size: 0.875rem; color: #64748b; text-decoration: none; border-radius: 8px; transition: 0.2s; }
        .nav-link-custom:hover { background: #f1f5f9; color: #1e293b; }
        .nav-link-custom.active { background: #f1f5f9; color: #2563eb; font-weight: 600; }
        .nav-category { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin: 1.5rem 0 0.5rem 10px; letter-spacing: 0.05em; }

        /* Main Content Styling */
        .main-content { margin-left: 260px; padding: 2.5rem; height: 100vh; overflow-y: auto; }
        .content-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        
        /* Search & Filter Inputs */
        .search-wrapper { position: relative; max-width: 450px; flex-grow: 1; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .custom-input { width: 100%; padding: 8px 12px 8px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; transition: border 0.2s; }
        .custom-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
        .custom-select { padding: 8px 35px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #475569; background-color: #fff; min-width: 180px; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }

        /* Table Styling */
        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.8rem; padding: 1rem 0.75rem; background: #fff; }
        .table tbody td { padding: 1.25rem 0.75rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        
        .avatar { width: 38px; height: 38px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; }
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; }
        .pill-active { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .pill-inactive { background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; }
        .pill-invited { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }

        /* Action Menu */
        .btn-action { color: #94a3b8; padding: 4px; transition: color 0.2s; background: none; border: none; }
        .btn-action:hover { color: #1e293b; }
        .dropdown-menu { border: 1px solid #f1f5f9; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 10px; padding: 6px; min-width: 180px; }
        .dropdown-item { font-size: 0.85rem; padding: 8px 12px; border-radius: 6px; color: #475569; }
        .dropdown-item:hover { background-color: #f8fafc; color: #1e293b; }
        .dropdown-item.text-danger:hover { background-color: #fff1f2; color: #e11d48; }

        .btn-new-user { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 16px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-new-user:hover { background-color: #16a34a; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
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
            <a href="user_management.php" class="nav-link-custom active"><i class="bi bi-people"></i> User Management</a>
            <a href="#" class="nav-link-custom"><i class="bi bi-shield-lock"></i> Access Levels</a>
            <a href="#" class="nav-link-custom"><i class="bi bi-gear"></i> System Settings</a>
            
            <p class="nav-category">Planning</p>
            <a href="features/3d-floor-plans.php" class="nav-link-custom"><i class="bi bi-layers"></i> 3D Floor Plans</a>
            </nav>
        <a href="logout.php" class="mt-auto nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 fw-bold mb-0">User Management</h2>
            <button class="btn-new-user" data-bs-toggle="modal" data-bs-target="#newUserModal">
                <i class="bi bi-plus-lg"></i> New User
            </button>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="content-card">
            <div class="mb-4">
                <h5 class="fw-bold mb-1">All Users</h5>
                <p class="text-muted small">Manage users and their roles in the system.</p>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="searchInput" class="custom-input" placeholder="Search users by name or email...">
                </div>
                <div class="d-flex gap-2">
                    <select id="roleFilter" class="custom-select">
                        <option value="All">Role: All</option>
                        <option value="Admin">Admin</option>
                        <option value="Project Manager">Project Manager</option>
                        <option value="Foreman">Foreman</option>
                        <option value="Client">Client</option>
                    </select>
                    <select id="statusFilter" class="custom-select">
                        <option value="All">Status: All</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Invited">Invited</option>
                    </select>
                </div>
            </div>

            <table class="table" id="usersTable">
                <thead>
                    <tr>
                        <th width="40%">Name</th>
                        <th width="20%">Role</th>
                        <th width="15%">Projects</th>
                        <th width="15%">Status</th>
                        <th width="10%" class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php 
                        // Logic for Initials
                        $names = explode(' ', $user['full_name']);
                        $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                        
                        // Active Projects Calculation
                        $projCount = ($user['role'] == 'Client') ? $user['active_client_projects'] : $user['active_pm_projects'];
                        
                        // Status from database
                        $status = $user['status'] ?? 'Active'; 
                    ?>
                    <tr class="user-row" data-role="<?= $user['role'] ?>" data-status="<?= $status ?>">
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar"><?= $initials ?></div>
                                <div>
                                    <div class="fw-semibold mb-0"><?= htmlspecialchars($user['full_name']) ?></div>
                                    <div class="text-muted small" style="font-size: 0.75rem;"><?= htmlspecialchars($user['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($user['role']) ?></td>
                        <td class="fw-500"><?= $projCount ?> Active</td>
                        <td>
                            <span class="status-pill pill-<?= strtolower($status) ?>">
                                <?= $status ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn-action" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="#" onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>')"><i class="bi bi-pencil me-2"></i> Edit User</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="openRoleModal(<?= $user['id'] ?>, '<?= $user['role'] ?>')"><i class="bi bi-arrow-left-right me-2"></i> Change Role</a></li>
                                    <li><form action="user_actions.php" method="POST" class="d-inline"><input type="hidden" name="action" value="resend_invite"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><button type="submit" class="dropdown-item"><i class="bi bi-send me-2"></i> Resend Invite</button></form></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php if($status === 'Active'): ?>
                                        <li><form action="user_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this user?');"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><button type="submit" class="dropdown-item text-danger"><i class="bi bi-slash-circle me-2"></i> Deactivate</button></form></li>
                                    <?php else: ?>
                                        <li><form action="user_actions.php" method="POST" class="d-inline"><input type="hidden" name="action" value="activate"><input type="hidden" name="user_id" value="<?= $user['id'] ?>"><button type="submit" class="dropdown-item text-success"><i class="bi bi-check-circle me-2"></i> Activate</button></form></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Modals -->
    <div class="modal fade" id="newUserModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="user_actions.php" method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Password</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Role</label>
                        <select class="form-select" name="role" required>
                            <option value="" disabled selected>Choose a role...</option>
                            <option value="Admin">Admin</option>
                            <option value="Project Manager">Project Manager</option>
                            <option value="Foreman">Foreman</option>
                            <option value="Client">Client</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="user_actions.php" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" id="editFullName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editEmail" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="changeRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="user_actions.php" method="POST">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="user_id" id="roleUserId">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Change Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select New Role</label>
                        <select class="form-select" name="role" id="roleSelect" required>
                            <option value="Admin">Admin</option>
                            <option value="Project Manager">Project Manager</option>
                            <option value="Foreman">Foreman</option>
                            <option value="Client">Client</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditModal(id, name, email) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFullName').value = name;
            document.getElementById('editEmail').value = email;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function openRoleModal(id, currentRole) {
            document.getElementById('roleUserId').value = id;
            document.getElementById('roleSelect').value = currentRole;
            new bootstrap.Modal(document.getElementById('changeRoleModal')).show();
        }

        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const rows = document.querySelectorAll('.user-row');

        function filterUsers() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedRole = roleFilter.value;
            const selectedStatus = statusFilter.value;

            rows.forEach(row => {
                const name = row.querySelector('.fw-semibold').innerText.toLowerCase();
                const email = row.querySelector('.text-muted.small').innerText.toLowerCase();
                const role = row.getAttribute('data-role');
                const status = row.getAttribute('data-status');

                const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
                const matchesRole = selectedRole === 'All' || role === selectedRole;
                const matchesStatus = selectedStatus === 'All' || status === selectedStatus;

                if (matchesSearch && matchesRole && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterUsers);
        roleFilter.addEventListener('change', filterUsers);
        statusFilter.addEventListener('change', filterUsers);
    </script>
</body>
</html>