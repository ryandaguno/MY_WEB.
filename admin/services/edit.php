<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$svc = $db->prepare('SELECT * FROM services WHERE id=?');
$svc->execute([$id]);
$svc = $svc->fetch();

if (!$svc) {
    SessionGuard::flashMessage('error', 'Service not found.');
    header('Location: index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $name     = trim($_POST['name'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $dur      = (int)($_POST['duration_minutes'] ?? 0);
    $cat      = trim($_POST['category'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $isActive = (int)($_POST['is_active'] ?? 1);

    if (!$name || !$cat)  { $error = 'Name and category are required.'; }
    elseif ($price <= 0)  { $error = 'Price must be greater than zero.'; }
    elseif ($dur <= 0)    { $error = 'Duration must be greater than zero.'; }
    else {
        $db->prepare('UPDATE services SET name=?,description=?,price=?,duration_minutes=?,category=?,is_active=? WHERE id=?')
           ->execute([$name, $desc, $price, $dur, $cat, $isActive, $id]);
        SessionGuard::flashMessage('success', '"' . $name . '" has been updated.');
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Edit Service';
require_once __DIR__ . '/../includes/admin_header.php';

$existingCats = $db->query("SELECT DISTINCT category FROM services ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$csrfToken    = SessionGuard::generateCsrfToken();
$d = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $svc;
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">Edit Service</h3>
    <p class="text-muted small mb-0">Editing: <strong><?= htmlspecialchars($svc['name']) ?></strong></p>
  </div>
</div>

<div class="card border-0 shadow-sm p-4" style="max-width:580px">
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <div class="mb-3">
      <label class="form-label fw-semibold">Service Name <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control"
             value="<?= htmlspecialchars($d['name']) ?>" required maxlength="100">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">
        Category <span class="text-danger">*</span>
        <span class="text-muted small fw-normal">— type or choose existing</span>
      </label>
      <input type="text" name="category" class="form-control"
             value="<?= htmlspecialchars($d['category']) ?>"
             required list="catList">
      <datalist id="catList">
        <?php foreach ($existingCats as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="form-label fw-semibold">Price (₱) <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text">₱</span>
          <input type="number" name="price" class="form-control"
                 value="<?= htmlspecialchars($d['price']) ?>"
                 step="0.01" min="1" required>
        </div>
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold">Duration <span class="text-danger">*</span></label>
        <div class="input-group">
          <input type="number" name="duration_minutes" class="form-control"
                 value="<?= htmlspecialchars($d['duration_minutes']) ?>"
                 min="1" required>
          <span class="input-group-text">min</span>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">
        Description <span class="text-muted small fw-normal">(optional)</span>
      </label>
      <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($d['description'] ?? '') ?></textarea>
    </div>

    <div class="mb-4">
      <div class="form-check form-switch">
        <input type="checkbox" name="is_active" value="1" class="form-check-input"
               id="activeToggle" <?= $d['is_active'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="activeToggle">
          Active — visible to clients
        </label>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary flex-fill">Cancel</a>
      <button type="submit" class="btn btn-sa-primary flex-fill">
        <i class="bi bi-check-circle me-1"></i>Save Changes
      </button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
