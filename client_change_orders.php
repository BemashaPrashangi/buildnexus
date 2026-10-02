<?php
// client_change_orders.php - Root routing alias for features/client_change_orders.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/client_change_orders.php" . $query);
exit;
