<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/Validator.php';

class PaymentHandler {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
        // Ensure transactions ENUM has all required values
        try {
            $this->db->exec("
                ALTER TABLE transactions MODIFY COLUMN status
                ENUM(
                    'Pending Verification',
                    'Verified - Downpayment',
                    'Verified - Full',
                    'Verified - Partial',
                    'Paid',
                    'Rejected',
                    'Failed'
                ) NOT NULL DEFAULT 'Pending Verification'
            ");
        } catch (PDOException $e) { /* already correct */ }

        // Add receipt_data column for DB-stored image (survives Railway restarts)
        try {
            $this->db->exec(
                "ALTER TABLE transactions ADD COLUMN receipt_data MEDIUMBLOB NULL"
            );
        } catch (PDOException $e) { /* already exists */ }
        try {
            $this->db->exec(
                "ALTER TABLE transactions ADD COLUMN receipt_mime VARCHAR(50) NULL"
            );
        } catch (PDOException $e) { /* already exists */ }
    }

    // ----------------------------------------------------------------
    // Calculate 50% downpayment
    // ----------------------------------------------------------------
    public function calculateDownpayment(float $price): int {
        return (int) ceil($price * 0.5);
    }

    // ----------------------------------------------------------------
    // GCASH RECEIPT UPLOAD
    // Creates booking with Pending status + transaction with
    // "Pending Verification" — never auto-confirms anything.
    // ----------------------------------------------------------------
    public function uploadGCashReceipt(array $file, int $bookingId): array {
        // Must have a file
        if (empty($file['tmp_name'])) {
            return ['success' => false, 'errors' => ['Please upload your GCash payment screenshot.']];
        }

        $errors = \Validator::validateFile($file, ALLOWED_FILE_TYPES, MAX_FILE_SIZE);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check for duplicate transaction for this booking (prevent double-submit)
        $dup = $this->db->prepare(
            "SELECT id FROM transactions WHERE booking_id = ? AND payment_method = 'GCash'"
        );
        $dup->execute([$bookingId]);
        if ($dup->fetch()) {
            return ['success' => false, 'errors' => ['A payment submission for this booking already exists.']];
        }

        // Save file to disk (best effort — may not survive Railway restarts)
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'gcash_' . $bookingId . '_' . uniqid() . '.' . $ext;
        $destPath = UPLOAD_PATH . $filename;

        // Ensure upload directory exists
        if (!is_dir(UPLOAD_PATH)) {
            @mkdir(UPLOAD_PATH, 0777, true);
        }

        $savedToDisk = @move_uploaded_file($file['tmp_name'], $destPath);

        // Read image binary data to store in DB (survives Railway restarts)
        $imageData = null;
        $imageMime = null;

        if ($savedToDisk && file_exists($destPath)) {
            $imageData = file_get_contents($destPath);
        } else {
            // tmp_name is still readable if move failed
            $tmpPath = $file['tmp_name'] ?? '';
            if ($tmpPath && file_exists($tmpPath)) {
                $imageData = file_get_contents($tmpPath);
            }
        }

        if ($imageData) {
            $finfo     = finfo_open(FILEINFO_MIME_TYPE);
            $imageMime = finfo_buffer($finfo, $imageData);
            finfo_close($finfo);
        }

        if (!$imageData) {
            return ['success' => false, 'errors' => ['Could not read the uploaded file. Please try again.']];
        }

        // Fetch downpayment amount from booking
        $stmt = $this->db->prepare('SELECT downpayment_amount, client_id FROM bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            return ['success' => false, 'errors' => ['Booking not found.']];
        }

        // Insert transaction — status stays "Pending Verification"
        // Booking status stays "Pending" — never auto-confirmed
        $stmt = $this->db->prepare(
            "INSERT INTO transactions
                (booking_id, client_id, payment_method, amount, status, receipt_image, receipt_data, receipt_mime)
             VALUES (?, ?, 'GCash', ?, 'Pending Verification', ?, ?, ?)"
        );
        $stmt->execute([
            $bookingId,
            $booking['client_id'],
            $booking['downpayment_amount'],
            $savedToDisk ? $filename : null,
            $imageData,
            $imageMime,
        ]);

        return ['success' => true, 'transaction_id' => (int)$this->db->lastInsertId()];
    }

    // ----------------------------------------------------------------
    // GCASH VERIFY (Admin)
    // Only called after admin manually reviews the receipt.
    // ----------------------------------------------------------------
    public function verifyGCash(int $transactionId, int $adminId, float $amountReceived = 0): array {
        $stmt = $this->db->prepare(
            "SELECT t.*, b.downpayment_amount, b.client_id,
                    sv.price AS service_price
             FROM transactions t
             JOIN bookings b  ON b.id  = t.booking_id
             JOIN services sv ON sv.id = b.service_id
             WHERE t.id = ? AND t.payment_method = 'GCash'"
        );
        $stmt->execute([$transactionId]);
        $txn = $stmt->fetch();

        if (!$txn) {
            return ['success' => false, 'message' => 'Transaction not found.'];
        }
        if ($txn['status'] !== 'Pending Verification') {
            return ['success' => false, 'message' => 'This transaction has already been processed.'];
        }
        if (empty($txn['receipt_image'])) {
            return ['success' => false, 'message' => 'No receipt uploaded. Cannot verify without proof.'];
        }
        if ($amountReceived <= 0) {
            return ['success' => false, 'message' => 'Please enter the actual amount received.'];
        }

        $expected = (float)$txn['downpayment_amount'];
        $svcPrice = (float)$txn['service_price'];

        if ($amountReceived >= $svcPrice) {
            $txnStatus     = 'Verified - Full';
            $bookingStatus = 'Accepted';
        } elseif ($amountReceived >= $expected) {
            $txnStatus     = 'Verified - Downpayment';
            $bookingStatus = 'Accepted';
        } else {
            $txnStatus     = 'Verified - Partial';
            $bookingStatus = 'Pending'; // partial — not enough to confirm
        }

        // Update transaction
        $this->db->prepare(
            "UPDATE transactions
             SET status = ?, amount = ?, verified_at = NOW(), verified_by = ?
             WHERE id = ?"
        )->execute([$txnStatus, $amountReceived, $adminId, $transactionId]);

        // Update booking
        $this->db->prepare("UPDATE bookings SET status = ? WHERE id = ?")
                 ->execute([$bookingStatus, $txn['booking_id']]);

        // Notify client
        $ns = new NotificationService();
        $ns->sendPaymentConfirmation((int)$txn['booking_id']);

        return [
            'success'        => true,
            'payment_status' => $txnStatus,
            'booking_status' => $bookingStatus,
        ];
    }

    // ----------------------------------------------------------------
    // GCASH REJECT (Admin)
    // ----------------------------------------------------------------
    public function rejectGCash(int $transactionId): array {
        $stmt = $this->db->prepare(
            "SELECT booking_id, status FROM transactions WHERE id = ?"
        );
        $stmt->execute([$transactionId]);
        $txn = $stmt->fetch();

        if (!$txn) {
            return ['success' => false, 'message' => 'Transaction not found.'];
        }
        if (!in_array($txn['status'], ['Pending Verification', 'Verified - Partial'])) {
            return ['success' => false, 'message' => 'This transaction cannot be rejected in its current state.'];
        }

        $this->db->prepare("UPDATE transactions SET status = 'Rejected' WHERE id = ?")
                 ->execute([$transactionId]);

        // Booking stays Pending so client can resubmit
        $ns = new NotificationService();
        $ns->sendGCashRejected((int)$txn['booking_id']);

        return ['success' => true];
    }

    // ----------------------------------------------------------------
    // PAYPAL — Server-side capture + verify via PayPal REST API
    // ----------------------------------------------------------------
    public function capturePayPalOrder(string $orderId): array {
        // Get PayPal access token
        $credentials = base64_encode(PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET);
        $baseUrl     = PAYPAL_MODE === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        // Request access token
        $ch = curl_init($baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $tokenRes = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (empty($tokenRes['access_token'])) {
            return ['success' => false, 'message' => 'Could not authenticate with PayPal.'];
        }

        $accessToken = $tokenRes['access_token'];

        // Capture the order server-side
        $ch = curl_init($baseUrl . '/v2/checkout/orders/' . $orderId . '/capture');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);
        $captureRes = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (
            isset($captureRes['status']) &&
            $captureRes['status'] === 'COMPLETED'
        ) {
            $paypalTxnId = $captureRes['purchase_units'][0]['payments']['captures'][0]['id']
                           ?? $orderId;
            return ['success' => true, 'txn_id' => $paypalTxnId, 'response' => $captureRes];
        }

        return [
            'success' => false,
            'message' => 'PayPal capture failed: ' . ($captureRes['message'] ?? 'Unknown error'),
        ];
    }

    // ----------------------------------------------------------------
    // PAYPAL RETURN HANDLER — only called after server-side capture
    // ----------------------------------------------------------------
    public function handlePayPalReturn(int $bookingId, string $paypalTxnId): array {
        $stmt = $this->db->prepare(
            'SELECT downpayment_amount, client_id FROM bookings WHERE id = ?'
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            return ['success' => false, 'message' => 'Booking not found.'];
        }

        // Guard against duplicate
        $dup = $this->db->prepare(
            "SELECT id FROM transactions WHERE booking_id = ? AND payment_method = 'PayPal'"
        );
        $dup->execute([$bookingId]);
        if ($dup->fetch()) {
            return ['success' => true, 'already_recorded' => true];
        }

        // Record transaction as Paid (PayPal is auto-verified by capture)
        $this->db->prepare(
            "INSERT INTO transactions
                (booking_id, client_id, payment_method, amount, status, paypal_transaction_id, verified_at)
             VALUES (?, ?, 'PayPal', ?, 'Paid', ?, NOW())"
        )->execute([
            $bookingId,
            $booking['client_id'],
            $booking['downpayment_amount'],
            $paypalTxnId,
        ]);

        // Confirm booking — PayPal is instantly verified
        $this->db->prepare("UPDATE bookings SET status = 'Accepted' WHERE id = ?")
                 ->execute([$bookingId]);

        $ns = new NotificationService();
        $ns->sendPaymentConfirmation($bookingId);

        return ['success' => true, 'transaction_id' => (int)$this->db->lastInsertId()];
    }

    // ----------------------------------------------------------------
    // GET transaction by booking
    // ----------------------------------------------------------------
    public function getTransactionByBooking(int $bookingId): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM transactions WHERE booking_id = ? ORDER BY submitted_at DESC LIMIT 1'
        );
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: null;
    }
}
