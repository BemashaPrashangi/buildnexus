<?php
// client_notes.php - Root routing alias for features/client_notes.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/client_notes.php" . $query);
exit;
