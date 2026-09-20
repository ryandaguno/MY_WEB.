<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/Validator.php';

class PaymentHandler {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function calculateDownpayment(float $price): int {
        return (int) ceil($price * 0.5);
    }

    // ----------------------------------------------------------------
    // GCASH RECEIPT UPLOAD
    // ----------------------------------------------------------------
    public function uploadGCashReceipt(array $file, int $bookingId): array {
        // Validate file
        $errors = \Validator::validateFile($file, ALLOWED_FILE_TYPES, MAX_FILE_SIZE);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Generate safe filename
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'gcash_' . $bookingId . '_' . uniqid() . '.' . $ext;
        $destPath = UPLOAD_PATH . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'errors' => ['File could not be saved. Please try again.']];
        }

        // Get booking downpayment amount
        $stmt = $this->db->prepare('SELECT downpayment_amount FROM bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        // Insert transaction
        $stmt = $this->db->prepare(
            'INSERT INTO transactions (booking_id, client_id, payment_method, amount, status, receipt_image)
             SELECT ?, client_id, \'GCash\', ?, \'Pending Verification\', ? FROM bookings WHERE id = ?'
        );
        $stmt->execute([$bookingId, $booking['downpayment_amount'], $filename, $bookingId]);

        return ['success' => true, 'transaction_id' => (int)$this->db->lastInsertId()];
    }

    // ----------------------------------------------------------------
    // PAYPAL RETURN HANDLER
    // ----------------------------------------------------------------
    public function handlePayPalReturn(int $bookingId, string $paypalTxnId): array {
        $stmt = $this->db->prepare('SELECT downpayment_amount, client_id FROM bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) return ['success' => false, 'message' => 'Booking not found.'];

        // Check if transaction already exists
        $stmt = $this->db->prepare('SELECT id FROM transactions WHERE booking_id = ? AND payment_method = \'PayPal\'');
        $stmt->execute([$bookingId]);
        if ($stmt->fetch()) return ['success' => true, 'already_recorded' => true];

        $stmt = $this->db->prepare(
            'INSERT INTO transactions (booking_id, client_id, payment_method, amount, status, paypal_transaction_id)
             VALUES (?, ?, \'PayPal\', ?, \'Paid\', ?)'
        );
        $stmt->execute([$bookingId, $booking['client_id'], $booking['downpayment_amount'], $paypalTxnId]);
        $txnId = (int)$this->db->lastInsertId();

        $ns = new NotificationService();
        $ns->sendPaymentConfirmation($bookingId);

        return ['success' => true, 'transaction_id' => $txnId];
    }

    // ----------------------------------------------------------------
    // GCASH VERIFY (Admin)
    // ----------------------------------------------------------------
    /**
     * Verify a GCash transaction with the actual amount received by the admin.
     *
     * Business rules:
     *   amount_received == expected  → Downpayment Paid
     *   amount_received >  expected  → Fully Paid
     *   amount_received <  expected  → Partial Payment
     */
    public function verifyGCash(int $transactionId, int $adminId, float $amountReceived = 0): array {
        $stmt = $this->db->prepare(
            'SELECT t.*, b.downpayment_amount, b.service_id, sv.price AS service_price
             FROM transactions t
             JOIN bookings b  ON b.id = t.booking_id
             JOIN services sv ON sv.id = b.service_id
             WHERE t.id = ? AND t.payment_method = \'GCash\''
        );
        $stmt->execute([$transactionId]);
        $txn = $stmt->fetch();

        if (!$txn) return ['success' => false, 'message' => 'Transaction not found.'];
        if (empty($txn['receipt_image'])) {
            return ['success' => false, 'message' => 'No receipt uploaded. Cannot verify.'];
        }
        if ($amountReceived <= 0) {
            return ['success' => false, 'message' => 'Please enter the actual amount received.'];
        }

        $expected = (float)$txn['downpayment_amount'];

        // Determine payment status based on amount entered by admin
        if ($amountReceived >= (float)$txn['service_price']) {
            $paymentStatus = 'Fully Paid';
        } elseif ($amountReceived >= $expected) {
            $paymentStatus = 'Downpayment Paid';
        } else {
            $paymentStatus = 'Partial Payment';
        }

        // Update transaction with actual amount and resolved status
        $this->db->prepare(
            'UPDATE transactions
             SET status = ?, amount = ?,
                 verified_at = NOW(), verified_by = ?
             WHERE id = ?'
        )->execute([$paymentStatus, $amountReceived, $adminId, $transactionId]);

        // Update booking status
        $bookingStatus = ($paymentStatus === 'Partial Payment') ? 'Pending' : 'Accepted';
        $this->db->prepare(
            'UPDATE bookings SET status = ? WHERE id = ?'
        )->execute([$bookingStatus, $txn['booking_id']]);

        $ns = new NotificationService();
        $ns->sendPaymentConfirmation((int)$txn['booking_id']);

        return [
            'success'        => true,
            'payment_status' => $paymentStatus,
            'booking_status' => $bookingStatus,
        ];
    }

    // ----------------------------------------------------------------
    // GCASH REJECT (Admin)
    // ----------------------------------------------------------------
    public function rejectGCash(int $transactionId): array {
        $stmt = $this->db->prepare('SELECT booking_id FROM transactions WHERE id = ?');
        $stmt->execute([$transactionId]);
        $txn = $stmt->fetch();
        if (!$txn) return ['success' => false, 'message' => 'Transaction not found.'];

        $this->db->prepare('UPDATE transactions SET status = \'Rejected\' WHERE id = ?')
                 ->execute([$transactionId]);

        $ns = new NotificationService();
        $ns->sendGCashRejected((int)$txn['booking_id']);

        return ['success' => true];
    }

    // ----------------------------------------------------------------
    // GET TRANSACTION for a booking
    // ----------------------------------------------------------------
    public function getTransactionByBooking(int $bookingId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM transactions WHERE booking_id = ? ORDER BY submitted_at DESC LIMIT 1');
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: null;
    }
}
