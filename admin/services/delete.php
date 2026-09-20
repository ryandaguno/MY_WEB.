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
        // Soft delete
        $db->prepare("UPDATE services SET is_active=0 WHERE id=?")->execute([$id]);
        SessionGuard::flashMessage('success', 'Service removed from catalog.');
    }
}
header('Location: ' . BASE_URL . '/admin/services/index.php');
exit;
