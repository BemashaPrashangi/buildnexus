<?php
// migrate_financials.php - Database schema migration and setup for Project Financials
require_once __DIR__ . '/db.php';

echo "=== MIGRATING FINANCIALS SCHEMA ===\n\n";

try {
    // 1. Update bills table: ensure bill_date and category exist
    $cols = [];
    $stmt = $pdo->query("DESCRIBE bills");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[$row['Field']] = $row;
    }

    if (!isset($cols['bill_date'])) {
        $pdo->exec("ALTER TABLE `bills` ADD COLUMN `bill_date` DATE NULL AFTER `bill_number`");
        $pdo->exec("UPDATE `bills` SET `bill_date` = DATE(created_at) WHERE `bill_date` IS NULL");
        echo "✔ Added `bill_date` to `bills`.\n";
    }

    if (!isset($cols['category'])) {
        $pdo->exec("ALTER TABLE `bills` ADD COLUMN `category` VARCHAR(50) DEFAULT 'Materials' AFTER `total_amount`");
        echo "✔ Added `category` to `bills`.\n";
    }

    // 2. Update purchase_orders table: ensure category exists
    $po_cols = [];
    $stmt = $pdo->query("DESCRIBE purchase_orders");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $po_cols[$row['Field']] = $row;
    }

    if (!isset($po_cols['category'])) {
        $pdo->exec("ALTER TABLE `purchase_orders` ADD COLUMN `category` VARCHAR(50) DEFAULT 'Materials' AFTER `total_amount`");
        echo "✔ Added `category` to `purchase_orders`.\n";
    }

    // 3. Create takeoff_items table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `takeoff_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `category` VARCHAR(50) NOT NULL DEFAULT 'Materials',
            `item_name` VARCHAR(255) NOT NULL,
            `quantity` DECIMAL(10,2) DEFAULT 1.00,
            `unit` VARCHAR(50) DEFAULT 'units',
            `unit_cost` DECIMAL(15,2) DEFAULT 0.00,
            `total_cost` DECIMAL(15,2) DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`project_id`),
            INDEX (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Table `takeoff_items` created or verified.\n";

    // 4. Locate or set up Project 4: Luxury Villa in Kandy
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = 4 OR project_name LIKE '%Luxury Villa%' LIMIT 1");
    $stmt->execute();
    $proj_id = $stmt->fetchColumn();

    if (!$proj_id) {
        $insP = $pdo->prepare("INSERT INTO projects (project_name, project_code, budget, stage, status) VALUES ('Luxury Villa in Kandy', 'PRJ-2024-004', 24845000.00, 'Finishing', 'Active')");
        $insP->execute();
        $proj_id = $pdo->lastInsertId();
    } else {
        // Set base budget so base budget + 155,000 approved COs = 25,000,000
        $pdo->prepare("UPDATE projects SET budget = 24845000.00, project_name = 'Luxury Villa in Kandy' WHERE id = ?")->execute([$proj_id]);
    }
    echo "✔ Project ID {$proj_id} configured with base budget RS. 24,845,000.00.\n";

    // 5. Seed signature Approved Change Order CO-0012 (+75,000)
    $stmt = $pdo->prepare("SELECT id FROM change_orders WHERE co_number = 'CO-0012' AND project_id = ?");
    $stmt->execute([$proj_id]);
    if (!$stmt->fetchColumn()) {
        $insCO = $pdo->prepare("
            INSERT INTO change_orders 
            (co_number, project_id, title, description, cost_impact, time_impact_days, status, created_at, approved_at)
            VALUES ('CO-0012', ?, 'Additional Pool Deck Teak Slatting', 'Upgrade poolside decking to weather-treated teak', 75000.00, 3, 'Approved', NOW(), NOW())
        ");
        $insCO->execute([$proj_id]);
        echo "✔ Seeded Change Order CO-0012 (+75,000 Approved).\n";
    }

    // 6. Seed signature Invoice INV-0128 (+2,500,000)
    $stmt = $pdo->prepare("SELECT id FROM invoices WHERE invoice_number = 'INV-0128' AND project_id = ?");
    $stmt->execute([$proj_id]);
    if (!$stmt->fetchColumn()) {
        $insInv = $pdo->prepare("
            INSERT INTO invoices 
            (project_id, client_id, invoice_number, amount, total_amount, issue_date, due_date, status, notes, created_at)
            VALUES (?, 1, 'INV-0128', 2500000.00, 2500000.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'Sent', 'Milestone 3 Progress Payment', NOW())
        ");
        $insInv->execute([$proj_id]);
        echo "✔ Seeded Invoice INV-0128 (+2,500,000).\n";
    }

    // 7. Seed signature Purchase Order PO-0098 (-450,000)
    $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE po_number = 'PO-0098' AND project_id = ?");
    $stmt->execute([$proj_id]);
    if (!$stmt->fetchColumn()) {
        $insPO = $pdo->prepare("
            INSERT INTO purchase_orders 
            (po_number, project_id, vendor_name, po_date, subtotal, tax_amount, total_amount, status, category, notes, created_at)
            VALUES ('PO-0098', ?, 'Lanka Steel Suppliers', CURDATE(), 450000.00, 0.00, 450000.00, 'Sent', 'Materials', 'Roofing beams & standing seam fasteners', NOW())
        ");
        $insPO->execute([$proj_id]);
        echo "✔ Seeded Purchase Order PO-0098 (-450,000).\n";
    }

    // 8. Seed signature Bill BILL-0045 (-120,000)
    $stmt = $pdo->prepare("SELECT id FROM bills WHERE bill_number = 'BILL-0045' AND project_id = ?");
    $stmt->execute([$proj_id]);
    if (!$stmt->fetchColumn()) {
        $insBill = $pdo->prepare("
            INSERT INTO bills 
            (project_id, vendor_name, bill_number, bill_date, total_amount, status, category, created_at)
            VALUES (?, 'Nippon Paint Lanka', 'BILL-0045', CURDATE(), 120000.00, 'Paid', 'Materials', NOW())
        ");
        $insBill->execute([$proj_id]);
        echo "✔ Seeded Bill BILL-0045 (-120,000).\n";
    }

    // 9. Additional bills and approved time cards for Project 4 to align Total Spent with 11,250,000
    // Current bills total for Project 4:
    $current_bills = floatval($pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE project_id = {$proj_id}")->fetchColumn());
    $current_labor = floatval($pdo->query("SELECT COALESCE(SUM(total_hours * hourly_rate), 0) FROM time_cards WHERE project_id = {$proj_id} AND approval_status = 'Approved'")->fetchColumn());
    $target_spent = 11250000.00;
    $diff = $target_spent - ($current_bills + $current_labor);

    if ($diff > 1000) {
        // Add breakdown bills to populate categories:
        // Materials: 4,000,000
        // Subcontractors: 5,000,000
        // Permits: 1,000,000
        // Contingency: 130,000
        $bills_to_add = [
            ['vendor' => 'Tokyo Cement Lanka', 'num' => 'BILL-1092', 'amt' => 3880000.00, 'cat' => 'Materials'],
            ['vendor' => 'Kandy Structural Specialists', 'num' => 'BILL-2041', 'amt' => 5000000.00, 'cat' => 'Subcontractors'],
            ['vendor' => 'Kandy Municipal Council', 'num' => 'BILL-3015', 'amt' => 1000000.00, 'cat' => 'Permits'],
            ['vendor' => 'Site Drainage Redirection', 'num' => 'BILL-4008', 'amt' => 200000.00, 'cat' => 'Contingency'],
        ];

        // Also ensure approved labor in time cards = 1,050,000
        $tc_exists = $pdo->query("SELECT COUNT(*) FROM time_cards WHERE project_id = {$proj_id} AND approval_status = 'Approved'")->fetchColumn();
        if ($tc_exists == 0) {
            $pdo->prepare("
                INSERT INTO time_cards 
                (project_id, user_id, work_date, clock_in, clock_out, break_minutes, total_hours, hourly_rate, approval_status, status, created_at)
                VALUES (?, 1, CURDATE(), '2026-10-01 08:00:00', '2026-10-01 17:00:00', 60, 300.00, 3500.00, 'Approved', 'Completed', NOW())
            ")->execute([$proj_id]);
            echo "✔ Seeded approved labor time card (300 hrs @ RS. 3,500 = RS. 1,050,000).\n";
        }

        // Recompute diff
        $cur_bills = floatval($pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE project_id = {$proj_id}")->fetchColumn());
        $cur_labor = floatval($pdo->query("SELECT COALESCE(SUM(total_hours * hourly_rate), 0) FROM time_cards WHERE project_id = {$proj_id} AND approval_status = 'Approved'")->fetchColumn());
        $remaining_needed = $target_spent - ($cur_bills + $cur_labor);

        if ($remaining_needed > 0) {
            $insB = $pdo->prepare("
                INSERT INTO bills (project_id, vendor_name, bill_number, bill_date, total_amount, status, category, created_at)
                VALUES (?, 'Kandy Masonry & Finishing Works', 'BILL-5501', CURDATE(), ?, 'Paid', 'Subcontractors', NOW())
            ");
            $insB->execute([$proj_id, $remaining_needed]);
            echo "✔ Adjusted bills with RS. " . number_format($remaining_needed, 2) . " to reach exact target spent RS. 11,250,000.00.\n";
        }
    }

    // 10. Seed Takeoff items for estimated category baseline matching Cost Analysis chart:
    // Materials: 8,000,000
    // Labor: 5,000,000
    // Subcontractors: 7,000,000
    // Permits: 800,000
    // Contingency: 2,500,000
    $takeoffs = [
        ['cat' => 'Materials', 'name' => 'Cement, rebar, masonry blocks & roof shingles', 'cost' => 8000000.00],
        ['cat' => 'Labor', 'name' => 'Site engineers, foremen & trade crew labor', 'cost' => 5000000.00],
        ['cat' => 'Subcontractors', 'name' => 'HVAC, plumbing rough-in, electrical wiring', 'cost' => 7000000.00],
        ['cat' => 'Permits', 'name' => 'Planning authority approval & utility connection fees', 'cost' => 800000.00],
        ['cat' => 'Contingency', 'name' => 'Design variance & unforeseen geotechnical buffer', 'cost' => 2500000.00]
    ];

    $has_to = $pdo->query("SELECT COUNT(*) FROM takeoff_items WHERE project_id = {$proj_id}")->fetchColumn();
    if ($has_to == 0) {
        $insTO = $pdo->prepare("
            INSERT INTO takeoff_items (project_id, category, item_name, quantity, unit, unit_cost, total_cost, created_at)
            VALUES (?, ?, ?, 1.00, 'lot', ?, ?, NOW())
        ");
        foreach ($takeoffs as $to) {
            $insTO->execute([$proj_id, $to['cat'], $to['name'], $to['cost'], $to['cost']]);
        }
        echo "✔ Seeded takeoff estimates for Cost Analysis chart.\n";
    }

    echo "\n🎉 FINANCIALS MIGRATION COMPLETED SUCCESSFULLY.\n";

} catch (Exception $e) {
    echo "❌ Error in migration: " . $e->getMessage() . "\n";
}
