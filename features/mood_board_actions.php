<?php
// features/mood_board_actions.php - Actions API for Mood Boards and Canvas Studio
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Client']);

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 1;

try {
    if ($action === 'create_board') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $room_space = trim($_POST['room_space'] ?? 'General');
        $description = trim($_POST['description'] ?? '');
        $cover_image = trim($_POST['cover_image'] ?? '');

        if ($project_id <= 0 || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Project and board title are required.']);
            exit();
        }

        // Handle cover file upload
        if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/moodboards/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                $filename = 'cover_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['cover_file']['tmp_name'], $upload_dir . $filename)) {
                    $cover_image = '../uploads/moodboards/' . $filename;
                }
            }
        }

        if (empty($cover_image)) {
            $cover_image = 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&auto=format&fit=crop';
        }

        $ins = $pdo->prepare("
            INSERT INTO project_mood_boards 
            (project_id, title, room_space, description, status, cover_image, created_by, created_at)
            VALUES (?, ?, ?, ?, 'Draft', ?, ?, NOW())
        ");
        $ins->execute([$project_id, $title, $room_space, $description, $cover_image, $user_id]);
        $board_id = $pdo->lastInsertId();

        // Handle initial uploaded inspiration files if multiple provided
        if (isset($_FILES['inspiration_files']) && is_array($_FILES['inspiration_files']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/moodboards/';
            $item_ins = $pdo->prepare("
                INSERT INTO mood_board_items 
                (mood_board_id, image_url, caption, item_type, pos_x, pos_y, sort_order, created_at)
                VALUES (?, ?, ?, 'Inspiration', ?, ?, ?, NOW())
            ");

            $idx = 0;
            foreach ($_FILES['inspiration_files']['name'] as $i => $orig_name) {
                if ($_FILES['inspiration_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                        $fname = 'pin_' . time() . '_' . $idx . '_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($_FILES['inspiration_files']['tmp_name'][$i], $upload_dir . $fname)) {
                            $img_url = '../uploads/moodboards/' . $fname;
                            $caption = pathinfo($orig_name, PATHINFO_FILENAME);
                            $item_ins->execute([$board_id, $img_url, $caption, ($idx % 3) * 220, floor($idx / 3) * 220, $idx]);
                            $idx++;
                        }
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Mood board '{$title}' created successfully!",
            'board_id' => $board_id,
            'redirect' => 'mood_board_editor.php?id=' . $board_id
        ]);
        exit();

    } elseif ($action === 'add_item') {
        $board_id = intval($_POST['mood_board_id'] ?? 0);
        $caption = trim($_POST['caption'] ?? 'Inspiration Asset');
        $item_type = in_array($_POST['item_type'] ?? '', ['Inspiration', 'Material Sample', 'Color Palette', 'Lighting']) ? $_POST['item_type'] : 'Inspiration';
        $image_url = trim($_POST['image_url'] ?? '');

        if ($board_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid Mood Board ID.']);
            exit();
        }

        // Check file upload
        if (isset($_FILES['item_file']) && $_FILES['item_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/moodboards/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['item_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                $fname = 'pin_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['item_file']['tmp_name'], $upload_dir . $fname)) {
                    $image_url = '../uploads/moodboards/' . $fname;
                }
            }
        }

        if (empty($image_url)) {
            $image_url = 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600&auto=format&fit=crop';
        }

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM mood_board_items WHERE mood_board_id = ?");
        $cntStmt->execute([$board_id]);
        $order = intval($cntStmt->fetchColumn() ?: 0);

        $stmt = $pdo->prepare("
            INSERT INTO mood_board_items 
            (mood_board_id, image_url, caption, item_type, pos_x, pos_y, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$board_id, $image_url, $caption, $item_type, ($order % 3) * 220, floor($order / 3) * 220, $order]);
        $item_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Item pinned to mood board canvas.',
            'item' => [
                'id' => $item_id,
                'image_url' => $image_url,
                'caption' => $caption,
                'item_type' => $item_type
            ]
        ]);
        exit();

    } elseif ($action === 'delete_item') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM mood_board_items WHERE id = ?");
        $del->execute([$item_id]);
        echo json_encode(['success' => true, 'message' => 'Item removed from mood board.']);
        exit();

    } elseif ($action === 'share_client') {
        $board_id = intval($_POST['mood_board_id'] ?? 0);
        $upd = $pdo->prepare("UPDATE project_mood_boards SET status = 'Shared with Client' WHERE id = ?");
        $upd->execute([$board_id]);

        echo json_encode(['success' => true, 'message' => 'Mood board shared with client portal for review!']);
        exit();

    } elseif ($action === 'client_vote') {
        $board_id = intval($_POST['mood_board_id'] ?? 0);
        $vote = in_array($_POST['vote'] ?? '', ['Approved', 'Revisions Requested']) ? $_POST['vote'] : 'Approved';
        $feedback = trim($_POST['feedback'] ?? '');

        $upd = $pdo->prepare("UPDATE project_mood_boards SET status = ?, client_feedback = ? WHERE id = ?");
        $upd->execute([$vote, $feedback, $board_id]);

        echo json_encode([
            'success' => true,
            'message' => ($vote === 'Approved') ? 'Mood board design approved by client!' : 'Revision requests recorded and transmitted to design team.',
            'status' => $vote
        ]);
        exit();

    } elseif ($action === 'convert_to_selection_room') {
        // Bridge Mood Board items directly into features/client_selections.php
        $board_id = intval($_POST['mood_board_id'] ?? 0);
        
        $bStmt = $pdo->prepare("SELECT * FROM project_mood_boards WHERE id = ?");
        $bStmt->execute([$board_id]);
        $board = $bStmt->fetch(PDO::FETCH_ASSOC);

        if (!$board) {
            echo json_encode(['success' => false, 'message' => 'Mood board not found.']);
            exit();
        }

        $project_id = $board['project_id'];
        $room_name = !empty($board['room_space']) ? $board['room_space'] : $board['title'];

        // 1. Locate or create selection room in project_selection_rooms
        $rStmt = $pdo->prepare("SELECT id FROM project_selection_rooms WHERE project_id = ? AND room_name = ? LIMIT 1");
        $rStmt->execute([$project_id, $room_name]);
        $room_id = $rStmt->fetchColumn();

        if (!$room_id) {
            $insRoom = $pdo->prepare("
                INSERT INTO project_selection_rooms (project_id, room_name, budget_allowance, created_at)
                VALUES (?, ?, 250000.00, NOW())
            ");
            $insRoom->execute([$project_id, $room_name]);
            $room_id = $pdo->lastInsertId();
        }

        // 2. Fetch mood board items
        $iStmt = $pdo->prepare("SELECT * FROM mood_board_items WHERE mood_board_id = ?");
        $iStmt->execute([$board_id]);
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        $imported = 0;
        $insSel = $pdo->prepare("
            INSERT INTO project_selections 
            (room_id, project_id, item_name, category, photo_url, unit_price, quantity, include_in_budget, approval_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1.00, 1, 'Pending', NOW())
        ");

        foreach ($items as $it) {
            // Map item_type to selection category
            $cat = ($it['item_type'] === 'Material Sample') ? 'Flooring' : (($it['item_type'] === 'Lighting') ? 'Lighting' : 'Fixtures');
            $price = 3500.00; // Standard finish baseline allowance
            $item_name = !empty($it['caption']) ? $it['caption'] : 'Inspiration Finish Item';

            $insSel->execute([$room_id, $project_id, $item_name, $cat, $it['image_url'], $price]);
            $imported++;
        }

        echo json_encode([
            'success' => true,
            'message' => "Successfully created/updated selection room '{$room_name}' with {$imported} finish items!",
            'redirect' => "client_selections.php?project_id={$project_id}&room_id={$room_id}"
        ]);
        exit();

    } elseif ($action === 'delete_board') {
        $board_id = intval($_POST['mood_board_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM project_mood_boards WHERE id = ?");
        $del->execute([$board_id]);

        echo json_encode(['success' => true, 'message' => 'Mood board deleted successfully.']);
        exit();

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
        exit();
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
