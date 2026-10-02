<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'Client') {
    $inv_id = $_POST['invoice_id'];

    try {
        $pdo->beginTransaction();

        // 1. Fetch invoice info
        $stmt_inv = $pdo->prepare("SELECT project_id, client_id, COALESCE(total_amount, amount) AS total_amount FROM invoices WHERE id = ?");
        $stmt_inv->execute([$inv_id]);
        $inv = $stmt_inv->fetch(PDO::FETCH_ASSOC);

        if (!$inv) {
            throw new Exception("Invoice not found.");
        }

        $amount = floatval($inv['total_amount']);
        $fee = round($amount * 0.015, 2); // 1.5% gateway processing fee
        $net_amount = $amount - $fee;
        $txn_id = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $gw_response = json_encode([
            'status' => 'succeeded',
            'gateway' => 'BuildNexus Online Gateway',
            'auth_code' => 'AUTH-' . rand(100000, 999999),
            'timestamp' => date('c')
        ]);

        // 2. Insert record into payments table
        $stmt_pay = $pdo->prepare("
            INSERT INTO payments (transaction_id, invoice_id, project_id, client_id, payment_date, payment_method, status, amount, fee_deducted, net_amount, gateway_response)
            VALUES (?, ?, ?, ?, CURRENT_DATE, 'Credit Card', 'Completed', ?, ?, ?, ?)
        ");
        $stmt_pay->execute([$txn_id, $inv_id, $inv['project_id'], $inv['client_id'], $amount, $fee, $net_amount, $gw_response]);

        // 3. Recalculate invoice status based on completed payments
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'Completed'");
        $sumStmt->execute([$inv_id]);
        $total_paid = floatval($sumStmt->fetchColumn());

        $new_status = ($total_paid >= $amount) ? 'Paid' : 'Partially Paid';
        $stmt_upd = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $stmt_upd->execute([$new_status, $inv_id]);

        $pdo->commit();
        header("Location: ../client_dashboard.php?msg=payment_success");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Payment Processing Error: " . $e->getMessage());
    }
}