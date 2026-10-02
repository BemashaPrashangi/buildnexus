<?php
// tasks.php - Root routing alias for features/tasks.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/tasks.php" . $query);
exit;
