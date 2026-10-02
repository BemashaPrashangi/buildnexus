<?php
require_once 'db.php';

try {
    echo "Starting safety schema migration...\n";

    // 1. Update safety_topics table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS safety_topics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(100) DEFAULT 'General Safety',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Add category column if missing
    $cols = $pdo->query("SHOW COLUMNS FROM safety_topics LIKE 'category'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE safety_topics ADD COLUMN category VARCHAR(100) DEFAULT 'General Safety' AFTER content");
        echo "Added 'category' column to safety_topics.\n";
    }

    // Add created_at column if missing
    $cols = $pdo->query("SHOW COLUMNS FROM safety_topics LIKE 'created_at'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE safety_topics ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added 'created_at' column to safety_topics.\n";
    }

    // 2. Update safety_meeting_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS safety_meeting_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            topic_id INT NULL,
            custom_topic VARCHAR(255) NULL,
            foreman_id INT NOT NULL,
            meeting_date DATE NOT NULL DEFAULT (CURRENT_DATE),
            attendees_count INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            signed_roster_file VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Check columns in safety_meeting_logs
    $cols = $pdo->query("SHOW COLUMNS FROM safety_meeting_logs LIKE 'custom_topic'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE safety_meeting_logs ADD COLUMN custom_topic VARCHAR(255) NULL AFTER topic_id");
        echo "Added 'custom_topic' column to safety_meeting_logs.\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM safety_meeting_logs LIKE 'signed_roster_file'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE safety_meeting_logs ADD COLUMN signed_roster_file VARCHAR(255) NULL AFTER notes");
        echo "Added 'signed_roster_file' column to safety_meeting_logs.\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM safety_meeting_logs LIKE 'created_at'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE safety_meeting_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added 'created_at' column to safety_meeting_logs.\n";
    }

    // 3. Create safety_meeting_attendees table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS safety_meeting_attendees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            worker_name VARCHAR(150) NOT NULL,
            trade_role VARCHAR(100) DEFAULT 'Laborer',
            signature_status ENUM('Signed', 'Present', 'Absent') DEFAULT 'Signed',
            FOREIGN KEY (meeting_id) REFERENCES safety_meeting_logs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created or verified safety_meeting_attendees table.\n";

    // 4. Ensure upload directory exists
    $uploadDir = __DIR__ . '/uploads/safety';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "Created uploads/safety directory.\n";
    }

    // 5. Seed comprehensive safety topics if not already seeded
    $seedTopics = [
        [
            'title' => 'Personal Protective Equipment (PPE)',
            'category' => 'General Safety',
            'content' => "Mandatory PPE on site includes hard hats (ANSI Z89.1 certified), high-visibility reflective vests, steel-toed boots (ASTM F2413), and protective eye goggles. Ensure all equipment is inspected daily for cracks, dents, tears, or degraded straps before entering the active work zones. Replace damaged helmets immediately."
        ],
        [
            'title' => 'Working at Heights',
            'category' => 'Fall Protection',
            'content' => "Fall protection is required for work elevated at 6 feet or higher. Inspect harnesses, lanyards, and anchor points daily. Double-check scaffolding cross-braces, guardrails, toe boards, and outriggers. Never climb scaffolding ladders while carrying heavy tools; use mechanical hoists or tool belts. Keep 100% tie-off compliance at all edge perimeters."
        ],
        [
            'title' => 'Electrical Safety',
            'category' => 'Hazard Prevention',
            'content' => "Inspect all extension cords and power tool casings for exposed copper, cuts, or damaged insulation. Ground Fault Circuit Interrupters (GFCI) must be utilized on all temporary site distribution panels and power drops. Maintain a minimum safe clearance of 10 feet from overhead power lines. Lockout/Tagout (LOTO) protocols strictly apply to all live distribution boxes."
        ],
        [
            'title' => 'Trenching and Shoring Safety',
            'category' => 'Excavation Safety',
            'content' => "All excavations 5 feet or deeper require engineered protective systems: sloping, benching, shielding (trench boxes), or shoring. Keep excavated spoil piles and heavy machinery at least 2 feet away from the trench crest. Safe egress ladders must be spaced within 25 feet of any worker inside the trench. A designated Competent Person must conduct atmospheric and wall stability tests daily before entry."
        ],
        [
            'title' => 'Scaffolding Erection & Inspection',
            'category' => 'Fall Protection',
            'content' => "Scaffolds must be erected on sound footings with mudsills and screw jacks. Platforms must be fully planked with no gaps exceeding 1 inch. Green inspection tags must be signed by the scaffold competent person daily. Red tags indicate out-of-service scaffolding. Never exceed manufacturer maximum rated load capacity."
        ],
        [
            'title' => 'Hot Work & Fire Prevention',
            'category' => 'Fire Safety',
            'content' => "Hot work permits are required prior to any welding, cutting, grinding, or open-flame operations. Combustible materials within 35 feet must be cleared or shielded with fire-retardant blankets. Maintain a dedicated Fire Watch with a charged, inspected multi-purpose ABC extinguisher during hot work and for at least 30 minutes following completion."
        ],
        [
            'title' => 'Crane, Rigging & Heavy Equipment',
            'category' => 'Equipment Safety',
            'content' => "Never stand or walk under suspended loads. Riggers must verify sling capacity, shackle pins, and hook safety latches before hoisting. Crane operators and signal persons must confirm standard hand or radio signals. Maintain barricades around the crane swing radius to prevent pinch-point and crush hazards."
        ],
        [
            'title' => 'Heat Stress & Hydration Protocols',
            'category' => 'Occupational Health',
            'content' => "High ambient heat and humidity pose severe risks of heat exhaustion and heat stroke. Workers must consume at least 1 cup of cool water or electrolyte drink every 15 to 20 minutes. Utilize shaded rest areas during scheduled breaks. Immediately report symptoms including dizziness, nausea, confusion, or lack of sweating to site first aiders."
        ]
    ];

    foreach ($seedTopics as $st) {
        $check = $pdo->prepare("SELECT id FROM safety_topics WHERE title = ?");
        $check->execute([$st['title']]);
        $existing = $check->fetch();
        if (!$existing) {
            $ins = $pdo->prepare("INSERT INTO safety_topics (title, category, content) VALUES (?, ?, ?)");
            $ins->execute([$st['title'], $st['category'], $st['content']]);
            echo "Seeded topic: {$st['title']}\n";
        } else {
            // Update category and content
            $upd = $pdo->prepare("UPDATE safety_topics SET category = ?, content = ? WHERE id = ?");
            $upd->execute([$st['category'], $st['content'], $existing['id']]);
        }
    }

    // 6. Ensure the 3 screenshot meetings exist in safety_meeting_logs with attendees
    // Meeting 1:
    // 2024-10-28 | Luxury Villa in Kandy (id: 4) | Trenching and Shoring Safety (topic) | Sunil Perera (id: 12) | 12 attendees
    // Meeting 2:
    // 2024-10-27 | Colombo Office Complex (id: 29) | Electrical Safety (topic) | Kamal Dias (id: 13) | 15 attendees
    // Meeting 3:
    // 2024-10-26 | Galle Boutique Hotel (id: 30) | Working at Heights (topic) | Ravi Fernando (id: 14) | 8 attendees

    $sampleMeetings = [
        [
            'date' => '2024-10-28',
            'project_name' => 'Luxury Villa in Kandy',
            'topic_title' => 'Trenching and Shoring Safety',
            'lead_name' => 'Sunil Perera',
            'attendees_count' => 12,
            'notes' => 'Discussed shoring box placement for hillside retaining wall excavation. Verified soil classification Type B. Spoil piles relocated 3 feet back from excavation edge. Ladder egress positioned at north and south ends.',
            'attendees' => [
                ['worker_name' => 'Nimal Jayawardena', 'trade_role' => 'Excavator Operator', 'signature_status' => 'Signed'],
                ['worker_name' => 'Ruwan Bandara', 'trade_role' => 'Shoring Carpenter', 'signature_status' => 'Signed'],
                ['worker_name' => 'Kasun Dissanayake', 'trade_role' => 'Laborer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Chaminda Silva', 'trade_role' => 'Laborer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Anura Senanayake', 'trade_role' => 'Pipelayer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Suresh Kumar', 'trade_role' => 'Mason', 'signature_status' => 'Signed'],
                ['worker_name' => 'Mohamed Rizwan', 'trade_role' => 'Laborer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Pradeep Kumara', 'trade_role' => 'Steel Fixer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Dinesh Gunasekara', 'trade_role' => 'Site Assistant', 'signature_status' => 'Signed'],
                ['worker_name' => 'Janaka Wickramasinghe', 'trade_role' => 'Carpenter', 'signature_status' => 'Signed'],
                ['worker_name' => 'Tharaka Mendis', 'trade_role' => 'Laborer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Lalith Gamage', 'trade_role' => 'Surveyor Aide', 'signature_status' => 'Signed']
            ]
        ],
        [
            'date' => '2024-10-27',
            'project_name' => 'Colombo Office Complex',
            'topic_title' => 'Electrical Safety',
            'lead_name' => 'Kamal Dias',
            'attendees_count' => 15,
            'notes' => 'Reviewed temporary switchboard grounding and GFCI daily testing. Tagged out two frayed 3-phase extension cables. Re-emphasized lockout/tagout (LOTO) protocols for 4th floor riser panels.',
            'attendees' => [
                ['worker_name' => 'Sarath Kumara', 'trade_role' => 'Lead Electrician', 'signature_status' => 'Signed'],
                ['worker_name' => 'Ajith Premasiri', 'trade_role' => 'Electrician', 'signature_status' => 'Signed'],
                ['worker_name' => 'Gayan Madushanka', 'trade_role' => 'Electrician', 'signature_status' => 'Signed'],
                ['worker_name' => 'Mahesh Weerasinghe', 'trade_role' => 'Apprentice Electrician', 'signature_status' => 'Signed'],
                ['worker_name' => 'Nuwan Fernando', 'trade_role' => 'HVAC Tech', 'signature_status' => 'Signed'],
                ['worker_name' => 'Asanka Jayasinghe', 'trade_role' => 'Plumber', 'signature_status' => 'Signed'],
                ['worker_name' => 'Buddhika Rathnayake', 'trade_role' => 'Drywaller', 'signature_status' => 'Signed'],
                ['worker_name' => 'Manjula Wijesinghe', 'trade_role' => 'Drywaller', 'signature_status' => 'Signed'],
                ['worker_name' => 'Kelum Pathirana', 'trade_role' => 'Painter', 'signature_status' => 'Signed'],
                ['worker_name' => 'Chandana Perera', 'trade_role' => 'Laborer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Roshan Abeysekara', 'trade_role' => 'Laborer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Dhammika Alwis', 'trade_role' => 'Scaffolder', 'signature_status' => 'Signed'],
                ['worker_name' => 'Vipula Saman', 'trade_role' => 'Safety Assistant', 'signature_status' => 'Signed'],
                ['worker_name' => 'Indika Ranasinghe', 'trade_role' => 'Welder', 'signature_status' => 'Signed'],
                ['worker_name' => 'Saman Jayasuriya', 'trade_role' => 'Foreman Assistant', 'signature_status' => 'Signed']
            ]
        ],
        [
            'date' => '2024-10-26',
            'project_name' => 'Galle Boutique Hotel',
            'topic_title' => 'Working at Heights',
            'lead_name' => 'Ravi Fernando',
            'attendees_count' => 8,
            'notes' => 'Inspected exterior bamboo and steel tubular scaffolding. Fall arrest harnesses checked for ANSI compliance and date tags. Confirmed lifelines secured to certified structural anchor points on the roof deck.',
            'attendees' => [
                ['worker_name' => 'Kusal Mendis', 'trade_role' => 'Scaffolder Lead', 'signature_status' => 'Signed'],
                ['worker_name' => 'Amila Pushpakumara', 'trade_role' => 'Roofer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Sunil Ranatunga', 'trade_role' => 'Carpenter', 'signature_status' => 'Signed'],
                ['worker_name' => 'Lasantha De Silva', 'trade_role' => 'Glazier', 'signature_status' => 'Signed'],
                ['worker_name' => 'Jagath Kumara', 'trade_role' => 'Mason', 'signature_status' => 'Signed'],
                ['worker_name' => 'Sanjaya Liyanage', 'trade_role' => 'Laborer', 'signature_status' => 'Signed'],
                ['worker_name' => 'Priyashantha Silva', 'trade_role' => 'Painter', 'signature_status' => 'Signed'],
                ['worker_name' => 'Nalin Wickramasinghe', 'trade_role' => 'Laborer', 'signature_status' => 'Signed']
            ]
        ]
    ];

    foreach ($sampleMeetings as $sm) {
        // Resolve project_id
        $pStmt = $pdo->prepare("SELECT id FROM projects WHERE project_name = ? LIMIT 1");
        $pStmt->execute([$sm['project_name']]);
        $proj = $pStmt->fetch();
        $projectId = $proj ? $proj['id'] : 1;

        // Resolve topic_id
        $tStmt = $pdo->prepare("SELECT id FROM safety_topics WHERE title = ? LIMIT 1");
        $tStmt->execute([$sm['topic_title']]);
        $top = $tStmt->fetch();
        $topicId = $top ? $top['id'] : null;

        // Resolve foreman_id
        $uStmt = $pdo->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
        $uStmt->execute([$sm['lead_name']]);
        $usr = $uStmt->fetch();
        $foremanId = $usr ? $usr['id'] : 1;

        // Check if meeting already exists
        $mCheck = $pdo->prepare("SELECT id FROM safety_meeting_logs WHERE project_id = ? AND meeting_date = ? LIMIT 1");
        $mCheck->execute([$projectId, $sm['date']]);
        $existingM = $mCheck->fetch();

        if (!$existingM) {
            $insM = $pdo->prepare("
                INSERT INTO safety_meeting_logs (project_id, topic_id, foreman_id, meeting_date, attendees_count, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insM->execute([$projectId, $topicId, $foremanId, $sm['date'], $sm['attendees_count'], $sm['notes']]);
            $meetingId = $pdo->lastInsertId();
            echo "Inserted safety meeting: {$sm['project_name']} ({$sm['date']}) with ID {$meetingId}\n";
        } else {
            $meetingId = $existingM['id'];
            $updM = $pdo->prepare("
                UPDATE safety_meeting_logs 
                SET topic_id = ?, foreman_id = ?, attendees_count = ?, notes = ? 
                WHERE id = ?
            ");
            $updM->execute([$topicId, $foremanId, $sm['attendees_count'], $sm['notes'], $meetingId]);
            echo "Updated safety meeting ID {$meetingId}\n";
        }

        // Insert attendees
        foreach ($sm['attendees'] as $att) {
            $attCheck = $pdo->prepare("SELECT id FROM safety_meeting_attendees WHERE meeting_id = ? AND worker_name = ?");
            $attCheck->execute([$meetingId, $att['worker_name']]);
            if (!$attCheck->fetch()) {
                $insAtt = $pdo->prepare("INSERT INTO safety_meeting_attendees (meeting_id, worker_name, trade_role, signature_status) VALUES (?, ?, ?, ?)");
                $insAtt->execute([$meetingId, $att['worker_name'], $att['trade_role'], $att['signature_status']]);
            }
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
}
