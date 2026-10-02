<?php
// features/client_selections.php - Client Selections & Finishes Studio
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

$current_user_role = $_SESSION['role'] ?? 'Admin';
$is_client_view = ($current_user_role === 'Client') || (isset($_GET['view']) && $_GET['view'] === 'client');

// 1. Fetch Active Projects
try {
    $p_stmt = $pdo->query("SELECT id, project_name, project_code, budget FROM projects WHERE status = 'Active' ORDER BY id DESC");
    $projects = $p_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($projects)) {
        // Fallback query for all projects
        $projects = $pdo->query("SELECT id, project_name, project_code, budget FROM projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $projects = [];
}

// 2. Determine Active Project
$selected_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if ($selected_project_id <= 0 && !empty($projects)) {
    // Default to Davis Kitchen Remodel or first project
    foreach ($projects as $p) {
        if (stripos($p['project_name'], 'Davis Kitchen Remodel') !== false) {
            $selected_project_id = $p['id'];
            break;
        }
    }
    if ($selected_project_id <= 0) {
        $selected_project_id = $projects[0]['id'];
    }
}

// 3. Fetch Rooms for this Project
$rooms = [];
try {
    $r_stmt = $pdo->prepare("SELECT * FROM project_selection_rooms WHERE project_id = ? ORDER BY id ASC");
    $r_stmt->execute([$selected_project_id]);
    $rooms = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no rooms exist for this project, create default Kitchen room
    if (empty($rooms)) {
        $ins_r = $pdo->prepare("INSERT INTO project_selection_rooms (project_id, room_name, budget_allowance) VALUES (?, 'Kitchen', 250000.00)");
        $ins_r->execute([$selected_project_id]);
        $new_r_id = $pdo->lastInsertId();
        $rooms = [['id' => $new_r_id, 'project_id' => $selected_project_id, 'room_name' => 'Kitchen', 'budget_allowance' => 250000.00]];
    }
} catch (Exception $e) {
    $rooms = [];
}

// 4. Determine Active Room
$selected_room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$active_room = null;

if ($selected_room_id > 0) {
    foreach ($rooms as $r) {
        if ($r['id'] == $selected_room_id) {
            $active_room = $r;
            break;
        }
    }
}

if (!$active_room && !empty($rooms)) {
    // Default to Kitchen if available, else first room
    foreach ($rooms as $r) {
        if (strcasecmp($r['room_name'], 'Kitchen') === 0) {
            $active_room = $r;
            break;
        }
    }
    if (!$active_room) {
        $active_room = $rooms[0];
    }
    $selected_room_id = $active_room['id'];
}

// Active Project details
$active_project_name = 'Davis Kitchen Remodel';
foreach ($projects as $p) {
    if ($p['id'] == $selected_project_id) {
        $active_project_name = $p['project_name'];
        break;
    }
}

// 5. Fetch Selection Items for Active Room
$selections = [];
$total_budget = floatval($active_room['budget_allowance'] ?? 250000.00);
$used_budget = 0.00;

try {
    $i_stmt = $pdo->prepare("SELECT * FROM project_selections WHERE room_id = ? ORDER BY id ASC");
    $i_stmt->execute([$selected_room_id]);
    $selections = $i_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($selections as $item) {
        if (intval($item['include_in_budget']) === 1) {
            $used_budget += (floatval($item['unit_price']) * floatval($item['quantity']));
        }
    }
} catch (Exception $e) {
    $selections = [];
}

$progress_percent = $total_budget > 0 ? ($used_budget / $total_budget) * 100 : 0;
if ($progress_percent > 100) {
    $gauge_color = '#ef4444'; // Red
} elseif ($progress_percent >= 90) {
    $gauge_color = '#f59e0b'; // Amber
} else {
    $gauge_color = '#22c55e'; // Green
}

// Get distinct categories in this room for filter dropdown
$categories = [];
foreach ($selections as $s) {
    if (!empty($s['category']) && !in_array($s['category'], $categories)) {
        $categories[] = $s['category'];
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selections Studio - <?= htmlspecialchars($active_project_name) ?> - BuildNexus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- BuildNexus Design Language (Zero Tailwind) --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .main-wrapper {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Left / Center Content Container */
        .content-container {
            flex-grow: 1;
            padding: 1.5rem 2rem;
            overflow-y: auto;
            background: #ffffff;
            transition: all 0.25s ease;
        }

        /* Header Navigation Breadcrumb */
        .breadcrumb-dropdown {
            cursor: pointer;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.95rem;
        }

        .breadcrumb-dropdown:hover {
            color: #22c55e;
        }

        /* Real-Time Budget Gauge (Matching UI Screenshot) */
        .budget-header-box {
            text-align: right;
            min-width: 320px;
        }

        .progress-nexus {
            height: 6px;
            background: #e2e8f0;
            border-radius: 10px;
            margin: 6px 0;
            overflow: hidden;
            width: 100%;
        }

        .progress-fill {
            height: 100%;
            background: <?= $gauge_color ?>;
            width: <?= min(100, $progress_percent) ?>%;
            border-radius: 10px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        /* Buttons matching Screenshot */
        .btn-convert-outline {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #1e293b;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 7px 18px;
            transition: all 0.15s;
        }

        .btn-convert-outline:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
        }

        .btn-nexus-success {
            background-color: #22c55e;
            border: 1px solid #16a34a;
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            font-size: 0.85rem;
            padding: 7px 16px;
            transition: background 0.15s;
        }

        .btn-nexus-success:hover {
            background-color: #16a34a;
            color: #ffffff;
        }

        .btn-nexus-outline {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #1e293b;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 6px 12px;
            transition: all 0.15s;
        }

        .btn-nexus-outline:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* Filter Selects */
        .filter-select {
            padding: 6px 30px 6px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #475569;
            background: #ffffff;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            cursor: pointer;
        }

        /* Card Matrix (Strict adherence to UI Screenshot) */
        .card-matrix {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .selection-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .selection-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        .card-img-container {
            width: 100%;
            height: 220px;
            overflow: hidden;
            position: relative;
            background: #f1f5f9;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .selection-card:hover .card-img {
            transform: scale(1.03);
        }

        /* Status Badges Pinned to Top Left */
        .status-pill {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
            z-index: 2;
        }

        .pill-Approved {
            color: #16a34a;
            border: 1px solid #dcfce7;
        }

        .pill-Declined {
            color: #dc2626;
            border: 1px solid #fee2e2;
        }

        .pill-Pending {
            color: #ca8a04;
            border: 1px solid #fef9c3;
        }

        /* Right Slide-out Drawer Panel (My Items) */
        .sidebar-util {
            width: 380px;
            background: #ffffff;
            border-left: 1px solid #e2e8f0;
            display: flex;
            height: 100vh;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .sidebar-icons {
            width: 60px;
            border-right: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 0;
            gap: 2.2rem;
            background: #ffffff;
        }

        .nav-icon {
            color: #94a3b8;
            font-size: 1.25rem;
            cursor: pointer;
            transition: color 0.15s;
        }

        .nav-icon:hover {
            color: #1e293b;
        }

        .nav-icon.active {
            color: #0f172a;
            position: relative;
        }

        .nav-icon.active::after {
            content: '';
            position: absolute;
            right: -19px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #0f172a;
        }

        .library-panel {
            flex-grow: 1;
            padding: 1.5rem;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .search-box-nexus {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 9px 12px 9px 36px;
            font-size: 0.875rem;
            background: #f8fafc;
            width: 100%;
            outline: none;
            transition: border-color 0.15s;
        }

        .search-box-nexus:focus {
            border-color: #22c55e;
            background: #ffffff;
        }

        .btn-create-catalog {
            background-color: #22c55e;
            color: #ffffff;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 0.9rem;
            width: 100%;
            transition: background 0.15s;
        }

        .btn-create-catalog:hover {
            background-color: #16a34a;
            color: #ffffff;
        }

        /* Client Presentation Banner */
        .client-presentation-banner {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 10px 16px;
            border-radius: 6px;
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <!-- Main Interior Selections Content Area -->
    <div class="content-container" id="mainContentArea">

        <!-- Client Mode Notice (if applicable) -->
        <?php if ($is_client_view): ?>
            <div class="client-presentation-banner d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-shield-check text-primary me-2"></i>
                    <strong>Client Voting Mode:</strong> Review selections for <em><?= htmlspecialchars($active_room['room_name']) ?></em>. You can approve finishes, request changes, or add notes.
                </div>
                <a href="client_selections.php?room_id=<?= $selected_room_id ?>" class="btn btn-sm btn-outline-primary py-0">Exit to Studio</a>
            </div>
        <?php endif; ?>

        <!-- Top Header Matching UI Screenshot -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <!-- Left: Breadcrumb Dropdown -->
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-list fs-4 text-dark cursor-pointer" onclick="toggleSidebarDrawer()" title="Toggle Material Catalog"></i>
                <div class="dropdown">
                    <span class="text-muted small breadcrumb-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars($active_project_name) ?> / 
                        <span class="text-dark fw-bold"><?= htmlspecialchars($active_room['room_name']) ?> <i class="bi bi-chevron-down ms-1" style="font-size: 0.8rem;"></i></span>
                    </span>
                    <ul class="dropdown-menu shadow-sm border-0" style="border-radius: 10px;">
                        <li class="dropdown-header small text-uppercase fw-bold">Select Space / Room</li>
                        <?php foreach ($rooms as $r): ?>
                            <li>
                                <a class="dropdown-item small d-flex justify-content-between align-items-center <?= ($r['id'] == $selected_room_id) ? 'active bg-success' : '' ?>" href="client_selections.php?project_id=<?= $selected_project_id ?>&room_id=<?= $r['id'] ?>">
                                    <span><i class="bi bi-door-closed me-2"></i><?= htmlspecialchars($r['room_name']) ?></span>
                                    <span class="badge bg-light text-dark ms-2">RS. <?= number_format($r['budget_allowance']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item small text-success fw-bold" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#newRoomModal">
                                <i class="bi bi-plus-circle me-2"></i>+ Add New Room
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Middle: Real-Time Dynamic Budget Gauge -->
            <div class="budget-header-box">
                <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span class="text-dark">Total Budget</span>
                    <span class="text-success" id="totalBudgetDisplay">RS. <?= number_format($total_budget, 2) ?></span>
                </div>
                <div class="progress-nexus">
                    <div class="progress-fill" id="budgetProgressFill" style="width: <?= min(100, $progress_percent) ?>%; background-color: <?= $gauge_color ?>;"></div>
                </div>
                <div class="small text-muted" id="usedBudgetDisplay">
                    RS. <?= number_format($used_budget, 2) ?> Used
                </div>
            </div>

            <!-- Right: Action Buttons (Mood Boards, Convert, Preview & Share) -->
            <div class="d-flex gap-2 align-items-center">
                <a href="mood-boards.php?project_id=<?= $selected_project_id ?>" class="btn-nexus-outline text-decoration-none d-flex align-items-center" title="Go to Architectural Mood Boards & Concepts">
                    <i class="bi bi-palette me-1 text-success"></i> Mood Boards
                </a>
                <button class="btn-convert-outline" data-bs-toggle="modal" data-bs-target="#convertModal">
                    Convert
                </button>
                <div class="btn-group">
                    <button class="btn-nexus-success" data-bs-toggle="dropdown" aria-expanded="false">
                        Preview and Share <i class="bi bi-caret-down-fill ms-1" style="font-size: 0.75rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 10px;">
                        <li>
                            <a class="dropdown-item small" href="client_selections.php?project_id=<?= $selected_project_id ?>&room_id=<?= $selected_room_id ?>&view=client">
                                <i class="bi bi-person-check me-2 text-primary"></i> For Client
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item small" href="javascript:void(0)" onclick="window.print()">
                                <i class="bi bi-printer me-2 text-secondary"></i> For Team (Print Specs)
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Filter Bar Matching UI Screenshot -->
        <div class="d-flex gap-3 mb-4 flex-wrap">
            <select id="statusFilter" class="filter-select" onchange="filterCards()">
                <option value="All">Approval Status: All</option>
                <option value="Approved">Approved</option>
                <option value="Declined">Declined</option>
                <option value="Pending">Pending</option>
            </select>

            <select id="categoryFilter" class="filter-select" onchange="filterCards()">
                <option value="All">Filter by: All</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Selection Card Matrix Grid -->
        <div class="card-matrix" id="selectionCardsGrid">
            <?php if (empty($selections)): ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-palette display-4 d-block mb-3 text-secondary"></i>
                    <h5>No selections added to this space yet.</h5>
                    <p class="small text-muted">Use the <strong>"Create +"</strong> button in the right drawer to add materials, tiles, and fixtures.</p>
                </div>
            <?php else: ?>
                <?php foreach ($selections as $item): ?>
                    <?php
                        $st = $item['approval_status'];
                        $icon = ($st === 'Approved') ? 'check-circle-fill' : (($st === 'Declined') ? 'x-circle-fill' : 'clock-fill');
                        $is_checked = (intval($item['include_in_budget']) === 1) ? 'checked' : '';
                    ?>
                    <div class="selection-card" id="card-item-<?= $item['id'] ?>" data-status="<?= htmlspecialchars($st) ?>" data-category="<?= htmlspecialchars($item['category'] ?? '') ?>" data-title="<?= htmlspecialchars(strtolower($item['item_name'])) ?>">
                        <!-- Status Pill Pinned Top Left -->
                        <div class="status-pill pill-<?= $st ?>" id="pill-<?= $item['id'] ?>">
                            <i class="bi bi-<?= $icon ?>" id="pill-icon-<?= $item['id'] ?>"></i> 
                            <span id="pill-text-<?= $item['id'] ?>"><?= htmlspecialchars($st) ?></span>
                        </div>

                        <!-- Product Thumbnail -->
                        <div class="card-img-container">
                            <img src="<?= htmlspecialchars($item['photo_url']) ?>" class="card-img" alt="<?= htmlspecialchars($item['item_name']) ?>" onerror="this.src='https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600&h=450&fit=crop'">
                        </div>

                        <!-- Card Content -->
                        <div class="p-3">
                            <h6 class="fw-bold mb-1" style="font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($item['item_name']) ?>">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </h6>
                            <p class="text-muted small mb-3">
                                Price: <span class="text-dark fw-bold">RS. <?= number_format($item['unit_price'], 2) ?></span>
                            </p>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="small text-muted">
                                    Qty: <span class="text-dark fw-bold"><?= number_format($item['quantity'], 0) ?></span>
                                </span>
                                <div class="form-check small mb-0">
                                    <input class="form-check-input" type="checkbox" id="check-<?= $item['id'] ?>" <?= $is_checked ?> onchange="toggleInclude(<?= $item['id'] ?>, this.checked)">
                                    <label class="form-check-label text-muted" for="check-<?= $item['id'] ?>" style="cursor: pointer;">
                                        Include in Budget
                                    </label>
                                </div>
                            </div>

                            <!-- Action Buttons: Decline & Approve -->
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-nexus-outline flex-grow-1 btn-sm" onclick="changeStatus(<?= $item['id'] ?>, 'Declined')">
                                    <i class="bi bi-x-circle me-1"></i> Decline
                                </button>
                                <button type="button" class="btn btn-nexus-success flex-grow-1 btn-sm" onclick="changeStatus(<?= $item['id'] ?>, 'Approved')">
                                    Approve
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right-Hand Material Catalog Drawer (My Items) -->
    <div class="sidebar-util" id="materialDrawer">
        <!-- Thin Utility Icon Strip -->
        <div class="sidebar-icons">
            <i class="bi bi-scissors nav-icon active" title="Cut & Finishes"></i>
            <i class="bi bi-box-seam nav-icon" title="Components & Fixtures"></i>
            <i class="bi bi-house nav-icon" title="Room Spaces"></i>
            <i class="bi bi-palette nav-icon" title="Color & Swatches"></i>
            <i class="bi bi-images nav-icon" title="Mood Boards"></i>
            <i class="bi bi-file-earmark-text nav-icon" title="Trade Specs"></i>
        </div>

        <!-- Library Panel -->
        <div class="library-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">My Items</h6>
                <i class="bi bi-x-lg text-muted cursor-pointer small" onclick="toggleSidebarDrawer()" title="Close Drawer"></i>
            </div>

            <!-- Search box -->
            <div class="position-relative mb-3">
                <i class="bi bi-search position-absolute text-muted" style="left: 12px; top: 10px;"></i>
                <input type="text" id="catalogSearch" class="search-box-nexus" placeholder="Search" onkeyup="filterDrawerSearch(this.value)">
            </div>

            <!-- Filter Buttons -->
            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-light border btn-sm px-3" onclick="alert('Displaying all active catalog assets for <?= htmlspecialchars($active_room['room_name']) ?>.')">
                    <i class="bi bi-sliders me-1"></i> Filters
                </button>
                <button class="btn btn-light border btn-sm px-3" onclick="alert('Customize catalog finish tags & sorting.')">
                    Customize
                </button>
            </div>

            <!-- Empty state or catalog content matching UI screenshot -->
            <div class="flex-grow-1 d-flex flex-column justify-content-center text-center text-muted small py-5" id="drawerEmptyNotice">
                <i class="bi bi-inbox fs-2 text-secondary mb-2"></i>
                <p class="mb-0">No items in this room yet.</p>
                <span class="text-secondary" style="font-size: 0.75rem;">Click "Create +" below to add a finish to <?= htmlspecialchars($active_room['room_name']) ?>.</span>
            </div>

            <!-- Bottom Action Button: Create + -->
            <div class="pt-3 border-top mt-auto">
                <button class="btn-create-catalog" data-bs-toggle="modal" data-bs-target="#newFinishModal">
                    Create <i class="bi bi-plus-lg ms-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: Convert Selections (PO / Change Order) -->
<!-- ========================================== -->
<div class="modal fade" id="convertModal" tabindex="-1" aria-labelledby="convertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold modal-title" id="convertModalLabel">Convert Selections</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">
                    Transform finishes and material allowances for <strong><?= htmlspecialchars($active_room['room_name']) ?></strong> into active procurement or contractual change records.
                </p>

                <div class="d-flex flex-column gap-3">
                    <!-- Option 1: Purchase Order -->
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-cart-check text-success me-2"></i> Generate Purchase Order
                            </h6>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Procurement</span>
                        </div>
                        <p class="text-muted small mb-3">
                            Automatically exports all client-approved items in this room to the procurement module for vendor dispatch.
                        </p>
                        <button type="button" class="btn btn-nexus-success btn-sm w-100" onclick="convertPurchaseOrder()">
                            Export Approved Items to PO
                        </button>
                    </div>

                    <!-- Option 2: Change Order -->
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-file-earmark-diff text-primary me-2"></i> Create Change Order
                            </h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Contractual</span>
                        </div>
                        <p class="text-muted small mb-3">
                            Passes client upgrades or variance over budget cap (RS. <?= number_format(max(0, $used_budget - $total_budget), 2) ?>) into contract change orders.
                        </p>
                        <button type="button" class="btn btn-outline-primary btn-sm w-100 fw-bold" onclick="convertChangeOrder()">
                            Create Budget Variance Change Order
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: + Create New Finish / Selection     -->
<!-- ========================================== -->
<div class="modal fade" id="newFinishModal" tabindex="-1" aria-labelledby="newFinishModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold modal-title" id="newFinishModalLabel">Add Finish / Selection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newFinishForm" onsubmit="submitNewFinish(event)">
                <input type="hidden" name="action" value="create_item">
                <input type="hidden" name="project_id" value="<?= $selected_project_id ?>">
                <input type="hidden" name="room_id" value="<?= $selected_room_id ?>">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-muted mb-1">Item / Finish Name</label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Heritage Hex Porcelain or Marble Top Vanity" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">Category</label>
                            <select name="category" class="form-select">
                                <option value="Flooring">Flooring</option>
                                <option value="Wall Finishes">Wall Finishes</option>
                                <option value="Plumbing">Plumbing & Fixtures</option>
                                <option value="Cabinetry">Cabinetry & Millwork</option>
                                <option value="Lighting">Lighting</option>
                                <option value="Hardware">Hardware</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Unit Price (RS.)</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Quantity</label>
                            <input type="number" step="0.01" name="quantity" class="form-control" value="1" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted mb-1">Photo Image URL</label>
                            <input type="url" name="photo_url" class="form-control" placeholder="https://images.unsplash.com/... or paste web link">
                            <div class="form-text small">Alternatively, upload an image file below:</div>
                            <input type="file" name="photo_file" class="form-control mt-1" accept="image/*">
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_in_budget" id="modalIncludeInBudget" checked>
                                <label class="form-check-label text-dark fw-semibold" for="modalIncludeInBudget">
                                    Include in Room Budget Allowance
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-success fw-bold">Add to Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: + Add New Room                      -->
<!-- ========================================== -->
<div class="modal fade" id="newRoomModal" tabindex="-1" aria-labelledby="newRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold modal-title" id="newRoomModalLabel">Add New Space / Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newRoomForm" onsubmit="submitNewRoom(event)">
                <input type="hidden" name="action" value="create_room">
                <input type="hidden" name="project_id" value="<?= $selected_project_id ?>">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Room / Space Name</label>
                        <input type="text" name="room_name" class="form-control" placeholder="e.g. Living Area, Balcony Terrace, Powder Room" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Budget Allowance Cap (RS.)</label>
                        <input type="number" step="0.01" name="budget_allowance" class="form-control" placeholder="e.g. 150000.00" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nexus-success fw-bold">Create Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const activeRoomId = <?= $selected_room_id ?>;
    const activeProjectId = <?= $selected_project_id ?>;

    // Toggle Material Drawer Panel
    function toggleSidebarDrawer() {
        const drawer = document.getElementById('materialDrawer');
        if (drawer.style.display === 'none') {
            drawer.style.display = 'flex';
        } else {
            drawer.style.display = 'none';
        }
    }

    // Live AJAX Approval / Decline Status Toggle
    function changeStatus(itemId, newStatus) {
        let formData = new FormData();
        formData.append('action', 'change_status');
        formData.append('item_id', itemId);
        formData.append('status', newStatus);

        fetch('selections_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update badge pill styling immediately
                const pill = document.getElementById('pill-' + itemId);
                const pillText = document.getElementById('pill-text-' + itemId);
                const pillIcon = document.getElementById('pill-icon-' + itemId);
                const card = document.getElementById('card-item-' + itemId);

                pill.className = 'status-pill pill-' + newStatus;
                pillText.innerText = newStatus;
                pillIcon.className = (newStatus === 'Approved') ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill';
                card.setAttribute('data-status', newStatus);

                updateBudgetGaugeUI(data.metrics);
            } else {
                alert('Action failed: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Network error updating status:', err);
        });
    }

    // Live AJAX "Include in Budget" Checkbox Toggle
    function toggleInclude(itemId, isChecked) {
        let formData = new FormData();
        formData.append('action', 'toggle_include');
        formData.append('item_id', itemId);
        formData.append('included', isChecked ? '1' : '0');

        fetch('selections_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateBudgetGaugeUI(data.metrics);
            } else {
                alert('Failed to update budget inclusion: ' + data.message);
            }
        })
        .catch(err => console.error(err));
    }

    // Refresh Budget Gauge UI without full page reload
    function updateBudgetGaugeUI(metrics) {
        if (!metrics) return;

        const totalDisplay = document.getElementById('totalBudgetDisplay');
        const usedDisplay = document.getElementById('usedBudgetDisplay');
        const fillBar = document.getElementById('budgetProgressFill');

        totalDisplay.innerText = 'RS. ' + Number(metrics.budget_allowance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        usedDisplay.innerText = 'RS. ' + Number(metrics.used_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Used';

        fillBar.style.width = Math.min(100, metrics.progress_percent) + '%';
        fillBar.style.backgroundColor = metrics.gauge_color;
    }

    // Client-side Card Filtering
    function filterCards() {
        const statusVal = document.getElementById('statusFilter').value;
        const catVal = document.getElementById('categoryFilter').value;

        const cards = document.querySelectorAll('.selection-card');
        cards.forEach(card => {
            const cardStatus = card.getAttribute('data-status') || '';
            const cardCategory = card.getAttribute('data-category') || '';

            const statusMatch = (statusVal === 'All' || cardStatus === statusVal);
            const catMatch = (catVal === 'All' || cardCategory === catVal);

            if (statusMatch && catMatch) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Filter drawer search
    function filterDrawerSearch(keyword) {
        const query = keyword.toLowerCase().trim();
        const cards = document.querySelectorAll('.selection-card');
        cards.forEach(card => {
            const title = card.getAttribute('data-title') || '';
            if (!query || title.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Handle Adding New Finish Item
    function submitNewFinish(e) {
        e.preventDefault();
        const form = document.getElementById('newFinishForm');
        const formData = new FormData(form);

        fetch('selections_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Close modal and reload space to display updated finishes
                const modal = bootstrap.Modal.getInstance(document.getElementById('newFinishModal'));
                if (modal) modal.hide();
                window.location.reload();
            } else {
                alert('Error creating finish: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error adding finish. Please check inputs.');
        });
    }

    // Handle Creating New Room
    function submitNewRoom(e) {
        e.preventDefault();
        const form = document.getElementById('newRoomForm');
        const formData = new FormData(form);

        fetch('selections_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.redirect) {
                window.location.href = data.redirect;
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => alert('Failed to create space.'));
    }

    // Convert to Purchase Order
    function convertPurchaseOrder() {
        if (!confirm('Generate a Purchase Order for all approved selections in this space?')) return;
        const formData = new FormData();
        formData.append('action', 'convert_po');
        formData.append('room_id', activeRoomId);

        fetch('selections_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = data.redirect;
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert('Conversion failed.'));
    }

    // Convert to Change Order
    function convertChangeOrder() {
        if (!confirm('Create a contractual Change Order for this room allowance?')) return;
        const formData = new FormData();
        formData.append('action', 'convert_co');
        formData.append('room_id', activeRoomId);

        fetch('selections_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = data.redirect;
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert('Change order creation failed.'));
    }
</script>
</body>
</html>
