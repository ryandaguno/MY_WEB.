<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../config/db.php';
SessionGuard::requireAdmin();
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE service_id=? AND status IN ('Pending','Accepted')");
    $stmt->execute([$id]);
    $cnt = (int)$stmt->fetchColumn();
    if ($cnt > 0) {
        SessionGuard::flashMessage('error', "Cannot delete: $cnt active booking(s) reference this service.");
    } else {
        // Hard delete
        $db->prepare("DELETE FROM services WHERE id=?")->execute([$id]);
        SessionGuard::flashMessage('success', 'Service deleted successfully.');
    }
}
header('Location: ' . BASE_URL . '/admin/services/index.php');
exit;
