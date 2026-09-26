<?php
/**
 * Serves the GCash QR code image from the filesystem.
 * This works around Railway's static file serving issues
 * by reading the file through PHP directly.
 */
$path = __DIR__ . '/../assets/images/gcash_qr.png';

if (!file_exists($path)) {
    http_response_code(404);
    exit;
}

$size = filesize($path);
$data = file_get_contents($path);

header('Content-Type: image/png');
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=604800'); // cache 1 week
echo $data;
exit;
