<?php
// client_chat.php - Root routing alias for features/client_chat.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/client_chat.php" . $query);
exit;
