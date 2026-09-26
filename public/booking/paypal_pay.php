<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';

// Booking details stored in session — NOT yet created in DB
$pending = $_SESSION['paypal_pending'] ?? null;
if (!$pending) {
    SessionGuard::flashMessage('error', 'No pending booking found. Please start over.');
    header('Location: ' . BASE_URL . '/public/booking/step1_service.php'); exit;
}

$db = getDB();

$svcStmt = $db->prepare('SELECT name, price FROM services WHERE id = ?');
$svcStmt->execute([(int)$pending['service_id']]);
$service = $svcStmt->fetch();

$stStmt = $db->prepare('SELECT name FROM stylists WHERE id = ?');
$stStmt->execute([(int)$pending['stylist_id']]);
$stylist = $stStmt->fetch();

$schStmt = $db->prepare('SELECT slot_date, start_time FROM schedules WHERE id = ?');
$schStmt->execute([(int)$pending['schedule_id']]);
$schedule = $schStmt->fetch();

if (!$service || !$schedule) {
    SessionGuard::flashMessage('error', 'Booking details not found. Please start over.');
    header('Location: ' . BASE_URL . '/public/booking/step1_service.php'); exit;
}

$downpayment    = $pending['downpayment'];
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
.pp-page  { background:#e8eaed; min-height:calc(100vh-56px); padding:40px 16px; }
.pp-card  { max-width:480px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 4px 24px rgba(0,0,0,.12); padding:30px 28px; }
.pp-title { font-size:1.1rem; font-weight:800; color:#003087; margin-bottom:20px; text-align:center; }
.pp-row   { display:flex; justify-content:space-between; font-size:.88rem; margin-bottom:8px; color:#444; }
.pp-row b { color:#111; }
.pp-divider { border:none; border-top:1.5px solid #eee; margin:16px 0; }
.pp-amount  { text-align:center; font-size:1.4rem; font-weight:800; color:#003087; margin-bottom:6px; }
.pp-note    { font-size:.78rem; color:#888; text-align:center; margin-bottom:20px; }
.pp-cancel  { display:block; text-align:center; margin-top:14px; color:#888; font-size:.82rem; text-decoration:none; }
</style>

<div class="pp-page">
  <div class="pp-card">
    <div class="pp-title"><i class="bi bi-paypal me-2"></i>Complete PayPal Payment</div>
    <div class="pp-row"><span>Service</span><b><?= htmlspecialchars($service['name']) ?></b></div>
    <div class="pp-row"><span>Stylist</span><b><?= htmlspecialchars($stylist['name'] ?? 'Any') ?></b></div>
    <div class="pp-row"><span>Date</span><b><?= date('F j, Y', strtotime($schedule['slot_date'])) ?></b></div>
    <div class="pp-row"><span>Time</span><b><?= date('g:i A', strtotime($schedule['start_time'])) ?></b></div>
    <hr class="pp-divider">
    <div class="pp-amount">Downpayment: ₱<?= number_format($downpayment, 2) ?></div>
    <div class="pp-note">50% downpayment required to confirm your slot</div>

    <?php if (!$hasCredentials): ?>
    <div style="background:#fff3cd;border:1.5px solid #f59e0b;border-radius:10px;padding:16px;text-align:center;font-size:.88rem;color:#856404;margin-bottom:16px">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>PayPal is not yet available.</strong> Please use <strong>GCash</strong>.<br>
      <a href="javascript:history.back()" class="btn btn-sm mt-2" style="background:#6B2D8B;color:#fff;border-radius:8px;padding:8px 18px;font-weight:700">
        ← Pay with GCash Instead
      </a>
    </div>
    <?php else: ?>
    <div id="pp-loading" style="text-align:center;color:#888;font-size:.85rem;padding:16px 0">
      <i class="bi bi-hourglass-split me-1"></i>Loading PayPal…
    </div>
    <div id="paypal-button-container"></div>
    <div id="pp-error" style="display:none;color:#c0392b;font-size:.85rem;text-align:center;margin-top:10px;background:#ffeaea;border-radius:8px;padding:10px"></div>
    <div id="pp-processing" style="display:none;text-align:center;padding:16px;color:#10b981;font-weight:700">
      <i class="bi bi-check-circle-fill me-1"></i>Payment received — confirming your booking…
    </div>
    <?php endif; ?>

    <a href="javascript:history.back()" class="pp-cancel">← Cancel and go back</a>
  </div>
</div>

<?php if ($hasCredentials): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars(PAYPAL_CLIENT_ID) ?>&currency=PHP"></script>
<script>
(function(){
  var AMOUNT      = '<?= number_format($downpayment, 2, '.', '') ?>';
  var CAPTURE_URL = '<?= BASE_URL ?>/public/booking/paypal_capture.php';
  var SUCCESS_URL = '<?= BASE_URL ?>/public/booking/paypal_success.php';

  function showError(msg) {
    var el = document.getElementById('pp-error');
    el.style.display = 'block';
    el.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' + msg;
  }

  paypal.Buttons({
    style: { layout:'vertical', color:'blue', shape:'rect', label:'pay' },
    createOrder: function(data, actions) {
      return actions.order.create({
        intent: 'CAPTURE',
        purchase_units: [{ description: 'Selah Aesthetics Downpayment', amount: { currency_code:'PHP', value:AMOUNT } }]
      });
    },
    onApprove: function(data, actions) {
      document.getElementById('paypal-button-container').style.display = 'none';
      document.getElementById('pp-processing').style.display = 'block';
      fetch(CAPTURE_URL, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ order_id: data.orderID })
      })
      .then(function(r){ return r.json(); })
      .then(function(json){
        if (json.success) {
          window.location.href = SUCCESS_URL + '?booking_id=' + json.booking_id;
        } else {
          document.getElementById('pp-processing').style.display = 'none';
          document.getElementById('paypal-button-container').style.display = 'block';
          showError(json.message || 'Verification failed. Please try GCash.');
        }
      })
      .catch(function(){ showError('Network error. Please contact the salon.'); });
    },
    onCancel: function(){ history.back(); },
    onError:  function(err){ showError('PayPal error. Please try GCash.'); }
  }).render('#paypal-button-container').then(function(){
    var l = document.getElementById('pp-loading');
    if (l) l.style.display = 'none';
  });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
