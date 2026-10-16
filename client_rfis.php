<?php
// client_rfis.php - Root routing alias for features/client_rfis.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/client_rfis.php" . $query);
exit;
