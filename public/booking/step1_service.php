<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();
require_once __DIR__ . '/../../config/db.php';

// Handle POST BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    if (!empty($_POST['service_id'])) {
        $_SESSION['booking']['service_id'] = (int)$_POST['service_id'];
        header('Location: ' . BASE_URL . '/public/booking/step2_stylist.php'); exit;
    }
}

// Pre-select from URL param (from services page)
if (isset($_GET['service_id']) && is_numeric($_GET['service_id'])) {
    $_SESSION['booking']['service_id'] = (int)$_GET['service_id'];
}

$db = getDB();
$categories = $db->query('SELECT DISTINCT category FROM services WHERE is_active = 1 ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
$services   = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY category, name')->fetchAll();
$grouped    = [];
foreach ($services as $svc) { $grouped[$svc['category']][] = $svc; }
$selectedId = $_SESSION['booking']['service_id'] ?? 0;
$csrfToken  = SessionGuard::generateCsrfToken();

$pageTitle = 'Step 1: Choose Service';
require_once __DIR__ . '/../../includes/header.php';
<div class="container my-4">
  <!-- Progress -->
  <div class="booking-steps">
    <?php foreach ([1=>'Service',2=>'Stylist',3=>'Date & Time',4=>'Details',5=>'Confirm'] as $n => $label): ?>
    <div class="text-center">
      <div class="step-indicator <?= $n===1?'active':'' ?>"><?= $n ?></div>
      <div class="small mt-1 text-muted"><?= $label ?></div>
    </div>
    <?php if ($n < 5): ?><div class="align-self-start mt-2 text-muted">—</div><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <h4 class="text-center mb-4 fw-bold" style="color:var(--sa-purple)">Step 1: Choose a Service</h4>

  <form method="post" id="step1Form">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="service_id" id="selectedServiceId" value="<?= $selectedId ?>">

    <!-- Category Filter -->
    <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
      <button type="button" class="btn btn-sm btn-sa-primary cat-filter active" data-cat="all">All</button>
      <?php foreach ($categories as $cat): ?>
      <button type="button" class="btn btn-sm btn-outline-secondary cat-filter" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($grouped as $category => $svcs): ?>
    <div class="category-group" data-cat="<?= htmlspecialchars($category) ?>">
      <h5 class="mb-3" style="color:var(--sa-teal)"><?= htmlspecialchars($category) ?></h5>
      <div class="row g-2 mb-4">
        <?php foreach ($svcs as $svc): ?>
        <div class="col-md-3 col-sm-4 col-6">
          <div class="service-card <?= $selectedId==$svc['id']?'selected':'' ?>"
               data-id="<?= $svc['id'] ?>" data-cat="<?= htmlspecialchars($category) ?>"
               onclick="selectService(<?= $svc['id'] ?>)">
            <div class="service-name"><?= htmlspecialchars($svc['name']) ?></div>
            <div class="text-muted small my-1"><i class="bi bi-clock me-1"></i><?= $svc['duration_minutes'] ?> min</div>
            <div class="service-price">₱<?= number_format($svc['price'], 2) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="d-flex justify-content-center gap-3 mt-3">
      <a href="<?= BASE_URL ?>/public/home.php" class="btn btn-outline-secondary px-4">Back</a>
      <button type="submit" class="btn btn-sa-teal px-5" id="nextBtn" <?= !$selectedId?'disabled':'' ?>>Next →</button>
    </div>
  </form>
</div>
<script>
function selectService(id) {
  document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
  document.querySelector('.service-card[data-id="'+id+'"]').classList.add('selected');
  document.getElementById('selectedServiceId').value = id;
  document.getElementById('nextBtn').disabled = false;
}
document.querySelectorAll('.cat-filter').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.cat-filter').forEach(b => b.classList.remove('active','btn-sa-primary'));
    document.querySelectorAll('.cat-filter').forEach(b => b.classList.add('btn-outline-secondary'));
    this.classList.add('active','btn-sa-primary');
    this.classList.remove('btn-outline-secondary');
    const cat = this.dataset.cat;
    document.querySelectorAll('.category-group').forEach(g => {
      g.style.display = (cat==='all'||g.dataset.cat===cat) ? '' : 'none';
    });
  });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
