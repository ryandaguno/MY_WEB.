<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/BookingManager.php';

if (empty($_SESSION['booking']['service_id'])) {
    header('Location: ' . BASE_URL . '/public/booking/step1_service.php'); exit;
}

$bm = new BookingManager();
$stylists = $bm->getAvailableStylists((int)$_SESSION['booking']['service_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $_SESSION['booking']['stylist_id']   = $_POST['stylist_id'] ?? 'any';
    $_SESSION['booking']['stylist_name'] = $_POST['stylist_name'] ?? 'First Available Stylist';
    header('Location: ' . BASE_URL . '/public/booking/step3_datetime.php'); exit;
}

$selected  = $_SESSION['booking']['stylist_id'] ?? '';
$csrfToken = SessionGuard::generateCsrfToken();
$pageTitle = 'Step 2: Pick Stylist';
require_once __DIR__ . '/../../includes/header.php';
<div class="container my-4">
  <div class="booking-steps">
    <?php foreach ([1=>'Service',2=>'Stylist',3=>'Date & Time',4=>'Details',5=>'Confirm'] as $n => $label): ?>
    <div class="text-center">
      <div class="step-indicator <?= $n<2?'done':($n===2?'active':'') ?>"><?= $n<2?'✓':$n ?></div>
      <div class="small mt-1 text-muted"><?= $label ?></div>
    </div>
    <?php if ($n < 5): ?><div class="align-self-start mt-2 text-muted">—</div><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <h4 class="text-center mb-4 fw-bold" style="color:var(--sa-purple)">Step 2: Pick a Stylist</h4>

  <form method="post" id="step2Form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="stylist_id" id="selectedStylistId" value="<?= htmlspecialchars($selected) ?>">
    <input type="hidden" name="stylist_name" id="selectedStylistName" value="">

    <div class="row g-3 justify-content-center mb-4">
      <!-- Any Available -->
      <div class="col-md-3 col-sm-4 col-6">
        <div class="stylist-card <?= $selected==='any'?'selected':'' ?>" onclick="selectStylist('any','First Available Stylist')">
          <i class="bi bi-people-fill mb-2" style="font-size:2rem;color:var(--sa-purple)"></i>
          <div class="fw-bold">Anyone</div>
          <div class="text-muted small mb-2">First Available Stylist</div>
          <span class="badge-available">Available</span>
        </div>
      </div>
      <?php foreach ($stylists as $st): ?>
      <div class="col-md-3 col-sm-4 col-6">
        <div class="stylist-card <?= $selected==$st['id']?'selected':'' ?>" onclick="selectStylist(<?= $st['id'] ?>, '<?= htmlspecialchars($st['name'], ENT_QUOTES) ?>')">
          <i class="bi bi-person-circle mb-2" style="font-size:2rem;color:var(--sa-purple)"></i>
          <div class="fw-bold"><?= htmlspecialchars($st['name']) ?></div>
          <div class="text-muted small mb-2"><?= htmlspecialchars($st['specialty']) ?></div>
          <?php if ($st['is_active']): ?>
            <span class="badge-available">Available</span>
          <?php else: ?>
            <span class="badge-unavailable">Not Available</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-center gap-3 mt-3">
      <a href="<?= BASE_URL ?>/public/booking/step1_service.php" class="btn btn-outline-secondary px-4">← Back</a>
      <button type="submit" class="btn btn-sa-teal px-5" id="nextBtn" <?= !$selected?'disabled':'' ?>>Next →</button>
    </div>
  </form>
</div>
<script>
function selectStylist(id, name) {
  document.querySelectorAll('.stylist-card').forEach(c => c.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  document.getElementById('selectedStylistId').value = id;
  document.getElementById('selectedStylistName').value = name;
  document.getElementById('nextBtn').disabled = false;
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
