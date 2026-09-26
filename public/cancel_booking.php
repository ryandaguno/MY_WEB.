<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();
require_once __DIR__ . '/../modules/BookingManager.php';

$id      = (int)($_GET['id'] ?? 0);
$bm      = new BookingManager();
$booking = $bm->getBooking($id);

if (!$booking || $booking['client_id'] != $_SESSION['client_id']) {
    SessionGuard::flashMessage('error', 'Booking not found.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}
if (!in_array($booking['status'], ['Pending','Accepted'])) {
    SessionGuard::flashMessage('error', 'This booking cannot be cancelled.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

// Handle POST — cancel and redirect immediately
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $result = $bm->cancelBooking($id, 'client', $_SESSION['client_id']);
    if ($result['success']) {
        SessionGuard::flashMessage('success', 'Booking #' . $id . ' has been cancelled successfully.');
    } else {
        SessionGuard::flashMessage('error', $result['message']);
    }
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$apptTime    = strtotime($booking['slot_date'] . ' ' . $booking['start_time']);
$hoursAway   = ($apptTime - time()) / 3600;
$isForfeited = ($hoursAway < 48);
$csrfToken   = SessionGuard::generateCsrfToken();

$pageTitle = 'Cancel Booking';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.cancel-page { background:#f8f4fb; min-height:calc(100vh - 56px); padding:40px 16px; }
.cancel-card {
  max-width:500px; margin:0 auto; background:#fff;
  border-radius:18px; box-shadow:0 8px 32px rgba(107,45,139,.12); overflow:hidden;
}
.cancel-header {
  background:linear-gradient(135deg,#dc2626,#ef4444);
  padding:24px 28px; color:#fff; text-align:center;
}
.cancel-header h4 { font-weight:800; margin:0; font-size:1.2rem; }
.cancel-header p  { margin:6px 0 0; font-size:.85rem; opacity:.85; }
.cancel-body { padding:28px; }
.info-row { display:flex; justify-content:space-between; font-size:.88rem; padding:7px 0;
            border-bottom:1px solid #f0eaf7; }
.info-row:last-child { border-bottom:none; }
.info-row span { color:#666; }
.info-row b { color:#1e1e1e; }
</style>

<div class="cancel-page">
  <div class="cancel-card">
    <div class="cancel-header">
      <div style="font-size:2.5rem;margin-bottom:8px">⚠️</div>
      <h4>Cancel Booking #<?= $booking['id'] ?>?</h4>
      <p>This action cannot be undone.</p>
    </div>

    <div class="cancel-body">
      <!-- Booking summary -->
      <div style="background:#f8f4fb;border-radius:10px;padding:16px;margin-bottom:18px">
        <div class="info-row"><span>Service</span><b><?= htmlspecialchars($booking['service_name']) ?></b></div>
        <div class="info-row"><span>Stylist</span><b><?= htmlspecialchars($booking['stylist_name']) ?></b></div>
        <div class="info-row"><span>Date</span><b><?= date('F j, Y', strtotime($booking['slot_date'])) ?></b></div>
        <div class="info-row"><span>Time</span><b><?= date('g:i A', strtotime($booking['start_time'])) ?></b></div>
      </div>

      <!-- Refund / forfeit notice -->
      <?php if ($isForfeited): ?>
      <div style="background:#fff8e1;border:1.5px solid #f59e0b;border-radius:10px;
                  padding:14px 16px;font-size:.85rem;color:#7c4f00;margin-bottom:20px">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Cancellation within 48 hours:</strong><br>
        Your downpayment of <strong>₱<?= number_format($booking['downpayment_amount'],2) ?></strong>
        will be <strong>forfeited</strong>.
      </div>
      <?php elseif ((float)($booking['downpayment_amount'] ?? 0) > 0): ?>
      <div style="background:#e0f2fe;border:1.5px solid #0284c7;border-radius:10px;
                  padding:14px 16px;font-size:.85rem;color:#0c4a6e;margin-bottom:20px">
        <i class="bi bi-info-circle-fill me-2"></i>
        Your downpayment of <strong>₱<?= number_format($booking['downpayment_amount'],2) ?></strong>
        may be eligible for a refund. Please contact us.
      </div>
      <?php endif; ?>

      <!-- Action buttons -->
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <button type="submit"
                style="width:100%;padding:14px;background:linear-gradient(135deg,#dc2626,#ef4444);
                       color:#fff;border:none;border-radius:10px;font-weight:800;font-size:.95rem;
                       text-transform:uppercase;letter-spacing:1px;cursor:pointer;margin-bottom:10px;
                       transition:opacity .2s"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <i class="bi bi-x-circle me-1"></i>Yes, Cancel Booking
        </button>
      </form>

      <a href="<?= BASE_URL ?>/public/my_bookings.php"
         style="display:block;text-align:center;padding:12px;background:#f3e8fb;color:#6B2D8B;
                border-radius:10px;font-weight:700;font-size:.9rem;text-decoration:none;
                border:1.5px solid #e0d5ea;transition:background .2s"
         onmouseover="this.style.background='#e8d5f5'" onmouseout="this.style.background='#f3e8fb'">
        <i class="bi bi-arrow-left me-1"></i>Back to My Bookings
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
