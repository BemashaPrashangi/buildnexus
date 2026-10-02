<?php
// features/process_new_campaign.php - Creation and Dispatch Handler
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: email-marketing.php");
    exit();
}

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || isset($_POST['ajax']);

$name = trim($_POST['campaign_name'] ?? $_POST['name'] ?? '');
$subject = trim($_POST['subject_line'] ?? $_POST['subject'] ?? '');
$audience = trim($_POST['target_audience'] ?? $_POST['group'] ?? 'All Contacts');
$dispatch_type = $_POST['dispatch_type'] ?? '';
$schedule_date = !empty($_POST['schedule_date']) ? $_POST['schedule_date'] : null;
$content = trim($_POST['content_html'] ?? $_POST['content'] ?? '');
$user_id = $_SESSION['user_id'] ?? 1;

if (empty($name) || empty($subject)) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Campaign name and subject line are required.']);
        exit();
    }
    $_SESSION['flash_msg'] = ['type' => 'danger', 'text' => 'Campaign name and subject line are required.'];
    header("Location: email-marketing.php");
    exit();
}

// Calculate audience count
$recipient_count = 0;
try {
    switch ($audience) {
        case 'Leads':
            $stmt = $pdo->query("SELECT COUNT(DISTINCT email) FROM leads WHERE email IS NOT NULL AND TRIM(email) != ''");
            $recipient_count = intval($stmt->fetchColumn() ?: 0);
            break;
        case 'Clients':
        case 'Past Clients':
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT email) FROM (
                    SELECT email FROM clients WHERE email IS NOT NULL AND TRIM(email) != ''
                    UNION
                    SELECT email FROM contacts WHERE role_type = 'Client' AND email IS NOT NULL AND TRIM(email) != ''
                ) c_emails
            ");
            $recipient_count = intval($stmt->fetchColumn() ?: 0);
            break;
        case 'Subcontractors':
            $stmt = $pdo->query("SELECT COUNT(DISTINCT email) FROM contacts WHERE role_type = 'Subcontractor' AND email IS NOT NULL AND TRIM(email) != ''");
            $recipient_count = intval($stmt->fetchColumn() ?: 0);
            break;
        case 'Vendors':
            $stmt = $pdo->query("SELECT COUNT(DISTINCT email) FROM contacts WHERE role_type = 'Vendor' AND email IS NOT NULL AND TRIM(email) != ''");
            $recipient_count = intval($stmt->fetchColumn() ?: 0);
            break;
        case 'All Contacts':
        default:
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT email) FROM (
                    SELECT email FROM contacts WHERE email IS NOT NULL AND TRIM(email) != ''
                    UNION
                    SELECT email FROM leads WHERE email IS NOT NULL AND TRIM(email) != ''
                    UNION
                    SELECT email FROM clients WHERE email IS NOT NULL AND TRIM(email) != ''
                ) all_emails
            ");
            $recipient_count = intval($stmt->fetchColumn() ?: 0);
            break;
    }
} catch (Exception $e) {
    $recipient_count = 0;
}

// Determine status & send_date
$status = 'Draft';
$send_date_val = null;
$total_sent = 0;

if (isset($_POST['send_now']) || $dispatch_type === 'send_now') {
    $status = 'Sent';
    $send_date_val = date('Y-m-d H:i:s');
    $total_sent = max(1, $recipient_count);
} elseif (isset($_POST['schedule']) || $dispatch_type === 'schedule') {
    $status = 'Scheduled';
    $send_date_val = !empty($schedule_date) ? date('Y-m-d H:i:s', strtotime($schedule_date)) : date('Y-m-d H:i:s', strtotime('+1 day'));
} else {
    // Draft
    $status = 'Draft';
    $send_date_val = !empty($schedule_date) ? date('Y-m-d H:i:s', strtotime($schedule_date)) : null;
}

try {
    $sql = "
        INSERT INTO marketing_campaigns 
        (campaign_name, subject_line, target_audience, recipient_count, status, send_date, content_html, total_sent, total_opened, total_clicked, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, NOW())
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $name,
        $subject,
        $audience,
        $recipient_count,
        $status,
        $send_date_val,
        $content,
        $total_sent,
        $user_id
    ]);
    $campaign_id = $pdo->lastInsertId();

    // If sent immediately, generate campaign logs
    if ($status === 'Sent') {
        $recipients = [];
        try {
            switch ($audience) {
                case 'Leads':
                    $recipients = $pdo->query("SELECT customer_name AS name, email FROM leads WHERE email IS NOT NULL AND TRIM(email) != '' GROUP BY email")->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'Clients':
                case 'Past Clients':
                    $recipients = $pdo->query("
                        SELECT full_name AS name, email FROM clients WHERE email IS NOT NULL AND TRIM(email) != ''
                        UNION
                        SELECT name, email FROM contacts WHERE role_type = 'Client' AND email IS NOT NULL AND TRIM(email) != ''
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'Subcontractors':
                    $recipients = $pdo->query("SELECT name, email FROM contacts WHERE role_type = 'Subcontractor' AND email IS NOT NULL AND TRIM(email) != '' GROUP BY email")->fetchAll(PDO::FETCH_ASSOC);
                    break;
                default:
                    $recipients = $pdo->query("
                        SELECT name, email FROM contacts WHERE email IS NOT NULL AND TRIM(email) != ''
                        UNION
                        SELECT customer_name AS name, email FROM leads WHERE email IS NOT NULL AND TRIM(email) != ''
                        UNION
                        SELECT full_name AS name, email FROM clients WHERE email IS NOT NULL AND TRIM(email) != ''
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    break;
            }
        } catch (Exception $e) {}

        if (empty($recipients)) {
            $recipients = [['name' => 'General Client', 'email' => 'client@buildnexus.lk']];
        }

        $insLog = $pdo->prepare("
            INSERT INTO campaign_logs (campaign_id, recipient_email, recipient_name, status, opened_at, clicked_at)
            VALUES (?, ?, ?, 'Sent', NULL, NULL)
        ");
        foreach ($recipients as $rec) {
            $insLog->execute([$campaign_id, $rec['email'], $rec['name'] ?? null]);
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Campaign '{$name}' created successfully with status {$status}!",
            'campaign_id' => $campaign_id
        ]);
        exit();
    }

    $_SESSION['flash_msg'] = [
        'type' => 'success',
        'text' => "Campaign '{$name}' created successfully with status {$status}!"
    ];
    header("Location: email-marketing.php?msg=created");
    exit();

} catch (Exception $e) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    $_SESSION['flash_msg'] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
    header("Location: email-marketing.php?msg=error");
    exit();
}