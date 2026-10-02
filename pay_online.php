<?php
// pay_online.php - Root routing alias for features/pay_online.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/pay_online.php" . $query);
exit;
