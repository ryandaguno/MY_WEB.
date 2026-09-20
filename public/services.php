<?php
$pageTitle = 'Our Services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Get categories
$categories = $db->query('SELECT DISTINCT category FROM services WHERE is_active = 1 ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

// Get services filtered by category
$selectedCategory = $_GET['category'] ?? 'all';
if ($selectedCategory !== 'all' && in_array($selectedCategory, $categories)) {
    $stmt = $db->prepare('SELECT * FROM services WHERE is_active = 1 AND category = ? ORDER BY name');
    $stmt->execute([$selectedCategory]);
} else {
    $stmt = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY category, name');
    $selectedCategory = 'all';
}
$services = $stmt->fetchAll();

// Group by category
$grouped = [];
foreach ($services as $svc) {
    $grouped[$svc['category']][] = $svc;
}
?>

<div class="container my-4">
  <h2 class="fw-bold mb-4" style="color:var(--sa-purple)"><i class="bi bi-scissors me-2"></i>Our Services</h2>

  <!-- Category Filter -->
  <div class="d-flex flex-wrap gap-2 mb-4">
    <a href="?category=all" class="btn <?= $selectedCategory==='all'?'btn-sa-primary':'btn-outline-secondary' ?> btn-sm">All Categories</a>
    <?php foreach ($categories as $cat): ?>
    <a href="?category=<?= urlencode($cat) ?>" class="btn <?= $selectedCategory===$cat?'btn-sa-primary':'btn-outline-secondary' ?> btn-sm"><?= htmlspecialchars($cat) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($services)): ?>
    <div class="alert alert-info">No services are currently available. Please check back soon.</div>
  <?php else: ?>
    <?php foreach ($grouped as $category => $svcs): ?>
    <h4 class="mb-3 mt-4" style="color:var(--sa-teal)"><i class="bi bi-tag me-2"></i><?= htmlspecialchars($category) ?></h4>
    <div class="row g-3 mb-3">
      <?php foreach ($svcs as $svc): ?>
      <div class="col-md-4 col-sm-6">
        <div class="card card-sa h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title"><?= htmlspecialchars($svc['name']) ?></h5>
            <p class="text-muted small flex-grow-1"><?= htmlspecialchars($svc['description'] ?? 'Professional salon service.') ?></p>
            <div class="d-flex justify-content-between align-items-center mt-3">
              <span class="fw-bold fs-5" style="color:var(--sa-teal)">₱<?= number_format($svc['price'], 2) ?></span>
              <span class="text-muted small"><i class="bi bi-clock me-1"></i><?= $svc['duration_minutes'] ?> min</span>
            </div>
            <?php if (SessionGuard::isClientLoggedIn()): ?>
            <a href="<?= BASE_URL ?>/public/booking/step1_service.php?service_id=<?= $svc['id'] ?>"
               class="btn btn-sa-primary btn-sm mt-3">Book This Service</a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>/public/auth/login.php" class="btn btn-outline-secondary btn-sm mt-3">Login to Book</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
