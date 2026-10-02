<?php
require_once '../db.php';
session_start();

try {
    $items = $pdo->query("SELECT * FROM estimate_selections ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate used budget (only items that are included)
    $project_budget = 250000.00; // Static example budget
    $used_budget = 0;
    foreach ($items as $item) {
        if ($item['included']) {
            $used_budget += ($item['price'] * $item['qty']);
        }
    }
    
    $progress_percent = ($used_budget / $project_budget) * 100;
    if ($progress_percent > 100) $progress_percent = 100;
} catch (PDOException $e) {
    die("DB Error");
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
    <title>Interactive Estimates - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS CUSTOM STYLING --- */
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; color: #1e293b; overflow-x: hidden; }
        
        /* Layout Structure */
        .app-container { display: flex; height: 100vh; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .side-panel { width: 380px; background: #fff; border-left: 1px solid #e2e8f0; display: flex; flex-direction: column; }
        
        /* Header Toolbar */
        .top-toolbar { height: 70px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem; }
        
        .toolbar-left { display: flex; align-items: center; gap: 15px; }
        .menu-icon { font-size: 1.5rem; color: #475569; cursor: pointer; }
        .project-title { font-size: 0.95rem; font-weight: 500; color: #64748b; }
        .project-title strong { color: #1e293b; font-weight: 600; }
        
        .toolbar-center { display: flex; flex-direction: column; align-items: center; width: 300px; }
        .budget-header { width: 100%; display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; margin-bottom: 4px; }
        .budget-label { color: #1e293b; }
        .budget-current { color: #10b981; }
        .progress { height: 6px; width: 100%; background-color: #e2e8f0; border-radius: 10px; margin-bottom: 4px; }
        .progress-bar { background-color: #22c55e; width: 30%; border-radius: 10px; }
        .budget-total { font-size: 0.75rem; color: #64748b; align-self: flex-end; }
        
        .toolbar-right { display: flex; align-items: center; gap: 10px; }
        .btn-convert { border: 1px solid #d1d5db; background: #e5e7eb; font-weight: 700; font-size: 0.85rem; padding: 6px 16px; border-radius: 4px; color: #1e293b; transition: 0.2s; }
        .btn-convert:hover { background: #d1d5db; }
        .btn-preview { background: #16a34a; color: #fff; font-weight: 600; font-size: 0.85rem; padding: 6px 12px; border-radius: 4px; border: none; display: flex; align-items: center; gap: 8px; }
        .btn-preview:hover { background: #15803d; }
        
        /* Main Scrollable Area */
        .content-scroll { flex: 1; overflow-y: auto; padding: 1.5rem; }
        
        /* Filters */
        .filter-row { display: flex; gap: 15px; margin-bottom: 1.5rem; }
        .filter-select { padding: 6px 30px 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; color: #475569; background: #fff; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; }
        
        /* Cards */
        .item-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; position: relative; transition: box-shadow 0.2s; }
        .item-card:hover { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .card-img-wrapper { height: 180px; position: relative; background: #f8fafc; }
        .card-img { width: 100%; height: 100%; object-fit: cover; }
        
        /* Badges */
        .status-badge { position: absolute; top: 10px; left: 10px; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 4px; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .badge-approved { color: #16a34a; }
        .badge-declined { color: #dc2626; }
        .badge-pending { color: #d97706; }
        
        .card-body { padding: 1rem; display: flex; flex-direction: column; flex: 1; }
        .card-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; color: #1e293b; line-height: 1.3; }
        .card-price { font-size: 0.85rem; color: #64748b; margin-bottom: 1rem; }
        .card-price strong { color: #1e293b; }
        
        .qty-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #475569; margin-bottom: 1.5rem; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 6px; }
        
        .card-actions { display: flex; gap: 10px; margin-top: auto; }
        .btn-decline { flex: 1; border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 6px; padding: 8px; font-size: 0.85rem; font-weight: 600; transition: 0.2s; }
        .btn-decline:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-approve { flex: 1; background: #22c55e; color: #fff; border: none; border-radius: 6px; padding: 8px; font-size: 0.85rem; font-weight: 600; transition: 0.2s; }
        .btn-approve:hover { background: #16a34a; }

        /* Side Panel Layout */
        .side-icons { width: 50px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: center; padding-top: 15px; gap: 20px; }
        .side-icon { color: #94a3b8; font-size: 1.1rem; cursor: pointer; transition: 0.2s; }
        .side-icon.active { color: #1e293b; }
        .side-icon:hover { color: #1e293b; }
        
        .panel-inner { flex: 1; display: flex; flex-direction: column; }
        .panel-header { padding: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
        .panel-title { font-weight: 700; font-size: 0.95rem; margin: 0; }
        
        .panel-body { padding: 15px; flex: 1; display: flex; flex-direction: column; }
        .search-box { position: relative; margin-bottom: 15px; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-box input { width: 100%; padding: 8px 12px 8px 35px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; }
        
        .panel-filters { display: flex; gap: 10px; margin-bottom: 20px; }
        .btn-panel-filter { flex: 1; border: 1px solid #e2e8f0; background: #fff; font-size: 0.8rem; font-weight: 600; padding: 6px; border-radius: 6px; color: #475569; display: flex; align-items: center; justify-content: center; gap: 6px; }
        
        .empty-state { flex: 1; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.85rem; }
        
        .panel-footer { padding: 15px; border-top: 1px solid #e2e8f0; }
        .btn-create { width: 100%; background: #16a34a; color: #fff; font-weight: 600; padding: 10px; border-radius: 8px; border: none; font-size: 0.9rem; }
    </style>
</head>
<body>

    <div class="app-container">
        <!-- MAIN CONTENT AREA -->
        <div class="main-content">
            <!-- Top Toolbar -->
            <div class="top-toolbar">
                <div class="toolbar-left">
                    <svg class="menu-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 15px; cursor: pointer;">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                    <div class="project-title">Davis Kitchen Remodel / <strong>Kitchen</strong> <i class="bi bi-chevron-down ms-1" style="font-size:0.75rem;"></i></div>
                </div>
                
                <div class="toolbar-center">
                    <div class="budget-header">
                        <span class="budget-label">Total Budget</span>
                        <span class="budget-current">RS. <?= number_format($project_budget, 2) ?></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?= $progress_percent ?>%;"></div>
                    </div>
                    <div class="budget-total">RS. <?= number_format($used_budget, 2) ?> Used</div>
                </div>
                
                <div class="toolbar-right">
                    <button class="btn-convert">Convert</button>
                    <div class="dropdown">
                        <button class="btn-preview dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Preview and Share
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="font-size:0.85rem;">
                            <li><a class="dropdown-item" href="selections.php#">For Client</a></li>
                            <li><a class="dropdown-item" href="selections.php#">For Team</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Content Scroll Area -->
            <div class="content-scroll">
                <!-- Filters -->
                <div class="filter-row">
                    <select class="filter-select">
                        <option>Approval Status: All</option>
                    </select>
                    <select class="filter-select">
                        <option>Filter by: All</option>
                    </select>
                </div>

                <!-- Grid -->
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                    <?php foreach ($items as $item): ?>
                    <div class="col">
                        <div class="item-card">
                            <div class="card-img-wrapper">
                                <?php if ($item['status'] == 'Approved'): ?>
                                    <div class="status-badge badge-approved"><i class="bi bi-check-circle-fill"></i> Approved</div>
                                <?php elseif ($item['status'] == 'Declined'): ?>
                                    <div class="status-badge badge-declined"><i class="bi bi-x-circle-fill"></i> Declined</div>
                                <?php else: ?>
                                    <div class="status-badge badge-pending"><i class="bi bi-clock-fill"></i> Pending</div>
                                <?php endif; ?>
                                <img src="<?= $item['img_url'] ?>" alt="" class="card-img">
                            </div>
                            <div class="card-body">
                                <div class="card-title"><?= htmlspecialchars($item['title']) ?></div>
                                <div class="card-price">Price: <strong>RS. <?= number_format($item['price'], 2) ?></strong></div>
                                
                                <div class="qty-row">
                                    <span>Qty: <strong><?= $item['qty'] ?></strong></span>
                                    <div class="checkbox-wrapper">
                                        <input type="checkbox" id="inc_<?= $item['id'] ?>" <?= $item['included'] ? 'checked' : '' ?> onchange="toggleInclude(<?= $item['id'] ?>, this)" class="form-check-input mt-0">
                                        <label for="inc_<?= $item['id'] ?>">Include in Budget</label>
                                    </div>
                                </div>
                                
                                <div class="card-actions">
                                    <button class="btn-decline" onclick="changeStatus(<?= $item['id'] ?>, 'Declined')"><i class="bi bi-x-circle"></i> Decline</button>
                                    <button class="btn-approve" onclick="changeStatus(<?= $item['id'] ?>, 'Approved')">Approve</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE PANEL -->
        <div class="side-panel d-flex flex-row">
            <div class="side-icons">
                <i class="bi bi-scissors side-icon active"></i>
                <i class="bi bi-box side-icon"></i>
                <i class="bi bi-house-door side-icon"></i>
                <i class="bi bi-palette side-icon"></i>
                <i class="bi bi-image side-icon"></i>
                <i class="bi bi-file-earmark-text side-icon"></i>
            </div>
            <div class="panel-inner">
                <div class="panel-header">
                    <h5 class="panel-title">My Items</h5>
                    <i class="bi bi-x-lg text-muted" style="cursor:pointer; font-size:0.9rem;"></i>
                </div>
                <div class="panel-body">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="Search">
                    </div>
                    <div class="panel-filters">
                        <button class="btn-panel-filter"><i class="bi bi-sliders"></i> Filters</button>
                        <button class="btn-panel-filter">Customize</button>
                    </div>
                    
                    <div class="empty-state">
                        No items in this room yet.
                    </div>
                </div>
                <div class="panel-footer">
                    <button class="btn-create">Create <span class="fw-light fs-5 ms-1" style="vertical-align: middle;">+</span></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeStatus(id, newStatus) {
            fetch('selection_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    'action': 'change_status',
                    'item_id': id,
                    'status': newStatus
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function toggleInclude(id, element) {
            fetch('selection_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    'action': 'toggle_include',
                    'item_id': id,
                    'included': element.checked
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
</body>
</html>