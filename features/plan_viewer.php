<?php
// features/plan_viewer.php - Interactive Architectural Blueprint & Spatial Pin Viewer
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

$plan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($plan_id <= 0) {
    header("Location: floor-plans.php");
    exit();
}

try {
    // 1. Fetch current drawing
    $stmt = $pdo->prepare("
        SELECT fp.*, p.project_name, p.project_code, u.full_name as author_name 
        FROM project_floor_plans fp 
        JOIN projects p ON fp.project_id = p.id 
        LEFT JOIN users u ON fp.uploaded_by = u.id 
        WHERE fp.id = ?
    ");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        header("Location: floor-plans.php?msg=not_found");
        exit();
    }

    // 2. Fetch all revisions for this plan_code
    $revStmt = $pdo->prepare("
        SELECT id, version_tag, status, created_at 
        FROM project_floor_plans 
        WHERE project_id = ? AND plan_code = ? 
        ORDER BY id DESC
    ");
    $revStmt->execute([$plan['project_id'], $plan['plan_code']]);
    $revisions = $revStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch all spatial markup pins on this sheet
    $pinStmt = $pdo->prepare("
        SELECT p.*, u.full_name as author_name 
        FROM floor_plan_pins p 
        LEFT JOIN users u ON p.created_by = u.id 
        WHERE p.floor_plan_id = ? 
        ORDER BY p.id ASC
    ");
    $pinStmt->execute([$plan_id]);
    $pins = $pinStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title><?= htmlspecialchars($plan['plan_code']) ?> - <?= htmlspecialchars($plan['title']) ?> - BuildNexus</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100vh;
        }

        /* Top Viewer Navigation Bar */
        .viewer-header {
            background-color: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 20;
            position: relative;
        }

        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.15s;
        }

        .back-btn:hover {
            color: #22c55e;
        }

        .viewer-layout {
            display: flex;
            height: calc(100vh - 65px);
            position: relative;
        }

        /* Drawing Viewport */
        .drawing-viewport {
            flex-grow: 1;
            background-color: #090d16;
            overflow: auto;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: default;
            user-select: none;
        }

        .drawing-viewport.pin-mode-active {
            cursor: crosshair !important;
        }

        .canvas-wrapper {
            position: relative;
            display: inline-block;
            transition: transform 0.15s ease-out;
            transform-origin: center center;
        }

        .blueprint-img {
            display: block;
            max-width: 90vw;
            max-height: 85vh;
            border-radius: 4px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            pointer-events: auto;
        }

        /* Spatial Pins */
        .spatial-pin {
            position: absolute;
            transform: translate(-50%, -100%);
            cursor: pointer;
            z-index: 10;
            transition: transform 0.15s;
        }

        .spatial-pin:hover {
            transform: translate(-50%, -115%) scale(1.25);
            z-index: 15;
        }

        .pin-marker {
            width: 28px;
            height: 28px;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            border: 2px solid #ffffff;
        }

        .pin-marker i {
            transform: rotate(45deg);
            font-size: 0.75rem;
            color: #ffffff;
        }

        .pin-Punchlist .pin-marker { background-color: #ef4444; } /* Red */
        .pin-RFI .pin-marker { background-color: #3b82f6; }       /* Blue */
        .pin-Inspection .pin-marker { background-color: #f59e0b; }/* Amber */
        .pin-Note .pin-marker { background-color: #22c55e; }      /* Green */

        /* Floating Controls Bar */
        .floating-controls {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid #334155;
            border-radius: 50px;
            padding: 6px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 25;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .control-btn {
            background: transparent;
            border: none;
            color: #cbd5e1;
            font-size: 1.1rem;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }

        .control-btn:hover {
            background: #334155;
            color: #ffffff;
        }

        .control-btn.active {
            background: #22c55e;
            color: #ffffff;
        }

        /* Side Panel: Pin List & Inspection Items */
        .pins-sidebar {
            width: 340px;
            background: #1e293b;
            border-left: 1px solid #334155;
            display: flex;
            flex-direction: column;
            height: 100%;
            z-index: 15;
            transition: width 0.2s;
        }

        .pin-card-item {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }

        .pin-card-item:hover, .pin-card-item.selected {
            border-color: #22c55e;
            background: #162032;
        }

        /* Discipline Badges */
        .badge-arch { background: #f3e8ff; color: #7e22ce; }
        .badge-struct { background: #eff6ff; color: #2563eb; }
        .badge-mep { background: #fef9c3; color: #a16207; }
    </style>
</head>
<body>

    <!-- Top Header -->
    <header class="viewer-header">
        <div class="d-flex align-items-center gap-3">
            <a href="floor-plans.php" class="back-btn">
                <i class="bi bi-arrow-left"></i> Blueprints
            </a>
            <div class="vr bg-secondary opacity-50 my-1"></div>
            <div>
                <h5 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    <?= htmlspecialchars($plan['plan_code']) ?>: <?= htmlspecialchars($plan['title']) ?>
                    <span class="badge bg-secondary text-light fw-normal" style="font-size: 0.75rem;"><?= htmlspecialchars($plan['version_tag']) ?></span>
                </h5>
                <small class="text-secondary">Project: <strong class="text-success"><?= htmlspecialchars($plan['project_name']) ?></strong> &bull; Discipline: <?= htmlspecialchars($plan['discipline']) ?></small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Revision Switcher -->
            <?php if (count($revisions) > 1): ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary text-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-clock-history me-1"></i> Revisions (<?= count($revisions) ?>)
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-sm">
                        <?php foreach ($revisions as $rev): ?>
                            <li>
                                <a class="dropdown-item small d-flex justify-content-between align-items-center <?= ($rev['id'] == $plan['id']) ? 'active' : '' ?>" href="plan_viewer.php?id=<?= $rev['id'] ?>">
                                    <span><?= htmlspecialchars($rev['version_tag']) ?> (<?= htmlspecialchars($rev['status']) ?>)</span>
                                    <small class="text-secondary ms-3"><?= date('M d, Y', strtotime($rev['created_at'])) ?></small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($plan['file_url']) ?>" target="_blank" download class="btn btn-sm btn-outline-secondary text-light">
                <i class="bi bi-download me-1"></i> Download
            </a>
            <button class="btn btn-sm btn-success fw-semibold" onclick="togglePinDropMode()" id="dropPinToolBtn">
                <i class="bi bi-pin-map-fill me-1"></i> Drop Markup Pin
            </button>
        </div>
    </header>

    <div class="viewer-layout">
        <!-- Interactive Drawing Canvas -->
        <div class="drawing-viewport" id="viewportArea">
            <div class="canvas-wrapper" id="canvasWrapper">
                <img src="<?= htmlspecialchars($plan['file_url']) ?>" class="blueprint-img" id="blueprintImage" alt="<?= htmlspecialchars($plan['title']) ?>">

                <!-- Render Spatial Markup Pins -->
                <div id="pinsOverlay">
                    <?php foreach ($pins as $p): ?>
                        <div class="spatial-pin pin-<?= $p['pin_type'] ?>" id="pin-elem-<?= $p['id'] ?>" style="left: <?= $p['x_percent'] ?>%; top: <?= $p['y_percent'] ?>%;" onclick="selectPin(<?= $p['id'] ?>)">
                            <div class="pin-marker" title="<?= htmlspecialchars($p['pin_type']) ?>: <?= htmlspecialchars($p['comment']) ?>">
                                <?php if ($p['pin_type'] === 'Punchlist'): ?>
                                    <i class="bi bi-exclamation-octagon-fill"></i>
                                <?php elseif ($p['pin_type'] === 'RFI'): ?>
                                    <i class="bi bi-question-circle-fill"></i>
                                <?php elseif ($p['pin_type'] === 'Inspection'): ?>
                                    <i class="bi bi-clipboard-check-fill"></i>
                                <?php else: ?>
                                    <i class="bi bi-chat-left-text-fill"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Floating Viewport Controls -->
            <div class="floating-controls">
                <button class="control-btn" onclick="zoomIn()" title="Zoom In"><i class="bi bi-zoom-in"></i></button>
                <button class="control-btn" onclick="zoomOut()" title="Zoom Out"><i class="bi bi-zoom-out"></i></button>
                <button class="control-btn" onclick="resetZoom()" title="Reset Zoom"><i class="bi bi-arrows-fullscreen"></i></button>
                <div class="vr bg-secondary opacity-50 my-1" style="height: 20px;"></div>
                <span class="small text-secondary fw-semibold" id="zoomLevelDisplay">100%</span>
            </div>
        </div>

        <!-- Right Side Panel: Markup Pin List & Tooltips -->
        <div class="pins-sidebar">
            <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-white"><i class="bi bi-geo-alt-fill text-success me-2"></i>Spatial Pins (<?= count($pins) ?>)</h6>
                <span class="badge bg-secondary text-light"><?= htmlspecialchars($plan['version_tag']) ?></span>
            </div>

            <!-- Filter Pins -->
            <div class="p-2 border-bottom border-secondary border-opacity-25">
                <select class="form-select form-select-sm bg-dark text-light border-secondary" id="pinTypeFilter" onchange="filterPinsList(this.value)">
                    <option value="All">All Markup Types</option>
                    <option value="Punchlist">Punchlist / Defect (Red)</option>
                    <option value="RFI">RFI Issue (Blue)</option>
                    <option value="Inspection">Inspection Sign-off (Amber)</option>
                    <option value="Note">General Note (Green)</option>
                </select>
            </div>

            <!-- Pins List -->
            <div class="p-3 flex-grow-1 overflow-auto" id="pinsListContainer">
                <?php if (empty($pins)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-pin-map display-6 d-block mb-2 text-muted"></i>
                        No spatial pins marked on this sheet yet.<br>Click "Drop Markup Pin" to flag an issue or note.
                    </div>
                <?php else: ?>
                    <?php foreach ($pins as $p): ?>
                        <div class="pin-card-item pin-card-type-<?= $p['pin_type'] ?>" id="pin-card-<?= $p['id'] ?>" onclick="focusPin(<?= $p['id'] ?>, <?= $p['x_percent'] ?>, <?= $p['y_percent'] ?>)">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <?php if ($p['pin_type'] === 'Punchlist'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Punchlist</span>
                                <?php elseif ($p['pin_type'] === 'RFI'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">RFI</span>
                                <?php elseif ($p['pin_type'] === 'Inspection'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Inspection</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Note</span>
                                <?php endif; ?>
                                <small class="text-secondary"><?= date('M d', strtotime($p['created_at'])) ?></small>
                            </div>
                            <p class="small text-light mb-2"><?= htmlspecialchars($p['comment']) ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-secondary" style="font-size: 0.72rem;">By: <?= htmlspecialchars($p['author_name'] ?: 'Staff') ?></span>
                                <button class="btn btn-link btn-sm text-danger p-0 text-decoration-none" onclick="event.stopPropagation(); deletePin(<?= $p['id'] ?>)">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL: Add Spatial Markup Pin              -->
    <!-- ========================================== -->
    <div class="modal fade" id="addPinModal" tabindex="-1" aria-labelledby="addPinModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light border border-secondary shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-secondary pb-0">
                    <h5 class="fw-bold modal-title" id="addPinModalLabel">
                        <i class="bi bi-geo-alt-fill text-success me-2"></i>Add Spatial Issue / Note
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newPinForm" onsubmit="submitNewPin(event)">
                    <input type="hidden" name="action" value="add_pin">
                    <input type="hidden" name="floor_plan_id" value="<?= $plan_id ?>">
                    <input type="hidden" name="x_percent" id="pinXInput">
                    <input type="hidden" name="y_percent" id="pinYInput">

                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Pin Type</label>
                            <select name="pin_type" class="form-select bg-dark text-light border-secondary" required>
                                <option value="Punchlist">Punchlist / Defect (Red Pin)</option>
                                <option value="RFI">RFI Inquiry (Blue Pin)</option>
                                <option value="Inspection">Inspection Check (Amber Pin)</option>
                                <option value="Note" selected>General Field Note (Green Pin)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Issue Description & Instructions</label>
                            <textarea name="comment" class="form-control bg-dark text-light border-secondary" rows="3" placeholder="Describe the defect, coordinate with subcontractor, or specify layout discrepancy..." required></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-secondary">Reference ID (Optional RFI / Punch ID)</label>
                            <input type="number" name="reference_id" class="form-control bg-dark text-light border-secondary" placeholder="e.g. 104">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold">Save Pin on Drawing</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let currentZoom = 1.0;
        let isPinDropMode = false;
        const blueprintImg = document.getElementById('blueprintImage');
        const canvasWrapper = document.getElementById('canvasWrapper');
        const viewportArea = document.getElementById('viewportArea');
        const dropPinBtn = document.getElementById('dropPinToolBtn');
        const zoomDisplay = document.getElementById('zoomLevelDisplay');

        // Zoom Functions
        function zoomIn() {
            if (currentZoom < 3.0) {
                currentZoom += 0.25;
                applyZoom();
            }
        }

        function zoomOut() {
            if (currentZoom > 0.5) {
                currentZoom -= 0.25;
                applyZoom();
            }
        }

        function resetZoom() {
            currentZoom = 1.0;
            applyZoom();
        }

        function applyZoom() {
            canvasWrapper.style.transform = `scale(${currentZoom})`;
            zoomDisplay.innerText = Math.round(currentZoom * 100) + '%';
        }

        // Mouse Wheel Zooming
        viewportArea.addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
                if (e.deltaY < 0) zoomIn();
                else zoomOut();
            }
        });

        // Toggle Pin Drop Mode
        function togglePinDropMode() {
            isPinDropMode = !isPinDropMode;
            if (isPinDropMode) {
                viewportArea.classList.add('pin-mode-active');
                dropPinBtn.classList.remove('btn-success');
                dropPinBtn.classList.add('btn-warning');
                dropPinBtn.innerHTML = '<i class="bi bi-cursor me-1"></i> Click Drawing to Place Pin';
            } else {
                viewportArea.classList.remove('pin-mode-active');
                dropPinBtn.classList.remove('btn-warning');
                dropPinBtn.classList.add('btn-success');
                dropPinBtn.innerHTML = '<i class="bi bi-pin-map-fill me-1"></i> Drop Markup Pin';
            }
        }

        // Click drawing to drop pin
        blueprintImg.addEventListener('click', function(e) {
            if (!isPinDropMode) return;

            const rect = blueprintImg.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const clickY = e.clientY - rect.top;

            const xPercent = ((clickX / rect.width) * 100).toFixed(2);
            const yPercent = ((clickY / rect.height) * 100).toFixed(2);

            document.getElementById('pinXInput').value = xPercent;
            document.getElementById('pinYInput').value = yPercent;

            togglePinDropMode(); // Disable tool
            const modal = new bootstrap.Modal(document.getElementById('addPinModal'));
            modal.show();
        });

        // Submit New Pin
        function submitNewPin(e) {
            e.preventDefault();
            const form = document.getElementById('newPinForm');
            const formData = new FormData(form);

            fetch('plan_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error saving pin: ' + data.message);
                }
            })
            .catch(err => alert('Network error placing pin.'));
        }

        // Delete Pin
        function deletePin(pinId) {
            if (!confirm('Are you sure you want to remove this spatial pin?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_pin');
            formData.append('pin_id', pinId);

            fetch('plan_actions.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const pinElem = document.getElementById('pin-elem-' + pinId);
                    const cardElem = document.getElementById('pin-card-' + pinId);
                    if (pinElem) pinElem.remove();
                    if (cardElem) cardElem.remove();
                } else {
                    alert('Error deleting pin: ' + data.message);
                }
            })
            .catch(err => alert('Network error removing pin.'));
        }

        // Focus & select pin
        function focusPin(pinId, x, y) {
            document.querySelectorAll('.pin-card-item').forEach(c => c.classList.remove('selected'));
            const card = document.getElementById('pin-card-' + pinId);
            if (card) card.classList.add('selected');

            const pinElem = document.getElementById('pin-elem-' + pinId);
            if (pinElem) {
                pinElem.style.transform = 'translate(-50%, -125%) scale(1.4)';
                setTimeout(() => {
                    pinElem.style.transform = '';
                }, 1500);
            }
        }

        function selectPin(pinId) {
            const card = document.getElementById('pin-card-' + pinId);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                card.click();
            }
        }

        // Filter list
        function filterPinsList(type) {
            const cards = document.querySelectorAll('.pin-card-item');
            cards.forEach(c => {
                if (type === 'All' || c.classList.contains('pin-card-type-' + type)) {
                    c.style.display = '';
                } else {
                    c.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
