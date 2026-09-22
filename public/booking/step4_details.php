<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();

if (empty($_SESSION['booking']['schedule_id'])) {
    header('Location: ' . BASE_URL . '/public/booking/step3_datetime.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $_SESSION['booking']['notes'] = htmlspecialchars(trim($_POST['notes'] ?? ''), ENT_QUOTES, 'UTF-8');
    header('Location: ' . BASE_URL . '/public/booking/step5_payment.php'); exit;
}

require_once __DIR__ . '/../../config/db.php';
$db = getDB();
$stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$_SESSION['client_id']]);
$client    = $stmt->fetch();
$csrfToken = SessionGuard::generateCsrfToken();
$pageTitle = 'Step 4: Add Details';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container my-4" style="max-width:600px">
  <div class="booking-steps">
    <?php foreach ([1=>'Service',2=>'Stylist',3=>'Date & Time',4=>'Details',5=>'Confirm'] as $n => $lbl): ?>
    <div class="text-center">
      <div class="step-indicator <?= $n<4?'done':($n===4?'active':'') ?>"><?= $n<4?'✓':$n ?></div>
      <div class="small mt-1 text-muted"><?= $lbl ?></div>
    </div>
    <?php if ($n < 5): ?><div class="align-self-start mt-2 text-muted">—</div><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <h4 class="text-center mb-4 fw-bold" style="color:var(--sa-purple)">Step 4: Add Details</h4>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <div class="mb-3">
      <label class="form-label fw-semibold">Name</label>
      <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($client['username']) ?>" readonly>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Phone Number</label>
      <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($client['phone']) ?>" readonly>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Email</label>
      <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($client['email']) ?>" readonly>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Notes / Special Requests <span class="text-muted small">(optional)</span></label>
      <textarea name="notes" class="form-control" rows="3" maxlength="500"
                placeholder="e.g. allergies, special preferences..."><?= htmlspecialchars($_SESSION['booking']['notes'] ?? '') ?></textarea>
    </div>
    <div class="d-flex justify-content-center gap-3 mt-3">
      <a href="<?= BASE_URL ?>/public/booking/step3_datetime.php" class="btn btn-outline-secondary px-4">← Back</a>
      <button type="submit" class="btn btn-sa-teal px-5">Next →</button>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
