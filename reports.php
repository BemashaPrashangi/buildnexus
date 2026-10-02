<?php
// reports.php - Root routing alias for features/reports.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/reports.php" . $query);
exit;
