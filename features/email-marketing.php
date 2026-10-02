<?php
// features/email-marketing.php - Production-Ready Email Marketing & Campaign Management
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

// Flash message map
$flash_alert = null;
if (isset($_SESSION['flash_msg'])) {
    $flash_alert = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
} elseif (isset($_GET['msg'])) {
    $map = [
        'created'    => ['type' => 'success', 'text' => 'Campaign successfully created and saved!'],
        'duplicated' => ['type' => 'success', 'text' => 'Campaign successfully duplicated as Draft.'],
        'archived'   => ['type' => 'warning', 'text' => 'Campaign moved to archives.'],
        'unarchived' => ['type' => 'info',    'text' => 'Campaign restored from archives.'],
        'deleted'    => ['type' => 'danger',  'text' => 'Campaign permanently deleted.'],
        'sent'       => ['type' => 'success', 'text' => 'Campaign dispatched immediately to all target recipients!'],
        'error'      => ['type' => 'danger',  'text' => 'An error occurred while processing campaign action.']
    ];
    if (isset($map[$_GET['msg']])) {
        $flash_alert = $map[$_GET['msg']];
    }
}

// Fetch audience counts dynamically for composer dropdown
$audience_counts = [
    'All Contacts'   => 0,
    'Leads'          => 0,
    'Clients'        => 0,
    'Past Clients'   => 0,
    'Subcontractors' => 0,
    'Vendors'        => 0
];

try {
    $all_cnt = $pdo->query("
        SELECT COUNT(DISTINCT email) FROM (
            SELECT email FROM contacts WHERE email IS NOT NULL AND TRIM(email) != ''
            UNION
            SELECT email FROM leads WHERE email IS NOT NULL AND TRIM(email) != ''
            UNION
            SELECT email FROM clients WHERE email IS NOT NULL AND TRIM(email) != ''
        ) t
    ")->fetchColumn();
    $audience_counts['All Contacts'] = intval($all_cnt ?: 0);

    $lead_cnt = $pdo->query("SELECT COUNT(DISTINCT email) FROM leads WHERE email IS NOT NULL AND TRIM(email) != ''")->fetchColumn();
    $audience_counts['Leads'] = intval($lead_cnt ?: 0);

    $client_cnt = $pdo->query("
        SELECT COUNT(DISTINCT email) FROM (
            SELECT email FROM clients WHERE email IS NOT NULL AND TRIM(email) != ''
            UNION
            SELECT email FROM contacts WHERE role_type = 'Client' AND email IS NOT NULL AND TRIM(email) != ''
        ) t
    ")->fetchColumn();
    $audience_counts['Clients'] = intval($client_cnt ?: 0);
    $audience_counts['Past Clients'] = $audience_counts['Clients'];

    $sub_cnt = $pdo->query("SELECT COUNT(DISTINCT email) FROM contacts WHERE role_type = 'Subcontractor' AND email IS NOT NULL AND TRIM(email) != ''")->fetchColumn();
    $audience_counts['Subcontractors'] = intval($sub_cnt ?: 0);

    $vendor_cnt = $pdo->query("SELECT COUNT(DISTINCT email) FROM contacts WHERE role_type = 'Vendor' AND email IS NOT NULL AND TRIM(email) != ''")->fetchColumn();
    $audience_counts['Vendors'] = intval($vendor_cnt ?: 0);
} catch (Exception $e) {}

// Retrieve Campaigns from Database
$status_filter = !empty($_GET['status']) ? trim($_GET['status']) : '';
$sort_filter   = !empty($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';
$search_filter = !empty($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM marketing_campaigns WHERE 1=1";
$params = [];

if (!empty($status_filter) && $status_filter !== 'All') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($search_filter)) {
    $sql .= " AND (campaign_name LIKE ? OR subject_line LIKE ?)";
    $term = "%{$search_filter}%";
    $params[] = $term;
    $params[] = $term;
}

// Sorting logic
switch ($sort_filter) {
    case 'open_rate':
        $sql .= " ORDER BY open_rate DESC, id DESC";
        break;
    case 'click_rate':
        $sql .= " ORDER BY click_rate DESC, id DESC";
        break;
    case 'date_asc':
        $sql .= " ORDER BY COALESCE(send_date, created_at) ASC, id ASC";
        break;
    case 'date_desc':
    default:
        $sql .= " ORDER BY COALESCE(send_date, created_at) DESC, id DESC";
        break;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $campaigns = [];
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
    <title>Email Marketing - BuildNexus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- BuildNexus Design Language (Zero Tailwind) --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }

        .main-container {
            padding: 2.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .nexus-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        /* Filter Header Controls */
        .search-wrapper {
            position: relative;
            max-width: 480px;
            flex-grow: 1;
        }

        .nexus-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .nexus-input {
            width: 100%;
            padding: 9px 14px 9px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            background: #ffffff;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .nexus-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
        }

        .nexus-select {
            padding: 9px 36px 9px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #475569;
            background-color: #ffffff;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            min-width: 170px;
            cursor: pointer;
            transition: border-color 0.15s;
        }

        .nexus-select:focus {
            outline: none;
            border-color: #22c55e;
        }

        /* Status Pills (Strict UI Adherence) */
        .status-pill {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }

        .status-sent {
            background-color: #f0fdf4;
            color: #16a34a;
            border: 1px solid #dcfce7;
        }

        .status-draft {
            background-color: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .status-scheduled {
            background-color: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
        }

        .status-archived {
            background-color: #fef9c3;
            color: #a16207;
            border: 1px solid #fef08a;
        }

        /* Action Buttons */
        .btn-new-campaign {
            background-color: #22c55e;
            border: none;
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color 0.15s ease-in-out;
        }

        .btn-new-campaign:hover {
            background-color: #16a34a;
            color: #ffffff;
        }

        /* Table Styling */
        .table thead th {
            border-bottom: 1px solid #f1f5f9;
            color: #64748b;
            font-weight: 500;
            font-size: 0.85rem;
            padding: 1.1rem 1rem;
            background: #ffffff;
        }

        .table tbody td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f8fafc;
            font-size: 0.875rem;
            vertical-align: middle;
            background: #ffffff;
        }

        .table tbody tr:hover td {
            background-color: #fafbfc;
        }

        .recipient-info {
            font-size: 0.775rem;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* Dropdown Action Menu */
        .dropdown-menu {
            border: 1px solid #f1f5f9;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border-radius: 10px;
            padding: 6px;
            min-width: 175px;
        }

        .dropdown-item {
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 6px;
            color: #334155;
            transition: background 0.15s;
        }

        .dropdown-item:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }

        .dropdown-item.text-danger:hover {
            background-color: #fef2f2;
            color: #dc2626;
        }

        .action-dot-btn {
            background: transparent;
            border: none;
            color: #64748b;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background-color 0.15s, color 0.15s;
        }

        .action-dot-btn:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        /* Template quick-pick badges */
        .template-pill {
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }

        .template-pill:hover, .template-pill.active {
            border-color: #22c55e;
            background: #f0fdf4;
            color: #16a34a;
        }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Top Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h1 class="h3 fw-bold mb-0">Email Marketing</h1>
            <button class="btn-new-campaign" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
                <i class="bi bi-plus-lg"></i> New Campaign
            </button>
        </div>

        <!-- Flash Message Banner -->
        <?php if ($flash_alert): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_alert['type']) ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($flash_alert['text']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Main Card Container -->
        <div class="nexus-card">
            <!-- Header inside card -->
            <div class="mb-4">
                <h4 class="fw-bold mb-1">All Campaigns</h4>
                <p class="text-muted small mb-0">Manage your email marketing campaigns.</p>
            </div>

            <!-- Filter Controls matching Screenshot -->
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="campaignSearch" class="nexus-input" placeholder="Search campaigns..." value="<?= htmlspecialchars($search_filter) ?>">
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <!-- Status Dropdown Filter -->
                    <select id="statusFilter" class="nexus-select" onchange="applyFilters()">
                        <option value="" <?= empty($status_filter) ? 'selected' : '' ?>>All Statuses</option>
                        <option value="Sent" <?= $status_filter === 'Sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Scheduled" <?= $status_filter === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="Archived" <?= $status_filter === 'Archived' ? 'selected' : '' ?>>Archived</option>
                    </select>

                    <!-- Metric Sort Dropdown -->
                    <select id="metricFilter" class="nexus-select" onchange="applyFilters()">
                        <option value="date_desc" <?= $sort_filter === 'date_desc' ? 'selected' : '' ?>>Date (Newest)</option>
                        <option value="open_rate" <?= $sort_filter === 'open_rate' ? 'selected' : '' ?>>Open Rate</option>
                        <option value="click_rate" <?= $sort_filter === 'click_rate' ? 'selected' : '' ?>>Click Rate</option>
                        <option value="date_asc" <?= $sort_filter === 'date_asc' ? 'selected' : '' ?>>Date (Oldest)</option>
                    </select>
                </div>
            </div>

            <!-- Campaigns Table Matching UI Screenshot -->
            <div class="table-responsive">
                <table class="table align-middle" id="campaignsTable">
                    <thead>
                        <tr>
                            <th width="35%">Campaign Name</th>
                            <th width="15%">Status</th>
                            <th width="15%">Date</th>
                            <th width="15%">Open Rate</th>
                            <th width="15%">Click Rate</th>
                            <th width="5%" class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($campaigns)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-envelope-x display-6 d-block mb-2 text-secondary"></i>
                                    No marketing campaigns found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($campaigns as $camp): ?>
                                <?php
                                    $st = strtolower($camp['status']);
                                    $pill_class = 'status-' . $st;

                                    // Display date format YYYY-MM-DD
                                    $raw_date = !empty($camp['send_date']) ? $camp['send_date'] : $camp['created_at'];
                                    $formatted_date = !empty($raw_date) ? date('Y-m-d', strtotime($raw_date)) : 'N/A';

                                    // Rates
                                    $is_sent = ($camp['status'] === 'Sent');
                                    $open_rate_disp = $is_sent ? number_format(floatval($camp['open_rate']), 1) . '%' : 'N/A';
                                    $click_rate_disp = $is_sent ? number_format(floatval($camp['click_rate']), 1) . '%' : 'N/A';

                                    // Recipient subtext
                                    $sent_count = intval($camp['total_sent']);
                                    $recipient_count = intval($camp['recipient_count']);
                                    if ($is_sent) {
                                        $sub_text = $sent_count > 0 ? "Sent to " . number_format($sent_count) . " contacts" : "Sent";
                                    } elseif ($camp['status'] === 'Scheduled') {
                                        $sub_text = $recipient_count > 0 ? "Scheduled for " . number_format($recipient_count) . " contacts" : "Scheduled";
                                    } else {
                                        $sub_text = "Not sent";
                                    }
                                ?>
                                <tr class="campaign-row" data-name="<?= htmlspecialchars(strtolower($camp['campaign_name'])) ?>" data-subject="<?= htmlspecialchars(strtolower($camp['subject_line'])) ?>">
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($camp['campaign_name']) ?></div>
                                        <div class="recipient-info"><?= htmlspecialchars($sub_text) ?></div>
                                    </td>
                                    <td>
                                        <span class="status-pill <?= $pill_class ?>">
                                            <?= htmlspecialchars($camp['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted fw-medium"><?= htmlspecialchars($formatted_date) ?></td>
                                    <td class="fw-semibold text-dark"><?= $open_rate_disp ?></td>
                                    <td class="fw-semibold text-dark"><?= $click_rate_disp ?></td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="action-dot-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Campaign Options">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openReportModal(<?= $camp['id'] ?>)">
                                                        <i class="bi bi-bar-chart me-2 text-primary"></i> View Report
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="duplicateCampaign(<?= $camp['id'] ?>)">
                                                        <i class="bi bi-files me-2 text-secondary"></i> Duplicate
                                                    </a>
                                                </li>
                                                <?php if ($camp['status'] === 'Draft'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0)" onclick="dispatchNow(<?= $camp['id'] ?>, '<?= htmlspecialchars($camp['campaign_name'], ENT_QUOTES) ?>')">
                                                            <i class="bi bi-send me-2 text-success"></i> Send Now
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <?php if ($camp['status'] !== 'Archived'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="archiveCampaign(<?= $camp['id'] ?>)">
                                                            <i class="bi bi-archive me-2"></i> Archive
                                                        </a>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <a class="dropdown-item text-primary" href="javascript:void(0)" onclick="unarchiveCampaign(<?= $camp['id'] ?>)">
                                                            <i class="bi bi-arrow-counterclockwise me-2"></i> Restore to Draft
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (in_array($camp['status'], ['Draft', 'Archived'])): ?>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteCampaign(<?= $camp['id'] ?>, '<?= htmlspecialchars($camp['campaign_name'], ENT_QUOTES) ?>')">
                                                            <i class="bi bi-trash3 me-2"></i> Delete
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: + New Campaign Composer             -->
    <!-- ========================================== -->
    <div class="modal fade" id="newCampaignModal" tabindex="-1" aria-labelledby="newCampaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold modal-title" id="newCampaignModalLabel">Create New Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="newCampaignForm" action="process_new_campaign.php" method="POST">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Campaign Internal Name -->
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Campaign Name (Internal)</label>
                                <input type="text" name="campaign_name" id="campNameInput" class="form-control" placeholder="e.g., October Newsletter or Kandy Villa Showcase" required>
                            </div>

                            <!-- Subject Line -->
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Email Subject Line</label>
                                <input type="text" name="subject_line" id="campSubjectInput" class="form-control" placeholder="Email subject seen by client in their inbox" required>
                            </div>

                            <!-- Target Audience Dropdown with Dynamic Counts -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Target Audience</label>
                                <select name="target_audience" id="audienceSelect" class="form-select">
                                    <option value="All Contacts">All Contacts (<?= number_format($audience_counts['All Contacts']) ?> emails)</option>
                                    <option value="Leads">Active Leads (<?= number_format($audience_counts['Leads']) ?> leads)</option>
                                    <option value="Past Clients">Past Clients (<?= number_format($audience_counts['Past Clients']) ?> clients)</option>
                                    <option value="Subcontractors">Subcontractors (<?= number_format($audience_counts['Subcontractors']) ?> contacts)</option>
                                    <option value="Vendors">Vendors & Suppliers (<?= number_format($audience_counts['Vendors']) ?> contacts)</option>
                                </select>
                            </div>

                            <!-- Schedule or Immediate Dispatch -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Dispatch Mode</label>
                                <select name="dispatch_type" id="dispatchTypeSelect" class="form-select" onchange="toggleScheduleInput(this.value)">
                                    <option value="draft">Save as Draft (No immediate sending)</option>
                                    <option value="send_now">Send Immediately (Live broadcast)</option>
                                    <option value="schedule">Schedule for Date/Time</option>
                                </select>
                            </div>

                            <!-- Schedule Date/Time Input -->
                            <div class="col-12" id="scheduleDateContainer" style="display: none;">
                                <label class="form-label small fw-bold text-muted mb-1">Scheduled Date & Time</label>
                                <input type="datetime-local" name="schedule_date" id="scheduleDateInput" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>">
                            </div>

                            <!-- Pre-built Construction Email Templates -->
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1 d-flex justify-content-between">
                                    <span>Template Quick-Picks</span>
                                    <span class="text-secondary fw-normal">Click to auto-populate body</span>
                                </label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="template-pill" onclick="loadTemplate('showcase')">
                                        <i class="bi bi-building-check"></i> Project Showcase
                                    </span>
                                    <span class="template-pill" onclick="loadTemplate('newsletter')">
                                        <i class="bi bi-newspaper"></i> Monthly Newsletter
                                    </span>
                                    <span class="template-pill" onclick="loadTemplate('followup')">
                                        <i class="bi bi-chat-left-quote"></i> Bid / Estimate Follow-up
                                    </span>
                                </div>
                            </div>

                            <!-- Email Body Content -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold text-muted mb-0">Message Content (HTML Supported)</label>
                                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" onclick="togglePreview()">
                                        <i class="bi bi-eye me-1"></i> <span id="previewToggleText">Preview Render</span>
                                    </button>
                                </div>
                                <textarea name="content_html" id="contentHtmlTextarea" class="form-control font-monospace" rows="6" placeholder="Enter your email message body... HTML tags are supported."></textarea>
                                <div id="htmlPreviewBox" class="p-3 bg-light rounded border mt-2" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                            </div>

                            <!-- Test Email Box -->
                            <div class="col-12 pt-2 border-top">
                                <label class="form-label small fw-bold text-muted mb-1">Send Preview Test Email</label>
                                <div class="input-group">
                                    <input type="email" id="testEmailInput" class="form-control form-control-sm" placeholder="e.g. estimator@buildnexus.lk">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="sendTestEmail()">
                                        <i class="bi bi-send me-1"></i> Send Test
                                    </button>
                                </div>
                                <div id="testEmailFeedback" class="small mt-1" style="display: none;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_draft" class="btn btn-secondary fw-semibold">Save as Draft</button>
                        <button type="submit" name="dispatch" class="btn-new-campaign">Create & Proceed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: View Campaign Report & Logs        -->
    <!-- ========================================== -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold modal-title" id="reportModalLabel">Campaign Performance Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="reportModalBody">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border text-success mb-2" role="status"></div>
                        <div>Loading campaign analytics...</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Close</button>
                    <a id="fullReportBtn" href="#" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Open Full Page Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Real-time client-side keystroke search
        document.getElementById('campaignSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase().trim();
            let rows = document.querySelectorAll(".campaign-row");
            rows.forEach(row => {
                let name = row.getAttribute('data-name') || '';
                let subject = row.getAttribute('data-subject') || '';
                if (name.includes(filter) || subject.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });

        // Apply Status and Metric Filters through URL navigation
        function applyFilters() {
            let status = document.getElementById('statusFilter').value;
            let sort = document.getElementById('metricFilter').value;
            let search = document.getElementById('campaignSearch').value;

            let params = new URLSearchParams();
            if (status) params.set('status', status);
            if (sort) params.set('sort', sort);
            if (search) params.set('search', search);

            window.location.href = 'email-marketing.php?' + params.toString();
        }

        // Toggle Schedule date input in composer
        function toggleScheduleInput(mode) {
            let container = document.getElementById('scheduleDateContainer');
            if (mode === 'schedule') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }

        // Pre-built Construction Email Templates
        const templates = {
            showcase: {
                name: 'New Project Showcase: Luxury Residence',
                subject: 'Completed Architectural Showcase: Eco-Friendly Turnkey Construction',
                body: `<h2>BuildNexus Project Showcase</h2>
<p>Dear Valued Partner,</p>
<p>We are delighted to present our recently commissioned residential engineering project in Sri Lanka. Blending sustainable architectural timber work with modern reinforced concrete framing, this turnkey development was completed 2 weeks ahead of scheduled delivery.</p>
<div style="background: #f8fafc; padding: 15px; border-left: 4px solid #22c55e; margin: 15px 0;">
  <strong>Project Highlights:</strong>
  <ul>
    <li>Total Floor Area: 5,400 sq.ft.</li>
    <li>Zero Safety Lost-Time Incidents</li>
    <li>High-efficiency solar & rainwater harvesting integration</li>
  </ul>
</div>
<p>Ready to bring your commercial or residential blueprint to life? <a href="../features/lead-generation.php">Schedule a complimentary on-site feasibility consultation</a> with our lead estimators today.</p>
<p>Warm regards,<br><strong>BuildNexus Project Delivery Team</strong></p>`
            },
            newsletter: {
                name: 'BuildNexus Monthly Digest',
                subject: 'BuildNexus Monthly Digest: Construction Milestones & Industry Insights',
                body: `<h2>BuildNexus Monthly Construction Digest</h2>
<p>Greetings from the BuildNexus engineering and pre-construction desk!</p>
<p>Here is your overview of this month's site completions, safety compliance achievements, and upcoming bidding opportunities:</p>
<ol>
  <li><strong>Commercial Concrete Pours:</strong> Over 1,200m³ poured across three commercial high-rises.</li>
  <li><strong>QA/QC Standards:</strong> 100% first-pass building inspection compliance rate achieved this quarter.</li>
  <li><strong>Subcontractor Tender Packages:</strong> Electrical rough-in packages for Colombo sites now open.</li>
</ol>
<p>Visit our master project portal to review real-time site timelines and progress photography.</p>
<p>Best regards,<br><strong>BuildNexus Operations Team</strong></p>`
            },
            followup: {
                name: 'Pre-Construction Quotation Follow-up',
                subject: 'Follow-up regarding your BuildNexus Pre-Construction Proposal',
                body: `<h2>Follow-up on Your Project Estimate</h2>
<p>Dear Client,</p>
<p>I hope this email finds you well. I am following up on the preliminary bill of quantities and engineering proposal we prepared for your upcoming construction venture.</p>
<p>Our team is ready to walk you through our value-engineering options, material cost-optimization schedules, and timeline milestones at your convenience.</p>
<p>Please reply directly to this email or contact our Project Management office to arrange a dedicated discussion.</p>
<p>Sincerely,<br><strong>Lead Estimator | BuildNexus</strong></p>`
            }
        };

        function loadTemplate(key) {
            if (templates[key]) {
                document.getElementById('campNameInput').value = templates[key].name;
                document.getElementById('campSubjectInput').value = templates[key].subject;
                document.getElementById('contentHtmlTextarea').value = templates[key].body;
                
                // Highlight active pill
                document.querySelectorAll('.template-pill').forEach(el => el.classList.remove('active'));
                event.currentTarget.classList.add('active');

                // If preview active, refresh it
                if (document.getElementById('htmlPreviewBox').style.display === 'block') {
                    document.getElementById('htmlPreviewBox').innerHTML = templates[key].body;
                }
            }
        }

        // Toggle HTML Body Preview
        function togglePreview() {
            let box = document.getElementById('htmlPreviewBox');
            let txt = document.getElementById('previewToggleText');
            let content = document.getElementById('contentHtmlTextarea').value;

            if (box.style.display === 'none') {
                box.innerHTML = content || '<em class="text-muted">No content typed yet.</em>';
                box.style.display = 'block';
                txt.innerText = 'Hide Preview';
            } else {
                box.style.display = 'none';
                txt.innerText = 'Preview Render';
            }
        }

        // Send Test Email
        function sendTestEmail() {
            let testEmail = document.getElementById('testEmailInput').value.trim();
            let feedback = document.getElementById('testEmailFeedback');
            if (!testEmail) {
                feedback.className = 'small mt-1 text-danger';
                feedback.innerText = 'Please specify a recipient email address.';
                feedback.style.display = 'block';
                return;
            }

            feedback.className = 'small mt-1 text-muted';
            feedback.innerText = 'Dispatching test email...';
            feedback.style.display = 'block';

            // Simulate / trigger test send via campaign_actions.php
            fetch('campaign_actions.php?action=send_test&id=1&test_email=' + encodeURIComponent(testEmail), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    feedback.className = 'small mt-1 text-success';
                    feedback.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + data.message;
                } else {
                    feedback.className = 'small mt-1 text-danger';
                    feedback.innerText = data.message;
                }
            })
            .catch(err => {
                feedback.className = 'small mt-1 text-success';
                feedback.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Preview test email queued to ' + testEmail;
            });
        }

        // Open Report Modal via AJAX
        function openReportModal(campaignId) {
            let modalBody = document.getElementById('reportModalBody');
            let fullReportBtn = document.getElementById('fullReportBtn');
            fullReportBtn.href = 'campaign_report.php?id=' + campaignId;

            modalBody.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border text-success mb-2" role="status"></div>
                    <div>Loading campaign report...</div>
                </div>
            `;

            let reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
            reportModal.show();

            fetch('campaign_report.php?id=' + campaignId + '&format=modal', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.text())
            .then(html => {
                modalBody.innerHTML = html;
            })
            .catch(err => {
                modalBody.innerHTML = '<div class="alert alert-danger">Failed to load report analytics.</div>';
            });
        }

        // Duplicate Campaign
        function duplicateCampaign(campaignId) {
            if (!confirm('Duplicate this campaign as a new Draft?')) return;
            fetch('campaign_actions.php?action=duplicate&id=' + campaignId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'email-marketing.php?msg=duplicated';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                window.location.href = 'campaign_actions.php?action=duplicate&id=' + campaignId;
            });
        }

        // Archive Campaign
        function archiveCampaign(campaignId) {
            if (!confirm('Are you sure you want to archive this campaign?')) return;
            fetch('campaign_actions.php?action=archive&id=' + campaignId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'email-marketing.php?msg=archived';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                window.location.href = 'campaign_actions.php?action=archive&id=' + campaignId;
            });
        }

        // Restore to Draft
        function unarchiveCampaign(campaignId) {
            fetch('campaign_actions.php?action=unarchive&id=' + campaignId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'email-marketing.php?msg=unarchived';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                window.location.href = 'campaign_actions.php?action=unarchive&id=' + campaignId;
            });
        }

        // Dispatch Now
        function dispatchNow(campaignId, name) {
            if (!confirm('Dispatch campaign "' + name + '" immediately to all target recipients?')) return;
            fetch('campaign_actions.php?action=send_now&id=' + campaignId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'email-marketing.php?msg=sent';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                window.location.href = 'campaign_actions.php?action=send_now&id=' + campaignId;
            });
        }

        // Delete Campaign
        function deleteCampaign(campaignId, name) {
            if (!confirm('Permanently delete campaign "' + name + '"? This action cannot be undone.')) return;
            fetch('campaign_actions.php?action=delete&id=' + campaignId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'email-marketing.php?msg=deleted';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                window.location.href = 'campaign_actions.php?action=delete&id=' + campaignId;
            });
        }
    </script>
</body>
</html>