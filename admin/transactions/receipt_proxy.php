<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../config/db.php';
SessionGuard::requireAdmin();

$id   = (int)($_GET['id'] ?? 0);
$db   = getDB();
$stmt = $db->prepare('SELECT receipt_image, receipt_data, receipt_mime FROM transactions WHERE id = ?');
$stmt->execute([$id]);
$tx   = $stmt->fetch();

if (!$tx) {
    http_response_code(404); die('Receipt not found.');
}

// 1. Try to serve from DB binary data first (survives Railway restarts)
if (!empty($tx['receipt_data'])) {
    $mime = $tx['receipt_mime'] ?: 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($tx['receipt_data']));
    header('Cache-Control: no-store');
    echo $tx['receipt_data'];
    exit;
}

// 2. Fall back to disk file
if (empty($tx['receipt_image'])) {
    http_response_code(404); die('No receipt on file.');
}

$path = UPLOAD_PATH . basename($tx['receipt_image']);
if (!file_exists($path)) {
    http_response_code(404); die('File not found on disk.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $path);
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
exit;
