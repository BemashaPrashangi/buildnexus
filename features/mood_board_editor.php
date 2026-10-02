<?php
// features/mood_board_editor.php - Interactive Concept Canvas & Pinboard Studio
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

$board_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($board_id <= 0) {
    header("Location: mood-boards.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'Admin';
$is_client = ($user_role === 'Client') || (isset($_GET['view']) && $_GET['view'] === 'client');

try {
    // 1. Fetch Mood Board Details
    $bStmt = $pdo->prepare("
        SELECT b.*, p.project_name, p.project_code, u.full_name as author_name 
        FROM project_mood_boards b 
        JOIN projects p ON b.project_id = p.id 
        LEFT JOIN users u ON b.created_by = u.id 
        WHERE b.id = ?
    ");
    $bStmt->execute([$board_id]);
    $board = $bStmt->fetch(PDO::FETCH_ASSOC);

    if (!$board) {
        header("Location: mood-boards.php?msg=not_found");
        exit();
    }

    // 2. Fetch Pinned Visual Assets
    $iStmt = $pdo->prepare("SELECT * FROM mood_board_items WHERE mood_board_id = ? ORDER BY sort_order ASC, id ASC");
    $iStmt->execute([$board_id]);
    $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($board['title']) ?> - Concept Studio - BuildNexus</title>
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
        }

        .studio-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 2rem;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .back-link {
            color: #64748b;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.15s;
        }

        .back-link:hover {
            color: #22c55e;
        }

        .btn-nexus-success {
            background-color: #22c55e;
            border: 1px solid #16a34a;
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s;
        }

        .btn-nexus-success:hover {
            background-color: #16a34a;
            color: #ffffff;
        }

        /* Canvas Layout */
        .studio-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 2rem;
        }

        .canvas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        /* Pin Tile */
        .pin-tile {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .pin-tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            border-color: #cbd5e1;
        }

        .pin-img-wrap {
            width: 100%;
            height: 240px;
            overflow: hidden;
            background: #f1f5f9;
            position: relative;
        }

        .pin-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .pin-tile:hover .pin-img {
            transform: scale(1.04);
        }

        .pin-type-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .badge-Inspiration     { color: #2563eb; }
        .badge-Material-Sample { color: #d97706; }
        .badge-Color-Palette   { color: #7c3aed; }
        .badge-Lighting        { color: #059669; }

        .pin-delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            border: none;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.15s;
            cursor: pointer;
        }

        .pin-tile:hover .pin-delete-btn {
            opacity: 1;
        }

        /* Sidebar Info Box */
        .sidebar-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            margin-bottom: 1.5rem;
        }

        /* Status Pills */
        .status-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
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

    <!-- Sticky Studio Navbar -->
    <header class="studio-navbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="mood-boards.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Mood Boards
            </a>
            <div class="vr bg-secondary opacity-25 my-1"></div>
            <div>
                <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <?= htmlspecialchars($board['title']) ?>
                    <?php
                        $clean_st = str_replace(' ', '-', $board['status']);
                    ?>
                    <span class="status-pill pill-<?= $clean_st ?>">
                        <?= htmlspecialchars($board['status']) ?>
                    </span>
                </h5>
                <small class="text-muted">
                    Project: <strong class="text-success"><?= htmlspecialchars($board['project_name']) ?></strong> &bull; Space: <?= htmlspecialchars($board['room_space']) ?>
                </small>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-outline-secondary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addPinModal">
                <i class="bi bi-plus-lg me-1"></i> Add Pin
            </button>
            <button class="btn btn-outline-primary btn-sm fw-semibold" onclick="shareWithClient(<?= $board_id ?>)">
                <i class="bi bi-share me-1"></i> Share with Client
            </button>
            <button class="btn-nexus-success" onclick="convertToSelections(<?= $board_id ?>)">
                <i class="bi bi-box-arrow-up-right"></i> Convert to Selections Room
            </button>
        </div>
    </header>

    <div class="studio-container">
        <!-- Client Sign-Off Banner (if in review or client view) -->
        <?php if ($board['status'] === 'Shared with Client' || $is_client): ?>
            <div class="alert alert-info border-0 shadow-sm rounded-3 p-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <i class="bi bi-person-check-fill me-2 fs-5 text-primary"></i>
                    <strong>Client Design Review:</strong> Please inspect this architectural concept and sign off to advance finishes into estimation and procurement.
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-success fw-bold px-3" onclick="submitClientVote('Approved')">
                        <i class="bi bi-check2-circle me-1"></i> Approve Concept
                    </button>
                    <button class="btn btn-sm btn-outline-danger fw-bold px-3" onclick="submitClientVote('Revisions Requested')">
                        <i class="bi bi-pencil-square me-1"></i> Request Changes
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($board['client_feedback'])): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4">
                <strong><i class="bi bi-chat-left-quote me-2"></i>Client Feedback:</strong>
                <p class="mb-0 mt-1 small"><?= nl2br(htmlspecialchars($board['client_feedback'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left 8 Columns: Dynamic Pinboard Grid Canvas -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Inspiration & Material Swatches (<?= count($items) ?>)</h5>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary active" onclick="filterPins('All')">All</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="filterPins('Inspiration')">Inspiration</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="filterPins('Material Sample')">Materials</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="filterPins('Color Palette')">Colors</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="filterPins('Lighting')">Lighting</button>
                    </div>
                </div>

                <div class="canvas-grid" id="pinboardGrid">
                    <?php if (empty($items)): ?>
                        <div class="col-12 text-center py-5 bg-white rounded-3 border">
                            <i class="bi bi-images display-5 text-secondary d-block mb-3"></i>
                            <h6 class="fw-bold">No visual assets pinned to this concept yet.</h6>
                            <p class="text-muted small">Click <strong>"Add Pin"</strong> above to upload inspiration photographs, swatches, or fixture samples.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php $clean_type = str_replace(' ', '-', $item['item_type']); ?>
                            <div class="pin-tile" id="pin-tile-<?= $item['id'] ?>" data-type="<?= htmlspecialchars($item['item_type']) ?>">
                                <div class="pin-img-wrap">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" class="pin-img" alt="<?= htmlspecialchars($item['caption']) ?>" onerror="this.src='https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600&auto=format&fit=crop'">
                                    <span class="pin-type-badge badge-<?= $clean_type ?>">
                                        <?= htmlspecialchars($item['item_type']) ?>
                                    </span>
                                    <button class="pin-delete-btn" onclick="deletePinItem(<?= $item['id'] ?>)" title="Remove item">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div class="p-3">
                                    <h6 class="fw-semibold mb-1 text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($item['caption']) ?></h6>
                                    <small class="text-muted">Added <?= date('M d, Y', strtotime($item['created_at'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right 4 Columns: Design Philosophy & Palette Notes -->
            <div class="col-lg-4">
                <div class="sidebar-card">
                    <h6 class="fw-bold text-dark mb-3">Design Vision & Aesthetic</h6>
                    <p class="text-muted small leading-relaxed mb-3">
                        <?= !empty($board['description']) ? nl2br(htmlspecialchars($board['description'])) : 'No detailed philosophy notes recorded for this space.' ?>
                    </p>
                    <hr class="my-3">
                    <div class="small">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Target Space:</span>
                            <strong class="text-dark"><?= htmlspecialchars($board['room_space']) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Curated By:</span>
                            <strong class="text-dark"><?= htmlspecialchars($board['author_name'] ?: 'Design Team') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Created:</span>
                            <span><?= date('M d, Y', strtotime($board['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- One-Click Selections Studio Bridge -->
                <div class="sidebar-card bg-light border-success border-opacity-50">
                    <h6 class="fw-bold text-success mb-2">
                        <i class="bi bi-arrow-right-circle me-1"></i> Selections Studio Bridge
                    </h6>
                    <p class="small text-muted mb-3">
                        Ready to convert these approved concepts into customer finishes with budget caps and live purchasing?
                    </p>
                    <button class="btn btn-nexus-success w-100 justify-content-center" onclick="convertToSelections(<?= $board_id ?>)">
                        Transfer into Selections Studio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: Add Inspiration Pin Asset          -->
    <!-- ========================================== -->
    <div class="modal fade" id="addPinModal" tabindex="-1" aria-labelledby="addPinModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold modal-title" id="addPinModalLabel">Pin Asset to Board</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addPinForm" onsubmit="submitNewPin(event)" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="mood_board_id" value="<?= $board_id ?>">

                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Asset Caption / Material Title</label>
                            <input type="text" name="caption" class="form-control" placeholder="e.g. Fluted Walnut Millwork or Brushed Brass Pull" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Asset Category</label>
                            <select name="item_type" class="form-select">
                                <option value="Inspiration" selected>Inspiration Architecture</option>
                                <option value="Material Sample">Material / Tile Sample</option>
                                <option value="Color Palette">Color Swatch</option>
                                <option value="Lighting">Luminaire / Lighting</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Upload Photo (PNG, JPG, WebP)</label>
                            <input type="file" name="item_file" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted mb-1">Or Web Image URL</label>
                            <input type="url" name="image_url" class="form-control" placeholder="https://images.unsplash.com/...">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-nexus-success fw-bold">Pin to Board</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Submit New Pin Item
        function submitNewPin(e) {
            e.preventDefault();
            const form = document.getElementById('addPinForm');
            const formData = new FormData(form);

            fetch('mood_board_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error adding pin: ' + data.message);
                }
            })
            .catch(err => alert('Network error adding pin.'));
        }

        // Delete Pin
        function deletePinItem(itemId) {
            if (!confirm('Remove this pin from the mood board?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('item_id', itemId);

            fetch('mood_board_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const tile = document.getElementById('pin-tile-' + itemId);
                    if (tile) tile.remove();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Network error.'));
        }

        // Share with Client
        function shareWithClient(boardId) {
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

        // Submit Client Vote
        function submitClientVote(vote) {
            const feedback = prompt(vote === 'Approved' ? 'Enter any sign-off notes (optional):' : 'Please describe requested revisions / changes:');
            if (feedback === null) return;

            const formData = new FormData();
            formData.append('action', 'client_vote');
            formData.append('mood_board_id', <?= $board_id ?>);
            formData.append('vote', vote);
            formData.append('feedback', feedback);

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
            .catch(err => alert('Action failed.'));
        }

        // Convert to Selections Room
        function convertToSelections(boardId) {
            if (!confirm('Transfer this mood board and its approved materials into the Selections Studio?')) return;

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
                if (data.success) {
                    alert(data.message);
                    window.location.href = data.redirect;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Conversion failed.'));
        }

        // Filter Pins on Canvas
        function filterPins(type) {
            document.querySelectorAll('#pinboardGrid .pin-tile').forEach(tile => {
                const itemType = tile.getAttribute('data-type');
                if (type === 'All' || itemType === type) {
                    tile.style.display = '';
                } else {
                    tile.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
