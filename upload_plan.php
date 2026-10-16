<?php
// upload_plan.php - Root routing alias for features/upload_plan.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/upload_plan.php" . $query);
exit;
