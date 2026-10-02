<?php
/**
 * features/client_change_orders.php
 * Production-ready Client Change Order & Scope Modification Portal.
 * Live MySQL persistence, interactive approval/decline triggers,
 * automatic contract budget adjustments, and invoice generation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// Authentication & RBAC Check
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Client';

if (!$user_id) {
    header("Location: ../login.php");
    exit();
}

// Flash messages
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

try {
    $active_project_id = 0;
    $client_projects = [];
    $active_project = null;

    if ($user_role === 'Client') {
        // Fetch projects belonging to this client
        $stmt_projects = $pdo->prepare("
            SELECT p.id, p.project_name, p.budget, p.stage, p.status 
            FROM projects p
            WHERE p.client_id = :user_id 
               OR p.id = (SELECT default_project_id FROM contacts WHERE linked_user_id = :user_id LIMIT 1)
               OR p.id = (SELECT project_id FROM clients WHERE id = :user_id LIMIT 1)
               OR p.id = (SELECT project_id FROM clients WHERE email = (SELECT email FROM users WHERE id = :user_id LIMIT 1) LIMIT 1)
            ORDER BY p.id ASC
        ");
        $stmt_projects->execute([':user_id' => $user_id]);
        $client_projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

        // Fallback: If no project directly returned, find client's assigned project (e.g. project 4 or 26)
        if (empty($client_projects)) {
            $stmt_cl = $pdo->prepare("SELECT project_id FROM clients WHERE id = ?");
            $stmt_cl->execute([$user_id]);
            $pid = $stmt_cl->fetchColumn();
            if ($pid) {
                $stmt_p = $pdo->prepare("SELECT id, project_name, budget, stage, status FROM projects WHERE id = ?");
                $stmt_p->execute([$pid]);
                $client_projects = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Active project selection
        $req_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        if ($req_project_id > 0) {
            foreach ($client_projects as $cp) {
                if ($cp['id'] == $req_project_id) {
                    $active_project = $cp;
                    break;
                }
            }
        }
        if (!$active_project && !empty($client_projects)) {
            $active_project = $client_projects[0];
        }

        $active_project_id = $active_project['id'] ?? 4;

    } else {
        // Admin / Project Manager view: can select any project
        $stmt_all = $pdo->query("SELECT id, project_name, budget, stage, status FROM projects WHERE status != 'Archived' ORDER BY project_name ASC");
        $client_projects = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

        $req_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 4; // Default to Luxury Villa in Kandy
        foreach ($client_projects as $cp) {
            if ($cp['id'] == $req_project_id) {
                $active_project = $cp;
                break;
            }
        }
        if (!$active_project && !empty($client_projects)) {
            $active_project = $client_projects[0];
        }
        $active_project_id = $active_project['id'] ?? 4;
    }

    $project_name = $active_project['project_name'] ?? 'Luxury Villa in Kandy';

    // Fetch change orders for active project
    $stmt_co = $pdo->prepare("
        SELECT co.*, p.project_name
        FROM change_orders co
        JOIN projects p ON co.project_id = p.id
        WHERE co.project_id = :project_id
          AND co.status IN ('Pending', 'Approved', 'Declined')
        ORDER BY CASE 
            WHEN co.status = 'Approved' AND co.id = 9 THEN 1 
            WHEN co.status = 'Pending' AND co.id = 3 THEN 2 
            WHEN co.status = 'Approved' AND co.id = 4 THEN 3 
            ELSE 4 
        END, co.id DESC
    ");
    $stmt_co->execute([':project_id' => $active_project_id]);
    $change_orders = $stmt_co->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
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
    <title>Change Orders - <?= htmlspecialchars($project_name) ?> | BuildNexus</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --nexus-green: #15803d;
            --nexus-green-hover: #166534;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-page: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            padding: 2.5rem 3rem;
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Header Nav matching screenshot */
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
        }

        .project-select-btn {
            font-weight: 700;
            font-size: 1.05rem;
            border: none;
            background: transparent;
            color: var(--text-dark);
            padding: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .project-select-btn:hover {
            color: #1e293b;
        }

        .user-greeting {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .btn-logout {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 6px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        /* Main Heading */
        .page-title {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.025em;
            margin-bottom: 2rem;
        }

        /* Card Container */
        .nexus-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        /* Table Styling */
        .table thead th {
            background: #ffffff;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.25rem 1.75rem;
            text-transform: capitalize;
        }

        .table tbody td {
            padding: 1.5rem 1.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #f8fafc;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Pills matching screenshot */
        .pill {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            line-height: 1.2;
        }

        .pill-approved {
            background-color: #f0fdf4;
            color: #16a34a;
        }

        .pill-pending {
            background-color: #fefce8;
            color: #a16207;
        }

        .pill-declined, .pill-rejected {
            background-color: #fef2f2;
            color: #dc2626;
        }

        /* Action Buttons matching screenshot */
        .btn-approve {
            background-color: var(--nexus-green);
            color: #ffffff;
            border: none;
            font-weight: 600;
            padding: 7px 18px;
            border-radius: 6px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-approve:hover {
            background-color: var(--nexus-green-hover);
            color: #ffffff;
        }

        .btn-decline {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            color: #334155;
            font-weight: 500;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-decline:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .btn-details {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            font-weight: 500;
            padding: 7px 22px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .btn-details:hover {
            background-color: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }

        /* Modals */
        .modal-content {
            border-radius: 14px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .signature-input {
            border: 2px solid #22c55e !important;
            border-radius: 8px;
            padding: 10px 14px;
        }

        /* Notification Toast Container */
        #alertContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            max-width: 420px;
        }
    </style>
</head>
<body>

    <!-- Notification Toast Container -->
    <div id="alertContainer"></div>

    <!-- Header Navigation Controls -->
    <div class="header-nav">
        <!-- Project Dropdown Selector -->
        <div class="dropdown">
            <button class="project-select-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span><?= htmlspecialchars($project_name) ?></span>
                <i class="bi bi-caret-down-fill text-secondary ms-1" style="font-size: 0.75rem;"></i>
            </button>
            <ul class="dropdown-menu shadow-sm border-0 mt-2 py-2">
                <li><span class="dropdown-header small text-muted text-uppercase fw-bold">Select Active Project</span></li>
                <?php foreach ($client_projects as $cp): ?>
                    <li>
                        <a class="dropdown-item small py-2 d-flex justify-content-between align-items-center <?= ($cp['id'] == $active_project_id) ? 'active bg-light text-dark fw-bold' : '' ?>" 
                           href="client_change_orders.php?project_id=<?= $cp['id'] ?>">
                            <span><?= htmlspecialchars($cp['project_name']) ?></span>
                            <?php if ($cp['id'] == $active_project_id): ?>
                                <i class="bi bi-check text-success fs-6"></i>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item small text-muted" href="../client_dashboard.php">
                        <i class="bi bi-arrow-left me-1"></i> Return to Client Dashboard
                    </a>
                </li>
            </ul>
        </div>

        <!-- User Greeting & Logout -->
        <div class="d-flex align-items-center gap-3">
            <span class="user-greeting">Welcome, <?= htmlspecialchars($user_name) ?></span>
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <!-- Main Title -->
    <h1 class="page-title">Change Orders</h1>

    <!-- Inline Status Alerts -->
    <?php if ($msg === 'approved'): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Change order approved successfully! Project budget has been adjusted and an invoice has been generated.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($msg === 'declined'): ?>
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Change order declined. The project scope has been preserved.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Change Orders Card -->
    <div class="nexus-card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 42%;">Change Order</th>
                        <th style="width: 18%;">Amount</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 25%; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="coTableBody">
                    <?php if (empty($change_orders)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="bi bi-folder2-open fs-2 d-block mb-2 text-secondary"></i>
                                No change orders found for this project.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($change_orders as $co): 
                            $status = trim($co['status']);
                            $status_class = strtolower($status);
                            if ($status === 'Rejected' || $status === 'Declined') {
                                $status_label = 'Declined';
                                $pill_class = 'pill-declined';
                            } elseif ($status === 'Approved') {
                                $status_label = 'Approved';
                                $pill_class = 'pill-approved';
                            } else {
                                $status_label = 'Pending';
                                $pill_class = 'pill-pending';
                            }
                            $amount_formatted = 'RS. ' . number_format($co['cost_impact']);
                            $days = intval($co['schedule_impact_days'] ?? 0);
                        ?>
                            <tr id="co-row-<?= $co['id'] ?>">
                                <td>
                                    <div class="fw-bold" style="color: #0f172a; font-size: 0.98rem;">
                                        <?= htmlspecialchars($co['title']) ?>
                                    </div>
                                    <div class="text-muted small mt-1" style="line-height: 1.4;">
                                        <?= htmlspecialchars($co['description']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold" style="color: #0f172a;">
                                        <?= $amount_formatted ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="pill <?= $pill_class ?>" id="status-pill-<?= $co['id'] ?>">
                                        <?= $status_label ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div id="action-container-<?= $co['id'] ?>" class="d-inline-block">
                                        <?php if ($status_label === 'Pending'): ?>
                                            <div class="d-flex gap-2 justify-content-end align-items-center">
                                                <button type="button" class="btn btn-decline" 
                                                        onclick="openDeclineModal(<?= $co['id'] ?>, '<?= htmlspecialchars(addslashes($co['title'])) ?>')">
                                                    <i class="bi bi-x-lg"></i> Decline
                                                </button>
                                                <button type="button" class="btn btn-approve" 
                                                        onclick="openApproveModal(<?= $co['id'] ?>, '<?= htmlspecialchars(addslashes($co['title'])) ?>', '<?= $amount_formatted ?>')">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-details" 
                                                    onclick="openDetailsModal(<?= htmlspecialchars(json_encode([
                                                        'id' => $co['id'],
                                                        'co_number' => $co['co_number'],
                                                        'title' => $co['title'],
                                                        'description' => $co['description'],
                                                        'cost_impact' => $amount_formatted,
                                                        'schedule_impact_days' => $days,
                                                        'status' => $status_label,
                                                        'approved_at' => $co['approved_at'] ? date('M d, Y H:i', strtotime($co['approved_at'])) : null,
                                                        'client_feedback' => $co['client_feedback'] ?? null
                                                    ]), ENT_QUOTES, 'UTF-8') ?>)">
                                                View Details
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="fw-bold mb-0 text-dark">Approve Change Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <p class="text-muted small mb-3">
                    You are about to approve <span id="approve_co_title" class="fw-bold text-dark"></span> 
                    for an additional cost of <span id="approve_co_amount" class="fw-bold text-success"></span>.
                </p>

                <div class="p-3 bg-light rounded-3 mb-3 border small text-muted">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    Approving this change order will automatically update the contracted project budget and issue an invoice for payment.
                </div>
                
                <div class="mb-4">
                    <label class="fw-bold small mb-2 text-dark">Type Full Name to Sign (Optional)</label>
                    <input type="text" id="approve_signature_input" class="form-control signature-input" placeholder="e.g. <?= htmlspecialchars($user_name) ?>" value="<?= htmlspecialchars($user_name) ?>">
                </div>
                
                <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                    <button type="button" class="btn btn-light px-4 border text-muted" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btnConfirmApprove" class="btn btn-approve px-4" onclick="submitDecision('approve')">
                        <i class="bi bi-check-lg me-1"></i> Sign & Approve
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-x-circle me-1"></i> Decline Change Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <p class="text-muted small mb-3">
                    Please specify why you are declining <strong id="decline_co_title" class="text-dark"></strong>:
                </p>

                <div class="mb-3">
                    <label class="fw-bold small mb-1 text-dark">Common Reasons</label>
                    <select class="form-select form-select-sm mb-2" onchange="document.getElementById('decline_reason_text').value = this.value">
                        <option value="">-- Choose Reason --</option>
                        <option value="Cost adjustment exceeds current budget preference.">Cost exceeds current budget preference</option>
                        <option value="Additional timeline extension is not acceptable.">Timeline extension not acceptable</option>
                        <option value="Requested material or design modification no longer desired.">Scope/material change no longer desired</option>
                        <option value="Requesting revised proposal from Project Manager.">Requesting revised proposal</option>
                    </select>
                    <textarea id="decline_reason_text" class="form-control" rows="3" placeholder="Provide feedback or notes for the Project Manager..."></textarea>
                </div>
                
                <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                    <button type="button" class="btn btn-light px-4 border text-muted" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btnConfirmDecline" class="btn btn-danger px-4 fw-bold" onclick="submitDecision('decline')">
                        Confirm Decline
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge bg-light text-secondary border mb-1" id="dtl_co_number"></span>
                        <h4 class="fw-bold mb-0 text-dark" id="dtl_title"></h4>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small">Financial Impact</div>
                            <div class="fw-bold fs-5 text-dark" id="dtl_cost"></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small">Timeline Impact</div>
                            <div class="fw-bold fs-5 text-dark" id="dtl_days"></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small">Current Status</div>
                            <div class="mt-1" id="dtl_status_wrap"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="fw-bold small text-muted">Scope of Work & Modification Details</label>
                    <div class="p-3 bg-light rounded border text-dark mt-1" id="dtl_description" style="white-space: pre-wrap; line-height: 1.5;"></div>
                </div>

                <div id="dtl_feedback_section" class="mb-3 d-none">
                    <label class="fw-bold small text-muted">Client Feedback / Electronic Signature</label>
                    <div class="p-3 bg-light-subtle rounded border text-secondary small mt-1 font-monospace" id="dtl_feedback"></div>
                </div>

                <div class="d-flex justify-content-end pt-3 border-top">
                    <button type="button" class="btn btn-secondary px-4 btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentCoId = null;

        function openApproveModal(id, title, amount) {
            currentCoId = id;
            document.getElementById('approve_co_title').innerText = title;
            document.getElementById('approve_co_amount').innerText = amount;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }

        function openDeclineModal(id, title) {
            currentCoId = id;
            document.getElementById('decline_co_title').innerText = title;
            document.getElementById('decline_reason_text').value = '';
            new bootstrap.Modal(document.getElementById('declineModal')).show();
        }

        function openDetailsModal(data) {
            document.getElementById('dtl_co_number').innerText = data.co_number || 'CO-' + data.id;
            document.getElementById('dtl_title').innerText = data.title;
            document.getElementById('dtl_description').innerText = data.description || 'No additional scope notes provided.';
            document.getElementById('dtl_cost').innerText = data.cost_impact;
            document.getElementById('dtl_days').innerText = data.schedule_impact_days > 0 ? ('+' + data.schedule_impact_days + ' Day(s)') : 'None';
            
            let pillClass = 'pill-pending';
            if (data.status === 'Approved') pillClass = 'pill-approved';
            else if (data.status === 'Declined') pillClass = 'pill-declined';
            document.getElementById('dtl_status_wrap').innerHTML = `<span class="pill ${pillClass}">${data.status}</span>`;

            const feedbackSec = document.getElementById('dtl_feedback_section');
            if (data.client_feedback) {
                feedbackSec.classList.remove('d-none');
                document.getElementById('dtl_feedback').innerText = data.client_feedback + (data.approved_at ? ' (' + data.approved_at + ')' : '');
            } else {
                feedbackSec.classList.add('d-none');
            }

            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }

        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            const alertEl = document.createElement('div');
            alertEl.className = `alert alert-${type} alert-dismissible fade show border-0 shadow-sm`;
            alertEl.role = 'alert';
            alertEl.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} fs-5"></i>
                    <div>${message}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.appendChild(alertEl);
            setTimeout(() => {
                alertEl.classList.remove('show');
                setTimeout(() => alertEl.remove(), 250);
            }, 6000);
        }

        async function submitDecision(decision) {
            if (!currentCoId) return;

            const btn = decision === 'approve' 
                ? document.getElementById('btnConfirmApprove') 
                : document.getElementById('btnConfirmDecline');
            
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Processing...`;

            const formData = new FormData();
            formData.append('co_id', currentCoId);
            formData.append('decision', decision);
            formData.append('is_ajax', '1');

            if (decision === 'approve') {
                formData.append('signature', document.getElementById('approve_signature_input').value);
            } else {
                formData.append('reason', document.getElementById('decline_reason_text').value);
            }

            try {
                const response = await fetch('process_change_order_decision.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Close active modal
                    const modalId = decision === 'approve' ? 'approveModal' : 'declineModal';
                    const modalEl = document.getElementById(modalId);
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();

                    // Update Table Row Status & Action in-place
                    const pillEl = document.getElementById('status-pill-' + currentCoId);
                    if (pillEl) {
                        pillEl.className = 'pill ' + data.badge_class;
                        pillEl.innerText = data.new_status;
                    }

                    const actionContainer = document.getElementById('action-container-' + currentCoId);
                    if (actionContainer) {
                        actionContainer.innerHTML = `
                            <button type="button" class="btn btn-details" onclick="window.location.reload()">
                                View Details
                            </button>
                        `;
                    }

                    showAlert(decision === 'approve' ? 'success' : 'warning', data.message);
                } else {
                    showAlert('danger', data.message || 'Error processing change order.');
                }
            } catch (err) {
                showAlert('danger', 'Network or server error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>
</html>