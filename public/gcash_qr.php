<?php
/**
 * Serves the GCash QR code image, resized to 300x300 for fast loading.
 * Uses GD to compress on first request, caches as JPEG.
 */
$sourcePath = __DIR__ . '/../assets/images/gcash_qr.png';
$cachePath  = __DIR__ . '/../assets/images/gcash_qr_cache.jpg';

// If cached JPEG exists, serve it
if (file_exists($cachePath)) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=604800');
    readfile($cachePath);
    exit;
}

// If source exists, resize and cache
if (file_exists($sourcePath) && function_exists('imagecreatefrompng')) {
    $src = @imagecreatefrompng($sourcePath);
    if ($src) {
        $w = imagesx($src);
        $h = imagesy($src);
        $newW = 300;
        $newH = (int)($h * $newW / $w);

        $dst = imagecreatetruecolor($newW, $newH);
        // White background (for PNG transparency)
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        // Save cache
        imagejpeg($dst, $cachePath, 85);
        imagedestroy($src);
        imagedestroy($dst);

        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=604800');
        readfile($cachePath);
        exit;
    }
}

// Fallback: serve original PNG
if (file_exists($sourcePath)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    readfile($sourcePath);
    exit;
}

http_response_code(404);
exit;
