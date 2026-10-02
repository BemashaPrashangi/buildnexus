<?php
// features/track_click.php - Email Link Click Tracking & Redirect Endpoint
require_once __DIR__ . '/../db.php';

$cid = isset($_GET['cid']) ? intval($_GET['cid']) : 0;
$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';
$target = isset($_GET['target']) ? trim($_GET['target']) : '';

// Fallback target
if (empty($target)) {
    $target = '../index.php';
}

if ($cid > 0) {
    try {
        // Increment total_clicked in marketing_campaigns
        $stmt = $pdo->prepare("UPDATE marketing_campaigns SET total_clicked = total_clicked + 1 WHERE id = ?");
        $stmt->execute([$cid]);

        // If specific recipient hash or email was passed, update audit log
        if (!empty($uid)) {
            $logStmt = $pdo->prepare("
                UPDATE campaign_logs 
                SET status = 'Clicked', clicked_at = NOW() 
                WHERE campaign_id = ? AND (recipient_email = ? OR MD5(recipient_email) = ?)
            ");
            $logStmt->execute([$cid, $uid, $uid]);
        }
    } catch (Exception $e) {
        // Log silently
    }
}

// Ensure target doesn't perform header injection
$clean_target = filter_var($target, FILTER_SANITIZE_URL);
if (!preg_match('~^(https?://|/[^/]|../)~i', $clean_target)) {
    $clean_target = '../index.php';
}

header("Location: " . $clean_target);
exit();
