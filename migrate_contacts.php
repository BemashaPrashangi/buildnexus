<?php
require_once 'db.php';

try {
    echo "Starting contacts & directory schema migration...\n";

    // 1. Create table contacts
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NULL,
            company_name VARCHAR(150) NULL,
            role_type ENUM('Project Manager', 'Foreman', 'Vendor', 'Client', 'Architect', 'Subcontractor', 'Engineer', 'Inspector') NOT NULL,
            linked_user_id INT NULL,
            default_project_id INT NULL,
            address TEXT NULL,
            status ENUM('Active', 'Archived') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (linked_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (default_project_id) REFERENCES projects(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created or verified table 'contacts'.\n";

    // 2. Create table contact_project_assignments
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_project_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contact_id INT NOT NULL,
            project_id INT NOT NULL,
            assignment_role VARCHAR(100) NULL,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created or verified table 'contact_project_assignments'.\n";

    // 3. Seed initial contacts matching screenshot
    $seedContacts = [
        [
            'name' => 'John Doe',
            'email' => 'john.doe@buildnexus.com',
            'phone' => '+94 77 123 4567',
            'company_name' => 'BuildNexus Corporate',
            'role_type' => 'Project Manager',
            'address' => 'Level 12, World Trade Center, Colombo 01',
            'user_email' => 'pm@buildnexus.com', // Link to PM user if found
            'projects' => ['Skyline Residence', 'Colombo Office Complex'] // Multiple projects!
        ],
        [
            'name' => 'Sunil Perera',
            'email' => 'sunil.p@fieldcrew.com',
            'phone' => '+94 71 987 6543',
            'company_name' => 'Nexus Field Operations',
            'role_type' => 'Foreman',
            'address' => 'Site Office, Peradeniya Road, Kandy',
            'user_email' => 'sunil.perera@buildnexus.com',
            'projects' => ['Luxury Villa in Kandy']
        ],
        [
            'name' => 'Lanka Tiles',
            'email' => 'sales@lankatiles.com',
            'phone' => '+94 11 476 5600',
            'company_name' => 'Lanka Walltiles PLC',
            'role_type' => 'Vendor',
            'address' => '212 Nawala Road, Rajagiriya, Sri Lanka',
            'user_email' => null,
            'projects' => ['Galle Boutique Hotel']
        ],
        [
            'name' => 'Mr. Silva',
            'email' => 'client.silva@email.com',
            'phone' => '+94 77 555 8899',
            'company_name' => 'Silva Holdings Ltd',
            'role_type' => 'Client',
            'address' => '45 Kandy Road, Katugastota',
            'user_email' => null,
            'projects' => ['Luxury Villa in Kandy']
        ],
        [
            'name' => 'K. Weerasinghe',
            'email' => 'k.weera@architects.lk',
            'phone' => '+94 11 258 9632',
            'company_name' => 'Weerasinghe & Associates Architects',
            'role_type' => 'Architect',
            'address' => '14 Alfred House Gardens, Colombo 03',
            'user_email' => null,
            'projects' => ['Colombo Office Complex']
        ],
        [
            'name' => 'Metro Builders',
            'email' => 'contact@metrobuilders.lk',
            'phone' => '+94 11 741 2589',
            'company_name' => 'Metro Builders & Civil Contractors',
            'role_type' => 'Subcontractor',
            'address' => '88 Galle Road, Dehiwala',
            'user_email' => null,
            'projects' => ['Colombo Office Complex']
        ]
    ];

    foreach ($seedContacts as $sc) {
        // Check if contact already exists by email
        $check = $pdo->prepare("SELECT id FROM contacts WHERE email = ?");
        $check->execute([$sc['email']]);
        $existing = $check->fetch();

        // Resolve linked user if any
        $linkedUserId = null;
        if (!empty($sc['user_email'])) {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $uStmt->execute([$sc['user_email']]);
            $uRow = $uStmt->fetch();
            if ($uRow) {
                $linkedUserId = $uRow['id'];
            }
        }

        // Resolve default project
        $defaultProjId = null;
        if (!empty($sc['projects'][0])) {
            $pStmt = $pdo->prepare("SELECT id FROM projects WHERE project_name = ? LIMIT 1");
            $pStmt->execute([$sc['projects'][0]]);
            $pRow = $pStmt->fetch();
            if ($pRow) {
                $defaultProjId = $pRow['id'];
            }
        }

        if (!$existing) {
            $ins = $pdo->prepare("
                INSERT INTO contacts (name, email, phone, company_name, role_type, linked_user_id, default_project_id, address, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $ins->execute([
                $sc['name'],
                $sc['email'],
                $sc['phone'],
                $sc['company_name'],
                $sc['role_type'],
                $linkedUserId,
                $defaultProjId,
                $sc['address']
            ]);
            $contactId = $pdo->lastInsertId();
            echo "Inserted contact: {$sc['name']} (ID {$contactId})\n";
        } else {
            $contactId = $existing['id'];
            $upd = $pdo->prepare("
                UPDATE contacts 
                SET name = ?, phone = ?, company_name = ?, role_type = ?, linked_user_id = ?, default_project_id = ?, address = ?, status = 'Active'
                WHERE id = ?
            ");
            $upd->execute([
                $sc['name'],
                $sc['phone'],
                $sc['company_name'],
                $sc['role_type'],
                $linkedUserId,
                $defaultProjId,
                $sc['address'],
                $contactId
            ]);
            echo "Updated contact: {$sc['name']} (ID {$contactId})\n";
        }

        // Associate projects
        foreach ($sc['projects'] as $projName) {
            $pStmt = $pdo->prepare("SELECT id FROM projects WHERE project_name = ? LIMIT 1");
            $pStmt->execute([$projName]);
            $pRow = $pStmt->fetch();
            if ($pRow) {
                $projId = $pRow['id'];
                // Check assignment
                $aCheck = $pdo->prepare("SELECT id FROM contact_project_assignments WHERE contact_id = ? AND project_id = ?");
                $aCheck->execute([$contactId, $projId]);
                if (!$aCheck->fetch()) {
                    $insAss = $pdo->prepare("INSERT INTO contact_project_assignments (contact_id, project_id, assignment_role) VALUES (?, ?, ?)");
                    $insAss->execute([$contactId, $projId, $sc['role_type']]);
                    echo "  -> Assigned to project: {$projName}\n";
                }
            }
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
}
