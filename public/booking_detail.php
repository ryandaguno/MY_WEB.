<?php
$pageTitle = 'Booking Detail';
require_once __DIR__ . '/../includes/header.php';
SessionGuard::requireClient();
require_once __DIR__ . '/../modules/BookingManager.php';

$id      = (int)($_GET['id'] ?? 0);
$bm      = new BookingManager();
$booking = $bm->getBooking($id);

if (!$booking || $booking['client_id'] != $_SESSION['client_id']) {
    SessionGuard::flashMessage('error', 'Booking not found.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$apptTime  = strtotime($booking['slot_date'] . ' ' . $booking['start_time']);
$hoursAway = ($apptTime - time()) / 3600;
$canCancel = in_array($booking['status'], ['Pending','Accepted']) && $hoursAway > 0;
$canRate   = $booking['status'] === 'Completed';

// Check if already rated
require_once __DIR__ . '/../config/db.php';
$db = getDB();
$rateStmt = $db->prepare('SELECT id FROM ratings WHERE booking_id = ?');
$rateStmt->execute([$id]);
$alreadyRated = (bool)$rateStmt->fetch();
?>
<div class="container my-4" style="max-width:700px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0" style="color:var(--sa-purple)">Booking #<?= $booking['id'] ?></h4>
    <a href="<?= BASE_URL ?>/public/my_bookings.php" class="btn btn-outline-secondary btn-sm">← My Bookings</a>
  </div>

  <div class="card card-sa p-4">
    <div class="row g-3">
      <div class="col-md-6">
        <h6 class="fw-bold" style="color:var(--sa-teal)">Service</h6>
        <p class="mb-1"><?= htmlspecialchars($booking['service_name']) ?></p>
        <p class="text-muted small">₱<?= number_format($booking['price'],2) ?> &bull; <?= $booking['duration_minutes'] ?> min</p>
      </div>
      <div class="col-md-6">
        <h6 class="fw-bold" style="color:var(--sa-teal)">Stylist</h6>
        <p class="mb-0"><?= htmlspecialchars($booking['stylist_name']) ?></p>
      </div>
      <div class="col-md-6">
        <h6 class="fw-bold" style="color:var(--sa-teal)">Appointment</h6>
        <p class="mb-0"><?= date('F j, Y', strtotime($booking['slot_date'])) ?></p>
        <p class="mb-0"><?= date('g:i A', strtotime($booking['start_time'])) ?></p>
      </div>
      <div class="col-md-6">
        <h6 class="fw-bold" style="color:var(--sa-teal)">Status</h6>
        <?php
        $colors = ['Pending'=>'warning','Accepted'=>'success','Cancelled'=>'danger','Completed'=>'primary'];
        $cls    = $colors[$booking['status']] ?? 'secondary';
        ?>
        <span class="badge bg-<?= $cls ?> fs-6"><?= $booking['status'] ?></span>
      </div>
      <?php if ($booking['notes']): ?>
      <div class="col-12">
        <h6 class="fw-bold" style="color:var(--sa-teal)">Notes</h6>
        <p class="mb-0"><?= htmlspecialchars($booking['notes']) ?></p>
      </div>
      <?php endif; ?>
      <div class="col-md-6">
        <h6 class="fw-bold" style="color:var(--sa-teal)">Downpayment</h6>
        <p class="mb-0">₱<?= number_format($booking['downpayment_amount'],2) ?></p>
        <?php if ($booking['payment_status']): ?>
          <span class="badge bg-success"><?= htmlspecialchars($booking['payment_status']) ?></span>
          <?php if ($booking['payment_method']): ?>
            <span class="badge bg-secondary"><?= htmlspecialchars($booking['payment_method']) ?></span>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge bg-warning text-dark">Pending Payment</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4 flex-wrap">
      <?php if ($canCancel): ?>
      <a href="<?= BASE_URL ?>/public/cancel_booking.php?id=<?= $booking['id'] ?>"
         class="btn btn-outline-danger">
        <i class="bi bi-x-circle me-1"></i>Cancel Booking
      </a>
      <?php endif; ?>
      <?php if ($canRate): ?>
        <?php if ($alreadyRated): ?>
        <a href="<?= BASE_URL ?>/public/feedback.php?booking_id=<?= $booking['id'] ?>"
           class="btn btn-outline-secondary">
          <i class="bi bi-star me-1"></i>View My Rating
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/public/feedback.php?booking_id=<?= $booking['id'] ?>"
           class="btn btn-sa-teal">
          <i class="bi bi-star-fill me-1"></i>Rate This Appointment
        </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
