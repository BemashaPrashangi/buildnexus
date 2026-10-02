<?php
// migrate_phases.php - Setup project_phases schema and initial data
require_once __DIR__ . '/db.php';

echo "=== MIGRATING PROJECT PHASES SCHEMA ===\n\n";

try {
    // 1. Create table project_phases if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `project_phases` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `phase_number` INT NOT NULL DEFAULT 1,
        `title` VARCHAR(150) NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `status` ENUM('UPCOMING', 'IN PROGRESS', 'COMPLETED') NOT NULL DEFAULT 'UPCOMING',
        `description` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_phases_project` (`project_id`),
        CONSTRAINT `fk_project_phases_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "✔ Table `project_phases` verified/created successfully.\n";

    // 2. Ensure Project 26 (Modern Kitchen Remodel) has the exact phases from the UI screenshot
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `project_phases` WHERE `project_id` = 26");
    $stmt->execute();
    $p26_count = $stmt->fetchColumn();

    if ($p26_count == 0) {
        $p26_phases = [
            [
                'phase_number' => 1,
                'title' => 'Phase 1: Demolition & Site Prep',
                'start_date' => '2026-01-10',
                'end_date' => '2026-01-15',
                'status' => 'COMPLETED',
                'description' => 'Full teardown of existing cabinetry, countertops, and appliances with site protection.'
            ],
            [
                'phase_number' => 2,
                'title' => 'Phase 2: Rough-in Plumbing & Electrical',
                'start_date' => '2026-01-16',
                'end_date' => '2026-01-22',
                'status' => 'IN PROGRESS',
                'description' => 'Installation of rough-in plumbing lines, electrical junction boxes, and new dedicated circuits.'
            ],
            [
                'phase_number' => 3,
                'title' => 'Phase 3: Cabinetry & Countertop Install',
                'start_date' => '2026-01-23',
                'end_date' => '2026-02-05',
                'status' => 'UPCOMING',
                'description' => 'Precision mounting of custom shaker cabinets, quartz countertops, and island waterfall edge.'
            ],
            [
                'phase_number' => 4,
                'title' => 'Phase 4: Tiling & Backsplash',
                'start_date' => '2026-02-06',
                'end_date' => '2026-02-12',
                'status' => 'UPCOMING',
                'description' => 'Subway tile backsplash installation, grouting, sealing, and final trim work.'
            ]
        ];

        $ins = $pdo->prepare("INSERT INTO `project_phases` (`project_id`, `phase_number`, `title`, `start_date`, `end_date`, `status`, `description`) 
                              VALUES (26, :pnum, :title, :sdate, :edate, :status, :desc)");
        foreach ($p26_phases as $phase) {
            $ins->execute([
                ':pnum' => $phase['phase_number'],
                ':title' => $phase['title'],
                ':sdate' => $phase['start_date'],
                ':edate' => $phase['end_date'],
                ':status' => $phase['status'],
                ':desc' => $phase['description']
            ]);
        }
        echo "✔ Seeded 4 phases for Project 26 (Modern Kitchen Remodel).\n";
    } else {
        echo "✔ Project 26 already has $p26_count phase(s).\n";
    }

    // 3. Ensure Project 4 (Luxury Villa in Kandy) also has sample phases if empty
    $stmt4 = $pdo->prepare("SELECT COUNT(*) FROM `project_phases` WHERE `project_id` = 4");
    $stmt4->execute();
    if ($stmt4->fetchColumn() == 0) {
        $p4_phases = [
            ['phase_number' => 1, 'title' => 'Phase 1: Foundation & Earthwork', 'start_date' => '2026-01-05', 'end_date' => '2026-01-25', 'status' => 'COMPLETED', 'description' => 'Excavation and foundation concrete pouring.'],
            ['phase_number' => 2, 'title' => 'Phase 2: Structural Framing & Columns', 'start_date' => '2026-01-26', 'end_date' => '2026-02-20', 'status' => 'IN PROGRESS', 'description' => 'Reinforced column construction and framing.'],
            ['phase_number' => 3, 'title' => 'Phase 3: MEP Rough-in & Insulation', 'start_date' => '2026-02-21', 'end_date' => '2026-03-15', 'status' => 'UPCOMING', 'description' => 'Mechanical, electrical, plumbing installation.'],
            ['phase_number' => 4, 'title' => 'Phase 4: Interior Finishes & Handover', 'start_date' => '2026-03-16', 'end_date' => '2026-04-10', 'status' => 'UPCOMING', 'description' => 'Floor finishes, painting, fixture installation and final inspection.']
        ];
        $ins = $pdo->prepare("INSERT INTO `project_phases` (`project_id`, `phase_number`, `title`, `start_date`, `end_date`, `status`, `description`) 
                              VALUES (4, :pnum, :title, :sdate, :edate, :status, :desc)");
        foreach ($p4_phases as $phase) {
            $ins->execute([
                ':pnum' => $phase['phase_number'],
                ':title' => $phase['title'],
                ':sdate' => $phase['start_date'],
                ':edate' => $phase['end_date'],
                ':status' => $phase['status'],
                ':desc' => $phase['description']
            ]);
        }
        echo "✔ Seeded 4 phases for Project 4 (Luxury Villa in Kandy).\n";
    }

    // 4. Ensure contacts linking for John Doe (user_id 10 -> default_project_id 26) and Mrs. Silva (user_id 4 -> default_project_id 4)
    $stmtC = $pdo->prepare("SELECT id FROM contacts WHERE linked_user_id = 10 LIMIT 1");
    $stmtC->execute();
    if (!$stmtC->fetch()) {
        $stmtInsC = $pdo->prepare("INSERT INTO contacts (name, email, phone, role_type, linked_user_id, default_project_id, status) 
                                   VALUES ('John Doe', 'john.doe@email.com', '+94 70 555 3456', 'Client', 10, 26, 'Active')");
        $stmtInsC->execute();
        echo "✔ Linked contact entry created for user 10 (John Doe) with default_project_id 26.\n";
    }

    $stmtS = $pdo->prepare("SELECT id FROM contacts WHERE linked_user_id = 4 LIMIT 1");
    $stmtS->execute();
    if (!$stmtS->fetch()) {
        // Update contact with email client.silva@email.com or create
        $stmtCheckEmail = $pdo->prepare("SELECT id FROM contacts WHERE email = 'client.silva@email.com' LIMIT 1");
        $stmtCheckEmail->execute();
        $cid = $stmtCheckEmail->fetchColumn();
        if ($cid) {
            $pdo->prepare("UPDATE contacts SET linked_user_id = 4, default_project_id = 4 WHERE id = ?")->execute([$cid]);
            echo "✔ Updated contact for Mrs. Silva (user_id 4 -> default_project_id 4).\n";
        } else {
            $pdo->prepare("INSERT INTO contacts (name, email, phone, role_type, linked_user_id, default_project_id, status) 
                           VALUES ('Mrs. Silva', 'client@buildnexus.com', '+94 70 456 7890', 'Client', 4, 4, 'Active')")->execute();
            echo "✔ Linked contact entry created for user 4 (Mrs. Silva) with default_project_id 4.\n";
        }
    }

    echo "\n=== MIGRATION COMPLETE ===\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
