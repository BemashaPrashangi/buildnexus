<?php
require_once 'c:/xampp/htdocs/buildnexus/db.php';

try {
    echo "=== MIGRATING project_notes TABLE ===\n";

    // 1. Check & Add is_internal_only
    $cols = $pdo->query("SHOW COLUMNS FROM project_notes LIKE 'is_internal_only'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE project_notes ADD COLUMN is_internal_only TINYINT(1) NOT NULL DEFAULT 0 AFTER note_text");
        echo "✔ Added column 'is_internal_only'\n";
    } else {
        echo "✔ Column 'is_internal_only' already exists\n";
    }

    // 2. Check & Add updated_at
    $cols = $pdo->query("SHOW COLUMNS FROM project_notes LIKE 'updated_at'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE project_notes ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "✔ Added column 'updated_at'\n";
    } else {
        echo "✔ Column 'updated_at' already exists\n";
    }

    // 3. Ensure notifications table exists for PM notification dispatch
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        project_id INT NULL,
        type VARCHAR(50) DEFAULT 'note',
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✔ Checked/Created 'notifications' table\n";

    // 4. Ensure Mr. Silva user exists (for matching screenshot initials 'MS' and name 'Mr. Silva')
    $stmt = $pdo->prepare("SELECT id FROM users WHERE full_name = 'Mr. Silva'");
    $stmt->execute();
    $mr_silva_id = $stmt->fetchColumn();
    if (!$mr_silva_id) {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $stmt_ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES ('Mr. Silva', 'mr.silva@buildnexus.com', ?, 'Client', 'Active')");
        $stmt_ins->execute([$hash]);
        $mr_silva_id = $pdo->lastInsertId();
        echo "✔ Created user Mr. Silva (ID: $mr_silva_id)\n";
    } else {
        echo "✔ User Mr. Silva exists (ID: $mr_silva_id)\n";
    }

    // Ensure John Doe exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE full_name = 'John Doe'");
    $stmt->execute();
    $john_doe_id = $stmt->fetchColumn();
    if (!$john_doe_id) {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $stmt_ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES ('John Doe', 'john.doe@buildnexus.com', ?, 'Project Manager', 'Active')");
        $stmt_ins->execute([$hash]);
        $john_doe_id = $pdo->lastInsertId();
        echo "✔ Created user John Doe (ID: $john_doe_id)\n";
    } else {
        echo "✔ User John Doe exists (ID: $john_doe_id)\n";
    }

    // 5. Seed screenshot baseline notes for Project 4 (Luxury Villa in Kandy) and Project 26 if not already present
    // Screenshot:
    // Root Note 1: John Doe (2 days ago):
    // "Client confirmed the selection for the kitchen backsplash tiles (Heritage Hex Porcelain). Please proceed with ordering."
    // Nested Child Reply: Mr. Silva (2 days ago):
    // "That is correct. Looking forward to seeing them installed!"
    // Root Note 2: Mr. Silva (1 day ago):
    // "Can we get an update on the expected delivery date for the bathroom vanity?"

    foreach ([4, 26] as $pid) {
        $stmt = $pdo->prepare("SELECT id FROM project_notes WHERE project_id = ? AND note_text LIKE '%Heritage Hex Porcelain%'");
        $stmt->execute([$pid]);
        $root1_id = $stmt->fetchColumn();

        if (!$root1_id) {
            // Insert root note 1 (2 days ago)
            $two_days_ago = date('Y-m-d H:i:s', strtotime('-2 days'));
            $stmt_n1 = $pdo->prepare("INSERT INTO project_notes (project_id, user_id, parent_id, note_text, is_internal_only, created_at) VALUES (?, ?, NULL, ?, 0, ?)");
            $stmt_n1->execute([
                $pid,
                $john_doe_id,
                "Client confirmed the selection for the kitchen backsplash tiles (Heritage Hex Porcelain). Please proceed with ordering.",
                $two_days_ago
            ]);
            $root1_id = $pdo->lastInsertId();

            // Insert child reply 1 (2 days ago + 1 hour)
            $reply_time = date('Y-m-d H:i:s', strtotime('-2 days +1 hour'));
            $stmt_r1 = $pdo->prepare("INSERT INTO project_notes (project_id, user_id, parent_id, note_text, is_internal_only, created_at) VALUES (?, ?, ?, ?, 0, ?)");
            $stmt_r1->execute([
                $pid,
                $mr_silva_id,
                $root1_id,
                "That is correct. Looking forward to seeing them installed!",
                $reply_time
            ]);

            // Insert root note 2 (1 day ago)
            $one_day_ago = date('Y-m-d H:i:s', strtotime('-1 day'));
            $stmt_n2 = $pdo->prepare("INSERT INTO project_notes (project_id, user_id, parent_id, note_text, is_internal_only, created_at) VALUES (?, ?, NULL, ?, 0, ?)");
            $stmt_n2->execute([
                $pid,
                $mr_silva_id,
                "Can we get an update on the expected delivery date for the bathroom vanity?",
                $one_day_ago
            ]);
            echo "✔ Seeded baseline notes for Project $pid\n";
        } else {
            echo "✔ Baseline notes already seeded for Project $pid\n";
        }
    }

    echo "=== MIGRATION COMPLETED SUCCESSFULLY ===\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
