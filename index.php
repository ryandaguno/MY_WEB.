<?php
/**
 * Entry point and router for Railway deployment (php -S)
 * Also handles direct browser visits to the root URL
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Serve static files directly (css, js, images, fonts)
$staticExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'map'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($ext, $staticExtensions)) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath)) {
        return false; // Let PHP built-in server serve the file
    }
}

// If a specific PHP file is requested and exists, serve it
$filePath = __DIR__ . $uri;

if ($uri !== '/' && file_exists($filePath) && is_file($filePath) && $ext === 'php') {
    require $filePath;
    return;
}

// If it's a directory, look for index.php inside
if (is_dir($filePath)) {
    $dirIndex = rtrim($filePath, '/') . '/index.php';
    if (file_exists($dirIndex)) {
        require $dirIndex;
        return;
    }
}

// Root request — redirect to landing page
if ($uri === '/' || $uri === '/index.php') {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/modules/SessionGuard.php';
    SessionGuard::start();

    if (SessionGuard::isAdminLoggedIn()) {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /public/home.php');
    }
    exit;
}

// Fallback — 404
http_response_code(404);
echo '404 Not Found';
