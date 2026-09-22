<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) {
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$db = getDB();

// Verify this booking belongs to the current client and is still pending
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

// Already paid — redirect back
$txStmt = $db->prepare(
    "SELECT id FROM transactions WHERE booking_id = ? AND payment_method = 'PayPal'
     AND status IN ('Paid','Verified - Full','Verified - Downpayment')"
);
$txStmt->execute([$bookingId]);
if ($txStmt->fetch()) {
    SessionGuard::flashMessage('success', 'This booking has already been paid via PayPal.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$downpayment   = number_format($booking['downpayment_amount'], 2, '.', '');
$hasCredentials = (
    PAYPAL_CLIENT_ID !== 'YOUR_PAYPAL_CLIENT_ID' &&
    PAYPAL_CLIENT_ID !== '' &&
    PAYPAL_SECRET    !== 'YOUR_PAYPAL_SECRET' &&
    PAYPAL_SECRET    !== ''
);

$pageTitle = 'Pay with PayPal';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.pp-page  { background:#e8eaed; min-height:calc(100vh-56px); padding:40px 16px; font-family:'Segoe UI',Arial,sans-serif; }
.pp-card  { max-width:480px; margin:0 auto; background:#fff; border-radius:14px;
            box-shadow:0 4px 24px rgba(0,0,0,.12); padding:30px 28px; }
.pp-title { font-size:1.1rem; font-weight:800; color:#003087; margin-bottom:20px; text-align:center; }
.pp-row   { display:flex; justify-content:space-between; font-size:.88rem; margin-bottom:8px; color:#444; }
.pp-row b { color:#111; }
.pp-divider { border:none; border-top:1.5px solid #eee; margin:16px 0; }
.pp-amount  { text-align:center; font-size:1.4rem; font-weight:800; color:#003087; margin-bottom:6px; }
.pp-note    { font-size:.78rem; color:#888; text-align:center; margin-bottom:20px; }
#paypal-button-container { min-height:50px; }
.pp-cancel  { display:block; text-align:center; margin-top:14px; color:#888; font-size:.82rem; text-decoration:none; }
.pp-cancel:hover { color:#333; }
.pp-loading { text-align:center; color:#888; font-size:.85rem; padding:16px 0; }
</style>

<div class="pp-page">
  <div class="pp-card">
    <div class="pp-title"><i class="bi bi-paypal me-2"></i>Complete PayPal Payment</div>

    <div class="pp-row"><span>Service</span><b><?= htmlspecialchars($booking['service_name']) ?></b></div>
    <div class="pp-row"><span>Stylist</span><b><?= htmlspecialchars($booking['stylist_name']) ?></b></div>
    <div class="pp-row"><span>Date</span><b><?= date('F j, Y', strtotime($booking['slot_date'])) ?></b></div>
    <div class="pp-row"><span>Time</span><b><?= date('g:i A', strtotime($booking['start_time'])) ?></b></div>
    <hr class="pp-divider">
    <div class="pp-amount">Downpayment: ₱<?= number_format($booking['downpayment_amount'], 2) ?></div>
    <div class="pp-note">50% downpayment required to confirm your slot</div>

    <?php if (!$hasCredentials): ?>
    <!-- PayPal not configured — show admin notice -->
    <div style="background:#fff3cd;border:1.5px solid #f59e0b;border-radius:10px;
                padding:16px 18px;text-align:center;font-size:.88rem;color:#856404;margin-bottom:16px">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>PayPal is not configured yet.</strong><br>
      Please use <strong>GCash</strong> to complete your payment, or contact the salon.<br>
      <a href="<?= BASE_URL ?>/public/booking/step5_payment.php"
         class="btn btn-sm mt-2" style="background:#6B2D8B;color:#fff;border-radius:8px">
        ← Back to Payment Options
      </a>
    </div>
    <?php else: ?>
    <div class="pp-loading" id="pp-loading">
      <i class="bi bi-hourglass-split me-1"></i>Loading PayPal…
    </div>
    <div id="paypal-button-container"></div>
    <div id="pp-error"
         style="display:none;color:#c0392b;font-size:.85rem;text-align:center;margin-top:10px;
                background:#ffeaea;border-radius:8px;padding:10px"></div>
    <div id="pp-processing"
         style="display:none;text-align:center;padding:16px;color:#10b981;font-weight:700">
      <i class="bi bi-check-circle-fill me-1"></i>Payment received — confirming your booking…
    </div>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/public/my_bookings.php" class="pp-cancel">← Cancel and go back to bookings</a>
  </div>
</div>

<?php if ($hasCredentials): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars(PAYPAL_CLIENT_ID) ?>&currency=PHP"></script>
<script>
(function() {
    var BOOKING_ID   = <?= (int)$bookingId ?>;
    var AMOUNT       = '<?= $downpayment ?>';
    var CAPTURE_URL  = '<?= BASE_URL ?>/public/booking/paypal_capture.php';
    var SUCCESS_URL  = '<?= BASE_URL ?>/public/booking/paypal_success.php';
    var CANCEL_URL   = '<?= BASE_URL ?>/public/my_bookings.php';

    function showError(msg) {
        var el = document.getElementById('pp-error');
        el.style.display = 'block';
        el.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' + msg;
    }

    paypal.Buttons({
        style: { layout: 'vertical', color: 'blue', shape: 'rect', label: 'pay' },

        // Step 1: Create order on PayPal
        createOrder: function(data, actions) {
            return actions.order.create({
                intent: 'CAPTURE',
                purchase_units: [{
                    reference_id: 'booking_' + BOOKING_ID,
                    description:  'Selah Aesthetics Downpayment – Booking #' + BOOKING_ID,
                    amount: {
                        currency_code: 'PHP',
                        value: AMOUNT
                    }
                }]
            });
        },

        // Step 2: Customer approved — capture on our server (NOT client-side)
        onApprove: function(data, actions) {
            // Hide button, show processing message
            document.getElementById('paypal-button-container').style.display = 'none';
            document.getElementById('pp-processing').style.display = 'block';

            // Send orderID to our server for server-side capture + verification
            return fetch(CAPTURE_URL, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    booking_id: BOOKING_ID,
                    order_id:   data.orderID
                    // Do NOT send txn_id from client — server gets it from PayPal API
                })
            })
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (json.success) {
                    window.location.href = SUCCESS_URL + '?booking_id=' + BOOKING_ID;
                } else {
                    document.getElementById('pp-processing').style.display = 'none';
                    document.getElementById('paypal-button-container').style.display = 'block';
                    showError(json.message || 'Payment verification failed. Please try again or use GCash.');
                }
            })
            .catch(function(err) {
                document.getElementById('pp-processing').style.display = 'none';
                document.getElementById('paypal-button-container').style.display = 'block';
                showError('Network error during verification. Please contact the salon with your PayPal transaction ID.');
                console.error(err);
            });
        },

        onCancel: function() {
            window.location.href = CANCEL_URL;
        },

        onError: function(err) {
            showError('PayPal encountered an error. Please try again or use GCash.');
            console.error('PayPal error:', err);
        }

    }).render('#paypal-button-container').then(function() {
        // Hide loading spinner once buttons are rendered
        var loading = document.getElementById('pp-loading');
        if (loading) loading.style.display = 'none';
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
