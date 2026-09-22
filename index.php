<?php
/**
 * Entry point and router for Railway (php -S)
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Serve static files directly with correct MIME types
$mimeTypes = [
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'png'   => 'image/png',
    'gif'   => 'image/gif',
    'ico'   => 'image/x-icon',
    'svg'   => 'image/svg+xml',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'eot'   => 'application/vnd.ms-fontobject',
    'map'   => 'application/json',
    'pdf'   => 'application/pdf',
];

$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (isset($mimeTypes[$ext])) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath)) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }
}

// If a specific PHP file is requested, serve it directly
if ($uri !== '/' && $uri !== '/index.php') {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath) && is_file($filePath) && $ext === 'php') {
        // Let PHP built-in server handle it natively (preserves __FILE__, headers, etc.)
        return false;
    }
}

// Root — load landing page
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modules/SessionGuard.php';
SessionGuard::start();

if (SessionGuard::isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

require __DIR__ . '/public/home.php';
