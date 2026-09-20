<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../config/db.php';
SessionGuard::requireAdmin();
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $db   = getDB();
    $stmt = $db->prepare("SELECT status FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if ($booking && in_array($booking['status'], ['Cancelled', 'Completed', 'Pending'])) {
        $db->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);
        SessionGuard::flashMessage('success', 'Booking #' . $id . ' permanently deleted.');
    } else {
        SessionGuard::flashMessage('error', 'Cannot delete an Accepted booking. Cancel it first.');
    }
}
header('Location: ' . BASE_URL . '/admin/bookings/index.php');
exit;
