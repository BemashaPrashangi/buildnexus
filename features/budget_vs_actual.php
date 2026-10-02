<?php
require_once '../db.php'; 
session_start();

if (isset($_GET['project_id'])) {
    require_once __DIR__ . '/project_financials.php';
    exit();
}

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

try {
    /** * CALCULATIVE LOGIC:
     * 1. JOIN projects with estimates to get Total Budgeted Amount (Approved only).
     * 2. JOIN with a bills/expenses subquery to get Total Actual Spend.
     * 3. Calculate Variance and Spent Percentage.
     */
    $query = "
        SELECT 
            p.id, 
            p.project_name,
            COALESCE(SUM(DISTINCT e.total_amount), 0) as budgeted_amount,
            (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE project_id = p.id AND status = 'Paid') as actual_spent
        FROM projects p
        LEFT JOIN estimates e ON p.id = e.project_id AND e.status = 'Approved'
        GROUP BY p.id
    ";
    
    $stmt = $pdo->query($query);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Budget vs. Actual Report - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- PURE CSS STYLING --- */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { padding: 2.5rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        
        /* Table Styling */
        .table thead th { border-bottom: 1px solid #f1f5f9; color: #64748b; font-weight: 600; font-size: 0.85rem; padding: 1rem; }
        .table tbody td { padding: 1.25rem 1rem; border-bottom: 1px solid #f8fafc; font-size: 0.9rem; vertical-align: middle; }
        
        /* Progress Bar */
        .progress-nexus { height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden; margin-top: 5px; }
        .progress-fill { height: 100%; border-radius: 10px; transition: width 0.3s; }
        
        /* Variance Colors */
        .text-over { color: #dc2626; font-weight: 600; }
        .text-under { color: #16a34a; font-weight: 600; }
        
        .btn-back { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 16px; font-weight: 600; color: #1e293b; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="reports.php" class="btn-back mb-3"><i class="bi bi-arrow-left"></i> Back to Reports</a>
            <h1 class="h3 fw-bold mb-0">Budget vs. Actual Analysis</h1>
        </div>
        <button class="btn btn-success fw-bold px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i> Print Report</button>
    </div>

    <div class="nexus-card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="25%">Project Name</th>
                        <th width="15%">Budgeted (Estimates)</th>
                        <th width="15%">Actual Spent (Bills)</th>
                        <th width="15%">Variance</th>
                        <th width="25%">Budget Usage</th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): 
                        $budget = $row['budgeted_amount'];
                        $actual = $row['actual_spent'];
                        $variance = $budget - $actual;
                        $percent = ($budget > 0) ? ($actual / $budget) * 100 : 0;
                        $barColor = ($percent > 100) ? '#dc2626' : ($percent > 85 ? '#f59e0b' : '#22c55e');
                    ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($row['project_name']) ?></td>
                        <td class="text-muted">RS. <?= number_format($budget, 2) ?></td>
                        <td class="text-muted">RS. <?= number_format($actual, 2) ?></td>
                        <td class="<?= $variance < 0 ? 'text-over' : 'text-under' ?>">
                            <?= $variance < 0 ? '-' : '+' ?> RS. <?= number_format(abs($variance), 2) ?>
                        </td>
                        <td>
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= round($percent, 1) ?>% Spent</span>
                            </div>
                            <div class="progress-nexus">
                                <div class="progress-fill" style="width: <?= min($percent, 100) ?>%; background-color: <?= $barColor ?>;"></div>
                            </div>
                        </td>
                        <td class="text-end">
                            <a href="project_financials.php?project_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-success py-1 px-2" title="View Financial Breakdown">
                                <i class="bi bi-graph-up me-1"></i> Financials
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>