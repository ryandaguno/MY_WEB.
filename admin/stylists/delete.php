<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../config/db.php';
SessionGuard::requireAdmin();
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE stylist_id = ? AND status IN ('Pending','Accepted')");
    $stmt->execute([$id]);
    $activeCount = (int)$stmt->fetchColumn();
    if ($activeCount > 0) {
        SessionGuard::flashMessage('error', "Cannot delete: $activeCount active booking(s) are assigned to this stylist.");
    } else {
        $db->prepare("DELETE FROM stylists WHERE id = ?")->execute([$id]);
        SessionGuard::flashMessage('success', 'Stylist deleted successfully.');
    }
}
header('Location: ' . BASE_URL . '/admin/stylists/index.php');
exit;
