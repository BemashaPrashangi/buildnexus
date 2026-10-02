<?php
// features/track_open.php - Email Open Tracking Pixel Endpoint
require_once __DIR__ . '/../db.php';

$cid = isset($_GET['cid']) ? intval($_GET['cid']) : 0;
$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';

if ($cid > 0) {
    try {
        // Increment total_opened in marketing_campaigns
        $stmt = $pdo->prepare("UPDATE marketing_campaigns SET total_opened = total_opened + 1 WHERE id = ?");
        $stmt->execute([$cid]);

        // If specific recipient hash or email was passed, update audit log
        if (!empty($uid)) {
            $logStmt = $pdo->prepare("
                UPDATE campaign_logs 
                SET status = 'Opened', opened_at = NOW() 
                WHERE campaign_id = ? AND (recipient_email = ? OR MD5(recipient_email) = ?) AND opened_at IS NULL
            ");
            $logStmt->execute([$cid, $uid, $uid]);
        }
    } catch (Exception $e) {
        // Silent catch to prevent broken image in recipient client
    }
}

// Disable browser and proxy caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: image/gif');

// Output 1x1 transparent GIF (43 bytes)
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit();
