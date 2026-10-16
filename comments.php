<?php
// comments.php - Root routing alias for features/comments.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/comments.php" . $query);
exit;
