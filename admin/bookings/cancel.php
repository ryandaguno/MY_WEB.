<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/BookingManager.php';
SessionGuard::requireAdmin();
$id     = (int)($_GET['id'] ?? 0);
$bm     = new BookingManager();
$result = $bm->cancelBooking($id, 'admin');
if ($result['success']) {
    SessionGuard::flashMessage('success', 'Booking #' . $id . ' cancelled. Client notified. Refund eligible.');
} else {
    SessionGuard::flashMessage('error', $result['message']);
}
header('Location: ' . BASE_URL . '/admin/bookings/index.php');
exit;
