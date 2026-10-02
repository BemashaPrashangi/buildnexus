<?php
// migrate_change_orders.php - Upgrade change_orders schema and seed data
require_once __DIR__ . '/db.php';

echo "=== MIGRATING CHANGE ORDERS SCHEMA ===\n\n";

try {
    // 1. Check existing columns in change_orders
    $cols = $pdo->query("DESCRIBE change_orders")->fetchAll(PDO::FETCH_COLUMN);

    // Add schedule_impact_days if missing
    if (!in_array('schedule_impact_days', $cols)) {
        $pdo->exec("ALTER TABLE change_orders ADD COLUMN schedule_impact_days INT DEFAULT 0 AFTER cost_impact");
        if (in_array('time_impact_days', $cols)) {
            $pdo->exec("UPDATE change_orders SET schedule_impact_days = COALESCE(time_impact_days, 0)");
        }
        echo "✔ Added `schedule_impact_days` column.\n";
    }

    // Add client_feedback if missing
    if (!in_array('client_feedback', $cols)) {
        $pdo->exec("ALTER TABLE change_orders ADD COLUMN client_feedback TEXT NULL AFTER status");
        if (in_array('rejection_reason', $cols)) {
            $pdo->exec("UPDATE change_orders SET client_feedback = rejection_reason WHERE rejection_reason IS NOT NULL");
        }
        echo "✔ Added `client_feedback` column.\n";
    }

    // Add approved_by if missing
    if (!in_array('approved_by', $cols)) {
        $pdo->exec("ALTER TABLE change_orders ADD COLUMN approved_by INT NULL AFTER client_feedback");
        echo "✔ Added `approved_by` column.\n";
    }

    // Ensure status enum includes 'Declined'
    $pdo->exec("ALTER TABLE change_orders MODIFY COLUMN status ENUM('Draft', 'Pending', 'Approved', 'Declined', 'Rejected') NOT NULL DEFAULT 'Pending'");
    echo "✔ Updated `status` enum with 'Declined'.\n";

    // 2. Ensure Project 4 (Luxury Villa in Kandy) has the exact 3 change orders from the screenshot
    // Update ID 9, 3, 4
    $pdo->exec("UPDATE change_orders SET 
        project_id = 4, 
        client_id = 4, 
        title = 'Additional Pool Deck Teak Slatting', 
        description = 'Upgrade poolside decking to weather-treated teak', 
        cost_impact = 75000.00, 
        schedule_impact_days = 3, 
        status = 'Approved', 
        approved_at = '2026-01-04 18:30:34' 
        WHERE id = 9");

    $pdo->exec("UPDATE change_orders SET 
        project_id = 4, 
        client_id = 4, 
        co_number = 'CO-2024-001', 
        title = 'CO-2024-001', 
        description = 'Upgrade master bathroom tiles to premium Italian marble.', 
        cost_impact = 150000.00, 
        schedule_impact_days = 5, 
        status = 'Pending' 
        WHERE id = 3");

    $pdo->exec("UPDATE change_orders SET 
        project_id = 4, 
        client_id = 4, 
        co_number = 'CO-2024-002', 
        title = 'CO-2024-002', 
        description = 'Additional outdoor lighting for the garden and pool area.', 
        cost_impact = 80000.00, 
        schedule_impact_days = 2, 
        status = 'Approved', 
        approved_at = '2026-01-04 18:30:34' 
        WHERE id = 4");

    echo "✔ Project 4 change orders verified/updated matching screenshot.\n";

    // 3. Ensure Project 26 (John Doe) also has realistic change orders if viewed
    $stmt_p26 = $pdo->prepare("SELECT COUNT(*) FROM change_orders WHERE project_id = 26");
    $stmt_p26->execute();
    if ($stmt_p26->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO change_orders (project_id, client_id, co_number, title, description, cost_impact, schedule_impact_days, status) 
                    VALUES (26, 10, 'CO-2026-001', 'Waterfall Island Countertop Upgrade', 'Calacatta gold quartz with waterfall edges on both island sides.', 125000.00, 3, 'Pending')");
        echo "✔ Seeded change order for Project 26.\n";
    }

    echo "\n=== CHANGE ORDERS MIGRATION COMPLETE ===\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
