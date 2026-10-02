<?php
require_once '../db.php'; 
session_start();

// Security: Allow only Admin or PM access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Project Manager'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Invalid Submittal ID.");
}

$submittal_id = $_GET['id'];

// Handle Review Decision Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $review_decision = $_POST['review_decision'];
    $review_comments = $_POST['review_comments'];
    
    // File upload logic for Stamped/Annotated document
    $annotated_file_path = null;
    if (isset($_FILES['annotated_file']) && $_FILES['annotated_file']['error'] === UPLOAD_ERR_OK) {
        $allowedExtensions = ['pdf', 'dwg', 'png', 'jpg', 'jpeg', 'docx'];
        $fileNameRaw = $_FILES['annotated_file']['name'];
        $fileExt = strtolower(pathinfo($fileNameRaw, PATHINFO_EXTENSION));
        
        if (in_array($fileExt, $allowedExtensions) && $_FILES['annotated_file']['size'] <= 26214400) {
            $uploadDir = '../uploads/submittals/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_reviewed_' . basename($fileNameRaw);
            $targetFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['annotated_file']['tmp_name'], $targetFile)) {
                $annotated_file_path = 'uploads/submittals/' . $fileName;
            }
        } else {
            $error = "Invalid file type or size exceeds 25MB.";
        }
    }

    if (!isset($error)) {
        try {
            $pdo->beginTransaction();
            
            // Insert review record
            $stmt = $pdo->prepare("INSERT INTO submittal_reviews (submittal_id, reviewer_id, review_decision, review_comments, annotated_file_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$submittal_id, $_SESSION['user_id'], $review_decision, $review_comments, $annotated_file_path]);
            
            // Update master submittal status
            $updateStmt = $pdo->prepare("UPDATE project_submittals SET status = ? WHERE id = ?");
            $updateStmt->execute([$review_decision, $submittal_id]);
            
            $pdo->commit();
            
            header("Location: submittal_details.php?id=" . $submittal_id);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error saving review: " . $e->getMessage();
        }
    }
}

try {
    // Fetch Submittal details
    $stmt = $pdo->prepare("
        SELECT s.*, p.project_name, u.full_name 
        FROM project_submittals s 
        LEFT JOIN projects p ON s.project_id = p.id 
        LEFT JOIN users u ON s.created_by = u.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$submittal_id]);
    $submittal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submittal) {
        die("Submittal not found.");
    }

    // Fetch Reviews
    $revStmt = $pdo->prepare("
        SELECT sr.*, u.full_name, u.role 
        FROM submittal_reviews sr 
        LEFT JOIN users u ON sr.reviewer_id = u.id 
        WHERE sr.submittal_id = ? 
        ORDER BY sr.reviewed_at ASC
    ");
    $revStmt->execute([$submittal_id]);
    $reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function getStatusPillClass($status) {
    switch ($status) {
        case 'Approved':
        case 'Approved as Noted':
            return 'pill-approved';
        case 'Pending':
            return 'pill-pending';
        case 'Revise & Resubmit':
            return 'pill-revise';
        case 'Rejected':
            return 'pill-rejected';
        default:
            return 'pill-pending';
    }
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
    <title><?= htmlspecialchars($submittal['submittal_number']) ?> - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .main-container { max-width: 900px; margin: 0 auto; padding: 2.5rem 1rem; }
        .nexus-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
        
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .pill-approved { background: #f0fdf4; color: #16a34a; }
        .pill-pending { background: #eff6ff; color: #2563eb; }
        .pill-revise { background: #fffbeb; color: #d97706; }
        .pill-rejected { background: #fef2f2; color: #dc2626; }

        .meta-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 4px; }
        .meta-value { font-size: 0.95rem; font-weight: 500; }
        
        .btn-nexus { background-color: #22c55e; border: none; color: #fff; font-weight: 600; border-radius: 8px; padding: 10px 20px; }
        .btn-nexus:hover { background-color: #16a34a; }
        .back-link { color: #64748b; text-decoration: none; font-weight: 500; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 1.5rem; transition: 0.2s; }
        .back-link:hover { color: #1e293b; }
        
        .review-box { border-left: 4px solid #e2e8f0; padding-left: 1rem; margin-bottom: 1.5rem; }
        .review-approved { border-left-color: #22c55e; }
        .review-revise { border-left-color: #f59e0b; }
        .review-rejected { border-left-color: #ef4444; }
    </style>
</head>
<body>

    <div class="main-container">
        <a href="submittals.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Submittals</a>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="nexus-card">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="h4 fw-bold mb-1">
                        <?php if ($submittal['spec_section']) echo htmlspecialchars($submittal['spec_section']) . ' - '; ?>
                        <?= htmlspecialchars($submittal['title']) ?>
                    </h2>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($submittal['submittal_number']) ?> • <?= htmlspecialchars($submittal['project_name']) ?> • <?= htmlspecialchars($submittal['submittal_type']) ?></p>
                </div>
                <span class="pill <?= getStatusPillClass($submittal['status']) ?>">
                    <?= htmlspecialchars($submittal['status']) ?>
                </span>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-sm-4">
                    <div class="meta-label">Submitted To</div>
                    <div class="meta-value"><?= htmlspecialchars($submittal['submitted_to_name']) ?> <span class="text-muted fw-normal">(<?= htmlspecialchars($submittal['submitted_to_role']) ?>)</span></div>
                </div>
                <div class="col-sm-4">
                    <div class="meta-label">Submitted By</div>
                    <div class="meta-value"><?= htmlspecialchars($submittal['full_name'] ?: 'Staff') ?></div>
                </div>
                <div class="col-sm-4">
                    <div class="meta-label">Review Due Date</div>
                    <div class="meta-value"><?= $submittal['review_due_date'] ? htmlspecialchars($submittal['review_due_date']) : 'N/A' ?></div>
                </div>
            </div>

            <?php if ($submittal['attachment_path']): ?>
                <div class="mt-4 pt-3 border-top">
                    <div class="meta-label mb-2">Original Document</div>
                    <a href="../<?= htmlspecialchars($submittal['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary fw-semibold">
                        <i class="bi bi-download"></i> View / Download Original File
                    </a>
                </div>
            <?php endif; ?>
            <div class="text-muted small text-end mt-3">Submitted on <?= date('M d, Y h:i A', strtotime($submittal['created_at'])) ?></div>
        </div>

        <h4 class="fw-bold mb-3">Review Audit Trail</h4>
        
        <div class="nexus-card mb-4">
            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $rev): 
                    $borderClass = '';
                    if (str_contains($rev['review_decision'], 'Approved')) $borderClass = 'review-approved';
                    elseif ($rev['review_decision'] === 'Revise & Resubmit') $borderClass = 'review-revise';
                    elseif ($rev['review_decision'] === 'Rejected') $borderClass = 'review-rejected';
                ?>
                    <div class="review-box <?= $borderClass ?>">
                        <div class="d-flex justify-content-between mb-1">
                            <div class="fw-bold"><?= htmlspecialchars($rev['full_name'] ?: 'Reviewer') ?> <span class="text-muted fw-normal small">(<?= htmlspecialchars($rev['role']) ?>)</span></div>
                            <div class="text-muted small"><?= date('M d, Y h:i A', strtotime($rev['reviewed_at'])) ?></div>
                        </div>
                        <div class="mb-2">
                            <span class="pill <?= getStatusPillClass($rev['review_decision']) ?>"><?= htmlspecialchars($rev['review_decision']) ?></span>
                        </div>
                        <p style="white-space: pre-wrap;" class="mb-2 small text-dark"><?= htmlspecialchars($rev['review_comments']) ?></p>
                        
                        <?php if ($rev['annotated_file_path']): ?>
                            <div>
                                <a href="../<?= htmlspecialchars($rev['annotated_file_path']) ?>" target="_blank" class="btn btn-sm btn-light border fw-semibold" style="font-size: 0.8rem;">
                                    <i class="bi bi-file-earmark-pdf text-danger"></i> View Stamped/Annotated File
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted mb-0">No reviews have been logged yet.</p>
            <?php endif; ?>
        </div>

        <?php if ($submittal['status'] !== 'Rejected'): ?>
            <div class="nexus-card mt-4">
                <h5 class="fw-bold mb-3">Log Review Decision</h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_review">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Review Decision</label>
                            <select name="review_decision" class="form-select" required>
                                <option value="Approved">Approved</option>
                                <option value="Approved as Noted">Approved as Noted</option>
                                <option value="Revise & Resubmit">Revise & Resubmit</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Annotated/Stamped File (Optional)</label>
                            <input type="file" name="annotated_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.dwg,.docx">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Reviewer Notes / Comments</label>
                        <textarea name="review_comments" class="form-control" rows="4" placeholder="Enter any architectural notes, exceptions, or resubmission requirements..." required></textarea>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-nexus">Log Decision</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
