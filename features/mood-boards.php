<?php
// features/mood-boards.php - Production-Ready Mood Boards & Architectural Concept Studio
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

$user_id = $_SESSION['user_id'] ?? 1;

// Flash message handler
$flash_msg = null;
if (isset($_GET['msg'])) {
    $msg_map = [
        'created'   => ['type' => 'success', 'text' => 'New concept mood board created successfully!'],
        'deleted'   => ['type' => 'warning', 'text' => 'Mood board and visual assets removed.'],
        'not_found' => ['type' => 'danger',  'text' => 'Requested concept mood board could not be found.']
    ];
    if (isset($msg_map[$_GET['msg']])) {
        $flash_msg = $msg_map[$_GET['msg']];
    }
}

// 1. Fetch Projects for filter and modal
try {
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
}

// 2. Filter Inputs
$filter_project = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$filter_room    = !empty($_GET['room']) ? trim($_GET['room']) : '';
$filter_status  = !empty($_GET['status']) ? trim($_GET['status']) : '';
$filter_search  = !empty($_GET['search']) ? trim($_GET['search']) : '';

// 3. Query Mood Boards with asset counts and sub-thumbnails
$sql = "
    SELECT b.*, p.project_name, p.project_code, u.full_name as author_name,
           (SELECT COUNT(*) FROM mood_board_items mbi WHERE mbi.mood_board_id = b.id) as item_count
    FROM project_mood_boards b 
    JOIN projects p ON b.project_id = p.id 
    LEFT JOIN users u ON b.created_by = u.id 
    WHERE 1=1
";
$params = [];

if ($filter_project > 0) {
    $sql .= " AND b.project_id = ?";
    $params[] = $filter_project;
}
if (!empty($filter_room) && $filter_room !== 'All') {
    $sql .= " AND b.room_space = ?";
    $params[] = $filter_room;
}
if (!empty($filter_status) && $filter_status !== 'All') {
    $sql .= " AND b.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_search)) {
    $sql .= " AND (b.title LIKE ? OR b.room_space LIKE ? OR p.project_name LIKE ?)";
    $term = "%{$filter_search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY b.created_at DESC, b.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $boards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch mini-thumbnails for each board
    $board_thumbs = [];
    if (!empty($boards)) {
        $board_ids = array_column($boards, 'id');
        $placeholders = implode(',', array_fill(0, count($board_ids), '?'));
        $thumbStmt = $pdo->prepare("
            SELECT mood_board_id, image_url 
            FROM mood_board_items 
            WHERE mood_board_id IN ($placeholders) 
            ORDER BY sort_order ASC, id ASC
        ");
        $thumbStmt->execute($board_ids);
        $all_thumbs = $thumbStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_thumbs as $th) {
            $board_thumbs[$th['mood_board_id']][] = $th['image_url'];
        }
    }

    // KPI Metrics
    $total_boards = count($boards);
    $approved_count = 0;
    $shared_count = 0;
    $total_items = 0;

    foreach ($boards as $b) {
        if ($b['status'] === 'Approved') $approved_count++;
        if ($b['status'] === 'Shared with Client') $shared_count++;
        $total_items += intval($b['item_count']);
    }

} catch (Exception $e) {
    $boards = [];
    $total_boards = 0;
    $approved_count = 0;
    $shared_count = 0;
    $total_items = 0;
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
    <title>Mood Boards & Architectural Concept Studio - BuildNexus</title>
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

        /* KPI Stat Cards */
        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            height: 100%;
        }

        .kpi-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .kpi-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* Filter Controls */
        .search-wrapper {
            position: relative;
            max-width: 420px;
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
            font-size: 0.875rem;
            background: #ffffff;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .nexus-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
        }

        .nexus-select {
            padding: 9px 34px 9px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #475569;
            background-color: #ffffff;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }

        .nexus-select:focus {
            outline: none;
            border-color: #22c55e;
        }

        .btn-new-board {
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
            transition: background-color 0.15s;
        }

        .btn-new-board:hover {
            background-color: #16a34a;
            color: #ffffff;
        }

        /* Mood Board Card */
        .board-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .board-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border-color: #cbd5e1;
        }

        .cover-img-wrap {
            width: 100%;
            height: 200px;
            position: relative;
            background-color: #0f172a;
            overflow: hidden;
            cursor: pointer;
        }

        .cover-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .board-card:hover .cover-img {
            transform: scale(1.04);
        }

        .cover-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .board-card:hover .cover-overlay {
            opacity: 1;
        }

        /* Sub-Thumbnail Strip */
        .thumb-strip {
            display: flex;
            gap: 4px;
            padding: 6px;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
        }

        .thumb-mini {
            width: 44px;
            height: 38px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }

        /* Status Pills */
        .status-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-block;
        }

        .pill-Approved            { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
        .pill-Shared-with-Client  { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .pill-Draft               { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .pill-Revisions-Requested { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small text-muted mb-1">
                        <li class="breadcrumb-item"><a href="../admin_dashboard.php" class="text-decoration-none text-muted">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Concept Studio</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-0">Mood Boards & Visual Concepts</h1>
            </div>
            <button class="btn-new-board" data-bs-toggle="modal" data-bs-target="#newBoardModal">
                <i class="bi bi-plus-lg"></i> New Mood Board
            </button>
        </div>

        <!-- Flash Alert -->
        <?php if ($flash_msg): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_msg['type']) ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($flash_msg['text']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <div class="kpi-title">Total Concept Boards</div>
                    <div class="kpi-value text-dark"><?= number_format($total_boards) ?></div>
                    <small class="text-muted">Across all active projects</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <div class="kpi-title">Client Approved</div>
                    <div class="kpi-value text-success"><?= number_format($approved_count) ?></div>
                    <small class="text-success"><i class="bi bi-check2-circle me-1"></i>Sign-off complete</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <div class="kpi-title">In Client Review</div>
                    <div class="kpi-value text-primary"><?= number_format($shared_count) ?></div>
                    <small class="text-primary">Shared with homeowner</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <div class="kpi-title">Total Pinned Assets</div>
                    <div class="kpi-value text-dark"><?= number_format($total_items) ?></div>
                    <small class="text-muted">Inspirations & swatches</small>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="nexus-card">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="search-wrapper">
                    <i class="bi bi-search nexus-icon"></i>
                    <input type="text" id="boardSearch" class="nexus-input" placeholder="Search by concept title, space, or project..." value="<?= htmlspecialchars($filter_search) ?>" onkeyup="clientFilter()">
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Project Filter -->
                    <select id="projectFilter" class="nexus-select" onchange="applyFilters()">
                        <option value="0">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($filter_project == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Room/Space Filter -->
                    <select id="roomFilter" class="nexus-select" onchange="applyFilters()">
                        <option value="All">All Spaces</option>
                        <option value="Kitchen" <?= ($filter_room === 'Kitchen') ? 'selected' : '' ?>>Kitchen</option>
                        <option value="Master Bath" <?= ($filter_room === 'Master Bath') ? 'selected' : '' ?>>Master Bath</option>
                        <option value="Living Room" <?= ($filter_room === 'Living Room') ? 'selected' : '' ?>>Living Room</option>
                        <option value="Exterior Facade" <?= ($filter_room === 'Exterior Facade') ? 'selected' : '' ?>>Exterior Facade</option>
                        <option value="Master Suite" <?= ($filter_room === 'Master Suite') ? 'selected' : '' ?>>Master Suite</option>
                    </select>

                    <!-- Status Filter -->
                    <select id="statusFilter" class="nexus-select" onchange="applyFilters()">
                        <option value="All">All Statuses</option>
                        <option value="Approved" <?= ($filter_status === 'Approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="Shared with Client" <?= ($filter_status === 'Shared with Client') ? 'selected' : '' ?>>Shared with Client</option>
                        <option value="Draft" <?= ($filter_status === 'Draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="Revisions Requested" <?= ($filter_status === 'Revisions Requested') ? 'selected' : '' ?>>Revisions Requested</option>
                    </select>
                </div>
            </div>

            <!-- Visual Collage Grid -->
            <div class="row g-4" id="boardGridContainer">
                <?php if (empty($boards)): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-palette display-4 text-secondary d-block mb-3"></i>
                        <h5>No design mood boards found matching criteria.</h5>
                        <p class="small text-muted">Click "+ New Mood Board" to curate visual aesthetics and finish swatches.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($boards as $b): ?>
                        <?php
                            $clean_status = str_replace(' ', '-', $b['status']);
                            $mini_list = $board_thumbs[$b['id']] ?? [];
                        ?>
                        <div class="col-md-6 col-lg-4 board-col" data-title="<?= htmlspecialchars(strtolower($b['title'])) ?>" data-space="<?= htmlspecialchars(strtolower($b['room_space'])) ?>" data-project="<?= htmlspecialchars(strtolower($b['project_name'])) ?>">
                            <div class="board-card">
                                <!-- Main Cover Thumbnail -->
                                <div class="cover-img-wrap" onclick="window.location.href='mood_board_editor.php?id=<?= $b['id'] ?>'">
                                    <img src="<?= htmlspecialchars($b['cover_image']) ?>" class="cover-img" alt="<?= htmlspecialchars($b['title']) ?>" onerror="this.src='https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&auto=format&fit=crop'">
                                    <div class="cover-overlay">
                                        <span class="btn btn-sm btn-light fw-bold px-3 shadow">
                                            <i class="bi bi-layout-wtf me-1"></i> Open Studio Canvas
                                        </span>
                                    </div>
                                    <div class="position-absolute top-0 start-0 m-2">
                                        <span class="badge bg-dark bg-opacity-75 text-light fw-semibold">
                                            <?= htmlspecialchars($b['room_space']) ?>
                                        </span>
                                    </div>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <span class="status-pill pill-<?= $clean_status ?>">
                                            <?= htmlspecialchars($b['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Sub-Thumbnail Strip -->
                                <?php if (!empty($mini_list)): ?>
                                    <div class="thumb-strip">
                                        <?php foreach (array_slice($mini_list, 0, 5) as $m_url): ?>
                                            <img src="<?= htmlspecialchars($m_url) ?>" class="thumb-mini" alt="Swatch" onerror="this.style.display='none'">
                                        <?php endforeach; ?>
                                        <?php if (count($mini_list) > 5): ?>
                                            <div class="thumb-mini d-flex align-items-center justify-content-center bg-white text-muted small fw-bold">
                                                +<?= count($mini_list) - 5 ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Card Body -->
                                <div class="p-3 d-flex flex-column flex-grow-1">
                                    <div class="mb-2">
                                        <h6 class="fw-bold mb-1 text-dark" style="font-size: 1rem;">
                                            <?= htmlspecialchars($b['title']) ?>
                                        </h6>
                                        <small class="text-success fw-medium">
                                            <i class="bi bi-building me-1"></i> <?= htmlspecialchars($b['project_name']) ?>
                                        </small>
                                    </div>

                                    <p class="text-muted small mb-3 text-truncate" title="<?= htmlspecialchars($b['description']) ?>">
                                        <?= htmlspecialchars($b['description'] ?: 'No aesthetic notes provided.') ?>
                                    </p>

                                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                        <span class="small text-muted">
                                            <i class="bi bi-images text-primary me-1"></i> <?= intval($b['item_count']) ?> assets pinned
                                        </span>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border p-1 px-2" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 10px;">
                                                <li>
                                                    <a class="dropdown-item small" href="mood_board_editor.php?id=<?= $b['id'] ?>">
                                                        <i class="bi bi-layout-wtf text-primary me-2"></i> Open Studio Canvas
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item small" href="javascript:void(0)" onclick="shareBoard(<?= $b['id'] ?>)">
                                                        <i class="bi bi-share text-info me-2"></i> Share with Client
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item small" href="javascript:void(0)" onclick="convertBoardToSelections(<?= $b['id'] ?>)">
                                                        <i class="bi bi-box-arrow-up-right text-success me-2"></i> Convert to Selection Room
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                    <a class="dropdown-item small text-danger" href="javascript:void(0)" onclick="deleteBoard(<?= $b['id'] ?>)">
                                                        <i class="bi bi-trash3 me-2"></i> Delete Board
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: + New Mood Board                    -->
    <!-- ========================================== -->
    <div class="modal fade" id="newBoardModal" tabindex="-1" aria-labelledby="newBoardModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold modal-title" id="newBoardModalLabel">Create New Concept Mood Board</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newBoardForm" onsubmit="submitNewBoard(event)" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_board">

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label small fw-bold text-muted mb-1">Target Construction Project</label>
                                <select name="project_id" class="form-select" required>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted mb-1">Room / Space Zone</label>
                                <input type="text" name="room_space" class="form-control" placeholder="e.g. Master Bath, Kitchen, Facade" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Mood Board Title</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Modern Japandi Master Bath with Travertine" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Design Philosophy & Color Palette</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Describe aesthetic vision, wood species, hex color chips, lighting moods..."></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Cover Photo (File or URL)</label>
                                <input type="file" name="cover_file" class="form-control mb-1" accept="image/*">
                                <input type="url" name="cover_image" class="form-control" placeholder="Or paste image URL https://...">
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">Initial Inspiration Photos (Multiple files supported)</label>
                                <input type="file" name="inspiration_files[]" class="form-control" multiple accept="image/*">
                                <div class="form-text small">Select multiple photos from your library to batch-import into this mood board.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-new-board">Create & Launch Canvas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Client-side Instant Filter
        function clientFilter() {
            const query = document.getElementById('boardSearch').value.toLowerCase().trim();
            document.querySelectorAll('.board-col').forEach(col => {
                const title = col.getAttribute('data-title') || '';
                const space = col.getAttribute('data-space') || '';
                const proj = col.getAttribute('data-project') || '';
                if (title.includes(query) || space.includes(query) || proj.includes(query)) {
                    col.style.display = '';
                } else {
                    col.style.display = 'none';
                }
            });
        }

        // Apply Server-side Filters via URL
        function applyFilters() {
            const projectId = document.getElementById('projectFilter').value;
            const room = document.getElementById('roomFilter').value;
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('boardSearch').value;

            const params = new URLSearchParams();
            if (projectId > 0) params.set('project_id', projectId);
            if (room && room !== 'All') params.set('room', room);
            if (status && status !== 'All') params.set('status', status);
            if (search) params.set('search', search);

            window.location.href = 'mood-boards.php?' + params.toString();
        }

        // Submit New Board
        function submitNewBoard(e) {
            e.preventDefault();
            const form = document.getElementById('newBoardForm');
            const formData = new FormData(form);

            fetch('mood_board_actions.php', {
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
            .catch(err => alert('Network error creating mood board.'));
        }

        // Share Board
        function shareBoard(boardId) {
            const formData = new FormData();
            formData.append('action', 'share_client');
            formData.append('mood_board_id', boardId);

            fetch('mood_board_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                window.location.reload();
            })
            .catch(err => alert('Failed to share board.'));
        }

        // Convert Board to Selections Room
        function convertBoardToSelections(boardId) {
            if (!confirm('Transfer this mood board directly into the Selections Studio?')) return;
            const formData = new FormData();
            formData.append('action', 'convert_to_selection_room');
            formData.append('mood_board_id', boardId);

            fetch('mood_board_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.redirect) {
                    alert(data.message);
                    window.location.href = data.redirect;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Conversion failed.'));
        }

        // Delete Board
        function deleteBoard(boardId) {
            if (!confirm('Delete this mood board and all pinned visual swatches?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_board');
            formData.append('mood_board_id', boardId);

            fetch('mood_board_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'mood-boards.php?msg=deleted';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Network error deleting board.'));
        }
    </script>
</body>
</html>