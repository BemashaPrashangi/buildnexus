<?php
/**
 * Vercel Serverless Function Router for BuildNexus
 * Routes all incoming requests to the respective PHP files or root index.php
 */

$rootDir = realpath(__DIR__ . '/..');

// Extract the path from REQUEST_URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$urlPath = parse_url($requestUri, PHP_URL_PATH);

// Remove '/buildnexus' subfolder prefix if present (local vs deployed path compatibility)
if (strpos($urlPath, '/buildnexus/') === 0) {
    $urlPath = substr($urlPath, strlen('/buildnexus'));
} elseif ($urlPath === '/buildnexus') {
    $urlPath = '/';
}

$cleanPath = ltrim($urlPath, '/');

// Default route is index.php
if ($cleanPath === '' || $cleanPath === '/') {
    $cleanPath = 'index.php';
}

// Check candidate files
$target = null;
if (file_exists($rootDir . '/' . $cleanPath) && is_file($rootDir . '/' . $cleanPath)) {
    $target = $rootDir . '/' . $cleanPath;
} elseif (file_exists($rootDir . '/' . $cleanPath . '.php') && is_file($rootDir . '/' . $cleanPath . '.php')) {
    $target = $rootDir . '/' . $cleanPath . '.php';
}

// Security: Check that resolved file is within the project directory
$realTarget = $target ? realpath($target) : false;
if (!$realTarget || strpos($realTarget, $rootDir) !== 0) {
    http_response_code(404);
    echo "<h1>404 Not Found</h1><p>The requested file does not exist.</p>";
    exit;
}

// If the file is not a PHP file (e.g. css/image/js fallback), serve directly
$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
if ($ext !== 'php') {
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'json' => 'application/json'
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($realTarget);
    exit;
}

// Change working directory to target script directory (e.g. features/ or root)
// so relative paths like '../db.php', 'db.php', and local includes resolve correctly
$targetDir = dirname($realTarget);
chdir($targetDir);
set_include_path($targetDir . PATH_SEPARATOR . $rootDir . PATH_SEPARATOR . get_include_path());

// Set standard server variables so included scripts work as expected
$_SERVER['SCRIPT_FILENAME'] = $realTarget;
$_SERVER['SCRIPT_NAME']     = '/' . ltrim(str_replace([$rootDir, '\\'], ['', '/'], $realTarget), '/');
$_SERVER['PHP_SELF']        = $_SERVER['SCRIPT_NAME'];

// Execute target PHP file
require $realTarget;
