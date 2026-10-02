<?php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Only process if the user is a logged-in Client
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    echo json_encode(['reply' => 'Unauthorized access.']);
    exit();
}

$client_id = $_SESSION['user_id'];
$user_message = strtolower(trim($_POST['message'] ?? ''));

try {
    // 1. Fetch Client's Project Details
    $stmt = $pdo->prepare("SELECT c.project_id, p.project_name, p.budget, p.status, p.stage, p.progress_percent 
                            FROM clients c 
                            JOIN projects p ON c.project_id = p.id 
                            WHERE c.id = ? OR c.email = (SELECT email FROM users WHERE id = ?) OR p.client_id = ?");
    $stmt->execute([$client_id, $client_id, $client_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        echo json_encode(['reply' => "I couldn't find an active project linked to your account."]);
        exit();
    }

    $project_id = $project['project_id'];
    $stage = !empty($project['stage']) ? $project['stage'] : $project['status'];
    $progress_pct = !empty($project['progress_percent']) ? $project['progress_percent'] : 45;

    // Detect User Intents
    $is_pure_greeting = preg_match('/^(hi|hello|hey|heya|howdy|greetings|yo|sup|good\s*(morning|afternoon|evening))\b/i', $user_message);
    $has_payment_intent  = (strpos($user_message, 'payment') !== false || strpos($user_message, 'balance') !== false || strpos($user_message, 'money') !== false || strpos($user_message, 'cost') !== false || strpos($user_message, 'budget') !== false || strpos($user_message, 'invoice') !== false || strpos($user_message, 'pay') !== false || strpos($user_message, 'due') !== false || strpos($user_message, 'paid') !== false);
    $has_progress_intent = (strpos($user_message, 'progress') !== false || strpos($user_message, 'stage') !== false || strpos($user_message, 'update') !== false || (strpos($user_message, 'status') !== false && !$has_payment_intent));
    $has_milestone_intent = (strpos($user_message, 'milestone') !== false || strpos($user_message, 'schedule') !== false || strpos($user_message, 'timeline') !== false || strpos($user_message, 'phase') !== false || strpos($user_message, 'next') !== false);

    // 1. Pure Greeting ("hi", "hello", "hey", etc.)
    if ($is_pure_greeting && !$has_progress_intent && !$has_payment_intent && !$has_milestone_intent) {
        $reply = "Hello! How can I help you today? You can ask me about your 'progress', 'payments', or 'next milestones'.";
    }

    // 2. Logic: Handle "Progress" questions
    elseif ($has_progress_intent) {
        $reply = "Your project, **" . $project['project_name'] . "**, is currently in the **" . $stage . "** stage. Overall progress is estimated at " . $progress_pct . "%.";
    }

    // 3. Logic: Handle "Payment" or "Balance" questions
    elseif ($has_payment_intent) {
        $stmt_pay = $pdo->prepare("SELECT SUM(amount) as paid FROM invoices WHERE project_id = ? AND status = 'Paid'");
        $stmt_pay->execute([$project_id]);
        $paid = $stmt_pay->fetchColumn() ?: 0;
        
        $balance = $project['budget'] - $paid;
        $reply = "The total budget for your project is **RS. " . number_format($project['budget']) . "**. You have paid **RS. " . number_format($paid) . "**, leaving a remaining balance of **RS. " . number_format($balance) . "**.";
    }

    // 4. Logic: Handle "Milestone" or "Schedule" questions
    elseif ($has_milestone_intent) {
        $stmt_ms = $pdo->prepare("SELECT phase_name, start_date FROM project_milestones WHERE project_id = ? AND status = 'Upcoming' ORDER BY start_date ASC LIMIT 1");
        $stmt_ms->execute([$project_id]);
        $milestone = $stmt_ms->fetch(PDO::FETCH_ASSOC);

        if ($milestone && !empty($milestone['phase_name'])) {
            $formatted_date = date('F j, Y', strtotime($milestone['start_date']));
            $reply = "Your next major milestone is **" . $milestone['phase_name'] . "**, which is scheduled to begin on **" . $formatted_date . "**.";
        } else {
            // Check project_phases table
            $stmt_phase = $pdo->prepare("SELECT title, start_date, status FROM project_phases WHERE project_id = ? AND status IN ('UPCOMING', 'IN PROGRESS') ORDER BY phase_number ASC LIMIT 1");
            $stmt_phase->execute([$project_id]);
            $phase = $stmt_phase->fetch(PDO::FETCH_ASSOC);
            if ($phase) {
                $formatted_date = date('F j, Y', strtotime($phase['start_date']));
                $reply = "Your upcoming phase is **" . $phase['title'] . "**, scheduled for **" . $formatted_date . "** (Status: " . ucfirst(strtolower($phase['status'])) . ").";
            } else {
                $reply = "All scheduled milestones for your current phase are complete! I'll update you when the next phase is planned.";
            }
        }
    }

    // 5. Logic: Handle Change Orders
    elseif (strpos($user_message, 'change order') !== false || strpos($user_message, 'scope') !== false) {
        $stmt_co = $pdo->prepare("SELECT COUNT(*) FROM change_orders WHERE project_id = ? AND status = 'Pending'");
        $stmt_co->execute([$project_id]);
        $pending_co = $stmt_co->fetchColumn() ?: 0;
        if ($pending_co > 0) {
            $reply = "You have **{$pending_co}** pending change order(s) awaiting your review. You can review and approve them in the Change Orders portal.";
        } else {
            $reply = "You currently have no pending change orders for **" . $project['project_name'] . "**.";
        }
    }

    // 6. Logic: Handle "How are you"
    elseif (strpos($user_message, 'how are you') !== false || strpos($user_message, 'how r u') !== false) {
        $reply = "I'm doing great, thank you! How can I assist you with your project today? Feel free to ask about your 'progress', 'payments', or 'next milestones'.";
    }

    // 7. Logic: Handle "Thank you" / "Thanks"
    elseif (strpos($user_message, 'thank') !== false || strpos($user_message, 'thx') !== false) {
        $reply = "You're very welcome! Let me know if there's anything else I can help you with regarding **" . $project['project_name'] . "**.";
    }

    // 8. Logic: Handle "Help" / "What can you do"
    elseif (strpos($user_message, 'help') !== false || strpos($user_message, 'what can you do') !== false) {
        $reply = "I can assist you with real-time updates on your project! You can ask me:\n• 'How is the project progress?'\n• 'What is my remaining balance?'\n• 'What is the next milestone?'";
    }

    // Default Fallback
    else {
        $reply = "I'm sorry, I didn't quite catch that. You can ask me about your 'progress', 'payments', or 'next milestones'.";
    }

    echo json_encode(['reply' => $reply]);

} catch (PDOException $e) {
    echo json_encode(['reply' => 'System error: ' . $e->getMessage()]);
}