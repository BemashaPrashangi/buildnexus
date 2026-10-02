<?php
require_once 'db.php';

try {
    // 1. Drop existing tables if needed or alter. It's safer to create/alter.
    // Assuming project_rfis might exist but missing columns, we'll recreate or alter.
    // Initialize project_rfis table schema
    
    $pdo->exec("DROP TABLE IF EXISTS rfi_responses;");
    $pdo->exec("DROP TABLE IF EXISTS project_rfis;");

    $sql1 = "
    CREATE TABLE project_rfis (
        id INT PRIMARY KEY AUTO_INCREMENT,
        rfi_number VARCHAR(50) UNIQUE,
        project_id INT,
        created_by INT,
        assigned_to_name VARCHAR(150),
        assigned_to_role VARCHAR(100),
        subject VARCHAR(255),
        question_details TEXT,
        suggested_solution TEXT NULL,
        status ENUM('Open', 'Answered', 'Overdue', 'Closed') DEFAULT 'Open',
        due_date DATE,
        date_sent DATE DEFAULT (CURRENT_DATE),
        attachment_file VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    );
    ";
    
    $pdo->exec($sql1);

    $sql2 = "
    CREATE TABLE rfi_responses (
        id INT PRIMARY KEY AUTO_INCREMENT,
        rfi_id INT,
        user_id INT,
        response_text TEXT,
        official_attachment VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (rfi_id) REFERENCES project_rfis(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    ";
    
    $pdo->exec($sql2);

    echo "Tables created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
