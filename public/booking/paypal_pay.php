<?php
$pageTitle = 'Pay with PayPal';
require_once __DIR__ . '/../../includes/header.php';
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) {
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$db = getDB();

// Verify this booking belongs to the current client
$stmt = $db->prepare(
    'SELECT b.id, b.downpayment_amount, s.name AS service_name, s.price,
            sc.slot_date, sc.start_time, st.name AS stylist_name
     FROM bookings b
     JOIN services s   ON b.service_id  = s.id
     JOIN schedules sc ON b.schedule_id = sc.id
     JOIN stylists st  ON b.stylist_id  = st.id
     WHERE b.id = ? AND b.client_id = ?'
);
$stmt->execute([$bookingId, $_SESSION['client_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    SessionGuard::flashMessage('error', 'Booking not found.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

// Check if already paid
$txStmt = $db->prepare("SELECT id FROM transactions WHERE booking_id = ? AND payment_method = 'PayPal'");
$txStmt->execute([$bookingId]);
if ($txStmt->fetch()) {
    SessionGuard::flashMessage('success', 'This booking has already been paid.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$downpayment = number_format($booking['downpayment_amount'], 2, '.', '');
?>

<style>
.pp-page { background:#e8eaed; min-height:calc(100vh - 56px); padding:40px 16px; font-family:'Segoe UI',Arial,sans-serif; }
.pp-card { max-width:480px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 4px 24px rgba(0,0,0,.12); padding:30px 28px; }
.pp-title { font-size:1.1rem; font-weight:800; color:#003087; margin-bottom:20px; text-align:center; }
.pp-row { display:flex; justify-content:space-between; font-size:.88rem; margin-bottom:8px; color:#444; }
.pp-row b { color:#111; }
.pp-divider { border:none; border-top:1.5px solid #eee; margin:16px 0; }
.pp-amount { text-align:center; font-size:1.4rem; font-weight:800; color:#003087; margin-bottom:20px; }
.pp-note { font-size:.78rem; color:#888; text-align:center; margin-bottom:18px; }
#paypal-button-container { min-height:50px; }
.pp-cancel { display:block; text-align:center; margin-top:14px; color:#888; font-size:.82rem; text-decoration:none; }
.pp-cancel:hover { color:#333; }
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

    <div id="paypal-button-container"></div>
    <div id="pp-error" style="display:none;color:#c0392b;font-size:.85rem;text-align:center;margin-top:10px;"></div>

    <a href="<?= BASE_URL ?>/public/my_bookings.php" class="pp-cancel">← Cancel and go back to bookings</a>
  </div>
</div>

<!-- PayPal JS SDK — replace YOUR_SANDBOX_CLIENT_ID with your actual PayPal sandbox client ID -->
<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars(PAYPAL_CLIENT_ID) ?>&currency=PHP"></script>
<script>
paypal.Buttons({
    style: {
        layout: 'vertical',
        color:  'blue',
        shape:  'rect',
        label:  'pay'
    },
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                description: 'Selah Aesthetics - Downpayment for Booking #<?= $bookingId ?>',
                amount: {
                    currency_code: 'PHP',
                    value: '<?= $downpayment ?>'
                }
            }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            // Send approval to backend
            return fetch('<?= BASE_URL ?>/public/booking/paypal_capture.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    booking_id:   <?= $bookingId ?>,
                    order_id:     data.orderID,
                    payer_id:     details.payer.payer_id || '',
                    txn_id:       details.id
                })
            })
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (json.success) {
                    window.location.href = '<?= BASE_URL ?>/public/my_bookings.php';
                } else {
                    document.getElementById('pp-error').style.display = 'block';
                    document.getElementById('pp-error').textContent = 'Payment recorded but an error occurred: ' + (json.message || 'Unknown error');
                }
            });
        });
    },
    onError: function(err) {
        document.getElementById('pp-error').style.display = 'block';
        document.getElementById('pp-error').textContent = 'PayPal encountered an error. Please try again or choose GCash.';
        console.error(err);
    },
    onCancel: function() {
        window.location.href = '<?= BASE_URL ?>/public/my_bookings.php';
    }
}).render('#paypal-button-container');
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
