<?php
require_once '../db.php'; 
session_start();

// Security: Allow only Admin or PM access (assuming 'Project Manager' role exists)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

// Handle Form Submission for New RFI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_rfi') {
    $project_id = $_POST['project_id'];
    $subject = $_POST['subject'];
    $assigned_to_name = $_POST['assigned_to_name'];
    $assigned_to_role = $_POST['assigned_to_role'];
    $question_details = $_POST['question_details'];
    $due_date = $_POST['due_date'];
    
    // File upload logic
    $attachment_file = null;
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
        // Create directory if not exists
        $uploadDir = '../uploads/rfis/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = time() . '_' . basename($_FILES['attachment_file']['name']);
        $targetFile = $uploadDir . $fileName;
        
        // 25MB limit (25 * 1024 * 1024)
        if ($_FILES['attachment_file']['size'] <= 26214400) {
            if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $targetFile)) {
                $attachment_file = 'uploads/rfis/' . $fileName;
            }
        }
    }

    try {
        // Generate RFI Number
        $year = date('Y');
        // Get the latest ID to auto-increment visually
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM project_rfis");
        $row = $stmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;
        $rfi_number = sprintf("RFI-%s-%03d", $year, $nextId);

        $insertStmt = $pdo->prepare("
            INSERT INTO project_rfis 
            (rfi_number, project_id, created_by, assigned_to_name, assigned_to_role, subject, question_details, due_date, attachment_file) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insertStmt->execute([
            $rfi_number,
            $project_id,
            $_SESSION['user_id'],
            $assigned_to_name,
            $assigned_to_role,
            $subject,
            $question_details,
            $due_date,
            $attachment_file
        ]);
        
        header("Location: rfi.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error saving RFI: " . $e->getMessage();
    }
}

// Handle Close Action
if (isset($_GET['action']) && $_GET['action'] == 'close' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("UPDATE project_rfis SET status = 'Closed' WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header("Location: rfi.php");
    exit();
}

try {
    // Fetch projects for the filter dropdown
    $projects = $pdo->query("SELECT id, project_name FROM projects")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch directory contacts for RFI recipient suggestions
    $directoryRecipients = $pdo->query("SELECT name, role_type, company_name FROM contacts WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch RFIs
    $rfisStmt = $pdo->query("
        SELECT r.*, p.project_name 
        FROM project_rfis r 
        LEFT JOIN projects p ON r.project_id = p.id 
        ORDER BY r.created_at DESC
    ");
    $rfis = $rfisStmt->fetchAll(PDO::FETCH_ASSOC);

    // Dynamic Status Calculation for Overdue
    $currentDate = date('Y-m-d');
    foreach ($rfis as &$r) {
        if ($r['status'] === 'Open' && $r['due_date'] < $currentDate && $r['due_date'] !== null) {
            $r['display_status'] = 'Overdue';
        } else {
            $r['display_status'] = $r['status'];
        }
    }
    unset($r);

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
    <title>RFIs - BuildNexus</title>
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
        .pill-answered { background: #f0fdf4; color: #16a34a; }
        .pill-open { background: #eff6ff; color: #2563eb; }
        .pill-overdue { background: #fef2f2; color: #dc2626; }
        .pill-closed { background: #f1f5f9; color: #475569; }

        .btn-new-rfi { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 8px 16px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; }
        .btn-new-rfi:hover { background-color: #16a34a; box-shadow: 0 4px 6px -1px rgba(22,163,74,0.2); }
    </style>
</head>
<body>

    <div class="main-container">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">Requests for Information (RFIs)</h1>
            <button class="btn-new-rfi" data-bs-toggle="modal" data-bs-target="#newRFIModal">
                <i class="bi bi-plus-lg"></i> New RFI
            </button>
        </div>

        <div class="nexus-card">
            <div class="mb-4">
                <h4 class="fw-bold mb-1">All RFIs</h4>
                <p class="text-muted small">Manage project questions and clarifications.</p>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="rfiSearch" class="nexus-input" placeholder="Search by subject or recipient...">
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
                        <option value="Open">Open</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Answered">Answered</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="rfiTable">
                    <thead>
                        <tr>
                            <th width="15%">RFI #</th>
                            <th width="30%">Subject</th>
                            <th width="20%">Project</th>
                            <th width="15%">Status</th>
                            <th width="15%">Date Sent</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rfis) === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted">No RFIs found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rfis as $r): ?>
                        <tr class="rfi-row" data-project="<?= htmlspecialchars($r['project_name']) ?>" data-status="<?= htmlspecialchars($r['display_status']) ?>">
                            <td class="fw-semibold"><?= htmlspecialchars($r['rfi_number']) ?></td>
                            <td>
                                <div class="fw-semibold mb-0 subject-text"><?= htmlspecialchars($r['subject']) ?></div>
                                <div class="text-muted small recipient-text" style="font-size: 0.75rem;">To: <?= htmlspecialchars($r['assigned_to_name']) ?> (<?= htmlspecialchars($r['assigned_to_role']) ?>)</div>
                            </td>
                            <td><a href="#" class="project-link"><?= htmlspecialchars($r['project_name']) ?></a></td>
                            <td>
                                <span class="pill pill-<?= strtolower($r['display_status']) ?>">
                                    <?= htmlspecialchars($r['display_status']) ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($r['date_sent']) ?></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn p-0 border-0" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li><a class="dropdown-item small" href="rfi_details.php?id=<?= $r['id'] ?>">View Details / Respond</a></li>
                                        <li><a class="dropdown-item small" href="#" onclick="alert('Reminder sent!');">Send Reminder</a></li>
                                        <li><a class="dropdown-item small" href="change-orders.php?rfi_id=<?= $r['id'] ?>">Convert to Change Order</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item small text-danger" href="rfi.php?action=close&id=<?= $r['id'] ?>">Close RFI</a></li>
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

    <!-- New RFI Modal -->
    <div class="modal fade" id="newRFIModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_rfi">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Create New RFI</h5>
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
                            <label class="form-label small fw-semibold">Due Date</label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Window specification clarification" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assigned To Name</label>
                            <input type="text" name="assigned_to_name" list="directoryRecipientsList" class="form-control" placeholder="e.g. K. Weerasinghe" required>
                            <datalist id="directoryRecipientsList">
                                <?php foreach($directoryRecipients as $dr): ?>
                                    <option value="<?= htmlspecialchars($dr['name']) ?>"><?= htmlspecialchars($dr['name']) ?> (<?= htmlspecialchars($dr['role_type']) ?><?= !empty($dr['company_name']) ? ' - ' . htmlspecialchars($dr['company_name']) : '' ?>)</option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assigned To Role</label>
                            <select name="assigned_to_role" class="form-select" required>
                                <option value="Architect">Architect</option>
                                <option value="Client">Client</option>
                                <option value="Subcontractor">Subcontractor</option>
                                <option value="Structural Engineer">Structural Engineer</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Question / Description</label>
                            <textarea name="question_details" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Attachment (Up to 25MB)</label>
                            <input type="file" name="attachment_file" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-semibold">Create RFI</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Filters implementation
        function filterTable() {
            let searchFilter = document.getElementById('rfiSearch').value.toLowerCase();
            let projectFilter = document.getElementById('projectFilter').value;
            let statusFilter = document.getElementById('statusFilter').value;
            
            let rows = document.querySelectorAll(".rfi-row");
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

        document.getElementById('rfiSearch').addEventListener('keyup', filterTable);
    </script>
</body>
</html>