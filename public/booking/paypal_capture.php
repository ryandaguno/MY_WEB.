<?php
/**
 * PayPal Capture Endpoint
 * Called via fetch() from paypal_pay.php AFTER the PayPal JS SDK
 * returns an approved order. We capture the order SERVER-SIDE via
 * the PayPal REST API before recording anything in the database.
 * A fake/replayed orderId will fail the server-side capture.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';

header('Content-Type: application/json');
SessionGuard::start();
SessionGuard::requireClient();

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$bookingId = (int)($data['booking_id'] ?? 0);
$orderId   = trim($data['order_id']   ?? '');

if (!$bookingId || !$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing payment data.']);
    exit;
}

$ph = new PaymentHandler();

// 1. Verify and capture the PayPal order server-side
$capture = $ph->capturePayPalOrder($orderId);
if (!$capture['success']) {
    echo json_encode(['success' => false, 'message' => $capture['message']]);
    exit;
}

// 2. Record the verified transaction and confirm booking
$result = $ph->handlePayPalReturn($bookingId, $capture['txn_id']);

if ($result['success']) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Could not record payment.']);
}
exit;
