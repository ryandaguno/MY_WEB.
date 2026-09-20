<?php
/**
 * PayPal Capture Endpoint
 * Called via fetch() from paypal_pay.php after PayPal JS SDK approves the order.
 * Records the transaction and marks the booking payment as Paid.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';

header('Content-Type: application/json');
SessionGuard::requireClient();

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$bookingId = (int)($data['booking_id'] ?? 0);
$orderId   = trim($data['order_id']   ?? '');
$txnId     = trim($data['txn_id']     ?? $orderId);

if (!$bookingId || !$txnId) {
    echo json_encode(['success' => false, 'message' => 'Missing payment data.']);
    exit;
}

$ph     = new PaymentHandler();
$result = $ph->handlePayPalReturn($bookingId, $txnId);

if ($result['success']) {
    SessionGuard::flashMessage('success', 'PayPal payment confirmed! Your booking is now secured.');
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Could not record payment.']);
}
exit;
