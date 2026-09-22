<?php
/**
 * PayPal Return (fallback redirect flow)
 * This handles cases where PayPal redirects back via URL instead of JS SDK.
 * We do NOT trust any query param as proof of payment — we verify via API.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';
SessionGuard::start();
SessionGuard::requireClient();

$bookingId = (int)($_GET['booking_id'] ?? 0);
$orderId   = trim($_GET['token'] ?? ''); // PayPal returns order ID as 'token'

if (!$bookingId || !$orderId) {
    SessionGuard::flashMessage('error', 'Invalid payment return. Please try again.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$ph = new PaymentHandler();

// Server-side capture and verify
$capture = $ph->capturePayPalOrder($orderId);
if (!$capture['success']) {
    SessionGuard::flashMessage('error', 'PayPal payment could not be verified. Booking remains pending.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$result = $ph->handlePayPalReturn($bookingId, $capture['txn_id']);

if ($result['success']) {
    header('Location: ' . BASE_URL . '/public/booking/paypal_success.php?booking_id=' . $bookingId); exit;
} else {
    SessionGuard::flashMessage('error', 'Payment recorded but an error occurred: ' . ($result['message'] ?? 'Unknown'));
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}
