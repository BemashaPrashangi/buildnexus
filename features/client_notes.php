<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../db.php';

// RBAC: Client, Admin, or Project Manager
checkRole(['Client', 'Admin', 'Project Manager']);

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Determine active project
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$client_projects = [];

try {
    if ($user_role === 'Client') {
        // Fetch projects accessible to this client
        $stmt_prj = $pdo->prepare("
            SELECT p.id, p.project_name 
            FROM projects p
            WHERE p.client_id = ? 
               OR p.id IN (SELECT project_id FROM clients WHERE id = ? OR email = (SELECT email FROM users WHERE id = ?))
            ORDER BY p.id DESC
        ");
        $stmt_prj->execute([$user_id, $user_id, $user_id]);
        $client_projects = $stmt_prj->fetchAll(PDO::FETCH_ASSOC);

        if (!$project_id && !empty($client_projects)) {
            $project_id = (int)$client_projects[0]['id'];
        }
    } else {
        // PM or Admin: fetch all active projects
        $client_projects = $pdo->query("SELECT id, project_name FROM projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        if (!$project_id && !empty($client_projects)) {
            $project_id = (int)$client_projects[0]['id'];
        }
    }

    if (!$project_id) {
        $project_id = 4; // Fallback to baseline project
    }

    // Helper functions
    function getInitials($name) {
        $parts = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ($parts as $p) {
            if (!empty($p)) $initials .= strtoupper($p[0]);
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

    // Query Root Notes (parent_id IS NULL)
    // Enforce Client Security Isolation (is_internal_only = 0)
    $client_filter = ($user_role === 'Client') ? "AND n.is_internal_only = 0" : "";
    $stmt_notes = $pdo->prepare("
        SELECT n.*, u.full_name as author_name, u.role as author_role
        FROM project_notes n
        JOIN users u ON n.user_id = u.id
        WHERE n.project_id = ? AND n.parent_id IS NULL $client_filter
        ORDER BY n.created_at DESC
    ");
    $stmt_notes->execute([$project_id]);
    $root_notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

    // Query Child Replies for these root notes
    $replies_by_parent = [];
    if (!empty($root_notes)) {
        $stmt_replies = $pdo->prepare("
            SELECT n.*, u.full_name as author_name, u.role as author_role
            FROM project_notes n
            JOIN users u ON n.user_id = u.id
            WHERE n.project_id = ? AND n.parent_id IS NOT NULL $client_filter
            ORDER BY n.created_at ASC
        ");
        $stmt_replies->execute([$project_id]);
        $all_replies = $stmt_replies->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_replies as $r) {
            $replies_by_parent[$r['parent_id']][] = $r;
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- BuildNexus Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/buildnexus/images/logo.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/buildnexus/images/logo.png?v=2">
    <link rel="shortcut icon" href="/buildnexus/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="/buildnexus/images/logo.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Notes - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- PURE CUSTOM CSS (Zero Tailwind) --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 2.5rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-title {
            color: #0f172a;
            font-weight: 700;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }

        .nexus-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: border-color 0.15s ease-in-out;
        }

        .note-input {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.95rem;
            min-height: 120px;
            resize: vertical;
            color: #334155;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .note-input:focus {
            border-color: #22c55e;
            outline: none;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }

        /* Avatar & Thread Styling */
        .avatar-circle {
            width: 40px;
            height: 40px;
            min-width: 40px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: #475569;
        }

        .thread-line {
            border-left: 2px solid #f1f5f9;
            margin-left: 19px;
            padding-left: 30px;
            margin-top: 15px;
            margin-bottom: 10px;
        }

        .timestamp {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .btn-add-note {
            background-color: #22c55e;
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 8px 22px;
            border-radius: 6px;
            transition: background-color 0.15s;
            cursor: pointer;
        }
        .btn-add-note:hover {
            background-color: #16a34a;
            color: #fff;
        }

        .btn-reply-link {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            padding: 0;
            background: none;
            border: none;
        }
        .btn-reply-link:hover {
            color: #16a34a;
        }

        .reply-box-container {
            transition: all 0.2s ease-in-out;
        }

        .note-content-text {
            color: #475569;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* Toast Alert Container */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>
<body>

    <!-- Notification Toast Container -->
    <div id="toastContainer"></div>

    <?php $dash_url = (strpos($_SERVER['PHP_SELF'], '/features/') !== false) ? '../client_dashboard.php' : 'client_dashboard.php'; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="<?= $dash_url ?>" class="text-decoration-none text-muted small">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
        <?php if (!empty($client_projects) && count($client_projects) > 1): ?>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted fw-semibold">Project:</span>
                <select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href='client_notes.php?project_id='+this.value">
                    <?php foreach ($client_projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $project_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Title -->
    <h2 class="page-title">Project Notes</h2>

    <!-- Add a New Note Card -->
    <div class="nexus-card">
        <h5 class="fw-bold mb-3" style="color: #0f172a;">Add a New Note</h5>
        <form id="addNoteForm" onsubmit="submitRootNote(event)">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <textarea name="note_text" id="rootNoteText" class="note-input mb-3" placeholder="Type your note here..." required></textarea>
            <?php if ($user_role !== 'Client'): ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_internal_only" value="1" id="internalOnlyCheck">
                    <label class="form-check-label small text-muted" for="internalOnlyCheck">
                        <i class="bi bi-lock me-1"></i>Internal only (hidden from client)
                    </label>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn-add-note" id="btnAddRoot">
                <span id="btnAddText">Add Note</span>
            </button>
        </form>
    </div>

    <!-- Notes Feed Container -->
    <div id="notesContainer">
        <?php if (empty($root_notes)): ?>
            <div class="nexus-card text-center py-5 text-muted" id="noNotesMsg">
                <i class="bi bi-chat-left-text fs-2 d-block mb-2 text-secondary"></i>
                <p class="mb-0">No project notes have been posted yet. Start the conversation above!</p>
            </div>
        <?php else: ?>
            <?php foreach ($root_notes as $note): 
                $root_initials = getInitials($note['author_name']);
                $root_time = getRelativeTime($note['created_at']);
                $child_replies = $replies_by_parent[$note['id']] ?? [];
            ?>
                <div class="nexus-card" id="note-<?= $note['id'] ?>">
                    <!-- Top Author & Timestamp Row -->
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3"><?= htmlspecialchars($root_initials) ?></div>
                            <div>
                                <span class="fw-bold d-block text-dark"><?= htmlspecialchars($note['author_name']) ?></span>
                            </div>
                        </div>
                        <span class="timestamp"><?= htmlspecialchars($root_time) ?></span>
                    </div>

                    <!-- Note Body -->
                    <p class="note-content-text ps-5 mb-2"><?= nl2br(htmlspecialchars($note['note_text'])) ?></p>

                    <!-- Reply Action Trigger -->
                    <div class="ps-5 mb-2">
                        <button type="button" class="btn-reply-link" onclick="toggleReplyBox(<?= $note['id'] ?>)">
                            <i class="bi bi-reply-fill me-1"></i>Reply
                        </button>
                    </div>

                    <!-- Inline Reply Input Box (Hidden by default) -->
                    <div class="reply-box-container ps-5 mt-2 d-none" id="reply-box-<?= $note['id'] ?>">
                        <div class="p-3 bg-light rounded-3 border">
                            <textarea class="form-control mb-2" rows="2" placeholder="Write a reply..." id="reply-text-<?= $note['id'] ?>"></textarea>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleReplyBox(<?= $note['id'] ?>)">Cancel</button>
                                <button type="button" class="btn btn-sm btn-add-note" onclick="submitReply(<?= $note['id'] ?>)">Send Reply</button>
                            </div>
                        </div>
                    </div>

                    <!-- Child Replies Container (Nested with thread-line) -->
                    <div class="thread-line <?= empty($child_replies) ? 'd-none' : '' ?>" id="replies-for-<?= $note['id'] ?>">
                        <?php foreach ($child_replies as $reply): 
                            $reply_initials = getInitials($reply['author_name']);
                            $reply_time = getRelativeTime($reply['created_at']);
                        ?>
                            <div class="reply-entry mb-3" id="note-<?= $reply['id'] ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3"><?= htmlspecialchars($reply_initials) ?></div>
                                        <div>
                                            <span class="fw-bold d-block text-dark"><?= htmlspecialchars($reply['author_name']) ?></span>
                                        </div>
                                    </div>
                                    <span class="timestamp"><?= htmlspecialchars($reply_time) ?></span>
                                </div>
                                <p class="note-content-text ps-5 mb-0"><?= nl2br(htmlspecialchars($reply['note_text'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const projectId = <?= (int)$project_id ?>;
        const processUrl = window.location.pathname.includes('/features/') ? 'process_project_note.php' : 'process_project_note.php';

        // Show Toast Notification
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show shadow-sm`;
            alert.style.minWidth = '280px';
            alert.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi ${type === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger'} me-2 fs-5"></i>
                    <div>${message}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            container.appendChild(alert);
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 200);
            }, 3500);
        }

        // Toggle Inline Reply Form
        function toggleReplyBox(noteId) {
            const box = document.getElementById(`reply-box-${noteId}`);
            if (!box) return;
            box.classList.toggle('d-none');
            if (!box.classList.contains('d-none')) {
                const textarea = document.getElementById(`reply-text-${noteId}`);
                if (textarea) textarea.focus();
            }
        }

        // Submit Root Note via AJAX
        async function submitRootNote(e) {
            e.preventDefault();
            const textarea = document.getElementById('rootNoteText');
            const noteText = textarea.value.trim();
            const btn = document.getElementById('btnAddRoot');
            const btnText = document.getElementById('btnAddText');

            if (!noteText) {
                showToast('Please type your note before submitting.', 'warning');
                return;
            }

            btn.disabled = true;
            btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting...';

            try {
                const formData = new URLSearchParams();
                formData.append('project_id', projectId);
                formData.append('note_text', noteText);
                formData.append('is_ajax', '1');

                const checkInternal = document.getElementById('internalOnlyCheck');
                if (checkInternal && checkInternal.checked) {
                    formData.append('is_internal_only', '1');
                }

                // Call endpoint
                const res = await fetch(processUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                });

                const data = await res.json();
                if (data.success) {
                    const noNotesMsg = document.getElementById('noNotesMsg');
                    if (noNotesMsg) noNotesMsg.remove();

                    // Prepend new note card to notesContainer
                    const notesContainer = document.getElementById('notesContainer');
                    const temp = document.createElement('div');
                    temp.innerHTML = data.html.trim();
                    const newCard = temp.firstElementChild;
                    notesContainer.prepend(newCard);

                    textarea.value = '';
                    showToast(data.message || 'Note added successfully!', 'success');
                } else {
                    showToast(data.error || 'Could not post note.', 'danger');
                }
            } catch (err) {
                console.error('Error posting note:', err);
                showToast('A network error occurred while posting.', 'danger');
            } finally {
                btn.disabled = false;
                btnText.innerHTML = 'Add Note';
            }
        }

        // Submit Inline Reply via AJAX
        async function submitReply(parentId) {
            const textarea = document.getElementById(`reply-text-${parentId}`);
            const noteText = textarea.value.trim();
            if (!noteText) {
                showToast('Please write a reply before submitting.', 'warning');
                return;
            }

            try {
                const formData = new URLSearchParams();
                formData.append('project_id', projectId);
                formData.append('parent_id', parentId);
                formData.append('note_text', noteText);
                formData.append('is_ajax', '1');

                const res = await fetch(processUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                });

                const data = await res.json();
                if (data.success) {
                    const repliesContainer = document.getElementById(`replies-for-${parentId}`);
                    if (repliesContainer) {
                        repliesContainer.classList.remove('d-none');
                        const temp = document.createElement('div');
                        temp.innerHTML = data.html.trim();
                        repliesContainer.appendChild(temp.firstElementChild);
                    }

                    textarea.value = '';
                    toggleReplyBox(parentId);
                    showToast(data.message || 'Reply posted successfully!', 'success');
                } else {
                    showToast(data.error || 'Could not post reply.', 'danger');
                }
            } catch (err) {
                console.error('Error posting reply:', err);
                showToast('A network error occurred while submitting reply.', 'danger');
            }
        }
    </script>
</body>
</html>