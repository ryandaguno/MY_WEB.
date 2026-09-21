<?php
/**
 * Router for PHP built-in server (Railway deployment)
 * Routes all requests to the correct file
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly (css, js, images, fonts)
$staticExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($ext, $staticExtensions)) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath)) {
        return false; // Serve the file as-is
    }
}

// Map URI to actual file
$filePath = __DIR__ . $uri;

// If it's a directory, look for index.php
if (is_dir($filePath)) {
    $filePath = rtrim($filePath, '/') . '/index.php';
}

// If file exists, include it
if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
    require $filePath;
    return true;
}

// Default to index.php
require __DIR__ . '/index.php';
