<?php
$pageTitle = 'Rate Your Appointment';
require_once __DIR__ . '/../includes/header.php';
SessionGuard::requireClient();
require_once __DIR__ . '/../modules/BookingManager.php';
require_once __DIR__ . '/../config/db.php';

$bookingId = (int)($_GET['booking_id'] ?? 0);
$bm        = new BookingManager();
$booking   = $bm->getBooking($bookingId);

if (!$booking || $booking['client_id'] != $_SESSION['client_id'] || $booking['status'] !== 'Completed') {
    SessionGuard::flashMessage('error', 'Feedback is only available for completed bookings.');
    header('Location: ' . BASE_URL . '/public/my_bookings.php'); exit;
}

$db       = getDB();
$rateStmt = $db->prepare('SELECT * FROM ratings WHERE booking_id = ?');
$rateStmt->execute([$bookingId]);
$existing = $rateStmt->fetch();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $stars   = (int)($_POST['stars'] ?? 0);
    $comment = htmlspecialchars(trim($_POST['comment'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($stars < 1 || $stars > 5) {
        $error = 'Please select a rating from 1 to 5 stars.';
    } else {
        $db->prepare(
            'INSERT INTO ratings (booking_id, client_id, service_id, stylist_id, stars, comment)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$bookingId, $_SESSION['client_id'], $booking['service_id'], $booking['stylist_id'], $stars, $comment]);
        SessionGuard::flashMessage('success', 'Thank you for your feedback!');
        header('Location: ' . BASE_URL . '/public/booking_detail.php?id=' . $bookingId); exit;
    }
}
$csrfToken = SessionGuard::generateCsrfToken();
?>
<div class="container my-4" style="max-width:560px">
  <h4 class="fw-bold mb-3" style="color:var(--sa-purple)"><i class="bi bi-star-fill me-2"></i>Rate Your Appointment</h4>
  <div class="card card-sa p-4">
    <p><b>Service:</b> <?= htmlspecialchars($booking['service_name']) ?></p>
    <p><b>Stylist:</b> <?= htmlspecialchars($booking['stylist_name']) ?></p>
    <p><b>Date:</b> <?= date('F j, Y', strtotime($booking['slot_date'])) ?></p>
    <hr>
    <?php if ($existing): ?>
      <!-- Read-only view -->
      <h6 class="fw-bold">Your Rating</h6>
      <div class="mb-2">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <i class="bi bi-star<?= $i <= $existing['stars'] ? '-fill' : '' ?>" style="color:#f59e0b;font-size:1.5rem"></i>
        <?php endfor; ?>
        <span class="ms-2 fw-bold"><?= $existing['stars'] ?>/5</span>
      </div>
      <?php if ($existing['comment']): ?>
        <p class="text-muted fst-italic">"<?= htmlspecialchars($existing['comment']) ?>"</p>
      <?php endif; ?>
      <p class="small text-muted">Submitted <?= date('F j, Y', strtotime($existing['submitted_at'])) ?></p>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="mb-3">
          <label class="form-label fw-semibold">Your Rating *</label>
          <div class="star-rating d-flex flex-row-reverse justify-content-end">
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" name="stars" id="star<?= $i ?>" value="<?= $i ?>" <?= (!empty($_POST['stars']) && $_POST['stars']==$i)?'checked':'' ?>>
            <label for="star<?= $i ?>" title="<?= $i ?> stars"><i class="bi bi-star-fill"></i></label>
            <?php endfor; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Comment <span class="text-muted">(optional)</span></label>
          <textarea name="comment" class="form-control" rows="3" maxlength="500" placeholder="Share your experience..."><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-sa-primary w-100">Submit Feedback</button>
      </form>
    <?php endif; ?>
  </div>
  <a href="<?= BASE_URL ?>/public/booking_detail.php?id=<?= $bookingId ?>" class="btn btn-outline-secondary mt-2 w-100">← Back to Booking</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
