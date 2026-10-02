<?php
require_once 'db.php';

try {
    $pdo->exec("DROP TABLE IF EXISTS submittal_reviews;");
    $pdo->exec("DROP TABLE IF EXISTS project_submittals;");

    $sql1 = "
    CREATE TABLE project_submittals (
        id INT PRIMARY KEY AUTO_INCREMENT,
        submittal_number VARCHAR(50) UNIQUE,
        project_id INT,
        spec_section VARCHAR(50) NULL,
        title VARCHAR(255),
        submitted_to_name VARCHAR(150),
        submitted_to_role VARCHAR(100),
        submittal_type ENUM('Product Data', 'Shop Drawing', 'Sample', 'Test Report') DEFAULT 'Product Data',
        status ENUM('Pending', 'Approved', 'Approved as Noted', 'Revise & Resubmit', 'Rejected') DEFAULT 'Pending',
        date_sent DATE DEFAULT (CURRENT_DATE),
        review_due_date DATE NULL,
        attachment_path VARCHAR(255) NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    );
    ";
    
    $pdo->exec($sql1);

    $sql2 = "
    CREATE TABLE submittal_reviews (
        id INT PRIMARY KEY AUTO_INCREMENT,
        submittal_id INT,
        reviewer_id INT,
        review_decision ENUM('Approved', 'Approved as Noted', 'Revise & Resubmit', 'Rejected'),
        review_comments TEXT,
        annotated_file_path VARCHAR(255) NULL,
        reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submittal_id) REFERENCES project_submittals(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
    );
    ";
    
    $pdo->exec($sql2);

    echo "Submittals Tables created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
