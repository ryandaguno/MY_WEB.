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
$stmt = $db->prepare(
    "SELECT b.id, b.status, b.downpayment_amount,
            s.name AS service_name, sc.slot_date, sc.start_time,
            t.status AS payment_status, t.paypal_transaction_id
     FROM bookings b
     JOIN services s  ON b.service_id  = s.id
     JOIN schedules sc ON b.schedule_id = sc.id
     LEFT JOIN transactions t ON t.booking_id = b.id AND t.payment_method = 'PayPal'
     WHERE b.id = ? AND b.client_id = ?"
);
$stmt->execute([$bookingId, $_SESSION['client_id']]);
$booking = $stmt->fetch();

if (!$booking || $booking['payment_status'] !== 'Paid') {
    // Payment not confirmed — redirect to bookings with a message
    SessionGuard::flashMessage('error', 'PayPal payment could not be verified. Your booking is still pending.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$pageTitle = 'Payment Confirmed';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.ps-page { background:#f0f7ff; min-height:calc(100vh - 56px); padding:48px 16px 80px; font-family:'Segoe UI',Arial,sans-serif; }
.ps-card { max-width:520px; margin:0 auto; background:#fff; border-radius:18px; box-shadow:0 8px 40px rgba(0,66,139,.12); overflow:hidden; }
.ps-header { background:linear-gradient(135deg,#003087,#009cde); padding:32px 28px 28px; text-align:center; color:#fff; }
.ps-header h1 { font-size:1.5rem; font-weight:800; margin:0 0 6px; }
.ps-header p  { margin:0; font-size:.92rem; opacity:.88; }
.ps-icon-wrap { display:flex; justify-content:center; margin:-28px 0 0; position:relative; z-index:1; }
.ps-icon { width:56px; height:56px; background:#22c55e; border-radius:50%; border:4px solid #fff; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 16px rgba(34,197,94,.40); font-size:1.6rem; color:#fff; }
.ps-body { padding:36px 28px 28px; text-align:center; }
.ps-greeting { font-size:1.1rem; font-weight:700; color:#003087; margin-bottom:4px; }
.ps-sub { font-size:.88rem; color:#555; margin-bottom:24px; line-height:1.65; }
.ps-info { background:#f0f7ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px 18px; text-align:left; margin-bottom:24px; font-size:.85rem; }
.ps-info-row { display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #dbeafe; }
.ps-info-row:last-child { border-bottom:none; }
.ps-info-row span { color:#555; }
.ps-info-row b { color:#1e3a5f; font-weight:700; }
.ps-badge { display:inline-flex; align-items:center; gap:7px; background:#dcfce7; border:1.5px solid #22c55e; color:#166534; border-radius:50px; padding:7px 20px; font-size:.84rem; font-weight:700; margin-bottom:24px; }
.ps-btn { display:block; width:100%; padding:14px; border-radius:10px; background:#003087; color:#fff; font-size:.92rem; font-weight:800; text-transform:uppercase; letter-spacing:1.4px; text-decoration:none; text-align:center; transition:background .15s; }
.ps-btn:hover { background:#002070; color:#fff; }
.ps-note { margin-top:16px; font-size:.8rem; color:#888; }
</style>

<div class="ps-page"><div class="ps-card">
  <div class="ps-header">
    <h1>🎉 Payment Confirmed!</h1>
    <p>Your PayPal downpayment was successfully processed.</p>
  </div>
  <div class="ps-icon-wrap">
    <div class="ps-icon"><i class="bi bi-check-lg"></i></div>
  </div>
  <div class="ps-body">
    <p class="ps-greeting">Hi, <?= htmlspecialchars($_SESSION['username'] ?? 'there') ?>! 👋</p>
    <p class="ps-sub">
      Your downpayment was received and your booking is now <strong>confirmed</strong>.<br>
      We look forward to seeing you!
    </p>

    <div><span class="ps-badge">
      <i class="bi bi-paypal"></i> Paid via PayPal
    </span></div>

    <div class="ps-info">
      <div class="ps-info-row"><span>Booking #</span><b>#<?= $bookingId ?></b></div>
      <div class="ps-info-row"><span>Service</span><b><?= htmlspecialchars($booking['service_name']) ?></b></div>
      <div class="ps-info-row"><span>Date</span><b><?= date('F j, Y', strtotime($booking['slot_date'])) ?></b></div>
      <div class="ps-info-row"><span>Time</span><b><?= date('g:i A', strtotime($booking['start_time'])) ?></b></div>
      <div class="ps-info-row"><span>Downpayment Paid</span><b>₱<?= number_format($booking['downpayment_amount'], 2) ?></b></div>
      <div class="ps-info-row"><span>PayPal Transaction</span><b style="font-size:.75rem"><?= htmlspecialchars($booking['paypal_transaction_id'] ?? '—') ?></b></div>
      <div class="ps-info-row"><span>Booking Status</span><b style="color:#166534">Confirmed ✓</b></div>
    </div>

    <a href="<?= BASE_URL ?>/public/my_bookings.php" class="ps-btn">
      <i class="bi bi-calendar-check me-1"></i>View My Bookings
    </a>
    <p class="ps-note">A confirmation has been sent to your email. Thank you for choosing <strong>Selah Aesthetics</strong>! 💜</p>
  </div>
</div></div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
