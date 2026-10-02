<?php
require_once '../db.php'; 
session_start();

// Security: Allow only Admin access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

try {
    // Fetch projects for the filter dropdown
    $projects = $pdo->query("SELECT id, project_name FROM projects")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Takeoffs from DB
    $takeoffs = $pdo->query("SELECT * FROM takeoffs ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Takeoffs - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (Zero Tailwind) --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        
        /* Search & Filter Inputs */
        .search-wrapper { position: relative; max-width: 450px; flex-grow: 1; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .nexus-input { width: 100%; padding: 8px 12px 8px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .nexus-select { padding: 8px 35px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 200px; }

        /* Table Styling */
        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.85rem; padding: 1rem; }
        .table tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        
        .project-thumb { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; }
        .project-link { color: #10b981; text-decoration: none; font-weight: 500; }
        .project-link:hover { text-decoration: underline; }

        /* Action Menu */
        .dropdown-menu { border: 1px solid #f1f5f9; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 10px; padding: 6px; min-width: 170px; }
        .dropdown-item { font-size: 0.85rem; padding: 8px 12px; border-radius: 6px; color: #475569; cursor: pointer; }
        .dropdown-item:hover { background-color: #f8fafc; color: #1e293b; }
        .dropdown-item.text-danger:hover { background-color: #fff1f2; color: #e11d48; }

        /* Status Pills */
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
        .pill-completed { background: #f0fdf4; color: #16a34a; }
        .pill-progress { background: #eff6ff; color: #2563eb; }
        .pill-archived { background: #f1f5f9; color: #64748b; }

        .btn-new-takeoff { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 16px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; }
        .btn-new-takeoff:hover { background-color: #16a34a; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
    </style>
</head>
<body>

    <div class="main-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">Takeoffs</h1>
            <button class="btn-new-takeoff" data-bs-toggle="modal" data-bs-target="#newTakeoffModal">
                <i class="bi bi-upload"></i> New Takeoff
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

        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">All Takeoff Projects</h4>
                <p class="text-muted small">Manage and track all blueprint takeoffs.</p>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="takeoffSearch" class="nexus-input" placeholder="Search takeoffs...">
                </div>
                <div class="d-flex gap-2">
                    <select class="nexus-select">
                        <option>Luxury Villa in Kandy</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="nexus-select">
                        <option>Completed</option>
                        <option>In Progress</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="takeoffsTable">
                    <thead>
                        <tr>
                            <th width="10%"></th>
                            <th width="15%">Takeoff ID</th>
                            <th width="25%">Project</th>
                            <th width="15%">Created By</th>
                            <th width="15%">Date</th>
                            <th width="15%">Status</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($takeoffs as $t): 
                            $dateStr = date('Y-m-d', strtotime($t['created_at']));
                            $statusClass = 'pill-progress';
                            if($t['status'] == 'Completed') $statusClass = 'pill-completed';
                            if($t['status'] == 'Archived') $statusClass = 'pill-archived';
                        ?>
                        <tr class="takeoff-row">
                            <td><img src="<?= htmlspecialchars($t['img_url']) ?>" class="project-thumb"></td>
                            <td class="fw-semibold"><?= htmlspecialchars($t['takeoff_no']) ?></td>
                            <td><a href="#" class="project-link"><?= htmlspecialchars($t['project_name']) ?></a></td>
                            <td class="text-muted"><?= htmlspecialchars($t['creator']) ?></td>
                            <td class="text-muted"><?= $dateStr ?></td>
                            <td>
                                <span class="pill <?= $statusClass ?>">
                                    <?= htmlspecialchars($t['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn p-0 border-0" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li><a class="dropdown-item small" href="#" onclick="viewTakeoff('<?= htmlspecialchars($t['img_url'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['takeoff_no'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['project_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['creator'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['status'], ENT_QUOTES) ?>')">View Takeoff</a></li>
                                        <li><form action="takeoff_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Generate an estimate for this takeoff?');"><input type="hidden" name="action" value="generate_estimate"><input type="hidden" name="takeoff_id" value="<?= $t['id'] ?>"><button type="submit" class="dropdown-item small">Generate Estimate</button></form></li>
                                        <li><form action="takeoff_actions.php" method="POST" class="d-inline"><input type="hidden" name="action" value="share"><input type="hidden" name="takeoff_id" value="<?= $t['id'] ?>"><button type="submit" class="dropdown-item small">Share</button></form></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if($t['status'] !== 'Archived'): ?>
                                        <li><form action="takeoff_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Archive this takeoff?');"><input type="hidden" name="action" value="archive"><input type="hidden" name="takeoff_id" value="<?= $t['id'] ?>"><button type="submit" class="dropdown-item small text-danger">Archive</button></form></li>
                                        <?php endif; ?>
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

    <!-- Modals -->
    <!-- New Takeoff Modal -->
    <div class="modal fade" id="newTakeoffModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="takeoff_actions.php" method="POST">
                <input type="hidden" name="action" value="create_takeoff">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">New Takeoff Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Project Name</label>
                        <select class="form-select" name="project_name" required>
                            <option value="" disabled selected>Select a Project</option>
                            <?php foreach($projects as $p): ?>
                                <option value="<?= htmlspecialchars($p['project_name']) ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Blueprint (Mock)</label>
                        <input type="file" class="form-control" accept="image/*,.pdf" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Upload & Initialize</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Takeoff Modal -->
    <div class="modal fade" id="viewTakeoffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Takeoff Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="vImg" src="" style="width:100%; max-height:200px; object-fit:cover; border-radius:8px; margin-bottom:15px;">
                    <h5 id="vProject" class="fw-bold"></h5>
                    <p class="text-muted mb-1">Takeoff ID: <span id="vNo"></span></p>
                    <p class="text-muted mb-1">Creator: <span id="vCreator"></span></p>
                    <p class="mt-2"><span id="vStatus" class="pill pill-progress"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function viewTakeoff(img, no, proj, creator, status) {
            document.getElementById('vImg').src = img;
            document.getElementById('vProject').innerText = proj;
            document.getElementById('vNo').innerText = no;
            document.getElementById('vCreator').innerText = creator;
            document.getElementById('vStatus').innerText = status;
            
            let statusPill = document.getElementById('vStatus');
            statusPill.className = 'pill ' + (status === 'Completed' ? 'pill-completed' : (status === 'Archived' ? 'pill-archived' : 'pill-progress'));
            
            new bootstrap.Modal(document.getElementById('viewTakeoffModal')).show();
        }

        // Real-time search function
        document.getElementById('takeoffSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll(".takeoff-row");
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });
    </script>
</body>
</html>