<?php
header('Content-Type: application/json; charset=utf-8');

$status = [
    'service'     => 'BuildNexus Construction Management ERP',
    'status'      => 'healthy',
    'timestamp'   => date('c'),
    'environment' => getenv('VERCEL_ENV') ?: (getenv('APP_ENV') ?: 'production'),
    'php_version' => PHP_VERSION,
    'database'    => 'unknown'
];

try {
    require_once __DIR__ . '/../db.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query('SELECT 1');
        $status['database'] = 'connected';
    }
} catch (Throwable $e) {
    $status['database'] = 'error: ' . $e->getMessage();
    $status['status']   = 'degraded';
}

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
