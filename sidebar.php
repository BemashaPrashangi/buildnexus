<?php
/**
 * BuildNexus - Standardized Sidebar Navigation Component
 * Supports root and subfolder inclusion with active link detection.
 */
if (!isset($base_url)) {
    // Detect whether called from root or features/
    $base_url = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'features') ? '../' : './';
}
$current_page = $current_page ?? 'dashboard';
?>
<aside class="sidebar">
    <!-- Brand Header -->
    <a href="<?= $base_url ?>dashboard.php" class="sidebar-brand d-flex align-items-center mb-4 text-decoration-none">
        <img src="<?= $base_url ?>images/logo.png" alt="BuildNexus" style="width: 30px; height: 30px; object-fit: contain;" class="me-2" onerror="this.onerror=null; this.src='https://cdn-icons-png.flaticon.com/512/4300/4300058.png';">
        <span class="fw-bold fs-5 text-dark" style="letter-spacing: -0.5px;">BuildNexus</span>
    </a>

    <!-- Create Project Trigger Button -->
    <button type="button" class="btn btn-create-project w-100 fw-semibold py-2 mb-3 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#newProjectModal">
        <i class="bi bi-plus-lg"></i>
        <span>Create New Project</span>
    </button>

    <!-- Nav List -->
    <div class="sidebar-nav-container flex-grow-1">
        <nav class="nav flex-column gap-1">
            <a href="<?= $base_url ?>dashboard.php" class="sidebar-nav-link <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2 me-2 fs-6"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= $base_url ?>features/projects.php" class="sidebar-nav-link <?= ($current_page === 'projects') ? 'active' : '' ?>">
                <i class="bi bi-stack me-2 fs-6"></i>
                <span>Projects</span>
            </a>

            <!-- Sub-items for Planning & Design -->
            <div class="sidebar-sub-nav ms-3 d-flex flex-column gap-1">
                <a href="<?= $base_url ?>features/estimates.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page === 'estimates') ? 'active' : '' ?>">
                    <span>Interactive Estimates</span>
                </a>
                <a href="<?= $base_url ?>features/takeoffs.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page === 'takeoffs') ? 'active' : '' ?>">
                    <span>Takeoffs</span>
                </a>
                <a href="<?= $base_url ?>features/floor-plans.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page === 'floor-plans') ? 'active' : '' ?>">
                    <span>3D Floor Plans</span>
                </a>
                <a href="<?= $base_url ?>features/mood-boards.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page === 'mood-boards') ? 'active' : '' ?>">
                    <span>Mood Boards</span>
                </a>
            </div>

            <a href="<?= $base_url ?>features/tasks.php" class="sidebar-nav-link <?= ($current_page === 'tasks') ? 'active' : '' ?>">
                <i class="bi bi-list-task me-2 fs-6"></i>
                <span>Tasks</span>
            </a>

            <a href="<?= $base_url ?>client_notes.php" class="sidebar-nav-link <?= ($current_page === 'notes') ? 'active' : '' ?>">
                <i class="bi bi-chat-left-text me-2 fs-6"></i>
                <span>Project Notes</span>
            </a>

            <a href="<?= $base_url ?>features/procurement.php" class="sidebar-nav-link <?= ($current_page === 'procurement') ? 'active' : '' ?>">
                <i class="bi bi-cart me-2 fs-6"></i>
                <span>Procurement</span>
            </a>

            <a href="<?= $base_url ?>features/project_financials.php" class="sidebar-nav-link <?= ($current_page === 'financials') ? 'active' : '' ?>">
                <i class="bi bi-bank me-2 fs-6"></i>
                <span>Financials</span>
            </a>

            <a href="<?= $base_url ?>features/reports.php" class="sidebar-nav-link <?= ($current_page === 'reports') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart me-2 fs-6"></i>
                <span>Reports</span>
            </a>
        </nav>
    </div>

    <!-- Sign Out Button at Bottom -->
    <div class="sidebar-footer pt-3 mt-auto border-top-0">
        <a href="<?= $base_url ?>logout.php" class="sidebar-logout-link d-flex align-items-center gap-2">
            <i class="bi bi-box-arrow-right fs-5"></i>
            <span class="fw-semibold">Sign Out</span>
        </a>
    </div>
</aside>
