<?php
require_once '../db.php'; 
session_start();

// Security: Allow only Admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

try {
    // Fetch all projects for the filter dropdown
    $projects = $pdo->query("SELECT id, project_name FROM projects")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch bids from database
    $proposals = $pdo->query("SELECT * FROM bids ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Proposals & Bids - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING (Zero Tailwind) --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; }
        
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        
        /* Header Elements */
        .search-wrapper { position: relative; max-width: 450px; flex-grow: 1; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .nexus-input { width: 100%; padding: 8px 12px 8px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .nexus-select { padding: 8px 35px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #475569; background: #fff; min-width: 210px; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }

        /* Table Styling */
        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.85rem; padding: 1rem 0.5rem; }
        .table tbody td { padding: 1.25rem 0.5rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; color: #1e293b; }
        
        .project-link { color: #10b981; text-decoration: none; font-weight: 500; }
        .project-link:hover { text-decoration: underline; }

        /* Action Dropdown */
        .btn-action { color: #94a3b8; border: none; background: none; transition: 0.2s; }
        .btn-action:hover { color: #1e293b; }
        .dropdown-menu { border: 1px solid #f1f5f9; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 10px; padding: 6px; min-width: 170px; }
        .dropdown-item { font-size: 0.85rem; padding: 8px 12px; border-radius: 6px; color: #475569; }
        .dropdown-item:hover { background-color: #f8fafc; color: #1e293b; }
        .dropdown-item.text-danger:hover { background-color: #fff1f2; color: #e11d48; }

        .btn-new-bid { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 16px; font-size: 0.875rem; }
        .btn-new-bid:hover { background-color: #16a34a; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
    </style>
</head>
<body>

    <div class="main-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">Proposals & Bids</h1>
            <button class="btn-new-bid" data-bs-toggle="modal" data-bs-target="#newBidModal">
                <i class="bi bi-plus-lg me-1"></i> New Bid Request
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
                <h4 class="fw-bold mb-1">All Bids</h4>
                <p class="text-muted small">Manage and track all bid requests and submissions.</p>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="bidSearch" class="nexus-input" placeholder="Search bids...">
                </div>
                <div class="d-flex gap-2">
                    <select class="nexus-select">
                        <option value="All">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="nexus-select">
                        <option>Submitted</option>
                        <option>Under Review</option>
                        <option>Awarded</option>
                    </select>
                </div>
            </div>

            <table class="table" id="bidsTable">
                <thead>
                    <tr>
                        <th width="15%">Bid Number</th>
                        <th width="25%">Project</th>
                        <th width="25%">Subcontractor</th>
                        <th width="20%">Amount</th>
                        <th width="10%">Status</th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proposals as $bid): ?>
                    <tr class="bid-row">
                        <td class="fw-semibold"><?= htmlspecialchars($bid['bid_no']) ?></td>
                        <td><a href="#" class="project-link"><?= htmlspecialchars($bid['project_name']) ?></a></td>
                        <td class="text-muted"><?= htmlspecialchars($bid['subcontractor']) ?></td>
                        <td class="fw-500">RS. <?= number_format($bid['amount']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($bid['status']) ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn-action" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li><a class="dropdown-item" href="#" onclick="viewBid('<?= htmlspecialchars($bid['bid_no'], ENT_QUOTES) ?>', '<?= htmlspecialchars($bid['project_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($bid['subcontractor'], ENT_QUOTES) ?>', '<?= number_format($bid['amount']) ?>', '<?= htmlspecialchars($bid['status'], ENT_QUOTES) ?>')">View Details</a></li>
                                    <?php if($bid['status'] !== 'Awarded'): ?>
                                    <li><form action="proposal_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Award this bid?');"><input type="hidden" name="action" value="award_bid"><input type="hidden" name="bid_id" value="<?= $bid['id'] ?>"><button type="submit" class="dropdown-item">Award Bid</button></form></li>
                                    <?php endif; ?>
                                    <li><form action="proposal_actions.php" method="POST" class="d-inline"><input type="hidden" name="action" value="send_message"><input type="hidden" name="bid_id" value="<?= $bid['id'] ?>"><button type="submit" class="dropdown-item">Send Message</button></form></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php if($bid['status'] !== 'Archived'): ?>
                                    <li><form action="proposal_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Archive this bid?');"><input type="hidden" name="action" value="archive_bid"><input type="hidden" name="bid_id" value="<?= $bid['id'] ?>"><button type="submit" class="dropdown-item text-danger">Archive</button></form></li>
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

    <!-- Modals -->
    <!-- New Bid Modal -->
    <div class="modal fade" id="newBidModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" action="proposal_actions.php" method="POST">
                <input type="hidden" name="action" value="create_bid">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">New Bid Request</h5>
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
                        <label class="form-label">Subcontractor</label>
                        <input type="text" class="form-control" name="subcontractor" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (RS)</label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Bid Modal -->
    <div class="modal fade" id="viewBidModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Bid Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Bid Number:</strong> <span id="vBidNo"></span></p>
                    <p><strong>Project:</strong> <span id="vProject"></span></p>
                    <p><strong>Subcontractor:</strong> <span id="vSub"></span></p>
                    <p><strong>Amount:</strong> RS. <span id="vAmount"></span></p>
                    <p><strong>Status:</strong> <span id="vStatus"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function viewBid(no, proj, sub, amt, status) {
            document.getElementById('vBidNo').innerText = no;
            document.getElementById('vProject').innerText = proj;
            document.getElementById('vSub').innerText = sub;
            document.getElementById('vAmount').innerText = amt;
            document.getElementById('vStatus').innerText = status;
            new bootstrap.Modal(document.getElementById('viewBidModal')).show();
        }

        // Real-time search function
        document.getElementById('bidSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll(".bid-row");
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });
    </script>
</body>
</html>