<?php
// migrate_reports.php - Database schema migration and setup for Construction Executive BI & Reports
require_once __DIR__ . '/db.php';

echo "=== MIGRATING EXECUTIVE REPORTS SCHEMA ===\n\n";

try {
    // 1. Create budgets table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `budgets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `allocated_amount` DECIMAL(15,2) DEFAULT 0.00,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Table `budgets` verified.\n";

    // 2. Add amount_paid to payments if not present
    $cols = [];
    $stmt = $pdo->query("DESCRIBE payments");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[$row['Field']] = $row;
    }

    if (!isset($cols['amount_paid'])) {
        $pdo->exec("ALTER TABLE `payments` ADD COLUMN `amount_paid` DECIMAL(15,2) NULL AFTER `amount`");
        $pdo->exec("UPDATE `payments` SET `amount_paid` = `amount` WHERE `amount_paid` IS NULL");
        echo "✔ Added `amount_paid` to `payments`.\n";
    }

    // 3. Ensure projects have start_date and end_date populated
    $pdo->exec("
        UPDATE `projects` 
        SET `start_date` = '2026-03-01' 
        WHERE `start_date` IS NULL OR `start_date` = '0000-00-00'
    ");
    $pdo->exec("
        UPDATE `projects` 
        SET `end_date` = '2026-11-30' 
        WHERE `end_date` IS NULL OR `end_date` = '0000-00-00'
    ");
    echo "✔ Projects timeline dates verified.\n";

    // 4. Ensure payments exist for clients with invoices so collected revenue is populated
    $clients = $pdo->query("SELECT id FROM clients")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($clients as $cid) {
        $inv = $pdo->prepare("SELECT id, project_id, total_amount FROM invoices WHERE client_id = ? AND status IN ('Paid', 'Sent') LIMIT 1");
        $inv->execute([$cid]);
        $inv_data = $inv->fetch(PDO::FETCH_ASSOC);

        if ($inv_data) {
            $has_pay = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE client_id = ?");
            $has_pay->execute([$cid]);
            if ($has_pay->fetchColumn() == 0) {
                $pay_amt = round(floatval($inv_data['total_amount']) * 0.85, 2);
                $insPay = $pdo->prepare("
                    INSERT INTO payments 
                    (transaction_id, invoice_id, project_id, client_id, amount, amount_paid, payment_method, status, payment_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'Bank Transfer', 'Completed', CURDATE(), NOW())
                ");
                $txId = 'TXN-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                $insPay->execute([$txId, $inv_data['id'], $inv_data['project_id'], $cid, $pay_amt, $pay_amt]);
                echo "✔ Seeded payment for client ID {$cid}: RS. " . number_format($pay_amt, 2) . ".\n";
            }
        }
    }

    // 5. Populate initial budget allocations if table empty
    $b_count = $pdo->query("SELECT COUNT(*) FROM budgets")->fetchColumn();
    if ($b_count == 0) {
        $projs = $pdo->query("SELECT id, budget FROM projects WHERE budget > 0")->fetchAll(PDO::FETCH_ASSOC);
        $insB = $pdo->prepare("INSERT INTO budgets (project_id, allocated_amount, notes, created_at) VALUES (?, ?, 'Initial ERP Project Budget Allocation', NOW())");
        foreach ($projs as $p) {
            $insB->execute([$p['id'], $p['budget']]);
        }
        echo "✔ Seeded " . count($projs) . " budget allocations.\n";
    }

    echo "\n🎉 EXECUTIVE REPORTS SCHEMA MIGRATION COMPLETED.\n";

} catch (Exception $e) {
    echo "❌ Error in migration: " . $e->getMessage() . "\n";
}
