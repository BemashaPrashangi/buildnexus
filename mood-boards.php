<?php
// mood-boards.php - Root routing alias for features/mood-boards.php
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: features/mood-boards.php" . $query);
exit;
