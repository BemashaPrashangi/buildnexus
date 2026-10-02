<?php
require_once 'db.php';

try {
    echo "Starting equipment & fleet schema migration...\n";

    // 1. Update equipment table schema
    // First, check/add equipment_code
    $cols = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'equipment_code'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE equipment ADD COLUMN equipment_code VARCHAR(50) NULL AFTER id");
        echo "Added 'equipment_code' column.\n";
    }

    // Modify name length to 150
    $pdo->exec("ALTER TABLE equipment MODIFY COLUMN name VARCHAR(150) NOT NULL");

    // Modify type enum to ('Vehicle', 'Machinery', 'Small Tool')
    $pdo->exec("ALTER TABLE equipment MODIFY COLUMN type ENUM('Vehicle', 'Machinery', 'Small Tool') NOT NULL DEFAULT 'Machinery'");

    // Check/add current_project_id
    $cols = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'current_project_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE equipment ADD COLUMN current_project_id INT NULL AFTER type");
        echo "Added 'current_project_id' column.\n";
    }

    // Modify status enum to ('Available', 'In Use', 'Maintenance', 'Decommissioned')
    $pdo->exec("ALTER TABLE equipment MODIFY COLUMN status ENUM('Available', 'In Use', 'Maintenance', 'Decommissioned') DEFAULT 'Available'");

    // Check/add next_service_date
    $cols = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'next_service_date'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE equipment ADD COLUMN next_service_date DATE NULL AFTER status");
        echo "Added 'next_service_date' column.\n";
    }

    // Check/add hourly_operating_cost
    $cols = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'hourly_operating_cost'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE equipment ADD COLUMN hourly_operating_cost DECIMAL(10,2) DEFAULT 0.00 AFTER next_service_date");
        echo "Added 'hourly_operating_cost' column.\n";
    }

    // Check/add created_at
    $cols = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'created_at'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE equipment ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added 'created_at' column.\n";
    }

    // 2. Update equipment_logs table schema
    // Modify hours_used and fuel_liters
    $pdo->exec("ALTER TABLE equipment_logs MODIFY COLUMN hours_used DECIMAL(6,2) NOT NULL DEFAULT 0.00");
    $pdo->exec("ALTER TABLE equipment_logs MODIFY COLUMN fuel_liters DECIMAL(6,2) DEFAULT 0.00");

    $cols = $pdo->query("SHOW COLUMNS FROM equipment_logs LIKE 'created_at'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE equipment_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added 'created_at' column to equipment_logs.\n";
    }

    // 3. Seed equipment matching screenshot
    $seedMachines = [
        [
            'code' => 'EQ-001',
            'name' => 'Caterpillar Excavator 320',
            'plate_number' => 'WP-EX-3201',
            'type' => 'Machinery',
            'project_name' => 'Highway Expansion E01',
            'status' => 'In Use',
            'next_service' => '2025-03-15',
            'hourly_cost' => 125.00,
            'logs' => [
                ['hours' => 7.5, 'fuel' => 65.0, 'date' => '2024-10-28', 'notes' => 'Earthwork grading and slope cutting on Section B. Engine oil pressure normal.'],
                ['hours' => 6.0, 'fuel' => 50.0, 'date' => '2024-10-27', 'notes' => 'Trench excavation for culvert drains. Replaced bucket tooth pin.'],
                ['hours' => 8.0, 'fuel' => 72.0, 'date' => '2024-10-25', 'notes' => 'Clearing subgrade rock formations. Hydraulic lines inspected.']
            ]
        ],
        [
            'code' => 'VH-012',
            'name' => 'Toyota Hilux',
            'plate_number' => 'CP-CAB-4590',
            'type' => 'Vehicle',
            'project_name' => 'Luxury Villa in Kandy',
            'status' => 'In Use',
            'next_service' => '2025-01-10',
            'hourly_cost' => 45.00,
            'logs' => [
                ['hours' => 3.5, 'fuel' => 25.0, 'date' => '2024-10-28', 'notes' => 'Transporting structural tie rods and site supervisor transit between sites.'],
                ['hours' => 4.0, 'fuel' => 30.0, 'date' => '2024-10-26', 'notes' => 'Delivered electrical distribution boxes and cable conduits from local depot.']
            ]
        ],
        [
            'code' => 'EQ-005',
            'name' => 'Concrete Mixer',
            'plate_number' => 'SER-CM-8820',
            'type' => 'Machinery',
            'project_name' => null,
            'status' => 'Available',
            'next_service' => '2025-02-01',
            'hourly_cost' => 35.00,
            'logs' => [
                ['hours' => 5.0, 'fuel' => 15.0, 'date' => '2024-10-20', 'notes' => 'Batching test pours for retaining wall footings. Drum washed and greased.']
            ]
        ],
        [
            'code' => 'EQ-003',
            'name' => 'JCB Backhoe Loader',
            'plate_number' => 'WP-LB-9904',
            'type' => 'Machinery',
            'project_name' => 'Colombo Office Complex',
            'status' => 'Maintenance',
            'next_service' => '2024-11-05',
            'hourly_cost' => 95.00,
            'logs' => [
                ['hours' => 2.0, 'fuel' => 18.0, 'date' => '2024-10-22', 'notes' => 'Hydraulic boom hose weeping oil. Scheduled for full hydraulic pack rebuild.']
            ]
        ],
        [
            'code' => 'EQ-002',
            'name' => 'Komatsu D65 Bulldozer',
            'plate_number' => 'WP-DZ-1102',
            'type' => 'Machinery',
            'project_name' => 'Highway Expansion E01',
            'status' => 'In Use',
            'next_service' => '2025-04-20',
            'hourly_cost' => 140.00,
            'logs' => []
        ],
        [
            'code' => 'VH-008',
            'name' => 'Isuzu Elf Tipper Truck',
            'plate_number' => 'SP-TR-7721',
            'type' => 'Vehicle',
            'project_name' => 'Galle Boutique Hotel',
            'status' => 'Available',
            'next_service' => '2025-01-25',
            'hourly_cost' => 60.00,
            'logs' => []
        ]
    ];

    foreach ($seedMachines as $sm) {
        // Resolve project_id
        $projId = null;
        if (!empty($sm['project_name'])) {
            $pStmt = $pdo->prepare("SELECT id FROM projects WHERE project_name = ? LIMIT 1");
            $pStmt->execute([$sm['project_name']]);
            $pRow = $pStmt->fetch();
            if ($pRow) {
                $projId = $pRow['id'];
            }
        }

        // Check if machine exists by code
        $mCheck = $pdo->prepare("SELECT id FROM equipment WHERE equipment_code = ?");
        $mCheck->execute([$sm['code']]);
        $existingM = $mCheck->fetch();

        if (!$existingM) {
            $insM = $pdo->prepare("
                INSERT INTO equipment (equipment_code, name, plate_number, type, current_project_id, status, next_service_date, hourly_operating_cost)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insM->execute([
                $sm['code'],
                $sm['name'],
                $sm['plate_number'],
                $sm['type'],
                $projId,
                $sm['status'],
                $sm['next_service'],
                $sm['hourly_cost']
            ]);
            $equipId = $pdo->lastInsertId();
            echo "Inserted machine: {$sm['name']} ({$sm['code']}) with ID {$equipId}\n";
        } else {
            $equipId = $existingM['id'];
            $updM = $pdo->prepare("
                UPDATE equipment 
                SET name = ?, plate_number = ?, type = ?, current_project_id = ?, status = ?, next_service_date = ?, hourly_operating_cost = ?
                WHERE id = ?
            ");
            $updM->execute([
                $sm['name'],
                $sm['plate_number'],
                $sm['type'],
                $projId,
                $sm['status'],
                $sm['next_service'],
                $sm['hourly_cost'],
                $equipId
            ]);
            echo "Updated machine: {$sm['name']} ({$sm['code']}) ID {$equipId}\n";
        }

        // Seed logs if any
        if (!empty($sm['logs'])) {
            foreach ($sm['logs'] as $log) {
                $lCheck = $pdo->prepare("SELECT id FROM equipment_logs WHERE equipment_id = ? AND log_date = ? AND hours_used = ?");
                $lCheck->execute([$equipId, $log['date'], $log['hours']]);
                if (!$lCheck->fetch()) {
                    $insLog = $pdo->prepare("
                        INSERT INTO equipment_logs (equipment_id, project_id, foreman_id, hours_used, fuel_liters, log_date, notes)
                        VALUES (?, ?, 1, ?, ?, ?, ?)
                    ");
                    $insLog->execute([
                        $equipId,
                        $projId ?: 1,
                        $log['hours'],
                        $log['fuel'],
                        $log['date'],
                        $log['notes']
                    ]);
                }
            }
        }
    }

    echo "Equipment migration completed successfully!\n";

} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
}
