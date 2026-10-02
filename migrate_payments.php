<?php
// migrate_payments.php - Setup invoice_payments table, update invoices schema, and prepare upload directory
require_once __DIR__ . '/db.php';

echo "=== MIGRATING PAYMENTS & INVOICES SCHEMA ===\n\n";

try {
    // 1. Update invoices table status ENUM to include 'Pending Verification'
    $pdo->exec("ALTER TABLE `invoices` MODIFY COLUMN `status` ENUM('Draft', 'Sent', 'Pending Verification', 'Paid', 'Partially Paid', 'Overdue', 'Void') NOT NULL DEFAULT 'Sent'");
    echo "✔ invoices.status updated with 'Pending Verification'.\n";

    // 2. Ensure amount is DECIMAL(15,2) NOT NULL DEFAULT 0.00
    $pdo->exec("ALTER TABLE `invoices` MODIFY COLUMN `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00");
    echo "✔ invoices.amount verified as DECIMAL(15,2).\n";

    // 3. Create table invoice_payments if it doesn't exist
    $sql_ip = "CREATE TABLE IF NOT EXISTS `invoice_payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_id` INT NOT NULL,
        `client_id` INT NOT NULL,
        `payment_method` ENUM('Card', 'Bank Transfer', 'Cheque', 'Cash') NOT NULL DEFAULT 'Bank Transfer',
        `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `transaction_reference` VARCHAR(100) NULL,
        `receipt_slip_url` VARCHAR(255) NULL,
        `status` ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
        `verified_by` INT NULL,
        `verified_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_inv_pay_invoice` (`invoice_id`),
        INDEX `idx_inv_pay_client` (`client_id`),
        INDEX `idx_inv_pay_status` (`status`),
        CONSTRAINT `fk_inv_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql_ip);
    echo "✔ Table `invoice_payments` verified/created successfully.\n";

    // 4. Ensure uploads/payment_slips directory exists
    $upload_dir = __DIR__ . '/uploads/payment_slips';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        echo "✔ Created directory `uploads/payment_slips`.\n";
    } else {
        echo "✔ Directory `uploads/payment_slips` exists.\n";
    }

    // 5. Update INV-0128 and change orders invoices to match client 4 (Mrs. Silva) so client portal shows exact screenshot
    $pdo->exec("UPDATE invoices SET client_id = 4 WHERE invoice_number = 'INV-0128'");
    $pdo->exec("UPDATE invoices SET due_date = '2026-10-15', status = 'Sent' WHERE invoice_number IN ('INV-0128', 'INV-2026-005', 'INV-2026-004', 'INV-2026-003', 'INV-2026-002')");
    echo "✔ Invoices updated for Mrs. Silva (client_id 4) matching screenshot.\n";

    // 6. Also ensure John Doe (client_id 10, project 26) has corresponding invoices if needed
    $stmt_jd = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE client_id = 10 OR (project_id = 26 AND client_id IS NOT NULL)");
    $stmt_jd->execute();
    if ($stmt_jd->fetchColumn() == 0) {
        // Link invoice 2 (INV-2026-001) to client_id 10
        $pdo->exec("UPDATE invoices SET client_id = 10, status = 'Sent', due_date = '2026-10-20' WHERE id = 2");
        // Insert sample invoice for project 26
        $pdo->exec("INSERT INTO invoices (project_id, client_id, invoice_number, amount, total_amount, due_date, status, notes) 
                    VALUES (26, 10, 'INV-2026-006', 462000.00, 462000.00, '2026-10-25', 'Sent', 'Phase 2 Milestone Payment')");
        echo "✔ Seeded invoices for John Doe (client_id 10, project 26).\n";
    }

    echo "\n=== MIGRATION COMPLETE ===\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
