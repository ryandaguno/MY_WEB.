<?php
$pageTitle = 'Cancel Booking';
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
if (!in_array($booking['status'], ['Pending','Accepted'])) {
    SessionGuard::flashMessage('error', 'This booking cannot be cancelled.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$apptTime  = strtotime($booking['slot_date'] . ' ' . $booking['start_time']);
$hoursAway = ($apptTime - time()) / 3600;
$isForfeited = ($hoursAway < 48);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $result = $bm->cancelBooking($id, 'client', $_SESSION['client_id']);
    if ($result['success']) {
        SessionGuard::flashMessage('success', 'Booking #' . $id . ' has been cancelled.');
    } else {
        SessionGuard::flashMessage('error', $result['message']);
    }
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}
$csrfToken = SessionGuard::generateCsrfToken();
?>
<div class="container my-4" style="max-width:560px">
  <div class="card card-sa p-4">
    <h4 class="fw-bold mb-3 text-danger"><i class="bi bi-x-circle me-2"></i>Cancel Booking #<?= $booking['id'] ?></h4>

    <div class="mb-3">
      <p><b>Service:</b> <?= htmlspecialchars($booking['service_name']) ?></p>
      <p><b>Date:</b> <?= date('F j, Y', strtotime($booking['slot_date'])) ?> at <?= date('g:i A', strtotime($booking['start_time'])) ?></p>
    </div>

    <?php if ($isForfeited): ?>
    <div class="alert alert-warning">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <strong>Cancellation Policy:</strong> Since this appointment is within 48 hours,
      your downpayment of <strong>₱<?= number_format($booking['downpayment_amount'],2) ?></strong> will be <strong>forfeited</strong>.
    </div>
    <?php else: ?>
    <div class="alert alert-info">
      <i class="bi bi-info-circle me-2"></i>
      Your downpayment of <strong>₱<?= number_format($booking['downpayment_amount'],2) ?></strong>
      is <strong>eligible for refund review</strong> since you are cancelling more than 48 hours in advance.
      Please contact us for refund processing.
    </div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/public/booking_detail.php?id=<?= $id ?>" class="btn btn-outline-secondary flex-fill">
          Keep My Booking
        </a>
        <button type="submit" class="btn btn-danger flex-fill">
          Yes, Cancel Booking
        </button>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
