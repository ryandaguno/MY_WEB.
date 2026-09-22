<?php
/**
 * Cancel a pending PayPal booking so the client can start fresh.
 * Only cancels if booking is still Pending and has no completed payment.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';

$bookingId = (int)($_GET['booking_id'] ?? 0);

if ($bookingId) {
    $db = getDB();

    // Only cancel if it belongs to this client, is still Pending, and has no verified payment
    $stmt = $db->prepare(
        "SELECT b.id, b.schedule_id, b.status
         FROM bookings b
         WHERE b.id = ? AND b.client_id = ? AND b.status = 'Pending'"
    );
    $stmt->execute([$bookingId, $_SESSION['client_id']]);
    $booking = $stmt->fetch();

    if ($booking) {
        // Check no verified/paid transaction exists
        $txStmt = $db->prepare(
            "SELECT id FROM transactions WHERE booking_id = ?
             AND status IN ('Paid','Verified - Downpayment','Verified - Full')"
        );
        $txStmt->execute([$bookingId]);

        if (!$txStmt->fetch()) {
            // Safe to cancel — free the slot
            $db->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?")
               ->execute([$bookingId]);
            $db->prepare("UPDATE schedules SET is_available = 1 WHERE id = ?")
               ->execute([$booking['schedule_id']]);
        }
    }

    // Clear any session leftovers
    unset($_SESSION['paypal_pending_booking_id']);
    unset($_SESSION['booking']);
}

// Redirect to step 1 to book again
header('Location: ' . BASE_URL . '/public/booking/step1_service.php');
exit;
