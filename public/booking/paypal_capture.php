<?php
/**
 * PayPal Capture — called via fetch() after PayPal JS SDK approves.
 * Captures PayPal order server-side FIRST, then creates the booking.
 * No booking is created unless PayPal confirms COMPLETED.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';
require_once __DIR__ . '/../../modules/BookingManager.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');
SessionGuard::start();
SessionGuard::requireClient();

$raw     = file_get_contents('php://input');
$data    = json_decode($raw, true);
$orderId = trim($data['order_id'] ?? '');

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing PayPal order ID.']);
    exit;
}

// Get pending booking details from session
$pending = $_SESSION['paypal_pending'] ?? null;
if (!$pending) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
    exit;
}

$ph = new PaymentHandler();

// 1. Capture PayPal order server-side — only proceed if COMPLETED
$capture = $ph->capturePayPalOrder($orderId);
if (!$capture['success']) {
    echo json_encode(['success' => false, 'message' => $capture['message']]);
    exit;
}

// 2. PayPal confirmed — NOW create the booking
$bm     = new BookingManager();
$result = $bm->createBooking([
    'client_id'   => (int)$pending['client_id'],
    'service_id'  => (int)$pending['service_id'],
    'stylist_id'  => (int)$pending['stylist_id'],
    'schedule_id' => (int)$pending['schedule_id'],
    'notes'       => $pending['notes'] ?? '',
]);

if (!$result['success']) {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Could not create booking.']);
    exit;
}

$bookingId = $result['booking_id'];

// 3. Record the PayPal transaction
$txResult = $ph->handlePayPalReturn($bookingId, $capture['txn_id']);

// 4. Clear pending session
unset($_SESSION['paypal_pending']);
$_SESSION['paypal_success_booking_id'] = $bookingId;

echo json_encode(['success' => true, 'booking_id' => $bookingId]);
exit;
