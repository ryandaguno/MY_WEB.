<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';
SessionGuard::requireClient();

$bookingId = (int)($_GET['booking_id'] ?? 0);
$paypalTxn = $_GET['tx'] ?? $_GET['PayerID'] ?? uniqid('PP_');

if (!$bookingId) {
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$ph     = new PaymentHandler();
$result = $ph->handlePayPalReturn($bookingId, $paypalTxn);

unset($_SESSION['booking']);

if ($result['success']) {
    SessionGuard::flashMessage('success', 'Payment confirmed! Your booking is now secured.');
} else {
    SessionGuard::flashMessage('error', 'Payment could not be verified. Please contact us.');
}
header('Location: ' . BASE_URL . '/public/my_bookings.php');
exit;
