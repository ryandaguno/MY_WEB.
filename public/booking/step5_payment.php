<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/BookingManager.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';

$bk = $_SESSION['booking'] ?? [];
if (empty($bk['schedule_id'])) {
    header('Location: ' . BASE_URL . '/public/booking/step3_datetime.php'); exit;
}

$db = getDB();

$svcStmt = $db->prepare('SELECT * FROM services WHERE id = ?');
$svcStmt->execute([(int)$bk['service_id']]);
$service = $svcStmt->fetch();

$schStmt = $db->prepare('SELECT * FROM schedules WHERE id = ?');
$schStmt->execute([(int)$bk['schedule_id']]);
$schedule = $schStmt->fetch();

if ($bk['stylist_id'] !== 'any' && is_numeric($bk['stylist_id'])) {
    $stStmt = $db->prepare('SELECT * FROM stylists WHERE id = ?');
    $stStmt->execute([(int)$bk['stylist_id']]);
    $stylist = $stStmt->fetch();
} else {
    $stylist = ['name' => 'First Available Stylist', 'specialty' => $service['category']];
}

$clientStmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
$clientStmt->execute([$_SESSION['client_id']]);
$client = $clientStmt->fetch();

$ph          = new PaymentHandler();
$downpayment = $ph->calculateDownpayment((float)$service['price']);
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');

    $paymentMethod = $_POST['payment_method'] ?? '';

    // ── Validate BEFORE creating booking ──────────────────────────
    if (!$paymentMethod) {
        $error = 'Please select a payment method (GCash or PayPal).';
    } elseif ($paymentMethod === 'gcash') {
        if (empty($_FILES['receipt']['tmp_name']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please upload your GCash payment screenshot before confirming.';
        }
    }

    if (!$error) {
        $bm        = new BookingManager();
        $stylistId = ($bk['stylist_id'] === 'any') ? 0 : (int)$bk['stylist_id'];

        if ($stylistId === 0) {
            $anyStmt = $db->prepare(
                'SELECT st.id FROM stylists st
                 JOIN schedules sc ON sc.stylist_id = st.id
                 WHERE sc.id = ? AND st.is_active = 1
                 AND sc.id NOT IN (SELECT schedule_id FROM bookings WHERE status IN (\'Pending\',\'Accepted\'))
                 LIMIT 1'
            );
            $anyStmt->execute([(int)$bk['schedule_id']]);
            $anyRow    = $anyStmt->fetch();
            $stylistId = $anyRow ? (int)$anyRow['id'] : 0;
            if (!$stylistId) {
                $error = 'No stylists available for the selected time slot. Please choose another slot.';
            }
        }
    }

    if (!$error) {
        if ($paymentMethod === 'gcash') {
            // GCash: create booking + upload receipt together.
            // If receipt upload fails, booking is immediately cancelled.
            $result = $bm->createBooking([
                'client_id'   => $_SESSION['client_id'],
                'service_id'  => (int)$bk['service_id'],
                'stylist_id'  => $stylistId,
                'schedule_id' => (int)$bk['schedule_id'],
                'notes'       => $bk['notes'] ?? '',
            ]);

            if (!$result['success']) {
                if ($result['conflict'] ?? false) {
                    SessionGuard::flashMessage('error', $result['message']);
                    header('Location: ' . BASE_URL . '/public/booking/step3_datetime.php'); exit;
                }
                $error = $result['message'];
            } else {
                $bookingId    = $result['booking_id'];
                $uploadResult = $ph->uploadGCashReceipt($_FILES['receipt'] ?? [], $bookingId);
                if (!$uploadResult['success']) {
                    // Cancel booking immediately — slot freed, nothing saved
                    $db->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?")
                       ->execute([$bookingId]);
                    $db->prepare("UPDATE schedules SET is_available = 1 WHERE id = ?")
                       ->execute([(int)$bk['schedule_id']]);
                    $error = implode(' ', $uploadResult['errors']);
                } else {
                    unset($_SESSION['booking']);
                    $_SESSION['gcash_success_booking_id'] = $bookingId;
                    header('Location: ' . BASE_URL . '/public/booking/gcash_success.php'); exit;
                }
            }

        } elseif ($paymentMethod === 'paypal') {
            // PayPal: do NOT create booking yet.
            // Store booking details in session — booking only created after
            // PayPal capture succeeds in paypal_capture.php.
            $_SESSION['paypal_pending'] = [
                'client_id'   => $_SESSION['client_id'],
                'service_id'  => (int)$bk['service_id'],
                'stylist_id'  => $stylistId,
                'schedule_id' => (int)$bk['schedule_id'],
                'notes'       => $bk['notes'] ?? '',
                'downpayment' => $downpayment,
            ];
            unset($_SESSION['booking']);
            header('Location: ' . BASE_URL . '/public/booking/paypal_pay.php'); exit;
        }
    }
}

$csrfToken = SessionGuard::generateCsrfToken();
$pageTitle = 'Step 5: Confirm Booking';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.s5-page  { background:#e8eaed; min-height:calc(100vh - 56px); padding:32px 16px 60px; font-family:'Segoe UI',Arial,sans-serif; }
.s5-outer { max-width:900px; margin:0 auto; }
.s5-title { text-align:center; font-size:.92rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#111; margin-bottom:22px; }
.s5-cols  { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
.s5-summary-card { background:#f5f5f5; border-radius:14px; padding:22px 20px; box-shadow:0 4px 24px rgba(0,0,0,.10); }
.s5-section-title { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#555; margin:14px 0 8px; padding-bottom:4px; border-bottom:1.5px solid #ddd; }
.s5-section-title:first-child { margin-top:0; }
.s5-row { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:6px; font-size:.85rem; }
.s5-row span { color:#666; }
.s5-row b    { color:#111; font-weight:700; text-align:right; max-width:55%; }
.s5-pay-card { background:#f5f5f5; border-radius:14px; padding:22px 20px; box-shadow:0 4px 24px rgba(0,0,0,.10); display:flex; flex-direction:column; }
.s5-downpay-box { background:#d6ffe0; border:1.5px solid #27ae36; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:.88rem; }
.s5-downpay-box b { font-size:1rem; color:#1a8c2a; }
.s5-policy { background:#fff8e1; border-left:3px solid #f59e0b; border-radius:0 6px 6px 0; padding:9px 12px; font-size:.78rem; color:#555; margin-bottom:16px; }
.s5-method-card { border:2px solid #ccc; border-radius:10px; padding:14px 16px; margin-bottom:10px; cursor:pointer; transition:border-color .15s,background .15s; background:#fff; }
.s5-method-card:hover  { border-color:#2ecc40; }
.s5-method-card.active { border-color:#2ecc40; background:#f0fff2; }
.s5-method-header { display:flex; align-items:center; gap:10px; font-size:.88rem; font-weight:700; color:#222; }
.s5-method-body { margin-top:12px; font-size:.82rem; }
.s5-confirm-btn { margin-top:auto; padding:15px; border-radius:10px; border:none; background:#2ecc40; color:#fff; font-size:.92rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; cursor:pointer; transition:background .15s; width:100%; }
.s5-confirm-btn:hover:not(:disabled) { background:#27ae36; }
.s5-confirm-btn:disabled { background:#a8d5ab; cursor:not-allowed; }
.s5-back-link { display:block; margin-top:10px; text-align:center; color:#666; font-size:.82rem; font-weight:600; text-decoration:none; }
.s5-back-link:hover { color:#333; }
.s5-file { width:100%; padding:8px; border-radius:6px; border:1.5px solid #ccc; background:#fff; font-size:.82rem; }
@media(max-width:640px){ .s5-cols { grid-template-columns:1fr; } }
</style>

<div class="s5-page">
  <div class="s5-outer">
    <div class="s5-title">Step 5: Confirm Your Booking</div>

    <?php if ($error): ?>
    <div style="background:#ffeaea;border:1.5px solid #e74c3c;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.88rem;color:#c0392b;">
      <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="s5-cols">

      <div class="s5-summary-card">
        <div class="s5-section-title">Service Details</div>
        <div class="s5-row"><span>Service</span><b><?= htmlspecialchars($service['name']) ?></b></div>
        <div class="s5-row"><span>Price</span><b>₱<?= number_format($service['price'],2) ?></b></div>
        <div class="s5-row"><span>Duration</span><b><?= (int)$service['duration_minutes'] ?> min</b></div>

        <div class="s5-section-title">Stylist</div>
        <div class="s5-row"><span>Name</span><b><?= htmlspecialchars($stylist['name']) ?></b></div>
        <div class="s5-row"><span>Specialty</span><b><?= htmlspecialchars($stylist['specialty']) ?></b></div>

        <div class="s5-section-title">Appointment</div>
        <div class="s5-row"><span>Date</span><b><?= date('F j, Y', strtotime($schedule['slot_date'])) ?></b></div>
        <div class="s5-row"><span>Time</span><b><?= date('g:i A', strtotime($schedule['start_time'])) ?></b></div>

        <div class="s5-section-title">Your Details</div>
        <div class="s5-row"><span>Name</span><b><?= htmlspecialchars($client['username']) ?></b></div>
        <div class="s5-row"><span>Phone</span><b><?= htmlspecialchars($client['phone']) ?></b></div>
        <div class="s5-row"><span>Email</span><b><?= htmlspecialchars($client['email']) ?></b></div>
      </div>

      <div class="s5-pay-card">
        <div class="s5-section-title" style="margin-top:0">Secure Your Booking</div>

        <div class="s5-downpay-box">
          <b>Downpayment: ₱<?= number_format($downpayment,2) ?></b><br>
          <small>50% of ₱<?= number_format($service['price'],2) ?> — required to confirm your slot</small>
        </div>

        <div class="s5-policy">
          <i class="bi bi-info-circle me-1"></i>
          <strong>Cancellation Policy:</strong> Cancellations within 48 hours will forfeit the downpayment.
        </div>

        <form method="post" enctype="multipart/form-data" id="payForm">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

          <div class="s5-method-card active" id="gcashCard" onclick="selectPayment('gcash')">
            <div class="s5-method-header">
              <input type="radio" name="payment_method" value="gcash" id="payGcash" checked>
              <label for="payGcash" style="cursor:pointer;margin:0"><i class="bi bi-qr-code me-1"></i>Pay with GCash</label>
            </div>
            <div class="s5-method-body" id="gcashSection">
              <div style="background:#e8f5e9;border:1.5px solid #2ecc40;border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:.82rem;color:#1a6b1a;">
                <i class="bi bi-info-circle-fill me-1"></i>
                <strong>How to pay:</strong> Send ₱<?= number_format($downpayment,2) ?> to GCash number <strong><?= GCASH_NUMBER ?></strong>, then take a screenshot of your receipt and upload it below.
              </div>
              <p class="mb-1">GCash Number: <strong><?= GCASH_NUMBER ?></strong></p>
              <img src="<?= BASE_URL ?>/public/gcash_qr.php"
                   alt="GCash QR Code"
                   style="max-width:160px;border-radius:8px;display:block;margin:8px 0;border:2px solid #e0e0e0"
                   onerror="this.style.display='none'">
              <label style="display:block;font-weight:600;margin-bottom:4px">Upload Payment Screenshot *</label>
              <input type="file" name="receipt" class="s5-file" accept="image/jpeg,image/png,image/gif" id="gcashReceiptFile">
              <div style="color:#888;font-size:.75rem;margin-top:3px">JPEG, PNG or GIF — max 5 MB</div>
            </div>
          </div>

          <div class="s5-method-card" id="paypalCard" onclick="selectPayment('paypal')">
            <div class="s5-method-header">
              <input type="radio" name="payment_method" value="paypal" id="payPaypal">
              <label for="payPaypal" style="cursor:pointer;margin:0"><i class="bi bi-paypal me-1"></i>Pay with PayPal</label>
            </div>
            <div id="paypalSection" style="display:none;margin-top:10px;font-size:.82rem;color:#555;">
              <i class="bi bi-box-arrow-up-right me-1"></i>You will be redirected to PayPal to complete your downpayment of <strong>₱<?= number_format($downpayment,2) ?></strong>.
            </div>
          </div>

          <button type="submit" class="s5-confirm-btn" id="confirmBtn">
            <i class="bi bi-check-circle me-1" id="confirmIcon"></i>
            <span id="confirmLabel">Confirm Booking</span>
          </button>
        </form>

        <a href="javascript:history.back()" class="s5-back-link">← Back</a>
      </div>

    </div>
  </div>
</div>

<script>
function selectPayment(method) {
  ['gcash','paypal'].forEach(function(m) {
    document.getElementById(m + 'Card').classList.toggle('active', m === method);
  });
  document.getElementById('payGcash').checked  = (method === 'gcash');
  document.getElementById('payPaypal').checked = (method === 'paypal');
  document.getElementById('gcashSection').style.display  = method === 'gcash'  ? 'block' : 'none';
  document.getElementById('paypalSection').style.display = method === 'paypal' ? 'block' : 'none';

  var btn   = document.getElementById('confirmBtn');
  var icon  = document.getElementById('confirmIcon');
  var label = document.getElementById('confirmLabel');

  btn.disabled = false;

  if (method === 'paypal') {
    icon.className    = 'bi bi-paypal me-1';
    label.textContent = 'Continue to PayPal';
    btn.style.background = '#003087';
  } else {
    icon.className    = 'bi bi-check-circle me-1';
    label.textContent = 'Confirm Booking';
    btn.style.background = '';
  }
}
// Prevent form submit if GCash selected but no file uploaded
document.getElementById('payForm').addEventListener('submit', function(e) {
  var method = document.querySelector('input[name="payment_method"]:checked');
  if (!method) {
    e.preventDefault();
    alert('Please select a payment method.');
    return;
  }
  if (method.value === 'gcash') {
    var file = document.getElementById('gcashReceiptFile');
    if (!file || !file.files || file.files.length === 0) {
      e.preventDefault();
      alert('Please upload your GCash payment screenshot before confirming.');
      file.focus();
      return;
    }
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
