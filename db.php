<?php
// BuildNexus Database Configuration for Aiven Cloud MySQL & Vercel Serverless

// Automatically load local .env if present (gitignored)
if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!getenv($name)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
            }
        }
    }
}

$host     = getenv('DB_HOST') ?: 'mysql-3e4c0b6e-buildnexus-project.f.aivencloud.com';
$port     = getenv('DB_PORT') ?: '14344';
$dbname   = getenv('DB_NAME') ?: 'defaultdb';
$username = getenv('DB_USER') ?: 'avnadmin';
$password = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: '');

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::MYSQL_ATTR_SSL_CA       => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    if (getenv('VERCEL_ENV') === 'production') {
        error_log('Database connection error: ' . $e->getMessage());
        die('Database connection failed. Please contact the administrator.');
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>