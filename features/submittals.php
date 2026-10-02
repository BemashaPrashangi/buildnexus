<?php
require_once '../db.php'; 
session_start();

// Security: Allow only Admin or PM access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

// Handle Form Submission for New Submittal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_submittal') {
    $project_id = $_POST['project_id'];
    $spec_section = $_POST['spec_section'];
    $title = $_POST['title'];
    $submittal_type = $_POST['submittal_type'];
    $submitted_to_name = $_POST['submitted_to_name'];
    $submitted_to_role = $_POST['submitted_to_role'];
    $review_due_date = !empty($_POST['review_due_date']) ? $_POST['review_due_date'] : null;
    
    // File upload logic
    $attachment_path = null;
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
        $allowedExtensions = ['pdf', 'dwg', 'png', 'jpg', 'jpeg', 'docx'];
        $fileNameRaw = $_FILES['attachment_file']['name'];
        $fileExt = strtolower(pathinfo($fileNameRaw, PATHINFO_EXTENSION));
        
        if (in_array($fileExt, $allowedExtensions) && $_FILES['attachment_file']['size'] <= 26214400) { // 25MB
            $uploadDir = '../uploads/submittals/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($fileNameRaw);
            $targetFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $targetFile)) {
                $attachment_path = 'uploads/submittals/' . $fileName;
            }
        } else {
            $error = "Invalid file type or size exceeds 25MB.";
        }
    }

    if (!isset($error)) {
        try {
            // Generate Submittal Number
            $year = date('Y');
            $stmt = $pdo->query("SELECT MAX(id) as max_id FROM project_submittals");
            $row = $stmt->fetch();
            $nextId = ($row['max_id'] ?? 0) + 1;
            $submittal_number = sprintf("SUB-%s-%03d", $year, $nextId);

            $insertStmt = $pdo->prepare("
                INSERT INTO project_submittals 
                (submittal_number, project_id, spec_section, title, submitted_to_name, submitted_to_role, submittal_type, review_due_date, attachment_path, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $submittal_number,
                $project_id,
                $spec_section,
                $title,
                $submitted_to_name,
                $submitted_to_role,
                $submittal_type,
                $review_due_date,
                $attachment_path,
                $_SESSION['user_id']
            ]);
            
            header("Location: submittals.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error saving Submittal: " . $e->getMessage();
        }
    }
}

// Handle Archive Action
if (isset($_GET['action']) && $_GET['action'] == 'archive' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("UPDATE project_submittals SET status = 'Rejected' WHERE id = ?"); // Using Rejected/Archived conceptually
    $stmt->execute([$_GET['id']]);
    header("Location: submittals.php");
    exit();
}

try {
    // Fetch projects for the filter dropdown
    $projects = $pdo->query("SELECT id, project_name FROM projects")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch directory contacts for reviewer suggestions
    $directoryReviewers = $pdo->query("SELECT name, role_type, company_name FROM contacts WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Submittals
    $submittalsStmt = $pdo->query("
        SELECT s.*, p.project_name 
        FROM project_submittals s 
        LEFT JOIN projects p ON s.project_id = p.id 
        ORDER BY s.created_at DESC
    ");
    $submittals = $submittalsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Helper function to map status to CSS class
function getStatusPillClass($status) {
    switch ($status) {
        case 'Approved':
        case 'Approved as Noted':
            return 'pill-approved';
        case 'Pending':
            return 'pill-pending';
        case 'Revise & Resubmit':
            return 'pill-revise';
        case 'Rejected':
            return 'pill-rejected';
        default:
            return 'pill-pending';
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
    <title>Submittals - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        
        .search-wrapper { position: relative; max-width: 460px; flex-grow: 1; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .nexus-input { width: 100%; padding: 8px 12px 8px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .nexus-select { padding: 8px 35px 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; min-width: 210px; }

        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 500; font-size: 0.85rem; padding: 1rem; }
        .table tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.875rem; vertical-align: middle; }
        
        .project-link { color: #10b981; text-decoration: none; font-weight: 500; }
        .project-link:hover { text-decoration: underline; }

        /* Status Pills */
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; display: inline-block; }
        .pill-approved { background: #f0fdf4; color: #16a34a; }
        .pill-pending { background: #eff6ff; color: #2563eb; }
        .pill-revise { background: #fffbeb; color: #d97706; }
        .pill-rejected { background: #fef2f2; color: #dc2626; }

        .btn-new-submittal { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 16px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; }
        .btn-new-submittal:hover { background-color: #16a34a; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
    </style>
</head>
<body>

    <div class="main-container">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">Submittals</h1>
            <button class="btn-new-submittal" data-bs-toggle="modal" data-bs-target="#newSubmittalModal">
                <i class="bi bi-plus-lg"></i> New Submittal
            </button>
        </div>

        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">All Submittals</h4>
                <p class="text-muted small">Manage project documentation and approval workflows.</p>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="searchFilter" class="nexus-input" placeholder="Search by subject or spec section...">
                </div>
                <div class="d-flex gap-2">
                    <select id="projectFilter" class="nexus-select" onchange="filterTable()">
                        <option value="All">All Projects</option>
                        <?php foreach($projects as $proj): ?>
                            <option value="<?= htmlspecialchars($proj['project_name']) ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter" class="nexus-select" onchange="filterTable()">
                        <option value="All">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Approved as Noted">Approved as Noted</option>
                        <option value="Revise & Resubmit">Revise & Resubmit</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="submittalTable">
                    <thead>
                        <tr>
                            <th width="15%">Submittal #</th>
                            <th width="30%">Subject / Spec Section</th>
                            <th width="20%">Project</th>
                            <th width="15%">Status</th>
                            <th width="15%">Date Sent</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($submittals) === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted">No Submittals found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($submittals as $sub): ?>
                        <tr class="sub-row" data-project="<?= htmlspecialchars($sub['project_name']) ?>" data-status="<?= htmlspecialchars($sub['status']) ?>">
                            <td class="fw-semibold"><?= htmlspecialchars($sub['submittal_number']) ?></td>
                            <td>
                                <div class="fw-semibold mb-0 subject-text">
                                    <?php if ($sub['spec_section']) echo htmlspecialchars($sub['spec_section']) . ' - '; ?>
                                    <?= htmlspecialchars($sub['title']) ?>
                                </div>
                                <div class="text-muted small recipient-text" style="font-size: 0.75rem;">To: <?= htmlspecialchars($sub['submitted_to_name']) ?> (<?= htmlspecialchars($sub['submitted_to_role']) ?>)</div>
                            </td>
                            <td><a href="#" class="project-link"><?= htmlspecialchars($sub['project_name']) ?></a></td>
                            <td>
                                <span class="pill <?= getStatusPillClass($sub['status']) ?>">
                                    <?= htmlspecialchars($sub['status']) ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($sub['date_sent']) ?></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn p-0 border-0" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li><a class="dropdown-item small" href="submittal_details.php?id=<?= $sub['id'] ?>">View Details & Documents</a></li>
                                        <li><a class="dropdown-item small" href="submittal_details.php?id=<?= $sub['id'] ?>">Log Review Decision</a></li>
                                        <li><a class="dropdown-item small" href="procurement.php?submittal_id=<?= $sub['id'] ?>">Convert to Purchase Order</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item small text-danger" href="submittals.php?action=archive&id=<?= $sub['id'] ?>">Close / Archive</a></li>
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

    <!-- New Submittal Modal -->
    <div class="modal fade" id="newSubmittalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_submittal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Create New Submittal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Project</label>
                            <select name="project_id" class="form-select" required>
                                <option value="">Select Project</option>
                                <?php foreach($projects as $proj): ?>
                                    <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Submittal Type</label>
                            <select name="submittal_type" class="form-select" required>
                                <option value="Product Data">Product Data</option>
                                <option value="Shop Drawing">Shop Drawing</option>
                                <option value="Sample">Sample</option>
                                <option value="Test Report">Test Report</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Spec Section (e.g. 08 44 13)</label>
                            <input type="text" name="spec_section" class="form-control" placeholder="08 44 13">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Subject / Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Glazed Aluminum Curtain Walls" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Sent To / Assinged Name</label>
                            <input type="text" name="submitted_to_name" list="directoryReviewersList" class="form-control" placeholder="e.g. K. Weerasinghe" required>
                            <datalist id="directoryReviewersList">
                                <?php foreach($directoryReviewers as $dr): ?>
                                    <option value="<?= htmlspecialchars($dr['name']) ?>"><?= htmlspecialchars($dr['name']) ?> (<?= htmlspecialchars($dr['role_type']) ?><?= !empty($dr['company_name']) ? ' - ' . htmlspecialchars($dr['company_name']) : '' ?>)</option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Sent To Role</label>
                            <select name="submitted_to_role" class="form-select" required>
                                <option value="Architect">Architect</option>
                                <option value="Consultant Engineer">Consultant Engineer</option>
                                <option value="Client">Client</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Review Due Date</label>
                            <input type="date" name="review_due_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Attachment (Up to 25MB - PDF, DWG, Image)</label>
                            <input type="file" name="attachment_file" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-semibold">Create Submittal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Filters implementation
        function filterTable() {
            let searchFilter = document.getElementById('searchFilter').value.toLowerCase();
            let projectFilter = document.getElementById('projectFilter').value;
            let statusFilter = document.getElementById('statusFilter').value;
            
            let rows = document.querySelectorAll(".sub-row");
            rows.forEach(row => {
                let text = row.querySelector('.subject-text').innerText.toLowerCase() + " " + row.querySelector('.recipient-text').innerText.toLowerCase();
                let proj = row.getAttribute('data-project');
                let status = row.getAttribute('data-status');
                
                let matchesSearch = text.includes(searchFilter);
                let matchesProject = (projectFilter === "All" || proj === projectFilter);
                let matchesStatus = (statusFilter === "All" || status === statusFilter);
                
                if(matchesSearch && matchesProject && matchesStatus) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        document.getElementById('searchFilter').addEventListener('keyup', filterTable);
    </script>
</body>
</html>