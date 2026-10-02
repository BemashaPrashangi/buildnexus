<?php
// features/plan_actions.php - Floor Plans & Spatial Pins AJAX Action Handler
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager', 'Foreman']);

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 1;

try {
    if ($action === 'add_pin') {
        $plan_id = intval($_POST['floor_plan_id'] ?? 0);
        $pin_type = in_array($_POST['pin_type'] ?? '', ['Punchlist', 'RFI', 'Inspection', 'Note']) ? $_POST['pin_type'] : 'Note';
        $x_percent = floatval($_POST['x_percent'] ?? 0);
        $y_percent = floatval($_POST['y_percent'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $ref_id = !empty($_POST['reference_id']) ? intval($_POST['reference_id']) : null;

        if ($plan_id <= 0 || empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid plan ID and markup comment.']);
            exit();
        }

        // Bound check percentage coordinates
        $x_percent = max(0.5, min(99.5, $x_percent));
        $y_percent = max(0.5, min(99.5, $y_percent));

        $stmt = $pdo->prepare("
            INSERT INTO floor_plan_pins (floor_plan_id, pin_type, reference_id, x_percent, y_percent, comment, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$plan_id, $pin_type, $ref_id, $x_percent, $y_percent, $comment, $user_id]);
        $pin_id = $pdo->lastInsertId();

        // Fetch author name
        $uStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $uStmt->execute([$user_id]);
        $author_name = $uStmt->fetchColumn() ?: 'Staff Member';

        echo json_encode([
            'success' => true,
            'message' => 'Spatial pin marked successfully on drawing.',
            'pin' => [
                'id' => $pin_id,
                'pin_type' => $pin_type,
                'reference_id' => $ref_id,
                'x_percent' => $x_percent,
                'y_percent' => $y_percent,
                'comment' => $comment,
                'author' => $author_name,
                'date' => date('M d, Y')
            ]
        ]);
        exit();

    } elseif ($action === 'delete_pin') {
        $pin_id = intval($_POST['pin_id'] ?? 0);
        if ($pin_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid Pin ID.']);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM floor_plan_pins WHERE id = ?");
        $stmt->execute([$pin_id]);

        echo json_encode(['success' => true, 'message' => 'Markup pin removed.']);
        exit();

    } elseif ($action === 'upload_revision') {
        $parent_plan_id = intval($_POST['parent_plan_id'] ?? 0);
        $version_tag = trim($_POST['version_tag'] ?? 'Rev 2.0');
        $status = $_POST['status'] ?? 'Approved for Construction';

        // Fetch parent plan
        $pStmt = $pdo->prepare("SELECT * FROM project_floor_plans WHERE id = ?");
        $pStmt->execute([$parent_plan_id]);
        $parent = $pStmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            echo json_encode(['success' => false, 'message' => 'Parent blueprint drawing not found.']);
            exit();
        }

        $file_url = '';
        if (isset($_FILES['revision_file']) && $_FILES['revision_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/blueprints/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['revision_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid file format. Please upload PDF, PNG, or JPG.']);
                exit();
            }

            $clean_name = time() . '_rev_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['revision_file']['name']);
            if (move_uploaded_file($_FILES['revision_file']['tmp_name'], $upload_dir . $clean_name)) {
                $file_url = '../uploads/blueprints/' . $clean_name;
            }
        } elseif (!empty($_POST['file_url'])) {
            $file_url = trim($_POST['file_url']);
        } else {
            $file_url = $parent['file_url']; // Inherit if mock
        }

        $pdo->beginTransaction();

        // 1. Mark previous version as 'Superseded'
        $updPrev = $pdo->prepare("UPDATE project_floor_plans SET status = 'Superseded' WHERE id = ?");
        $updPrev->execute([$parent_plan_id]);

        // 2. Insert new revision
        $insNew = $pdo->prepare("
            INSERT INTO project_floor_plans 
            (project_id, plan_code, title, discipline, file_url, version_tag, status, uploaded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $insNew->execute([
            $parent['project_id'],
            $parent['plan_code'],
            $parent['title'],
            $parent['discipline'],
            $file_url,
            $version_tag,
            $status,
            $user_id
        ]);
        $new_id = $pdo->lastInsertId();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Revision {$version_tag} published successfully!",
            'new_plan_id' => $new_id
        ]);
        exit();

    } elseif ($action === 'delete_plan') {
        $plan_id = intval($_POST['plan_id'] ?? 0);
        if ($plan_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid Plan ID.']);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM project_floor_plans WHERE id = ?");
        $stmt->execute([$plan_id]);

        echo json_encode(['success' => true, 'message' => 'Drawing deleted successfully.']);
        exit();

    } elseif ($action === 'change_status') {
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        if (!in_array($new_status, ['Approved for Construction', 'Under Review', 'Superseded'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE project_floor_plans SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $plan_id]);

        echo json_encode(['success' => true, 'message' => "Status updated to '{$new_status}'."]);
        exit();

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action requested.']);
        exit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
