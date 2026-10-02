<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../db.php';

// Strict RBAC: Client, Admin, or Project Manager
checkRole(['Client', 'Admin', 'Project Manager']);

header('Content-Type: application/json');

function getInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach ($parts as $p) {
        if (!empty($p)) {
            $initials .= strtoupper($p[0]);
        }
    }
    return substr($initials, 0, 2) ?: 'U';
}

function getRelativeTime($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400 * 30) {
        $days = round($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400 * 365) {
        $months = round($diff / (86400 * 30));
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = round($diff / (86400 * 365));
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

try {
    $user_id = (int)$_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $note_text = trim($_POST['note_text'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $is_internal_only = ($user_role !== 'Client' && !empty($_POST['is_internal_only'])) ? 1 : 0;

    if (!$project_id || empty($note_text)) {
        echo json_encode([
            'success' => false,
            'error' => 'Project ID and note text are required.'
        ]);
        exit();
    }

    // Security validation: verify user belongs to this project if Client
    if ($user_role === 'Client') {
        $stmt_check = $pdo->prepare("
            SELECT 1 FROM clients WHERE (id = ? OR email = (SELECT email FROM users WHERE id = ?)) AND project_id = ?
            UNION
            SELECT 1 FROM projects WHERE client_id = ? AND id = ?
        ");
        $stmt_check->execute([$user_id, $user_id, $project_id, $user_id, $project_id]);
        if (!$stmt_check->fetch()) {
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized: You do not have access to post notes on this project.'
            ]);
            exit();
        }
    }

    // Insert Note into MySQL
    $stmt = $pdo->prepare("
        INSERT INTO project_notes (project_id, user_id, parent_id, note_text, is_internal_only, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$project_id, $user_id, $parent_id, $note_text, $is_internal_only]);
    $new_note_id = $pdo->lastInsertId();

    // Fetch author details
    $stmt_u = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt_u->execute([$user_id]);
    $author_name = $stmt_u->fetchColumn() ?: 'User';
    $initials = getInitials($author_name);

    // If author is Client, dispatch in-app notification to assigned Project Manager
    if ($user_role === 'Client') {
        $stmt_pm = $pdo->prepare("SELECT pm_id, project_name FROM projects WHERE id = ?");
        $stmt_pm->execute([$project_id]);
        $prj = $stmt_pm->fetch(PDO::FETCH_ASSOC);

        if ($prj && !empty($prj['pm_id'])) {
            $notif_title = $parent_id ? "New Reply on " . $prj['project_name'] : "New Project Note on " . $prj['project_name'];
            $snippet = mb_strlen($note_text) > 80 ? mb_substr($note_text, 0, 77) . '...' : $note_text;
            $notif_msg = "$author_name: \"$snippet\"";

            $stmt_notif = $pdo->prepare("
                INSERT INTO notifications (user_id, project_id, type, title, message)
                VALUES (?, ?, 'note', ?, ?)
            ");
            $stmt_notif->execute([$prj['pm_id'], $project_id, $notif_title, $notif_msg]);
        }
    }

    // Build rendered HTML for dynamic DOM insertion
    $escaped_name = htmlspecialchars($author_name, ENT_QUOTES, 'UTF-8');
    $escaped_text = nl2br(htmlspecialchars($note_text, ENT_QUOTES, 'UTF-8'));
    $time_ago = 'Just now';

    if ($parent_id) {
        // Child Reply HTML
        $html = '
        <div class="reply-entry mb-2" id="note-' . $new_note_id . '">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle me-3">' . $initials . '</div>
                    <div><span class="fw-bold d-block text-dark">' . $escaped_name . '</span></div>
                </div>
                <span class="timestamp">' . $time_ago . '</span>
            </div>
            <p class="small text-muted ps-5 mb-0">' . $escaped_text . '</p>
        </div>';
    } else {
        // Root Note Card HTML
        $html = '
        <div class="nexus-card" id="note-' . $new_note_id . '">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle me-3">' . $initials . '</div>
                    <div><span class="fw-bold d-block text-dark">' . $escaped_name . '</span></div>
                </div>
                <span class="timestamp">' . $time_ago . '</span>
            </div>
            <p class="small text-muted ps-5 mb-2">' . $escaped_text . '</p>
            
            <div class="ps-5 mb-2">
                <button type="button" class="btn btn-link btn-reply-toggle p-0 text-decoration-none small" onclick="toggleReplyBox(' . $new_note_id . ')">
                    <i class="bi bi-reply-fill me-1"></i>Reply
                </button>
            </div>

            <!-- Inline Reply Box -->
            <div class="reply-box-container ps-5 mt-2 d-none" id="reply-box-' . $new_note_id . '">
                <div class="p-3 bg-light rounded-3 border">
                    <textarea class="form-control mb-2 reply-textarea" rows="2" placeholder="Write a reply..." id="reply-text-' . $new_note_id . '"></textarea>
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleReplyBox(' . $new_note_id . ')">Cancel</button>
                        <button type="button" class="btn btn-sm btn-add-note" onclick="submitReply(' . $new_note_id . ')">Send Reply</button>
                    </div>
                </div>
            </div>

            <!-- Thread replies container -->
            <div class="thread-line mt-3" id="replies-for-' . $new_note_id . '"></div>
        </div>';
    }

    echo json_encode([
        'success' => true,
        'note_id' => $new_note_id,
        'parent_id' => $parent_id,
        'author_name' => $author_name,
        'initials' => $initials,
        'time_ago' => $time_ago,
        'html' => $html,
        'message' => $parent_id ? 'Reply posted successfully!' : 'Note posted successfully!'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
