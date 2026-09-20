<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../config/db.php';
SessionGuard::requireAdmin();

$id   = (int)($_GET['id'] ?? 0);
$db   = getDB();
$stmt = $db->prepare('SELECT receipt_image FROM transactions WHERE id=?');
$stmt->execute([$id]);
$tx   = $stmt->fetch();

if (!$tx || empty($tx['receipt_image'])) {
    http_response_code(404); die('Receipt not found.');
}

$path = UPLOAD_PATH . basename($tx['receipt_image']); // basename prevents path traversal
if (!file_exists($path)) {
    http_response_code(404); die('File not found.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $path);
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
exit;
