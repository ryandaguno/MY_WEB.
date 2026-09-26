<?php
/**
 * GCash Payment Page for an existing booking.
 * Used when a client switches from PayPal to GCash,
 * or is sent here directly for a pending booking.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) {
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$db = getDB();

// Verify booking belongs to this client and is still pending
$stmt = $db->prepare(
    'SELECT b.id, b.downpayment_amount, b.status,
            s.name AS service_name, s.price,
            sc.slot_date, sc.start_time, st.name AS stylist_name
     FROM bookings b
     JOIN services  s  ON b.service_id  = s.id
     JOIN schedules sc ON b.schedule_id = sc.id
     JOIN stylists  st ON b.stylist_id  = st.id
     WHERE b.id = ? AND b.client_id = ?'
);
$stmt->execute([$bookingId, $_SESSION['client_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    SessionGuard::flashMessage('error', 'Booking not found.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

// Already has a GCash submission pending
$txStmt = $db->prepare(
    "SELECT id, status FROM transactions
     WHERE booking_id = ? AND payment_method = 'GCash'
     ORDER BY submitted_at DESC LIMIT 1"
);
$txStmt->execute([$bookingId]);
$existingTx = $txStmt->fetch();

if ($existingTx && in_array($existingTx['status'], ['Verified - Downpayment','Verified - Full','Paid'])) {
    SessionGuard::flashMessage('success', 'This booking is already paid.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

// Handle GCash upload POST
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');

    $ph = new PaymentHandler();
    $result = $ph->uploadGCashReceipt($_FILES['receipt'] ?? [], $bookingId);

    if (!$result['success']) {
        $error = implode(' ', $result['errors']);
    } else {
        $_SESSION['gcash_success_booking_id'] = $bookingId;
        header('Location: ' . BASE_URL . '/public/booking/gcash_success.php'); exit;
    }
}

$csrfToken = SessionGuard::generateCsrfToken();
$pageTitle = 'Pay with GCash';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.gc-page { background:#e8eaed; min-height:calc(100vh-56px); padding:40px 16px 80px; font-family:'Segoe UI',Arial,sans-serif; }
.gc-card { max-width:520px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 4px 24px rgba(0,0,0,.12); overflow:hidden; }
.gc-header { background:linear-gradient(135deg,#005f2f,#00a046); padding:24px 28px 20px; text-align:center; color:#fff; }
.gc-header h2 { font-size:1.2rem; font-weight:800; margin:0 0 4px; }
.gc-header p  { margin:0; font-size:.85rem; opacity:.85; }
.gc-body { padding:24px 28px 28px; }
.gc-row { display:flex; justify-content:space-between; font-size:.88rem; margin-bottom:7px; color:#444; }
.gc-row b { color:#111; }
.gc-divider { border:none; border-top:1.5px solid #eee; margin:14px 0; }
.gc-amount { text-align:center; font-size:1.3rem; font-weight:800; color:#005f2f; margin-bottom:4px; }
.gc-note   { font-size:.78rem; color:#888; text-align:center; margin-bottom:18px; }
.gc-instructions {
    background:#f0fdf4; border:1.5px solid #22c55e; border-radius:10px;
    padding:14px 16px; margin-bottom:18px; font-size:.85rem; color:#166534;
}
.gc-instructions ol { margin:8px 0 0 0; padding-left:18px; }
.gc-instructions li { margin-bottom:4px; }
.gc-number { font-size:1.1rem; font-weight:800; letter-spacing:1px; }
.gc-upload-label { font-weight:700; font-size:.85rem; color:#333; display:block; margin-bottom:6px; }
.gc-file-input { width:100%; padding:10px; border-radius:8px; border:1.5px solid #ccc; background:#f9f9f9; font-size:.85rem; }
.gc-file-input:focus { border-color:#005f2f; outline:none; }
.gc-submit { width:100%; padding:14px; border-radius:10px; border:none; background:#005f2f; color:#fff; font-size:.92rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; cursor:pointer; margin-top:14px; transition:background .15s; }
.gc-submit:hover { background:#004a24; }
.gc-back { display:block; text-align:center; margin-top:12px; color:#888; font-size:.82rem; text-decoration:none; }
.gc-back:hover { color:#333; }
.gc-error { background:#ffeaea; border:1.5px solid #ef4444; border-radius:8px; padding:10px 14px; color:#c0392b; font-size:.85rem; margin-bottom:14px; }
.gc-pending-notice { background:#fff8e1; border:1.5px solid #f59e0b; border-radius:8px; padding:10px 14px; font-size:.83rem; color:#856404; margin-bottom:14px; }
</style>

<div class="gc-page">
  <div class="gc-card">
    <div class="gc-header">
      <h2><i class="bi bi-qr-code me-2"></i>Pay with GCash</h2>
      <p>Booking #<?= $bookingId ?></p>
    </div>
    <div class="gc-body">

      <!-- Booking summary -->
      <div class="gc-row"><span>Service</span><b><?= htmlspecialchars($booking['service_name']) ?></b></div>
      <div class="gc-row"><span>Stylist</span><b><?= htmlspecialchars($booking['stylist_name']) ?></b></div>
      <div class="gc-row"><span>Date</span><b><?= date('F j, Y', strtotime($booking['slot_date'])) ?></b></div>
      <div class="gc-row"><span>Time</span><b><?= date('g:i A', strtotime($booking['start_time'])) ?></b></div>
      <hr class="gc-divider">
      <div class="gc-amount">₱<?= number_format($booking['downpayment_amount'], 2) ?></div>
      <div class="gc-note">50% downpayment required to confirm your slot</div>

      <?php if ($existingTx && $existingTx['status'] === 'Pending Verification'): ?>
      <div class="gc-pending-notice">
        <i class="bi bi-hourglass-split me-1"></i>
        <strong>You have already submitted a receipt.</strong> It is currently under review.
        You can submit a new one below to replace it if needed.
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="gc-error"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- GCash instructions -->
      <div class="gc-instructions">
        <strong><i class="bi bi-info-circle me-1"></i>How to pay:</strong>
        <ol>
          <li>Open your <strong>GCash app</strong></li>
          <li>Send <strong>₱<?= number_format($booking['downpayment_amount'], 2) ?></strong> to:<br>
            <span class="gc-number"><?= GCASH_NUMBER ?></span>
          </li>
          <li>Take a <strong>screenshot</strong> of your payment receipt</li>
          <li>Upload it below and click <strong>Submit Payment</strong></li>
        </ol>
      </div>

      <?php if (!empty(GCASH_QR_PATH)): ?>
      <div style="text-align:center;margin-bottom:16px">
        <?php
        $qrImagePath = __DIR__ . '/../../assets/images/gcash_qr.png';
        if (file_exists($qrImagePath)): ?>
          <img src="<?= BASE_URL ?>/assets/images/gcash_qr.png"
               alt="GCash QR Code"
               style="max-width:180px;border-radius:10px;border:2px solid #e0e0e0">
          <div style="font-size:.75rem;color:#888;margin-top:4px">Scan to pay via GCash</div>
        <?php else: ?>
          <!-- No QR uploaded yet — show number prominently -->
          <div style="background:#f0fdf4;border:2px solid #22c55e;border-radius:12px;padding:16px 20px;display:inline-block">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#166534;margin-bottom:4px">
              Send to GCash Number
            </div>
            <div style="font-size:1.6rem;font-weight:900;letter-spacing:3px;color:#005f2f">
              <?= GCASH_NUMBER ?>
            </div>
          </div>
          <div style="font-size:.75rem;color:#888;margin-top:8px">
            <i class="bi bi-info-circle me-1"></i>QR code coming soon — use the number above to send payment
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Upload form -->
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <label class="gc-upload-label">
          <i class="bi bi-camera me-1"></i>Upload Payment Screenshot *
        </label>
        <input type="file" name="receipt" class="gc-file-input"
               accept="image/jpeg,image/png,image/gif" required>
        <div style="font-size:.73rem;color:#888;margin-top:4px">JPEG, PNG or GIF — max 5 MB</div>
        <button type="submit" class="gc-submit">
          <i class="bi bi-check-circle me-1"></i>Submit Payment Receipt
        </button>
      </form>

      <a href="<?= BASE_URL ?>/public/my_bookings.php" class="gc-back">
        ← Go to My Bookings (pay later)
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
