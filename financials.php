<?php
// financials.php - Root routing alias for features/project_financials.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/project_financials.php" . $query);
exit;
