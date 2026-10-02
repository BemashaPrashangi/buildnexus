<?php
// migrate_tasks.php - Database schema migration for BuildNexus Tasks module
require_once __DIR__ . '/db.php';

echo "=== MIGRATING TASKS SCHEMA ===\n\n";

try {
    // 1. Check existing columns in tasks table
    $existing_cols = [];
    $stmt = $pdo->query("DESCRIBE tasks");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_cols[$row['Field']] = $row;
    }

    // Rename task_title to title if task_title exists and title does not
    if (isset($existing_cols['task_title']) && !isset($existing_cols['title'])) {
        $pdo->exec("ALTER TABLE `tasks` CHANGE `task_title` `title` VARCHAR(255) NOT NULL");
        echo "✔ Renamed `task_title` to `title`.\n";
    } elseif (!isset($existing_cols['title']) && !isset($existing_cols['task_title'])) {
        $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `title` VARCHAR(255) NOT NULL AFTER `project_id`");
        echo "✔ Added `title` column.\n";
    }

    // Refresh columns
    $existing_cols = [];
    $stmt = $pdo->query("DESCRIBE tasks");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_cols[$row['Field']] = $row;
    }

    // Add assigned_to
    if (!isset($existing_cols['assigned_to'])) {
        $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `assigned_to` INT NULL AFTER `project_id`");
        echo "✔ Added `assigned_to` column.\n";
    }

    // Add description
    if (!isset($existing_cols['description'])) {
        $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `description` TEXT NULL AFTER `title`");
        echo "✔ Added `description` column.\n";
    }

    // Add priority
    if (!isset($existing_cols['priority'])) {
        $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `priority` ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium' AFTER `description`");
        echo "✔ Added `priority` column.\n";
    }

    // Update status column enum
    $pdo->exec("ALTER TABLE `tasks` MODIFY COLUMN `status` ENUM('To Do', 'In Progress', 'Done', 'Blocked') DEFAULT 'To Do'");
    echo "✔ Updated `status` enum definition to include 'Blocked'.\n";

    // Add completed_at
    if (!isset($existing_cols['completed_at'])) {
        $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `completed_at` DATETIME NULL AFTER `due_date`");
        echo "✔ Added `completed_at` column.\n";
    }

    // Add created_by
    if (!isset($existing_cols['created_by'])) {
        $pdo->exec("ALTER TABLE `tasks` ADD COLUMN `created_by` INT NULL AFTER `completed_at`");
        echo "✔ Added `created_by` column.\n";
    }

    // Add indexes for performance
    try {
        $pdo->exec("ALTER TABLE `tasks` ADD INDEX `idx_tasks_project` (`project_id`)");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `tasks` ADD INDEX `idx_tasks_assigned` (`assigned_to`)");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `tasks` ADD INDEX `idx_tasks_status` (`status`)");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE `tasks` ADD INDEX `idx_tasks_due` (`due_date`)");
    } catch (Exception $e) {}

    // 2. Ensure projects table has progress_percent column
    $p_cols = [];
    $stmt = $pdo->query("DESCRIBE projects");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $p_cols[$row['Field']] = $row;
    }
    if (!isset($p_cols['progress_percent'])) {
        $pdo->exec("ALTER TABLE `projects` ADD COLUMN `progress_percent` INT DEFAULT 0 AFTER `stage`");
        echo "✔ Added `progress_percent` column to `projects`.\n";
    }

    // 3. Find default user (PM/Admin) and projects for seeding
    $user_id = $pdo->query("SELECT id FROM users WHERE role IN ('Admin', 'Project Manager') LIMIT 1")->fetchColumn() ?: 1;
    $projects = $pdo->query("SELECT id, project_name FROM projects ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($projects)) {
        // Create sample project if none exist
        $pdo->exec("INSERT INTO projects (project_name, project_code, stage, status) VALUES ('Project Alpha', 'PRJ-2026-001', 'Planning', 'Active')");
        $projects = $pdo->query("SELECT id, project_name FROM projects ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    $proj_map = [];
    foreach ($projects as $p) {
        $proj_map[strtolower(trim($p['project_name']))] = $p['id'];
    }

    // Helper to find matching project or create if needed
    $getProjId = function($name) use ($pdo, &$proj_map, $projects) {
        $key = strtolower(trim($name));
        if (isset($proj_map[$key])) {
            return $proj_map[$key];
        }
        foreach ($proj_map as $k => $id) {
            if (strpos($k, $key) !== false || strpos($key, $k) !== false) {
                return $id;
            }
        }
        // Insert project if missing
        $code = 'PRJ-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)) . '-01';
        $ins = $pdo->prepare("INSERT INTO projects (project_name, project_code, stage, status) VALUES (?, ?, 'Framing', 'Active')");
        $ins->execute([$name, $code]);
        $new_id = $pdo->lastInsertId();
        $proj_map[$key] = $new_id;
        return $new_id;
    };

    // 4. Seed realistic tasks matching user screenshot & dynamic dates
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $in2days = date('Y-m-d', strtotime('+2 days'));
    $in4days = date('Y-m-d', strtotime('+4 days'));
    $nextWeek = date('Y-m-d', strtotime('+9 days'));

    $seed_tasks = [
        [
            'title' => 'Review and Approve Quote #Q-0012',
            'project_name' => 'Project Alpha',
            'due_date' => $today,
            'status' => 'In Progress',
            'priority' => 'High',
            'description' => 'Check supplier pricing for electrical switchboards and main cables against approved MEP schedule.'
        ],
        [
            'title' => "Finalize selections with client for 'Galle Boutique Hotel'",
            'project_name' => 'Galle Boutique Hotel',
            'due_date' => $yesterday, // Overdue item appearing in Today's tab
            'status' => 'To Do',
            'priority' => 'Medium',
            'description' => 'Present fluted timber panels and imported terrazzo tiles to client rep for final sign-off.'
        ],
        [
            'title' => "Submit PO for roofing materials - 'Luxury Villa'",
            'project_name' => 'Luxury Villa',
            'due_date' => $in2days, // This Week
            'status' => 'To Do',
            'priority' => 'Urgent',
            'description' => 'Transmit purchase order to Lanka Steel for zinc-aluminum sheets and standing seam clips.'
        ],
        [
            'title' => "Onboard new subcontractor for 'Highway Expansion E01'",
            'project_name' => 'Highway Expansion E01',
            'due_date' => $yesterday,
            'status' => 'Done',
            'priority' => 'Medium',
            'description' => 'Verify sub-contractor safety manual compliance, contractor insurance, and workers compensation.'
        ],
        [
            'title' => "Pour concrete foundation for Block B - 'Skyline Residence'",
            'project_name' => 'Skyline Residence',
            'due_date' => $in4days, // This Week
            'status' => 'To Do',
            'priority' => 'High',
            'description' => 'Coordinate with ready-mix concrete plant and QA team for cylinder compression test batching.'
        ],
        [
            'title' => "Conduct acoustic testing for Executive Boardroom",
            'project_name' => 'Davis Kitchen Remodel',
            'due_date' => $nextWeek, // Future date (All Tasks tab)
            'status' => 'To Do',
            'priority' => 'Low',
            'description' => 'Measure reverberation time and sound transmission class across partition walls.'
        ]
    ];

    $count = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    if ($count == 0) {
        $insStmt = $pdo->prepare("
            INSERT INTO tasks 
            (project_id, assigned_to, title, description, priority, status, due_date, completed_at, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($seed_tasks as $st) {
            $p_id = $getProjId($st['project_name']);
            $comp_at = ($st['status'] === 'Done') ? date('Y-m-d H:i:s') : null;
            $insStmt->execute([
                $p_id,
                $user_id,
                $st['title'],
                $st['description'],
                $st['priority'],
                $st['status'],
                $st['due_date'],
                $comp_at,
                $user_id
            ]);
        }
        echo "✔ Seeded " . count($seed_tasks) . " realistic tasks matching UI requirements.\n";
    } else {
        echo "ℹ tasks table already contains {$count} records. Ensuring project sync.\n";
    }

    // 5. Sync project progress_percent for all projects
    $allProjects = $pdo->query("SELECT id FROM projects")->fetchAll(PDO::FETCH_COLUMN);
    $updProj = $pdo->prepare("UPDATE projects SET progress_percent = ? WHERE id = ?");
    foreach ($allProjects as $pid) {
        $tot = $pdo->query("SELECT COUNT(*) FROM tasks WHERE project_id = {$pid}")->fetchColumn();
        $done = $pdo->query("SELECT COUNT(*) FROM tasks WHERE project_id = {$pid} AND status = 'Done'")->fetchColumn();
        $pct = ($tot > 0) ? round(($done / $tot) * 100) : 0;
        $updProj->execute([$pct, $pid]);
    }
    echo "✔ Synced progress percentages for " . count($allProjects) . " projects.\n";

    echo "\n🎉 MIGRATION COMPLETED SUCCESSFULLY.\n";

} catch (Exception $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
}
