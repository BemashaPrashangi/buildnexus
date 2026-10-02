<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';
checkRole(['Admin', 'Project Manager']);

header('Content-Type: application/json');

$lead_id = intval($_POST['lead_id'] ?? $_GET['lead_id'] ?? 0);
$customer_name = trim($_POST['lead_name'] ?? $_POST['customer_name'] ?? '');
$project_type = trim($_POST['project'] ?? $_POST['project_type'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$lead = null;
if ($lead_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($lead) {
        $customer_name = $lead['customer_name'];
        $project_type = $lead['project_type'];
        $notes = $lead['notes'] ?? '';
    }
}

if (empty($customer_name)) {
    $customer_name = 'Valued Client';
}
if (empty($project_type)) {
    $project_type = 'Construction Development';
}

$firstName = explode(' ', trim($customer_name))[0];
$budgetMention = (!empty($lead['estimated_budget']) && $lead['estimated_budget'] > 0) ? "with an estimated investment budget of LKR " . number_format($lead['estimated_budget'], 2) : "";
$locationMention = !empty($lead['site_location']) ? "at " . htmlspecialchars($lead['site_location']) : "for your proposed jobsite";

// Generate professional Pre-Construction response template
$ai_reply = "Ayubowan {$firstName},\n\n" .
            "Thank you for contacting Tharaka Construction & BuildNexus regarding your upcoming {$project_type} project {$locationMention}.\n\n" .
            "We have carefully reviewed your initial requirements" . (!empty($notes) ? " (\"" . trim(strip_tags($notes)) . "\")" : "") . ". With over 15 years of industry-leading commercial and residential engineering experience across Sri Lanka, our multidisciplinary team specializes in high-quality structural execution, modern architectural finishes, and transparent turnkey project management.\n\n" .
            "To provide you with a comprehensive Bill of Quantities (BOQ) and preliminary project timeline, we would welcome the opportunity to conduct an initial on-site consultation or technical coordination call at your convenience.\n\n" .
            "Please let us know your preferred availability this week for an exploratory discussion.\n\n" .
            "Warm regards,\n\n" .
            "Lead Estimator & Pre-Construction Team\n" .
            "Tharaka Construction | Powered by BuildNexus\n" .
            "Direct: +94 11 234 5678 | Email: inquiries@buildnexus.com";

// Cache generated response draft in database if lead_id is provided
if ($lead_id > 0) {
    try {
        $upd = $pdo->prepare("UPDATE leads SET ai_response_draft = ? WHERE id = ?");
        $upd->execute([$ai_reply, $lead_id]);
    } catch (Exception $e) {}
}

echo json_encode([
    'success' => true,
    'suggestion' => $ai_reply,
    'customer_name' => $customer_name,
    'email' => $lead['email'] ?? '',
    'phone' => $lead['phone'] ?? '',
    'project_type' => $project_type
]);
exit();
