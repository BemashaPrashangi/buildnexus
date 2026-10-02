<?php
require_once '../db.php'; 
session_start();

// Security: Allow only Admin or PM access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Invalid RFI ID.");
}

$rfi_id = $_GET['id'];

// Handle Response Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_response') {
    $response_text = $_POST['response_text'];
    
    // File upload logic
    $official_attachment = null;
    if (isset($_FILES['official_attachment']) && $_FILES['official_attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/rfis/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = time() . '_resp_' . basename($_FILES['official_attachment']['name']);
        $targetFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['official_attachment']['tmp_name'], $targetFile)) {
            $official_attachment = 'uploads/rfis/' . $fileName;
        }
    }

    try {
        // Insert response
        $stmt = $pdo->prepare("INSERT INTO rfi_responses (rfi_id, user_id, response_text, official_attachment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$rfi_id, $_SESSION['user_id'], $response_text, $official_attachment]);
        
        // Update RFI status to Answered
        $updateStmt = $pdo->prepare("UPDATE project_rfis SET status = 'Answered' WHERE id = ?");
        $updateStmt->execute([$rfi_id]);
        
        header("Location: rfi_details.php?id=" . $rfi_id);
        exit();
    } catch (PDOException $e) {
        $error = "Error saving response: " . $e->getMessage();
    }
}

try {
    // Fetch RFI details
    $stmt = $pdo->prepare("
        SELECT r.*, p.project_name, u.full_name 
        FROM project_rfis r 
        LEFT JOIN projects p ON r.project_id = p.id 
        LEFT JOIN users u ON r.created_by = u.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$rfi_id]);
    $rfi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rfi) {
        die("RFI not found.");
    }

    // Fetch Responses
    $respStmt = $pdo->prepare("
        SELECT rr.*, u.full_name, u.role 
        FROM rfi_responses rr 
        LEFT JOIN users u ON rr.user_id = u.id 
        WHERE rr.rfi_id = ? 
        ORDER BY rr.created_at ASC
    ");
    $respStmt->execute([$rfi_id]);
    $responses = $respStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title><?= htmlspecialchars($rfi['rfi_number']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { max-width: 900px; margin: 0 auto; padding: 2.5rem 1rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
        
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .pill-answered { background: #f0fdf4; color: #16a34a; }
        .pill-open { background: #eff6ff; color: #2563eb; }
        .pill-overdue { background: #fef2f2; color: #dc2626; }
        .pill-closed { background: #f1f5f9; color: #475569; }

        .meta-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 4px; }
        .meta-value { font-size: 0.95rem; font-weight: 500; }
        
        .question-box { background: #f8fafc; border-radius: 8px; padding: 1.5rem; border: 1px solid #e2e8f0; }
        .response-box { border-left: 3px solid #10b981; padding-left: 1rem; margin-top: 1.5rem; }
        
        .btn-nexus { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 10px 20px; }
        .btn-nexus:hover { background-color: #16a34a; }
        .back-link { color: #64748b; text-decoration: none; font-weight: 500; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 1.5rem; transition: 0.2s; }
        .back-link:hover { color: #1e293b; }
    </style>
</head>
<body>

    <div class="main-container">
        <a href="rfi.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to RFIs</a>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="nexus-card">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="h4 fw-bold mb-1"><?= htmlspecialchars($rfi['subject']) ?></h2>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($rfi['rfi_number']) ?> • <?= htmlspecialchars($rfi['project_name']) ?></p>
                </div>
                <span class="pill pill-<?= strtolower($rfi['status']) ?>">
                    <?= htmlspecialchars($rfi['status']) ?>
                </span>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-sm-4">
                    <div class="meta-label">Assigned To</div>
                    <div class="meta-value"><?= htmlspecialchars($rfi['assigned_to_name']) ?> <span class="text-muted fw-normal">(<?= htmlspecialchars($rfi['assigned_to_role']) ?>)</span></div>
                </div>
                <div class="col-sm-4">
                    <div class="meta-label">Created By</div>
                    <div class="meta-value"><?= htmlspecialchars($rfi['full_name'] ?: 'Staff') ?></div>
                </div>
                <div class="col-sm-4">
                    <div class="meta-label">Due Date</div>
                    <div class="meta-value"><?= htmlspecialchars($rfi['due_date']) ?></div>
                </div>
            </div>

            <div class="question-box mb-4">
                <div class="meta-label mb-2">Question / Description</div>
                <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($rfi['question_details']) ?></p>
                
                <?php if ($rfi['attachment_file']): ?>
                    <hr class="my-3">
                    <a href="../<?= htmlspecialchars($rfi['attachment_file']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary fw-semibold">
                        <i class="bi bi-paperclip"></i> View Attached File
                    </a>
                <?php endif; ?>
            </div>
            <div class="text-muted small text-end">Submitted on <?= date('M d, Y h:i A', strtotime($rfi['created_at'])) ?></div>
        </div>

        <h4 class="fw-bold mb-3">Official Responses</h4>
        
        <?php if (count($responses) > 0): ?>
            <?php foreach ($responses as $resp): ?>
                <div class="nexus-card mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <div class="fw-bold"><?= htmlspecialchars($resp['full_name'] ?: 'Responder') ?> <span class="text-muted fw-normal small">(<?= htmlspecialchars($resp['role']) ?>)</span></div>
                        <div class="text-muted small"><?= date('M d, Y h:i A', strtotime($resp['created_at'])) ?></div>
                    </div>
                    <p style="white-space: pre-wrap;" class="mb-0"><?= htmlspecialchars($resp['response_text']) ?></p>
                    
                    <?php if ($resp['official_attachment']): ?>
                        <div class="mt-3">
                            <a href="../<?= htmlspecialchars($resp['official_attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary fw-semibold">
                                <i class="bi bi-paperclip"></i> View Response Attachment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted mb-4">No responses yet.</p>
        <?php endif; ?>

        <?php if ($rfi['status'] !== 'Closed'): ?>
            <div class="nexus-card mt-4">
                <h5 class="fw-bold mb-3">Submit Official Answer</h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_response">
                    <div class="mb-3">
                        <textarea name="response_text" class="form-control" rows="4" placeholder="Type clarification, instructions, or resolution here..." required></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Attach Document (Optional)</label>
                        <input type="file" name="official_attachment" class="form-control form-control-sm">
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-nexus">Post Answer</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
