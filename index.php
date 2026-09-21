<?php
/**
 * Entry point and router for Railway (php -S)
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Serve static files directly
$staticExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'map'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($ext, $staticExtensions)) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath)) {
        return false; // Let PHP built-in server serve it
    }
}

// If a specific PHP file is requested, serve it directly
if ($uri !== '/' && $uri !== '/index.php') {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath) && is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        require $filePath;
        return;
    }
}

// Root — redirect to landing page
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modules/SessionGuard.php';
SessionGuard::start();

if (SessionGuard::isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

// Load landing page directly (no redirect)
require __DIR__ . '/public/home.php';
