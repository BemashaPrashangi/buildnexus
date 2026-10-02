<?php
// 1. Fetch all items for the specific project/procurement request
$project_id = $_POST['project_id'];
$query_items = $pdo->prepare("SELECT * FROM procurement_items WHERE project_id = ?");
$query_items->execute([$project_id]);
$items = $query_items->fetchAll();

$subtotal = 0;
$total_tax = 0;

// 2. Automate the calculation logic
foreach ($items as $item) {
    $line_total = $item['unit_cost'] * $item['quantity'];
    $subtotal += $line_total;
    $total_tax += ($line_total * ($item['tax_rate'] / 100));
}

$grand_total = $subtotal + $total_tax;

// 3. Generate the Master Invoice Record
$invoice_ref = "INV-" . time(); // Unique Reference
$sql_invoice = "INSERT INTO invoices (project_id, reference_no, subtotal, tax_amount, total_amount, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";

$stmt = $pdo->prepare($sql_invoice);
$success = $stmt->execute([$project_id, $invoice_ref, $subtotal, $total_tax, $grand_total]);

if ($success) {
    echo "Invoice $invoice_ref generated successfully for Rs. " . number_format($grand_total, 2);
}
?>