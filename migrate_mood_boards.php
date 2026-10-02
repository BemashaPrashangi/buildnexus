<?php
// migrate_mood_boards.php
require_once __DIR__ . '/db.php';

echo "=== MIGRATING MOOD BOARDS & CONCEPT STUDIO SCHEMA ===\n\n";

try {
    // 1. Table project_mood_boards
    $sql_boards = "
    CREATE TABLE IF NOT EXISTS `project_mood_boards` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `title` VARCHAR(150) NOT NULL,
        `room_space` VARCHAR(100) DEFAULT 'General',
        `description` TEXT NULL,
        `status` ENUM('Draft', 'Shared with Client', 'Approved', 'Revisions Requested') DEFAULT 'Draft',
        `client_feedback` TEXT NULL,
        `cover_image` VARCHAR(255) NULL,
        `created_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`project_id`),
        INDEX (`room_space`),
        INDEX (`status`),
        CONSTRAINT `fk_pmb_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_pmb_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_boards);
    echo "✔ Table `project_mood_boards` created or verified.\n";

    // 2. Table mood_board_items
    $sql_items = "
    CREATE TABLE IF NOT EXISTS `mood_board_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `mood_board_id` INT NOT NULL,
        `image_url` VARCHAR(255) NOT NULL,
        `caption` VARCHAR(255) NULL,
        `item_type` ENUM('Inspiration', 'Material Sample', 'Color Palette', 'Lighting') DEFAULT 'Inspiration',
        `pos_x` INT DEFAULT 0,
        `pos_y` INT DEFAULT 0,
        `sort_order` INT DEFAULT 0,
        `linked_selection_id` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`mood_board_id`),
        INDEX (`item_type`),
        CONSTRAINT `fk_mbi_board` FOREIGN KEY (`mood_board_id`) REFERENCES `project_mood_boards`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_mbi_selection` FOREIGN KEY (`linked_selection_id`) REFERENCES `project_selections`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_items);
    echo "✔ Table `mood_board_items` created or verified.\n";

    // 3. Ensure uploads/moodboards directory exists
    $upload_dir = __DIR__ . '/uploads/moodboards/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
        echo "✔ Created directory `uploads/moodboards/`.\n";
    }

    // 4. Seed realistic mood boards and inspiration assets if empty
    $count = $pdo->query("SELECT COUNT(*) FROM `project_mood_boards`")->fetchColumn();
    if ($count == 0) {
        $p_stmt = $pdo->query("SELECT id FROM projects ORDER BY id ASC LIMIT 2");
        $project_ids = $p_stmt->fetchAll(PDO::FETCH_COLUMN);
        $p1 = $project_ids[0] ?? 1;
        $p2 = $project_ids[1] ?? $p1;
        $user_id = $pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn() ?: 1;

        $boards = [
            [
                'project_id' => $p1,
                'title' => 'Modern Japandi Master Bath',
                'room_space' => 'Master Bath',
                'description' => 'Earthy minimalist aesthetic combining organic fluted oak millwork, warm travertine tiles, matte black plumbing accents, and soft recessed cove lighting.',
                'status' => 'Approved',
                'cover_image' => 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=800&auto=format&fit=crop',
                'items' => [
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600&auto=format&fit=crop',
                        'caption' => 'Freestanding Stone Composite Soaking Tub',
                        'item_type' => 'Inspiration'
                    ],
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=600&auto=format&fit=crop',
                        'caption' => 'Fluted White Oak Double Vanity',
                        'item_type' => 'Material Sample'
                    ],
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1584622781564-1d987f7333c1?w=600&auto=format&fit=crop',
                        'caption' => 'Wall-Mount Matte Black Fixture Trim',
                        'item_type' => 'Material Sample'
                    ],
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=600&auto=format&fit=crop',
                        'caption' => 'Warm Sand & Beige Color Swatches (#E8DFD8)',
                        'item_type' => 'Color Palette'
                    ]
                ]
            ],
            [
                'project_id' => $p1,
                'title' => 'Executive Chef Kitchen & Butler Pantry',
                'room_space' => 'Kitchen',
                'description' => 'Dramatic high-contrast culinary space featuring waterfall Calacatta marble island, fluted glass upper cabinetry, and integrated brushed brass task pendants.',
                'status' => 'Shared with Client',
                'cover_image' => 'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=800&auto=format&fit=crop',
                'items' => [
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=600&auto=format&fit=crop',
                        'caption' => 'Calacatta Gold Waterfall Island Countertop',
                        'item_type' => 'Inspiration'
                    ],
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=600&auto=format&fit=crop',
                        'caption' => 'Brushed Brass Linear Pendant Luminaire',
                        'item_type' => 'Lighting'
                    ],
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?w=600&auto=format&fit=crop',
                        'caption' => 'Smoked Oak Base Cabinetry Finish',
                        'item_type' => 'Material Sample'
                    ]
                ]
            ],
            [
                'project_id' => $p2,
                'title' => 'Tropical Modern Living & Terrace Transition',
                'room_space' => 'Living Room',
                'description' => 'Biophilic indoor-outdoor entertaining lounge with polished concrete floors, louvered teak screens, and oversized modular linen sectionals.',
                'status' => 'Draft',
                'cover_image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&auto=format&fit=crop',
                'items' => [
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600&auto=format&fit=crop',
                        'caption' => 'Polished Concrete Slab with Teak Louvers',
                        'item_type' => 'Inspiration'
                    ],
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=600&auto=format&fit=crop',
                        'caption' => 'Earthy Forest & Clay Palette (#2C3E2D, #C47A53)',
                        'item_type' => 'Color Palette'
                    ]
                ]
            ],
            [
                'project_id' => $p1,
                'title' => 'Exterior Courtyard & Water Feature Concept',
                'room_space' => 'Exterior Facade',
                'description' => 'Cantilevered exposed aggregate pergolas framing a tranquil basalt reflecting pond with landscape wash downlights.',
                'status' => 'Revisions Requested',
                'cover_image' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=800&auto=format&fit=crop',
                'items' => [
                    [
                        'image_url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=600&auto=format&fit=crop',
                        'caption' => 'Basalt Stone Pavers & Reflecting Pond',
                        'item_type' => 'Inspiration'
                    ]
                ]
            ]
        ];

        $ins_board = $pdo->prepare("
            INSERT INTO `project_mood_boards` 
            (`project_id`, `title`, `room_space`, `description`, `status`, `cover_image`, `created_by`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $ins_item = $pdo->prepare("
            INSERT INTO `mood_board_items` 
            (`mood_board_id`, `image_url`, `caption`, `item_type`, `pos_x`, `pos_y`, `sort_order`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($boards as $b) {
            $ins_board->execute([
                $b['project_id'],
                $b['title'],
                $b['room_space'],
                $b['description'],
                $b['status'],
                $b['cover_image'],
                $user_id
            ]);
            $board_id = $pdo->lastInsertId();

            $idx = 0;
            foreach ($b['items'] as $it) {
                $ins_item->execute([
                    $board_id,
                    $it['image_url'],
                    $it['caption'],
                    $it['item_type'],
                    ($idx % 3) * 220,
                    floor($idx / 3) * 220,
                    $idx
                ]);
                $idx++;
            }
        }
        echo "✔ Seeded 4 mood boards with inspiration items.\n";
    } else {
        echo "ℹ `project_mood_boards` already contains {$count} records.\n";
    }

    echo "=== MIGRATION COMPLETE ===\n";

} catch (Exception $e) {
    echo "❌ Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}
