<?php
$pageTitle = 'Booking Submitted';
require_once __DIR__ . '/../../includes/header.php';
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';

$bookingId = isset($_SESSION['gcash_success_booking_id'])
    ? (int)$_SESSION['gcash_success_booking_id']
    : 0;

if (!$bookingId) {
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

unset($_SESSION['gcash_success_booking_id']);

$db   = getDB();
$stmt = $db->prepare(
    'SELECT b.id, b.status, b.created_at, b.downpayment_amount,
            s.name AS service_name,
            sc.slot_date, sc.start_time
     FROM bookings b
     JOIN services  s  ON s.id  = b.service_id
     JOIN schedules sc ON sc.id = b.schedule_id
     WHERE b.id = ? AND b.client_id = ?'
);
$stmt->execute([$bookingId, $_SESSION['client_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}
?>
<style>
.gs-page { background:#f8f4fb; min-height:calc(100vh - 56px); padding:48px 16px 80px; font-family:'Segoe UI',Arial,sans-serif; }
.gs-card { max-width:560px; margin:0 auto; background:#fff; border-radius:18px; box-shadow:0 8px 40px rgba(107,45,139,.14); overflow:hidden; }
.gs-header { background:linear-gradient(135deg,#6B2D8B,#9b4dca); padding:32px 28px 28px; text-align:center; color:#fff; }
.gs-header h1 { font-size:1.55rem; font-weight:800; margin:0 0 6px; }
.gs-header p  { margin:0; font-size:.92rem; opacity:.88; }
.gs-icon-wrap { display:flex; justify-content:center; margin:-28px 0 0; position:relative; z-index:1; }
.gs-icon { width:56px; height:56px; background:#22c55e; border-radius:50%; border:4px solid #fff; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 16px rgba(34,197,94,.40); font-size:1.6rem; color:#fff; }
.gs-body { padding:36px 28px 28px; text-align:center; }
.gs-greeting { font-size:1.15rem; font-weight:700; color:#6B2D8B; margin-bottom:4px; }
.gs-main-msg  { font-size:.95rem; color:#1e1e1e; font-weight:600; margin-bottom:4px; }
.gs-sub-msg   { font-size:.86rem; color:#555; margin-bottom:24px; line-height:1.65; }
.gs-status-pill { display:inline-flex; align-items:center; gap:7px; background:#fff8e1; border:1.5px solid #f59e0b; color:#92610a; border-radius:50px; padding:7px 20px; font-size:.84rem; font-weight:700; margin-bottom:24px; }
.gs-status-pill .dot { width:10px; height:10px; background:#f59e0b; border-radius:50%; display:inline-block; animation:pulse 1.6s infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.75)} }
.gs-info { background:#f8f4fb; border:1px solid #e0d5ea; border-radius:10px; padding:14px 18px; text-align:left; margin-bottom:24px; font-size:.85rem; }
.gs-info-row { display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #ecdff5; }
.gs-info-row:last-child { border-bottom:none; }
.gs-info-row span { color:#666; }
.gs-info-row b { color:#2d2d2d; font-weight:700; }
.gs-notice { background:#eff6ff; border-left:4px solid #3b82f6; border-radius:0 8px 8px 0; padding:11px 14px; font-size:.82rem; color:#1e40af; text-align:left; margin-bottom:28px; line-height:1.55; }
.gs-btn { display:block; width:100%; padding:14px; border-radius:10px; background:#6B2D8B; color:#fff; font-size:.92rem; font-weight:800; text-transform:uppercase; letter-spacing:1.4px; text-decoration:none; text-align:center; transition:background .15s; }
.gs-btn:hover { background:#4a1f62; color:#fff; }
.gs-signoff { margin-top:22px; font-size:.82rem; color:#888; line-height:1.6; }
</style>

<div class="gs-page"><div class="gs-card">
  <div class="gs-header">
    <h1>🎉 Thank You for Your Booking!</h1>
    <p>Your appointment has been successfully submitted.</p>
  </div>
  <div class="gs-icon-wrap"><div class="gs-icon"><i class="bi bi-check-lg"></i></div></div>
  <div class="gs-body">
    <p class="gs-greeting">Hi, <?= htmlspecialchars($_SESSION['username'] ?? 'there') ?>! 👋</p>
    <p class="gs-main-msg">We received your GCash payment receipt.</p>
    <p class="gs-sub-msg">Your booking is now under review. Once our admin verifies your payment,<br>your appointment will be officially confirmed.</p>
    <div><span class="gs-status-pill"><span class="dot"></span>Pending Payment Verification</span></div>
    <div class="gs-info">
      <div class="gs-info-row"><span>Booking #</span><b>#<?= $bookingId ?></b></div>
      <div class="gs-info-row"><span>Service</span><b><?= htmlspecialchars($booking['service_name']) ?></b></div>
      <div class="gs-info-row"><span>Date</span><b><?= date('F j, Y', strtotime($booking['slot_date'])) ?></b></div>
      <div class="gs-info-row"><span>Time</span><b><?= date('g:i A', strtotime($booking['start_time'])) ?></b></div>
      <div class="gs-info-row"><span>Downpayment</span><b>₱<?= number_format($booking['downpayment_amount'], 2) ?></b></div>
    </div>
    <div class="gs-notice"><i class="bi bi-bell me-1"></i><strong>What happens next?</strong><br>Our admin will review your screenshot and verify your payment. You will be notified once your appointment is confirmed. 🔔</div>
    <a href="<?= BASE_URL ?>/public/my_bookings.php" class="gs-btn"><i class="bi bi-calendar-check me-1"></i>Go to My Bookings</a>
    <p class="gs-signoff">Thank you for choosing <strong>Selah Aesthetics</strong>. 💜<br>We look forward to serving you!</p>
  </div>
</div></div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
