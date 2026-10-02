<?php
// features/reports.php - Production-Ready Construction Executive BI & Reports Hub
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

// Fetch active projects for quick launch modal
try {
    $projects = $pdo->query("SELECT id, project_name, project_code FROM projects WHERE status != 'Archived' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
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
    <title>Admin Reports - BuildNexus Executive BI</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- BuildNexus Standard Design Language (Zero Tailwind) --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #fcfcfc;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .main-container {
            padding: 2.5rem 3rem;
            max-width: 1440px;
            margin: 0 auto;
        }

        .page-header-title {
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        /* Report Grid Strictly Matching Screenshot */
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.75rem;
        }

        .report-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 2.25rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 230px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border-color: #cbd5e1;
        }

        .report-icon {
            color: #22c55e;
            font-size: 1.45rem;
            margin-right: 12px;
            display: inline-block;
        }

        .report-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            margin-bottom: 0.65rem;
            letter-spacing: -0.01em;
        }

        .report-desc {
            color: #64748b;
            font-size: 0.925rem;
            line-height: 1.5;
            margin-bottom: 2rem;
            min-height: 44px;
        }

        .btn-generate {
            background-color: #22c55e;
            border: 1px solid #16a34a;
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-generate:hover {
            background-color: #16a34a;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
        }

        .btn-nexus-outline {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }

        .btn-nexus-outline:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
    </style>
</head>
<body>

<div class="main-container">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-header-title mb-0">Admin Reports</h1>
            <p class="text-muted small mb-0 mt-1">Executive Business Intelligence, financial variance analysis, and schedule governance.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn-nexus-outline" data-bs-toggle="modal" data-bs-target="#customFilterModal">
                <i class="bi bi-sliders text-success"></i> Custom Filter & Export
            </button>
            <a href="project_financials.php" class="btn-nexus-outline">
                <i class="bi bi-cash-stack text-primary"></i> Project Financials
            </a>
        </div>
    </div>

    <!-- 3 Core Report Cards Matching UI Screenshot -->
    <div class="report-grid">

        <!-- Card 1: Budget vs. Actual -->
        <div class="report-card">
            <div>
                <div class="report-title">
                    <i class="bi bi-file-earmark-bar-graph report-icon"></i> Budget vs. Actual
                </div>
                <p class="report-desc">Compare budgeted vs. actual costs across all projects.</p>
            </div>
            <a href="reports_view.php?type=budget_actual" class="btn-generate">
                Generate Report
            </a>
        </div>

        <!-- Card 2: Client Profitability -->
        <div class="report-card">
            <div>
                <div class="report-title">
                    <i class="bi bi-file-earmark-person report-icon"></i> Client Profitability
                </div>
                <p class="report-desc">Analyze profit margins by client.</p>
            </div>
            <a href="reports_view.php?type=profitability" class="btn-generate">
                Generate Report
            </a>
        </div>

        <!-- Card 3: Project Timeline Analysis -->
        <div class="report-card">
            <div>
                <div class="report-title">
                    <i class="bi bi-file-earmark-check report-icon"></i> Project Timeline Analysis
                </div>
                <p class="report-desc">Review schedule adherence and delays.</p>
            </div>
            <a href="reports_view.php?type=timeline" class="btn-generate">
                Generate Report
            </a>
        </div>

    </div>

</div>

<!-- ========================================== -->
<!-- MODAL: Custom Filter & Rapid Report Launch -->
<!-- ========================================== -->
<div class="modal fade" id="customFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold mb-0">Generate Custom Executive Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="reports_view.php" method="GET">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Select Report Type</label>
                        <select name="type" class="form-select" required>
                            <option value="budget_actual" selected>Budget vs. Actual Cost Analysis</option>
                            <option value="profitability">Client Profitability & Margin Analysis</option>
                            <option value="timeline">Project Timeline & Schedule Adherence</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Select Specific Project</label>
                        <select name="project_id" class="form-select">
                            <option value="0">All Projects (Portfolio-Wide)</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['project_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1">Status Filter</label>
                        <select name="status" class="form-select">
                            <option value="All" selected>All Statuses</option>
                            <option value="Active">Active Projects</option>
                            <option value="Completed">Completed Handover</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4" style="background-color: #22c55e; border-color: #16a34a;">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> Open Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>