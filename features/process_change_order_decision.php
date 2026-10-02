<?php
/**
 * features/process_change_order_decision.php
 * Transactional AJAX & Form endpoint for Client Change Order Approval and Decline decisions.
 * Updates change_orders status, increments contract budget, updates budgets baseline,
 * and automatically generates an invoice.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// Set JSON response header if AJAX
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
           || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1')
           || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

function sendResponse($success, $message, $extra = []) {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        exit();
    } else {
        $param = $success ? 'msg=' . urlencode($extra['action_code'] ?? 'success') : 'error=' . urlencode($message);
        $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'client_change_orders.php';
        $delimiter = strpos($redirect, '?') !== false ? '&' : '?';
        header("Location: " . $redirect . $delimiter . $param);
        exit();
    }
}

// 1. Security Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Invalid request method.");
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

if (!$user_id) {
    sendResponse(false, "Authentication required. Please log in.");
}

// 2. Extract and Validate Parameters
$co_id = intval($_POST['co_id'] ?? 0);
$decision = strtolower(trim($_POST['decision'] ?? ''));
$signature = trim($_POST['signature'] ?? '');
$reason = trim($_POST['reason'] ?? $_POST['client_feedback'] ?? '');

if ($co_id <= 0) {
    sendResponse(false, "Invalid change order ID.");
}

if (!in_array($decision, ['approve', 'decline'])) {
    sendResponse(false, "Invalid decision type.");
}

try {
    $pdo->beginTransaction();

    // 3. Fetch Change Order and verify ownership
    $stmt_co = $pdo->prepare("
        SELECT co.*, p.project_name, p.client_id as proj_client_id, p.budget as current_budget
        FROM change_orders co
        JOIN projects p ON co.project_id = p.id
        WHERE co.id = ?
    ");
    $stmt_co->execute([$co_id]);
    $co = $stmt_co->fetch(PDO::FETCH_ASSOC);

    if (!$co) {
        throw new Exception("Change Order not found.");
    }

    if ($co['status'] !== 'Pending') {
        throw new Exception("This change order has already been " . strtolower($co['status']) . ".");
    }

    $project_id = $co['project_id'];
    $cost_impact = floatval($co['cost_impact']);

    // RBAC: If Client, verify project ownership
    if ($user_role === 'Client') {
        $stmt_verify = $pdo->prepare("
            SELECT id FROM projects 
            WHERE id = :project_id AND (
                client_id = :user_id 
                OR id = (SELECT default_project_id FROM contacts WHERE linked_user_id = :user_id LIMIT 1)
                OR id = (SELECT project_id FROM clients WHERE id = :user_id LIMIT 1)
                OR id = (SELECT project_id FROM clients WHERE email = (SELECT email FROM users WHERE id = :user_id LIMIT 1) LIMIT 1)
            )
            LIMIT 1
        ");
        $stmt_verify->execute([':project_id' => $project_id, ':user_id' => $user_id]);
        if (!$stmt_verify->fetch()) {
            throw new Exception("Access Denied: You do not have permission to modify this change order.");
        }
    }

    // Determine client_id for invoices
    $client_id_for_inv = $co['client_id'] ?: ($co['proj_client_id'] ?: $user_id);

    if ($decision === 'approve') {
        // --- DECISION: APPROVE ---
        $feedback_text = !empty($signature) ? "Electronically Signed by: " . $signature : "Approved by client via Client Portal";

        // A. Update change_orders table
        $stmt_upd_co = $pdo->prepare("
            UPDATE change_orders 
            SET status = 'Approved', 
                approved_by = :approved_by, 
                approved_at = NOW(), 
                client_feedback = :feedback 
            WHERE id = :id
        ");
        $stmt_upd_co->execute([
            ':approved_by' => $user_id,
            ':feedback' => $feedback_text,
            ':id' => $co_id
        ]);

        // B. Automatically increment the project's contracted budget
        $stmt_upd_proj = $pdo->prepare("
            UPDATE projects 
            SET budget = budget + :cost_impact 
            WHERE id = :project_id
        ");
        $stmt_upd_proj->execute([
            ':cost_impact' => $cost_impact,
            ':project_id' => $project_id
        ]);

        // C. Update financial baseline in budgets table
        $stmt_b = $pdo->prepare("SELECT id FROM budgets WHERE project_id = ? LIMIT 1");
        $stmt_b->execute([$project_id]);
        $budget_row_id = $stmt_b->fetchColumn();

        if ($budget_row_id) {
            $stmt_upd_b = $pdo->prepare("UPDATE budgets SET allocated_amount = allocated_amount + ? WHERE id = ?");
            $stmt_upd_b->execute([$cost_impact, $budget_row_id]);
        } else {
            $stmt_ins_b = $pdo->prepare("INSERT INTO budgets (project_id, allocated_amount, notes) VALUES (?, ?, ?)");
            $stmt_ins_b->execute([$project_id, $cost_impact, 'Baseline Budget from Change Order: ' . ($co['co_number'] ?: $co['title'])]);
        }

        // D. Automatically generate pending invoice in invoices
        $year = date('Y');
        $seqStmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
        $seqStmt->execute(["INV-{$year}-%"]);
        $lastNo = $seqStmt->fetchColumn();

        if ($lastNo && preg_match("/INV-{$year}-(\d+)/", $lastNo, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $maxStmt = $pdo->query("SELECT MAX(id) FROM invoices");
            $nextSeq = ($maxStmt->fetchColumn() ?: 0) + 1;
        }
        $invoice_number = sprintf("INV-%s-%03d", $year, $nextSeq);

        $inv_notes = 'Generated from Change Order: ' . ($co['co_number'] ?: $co['title']);
        $stmt_ins_inv = $pdo->prepare("
            INSERT INTO invoices (
                project_id, client_id, invoice_number, amount, total_amount, 
                due_date, status, notes, issue_date
            ) VALUES (
                ?, ?, ?, ?, ?, 
                DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY), 'Sent', ?, CURRENT_DATE
            )
        ");
        $stmt_ins_inv->execute([
            $project_id, $client_id_for_inv, $invoice_number, 
            $cost_impact, $cost_impact, $inv_notes
        ]);

        $pdo->commit();

        sendResponse(true, "Change Order approved successfully! Contract budget has been updated and Invoice {$invoice_number} has been generated.", [
            'action_code' => 'approved',
            'decision' => 'approve',
            'new_status' => 'Approved',
            'badge_class' => 'pill-approved',
            'co_id' => $co_id,
            'invoice_number' => $invoice_number
        ]);

    } elseif ($decision === 'decline') {
        // --- DECISION: DECLINE ---
        $decline_reason = !empty($reason) ? $reason : "Declined by client without comments.";

        $stmt_upd_co = $pdo->prepare("
            UPDATE change_orders 
            SET status = 'Declined', 
                client_feedback = :feedback, 
                approved_at = NOW(),
                approved_by = :user_id 
            WHERE id = :id
        ");
        $stmt_upd_co->execute([
            ':feedback' => $decline_reason,
            ':user_id' => $user_id,
            ':id' => $co_id
        ]);

        $pdo->commit();

        sendResponse(true, "Change Order has been declined.", [
            'action_code' => 'declined',
            'decision' => 'decline',
            'new_status' => 'Declined',
            'badge_class' => 'pill-declined',
            'co_id' => $co_id
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse(false, "Processing Error: " . $e->getMessage());
}
