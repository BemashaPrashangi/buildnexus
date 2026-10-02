<?php
require_once 'db.php';

try {
    $pdo->exec("DROP TABLE IF EXISTS purchase_order_items;");
    $pdo->exec("DROP TABLE IF EXISTS purchase_orders;");
    // We assume bills table exists and we might just need to ensure the columns are there.
    // Schema specification: Synchronize converted bill into bills table to reflect in budget_vs_actual.php

    $sql1 = "
    CREATE TABLE purchase_orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        po_number VARCHAR(50) UNIQUE,
        project_id INT,
        supplier_id INT NULL,
        vendor_name VARCHAR(255),
        po_date DATE DEFAULT (CURRENT_DATE),
        expected_delivery_date DATE NULL,
        subtotal DECIMAL(15,2) DEFAULT 0.00,
        tax_amount DECIMAL(15,2) DEFAULT 0.00,
        total_amount DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('Draft', 'Pending Delivery', 'Goods Received', 'Converted to Bill', 'Paid', 'Cancelled') DEFAULT 'Draft',
        notes TEXT NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    );
    ";
    
    $pdo->exec($sql1);

    $sql2 = "
    CREATE TABLE purchase_order_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        po_id INT,
        item_description VARCHAR(255),
        quantity_ordered DECIMAL(10,2),
        quantity_received DECIMAL(10,2) DEFAULT 0.00,
        unit VARCHAR(50),
        unit_price DECIMAL(15,2),
        line_total DECIMAL(15,2),
        FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
    );
    ";
    
    $pdo->exec($sql2);

    // Let's create `bills` table if it doesn't exist, or just leave it. If phpMyAdmin shows it exists, it should have `project_id, vendor_name, bill_number, total_amount, status` at least.
    // I will run a safe ALTER to add any missing columns just in case, but usually we just insert.
    // For safety, I'll recreate bills if it's simple, or just use what's there. 
    // phpMyAdmin showed `bills` with 4 rows. I'll just rely on the standard columns.

    echo "Procurement Tables created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
