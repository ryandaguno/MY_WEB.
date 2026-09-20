<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/BookingManager.php';
SessionGuard::requireAdmin();
$id     = (int)($_GET['id'] ?? 0);
$bm     = new BookingManager();
$result = $bm->acceptBooking($id);
if ($result['success']) {
    SessionGuard::flashMessage('success', 'Booking #' . $id . ' accepted and client notified.');
} else {
    SessionGuard::flashMessage('error', $result['message']);
}
header('Location: ' . BASE_URL . '/admin/bookings/index.php');
exit;
