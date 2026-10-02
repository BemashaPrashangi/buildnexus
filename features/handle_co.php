<?php
// features/handle_co.php - Bridges to process_change_order_decision.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && !isset($_POST['decision'])) {
        $_POST['decision'] = $_POST['action'];
    }
    require_once __DIR__ . '/process_change_order_decision.php';
}