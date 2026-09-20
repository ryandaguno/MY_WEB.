<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $name  = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $dur   = (int)($_POST['duration_minutes'] ?? 0);
    $cat   = trim($_POST['category'] ?? '');
    $desc  = trim($_POST['description'] ?? '');

    if (!$name || !$cat)  { $error = 'Name and category are required.'; }
    elseif ($price <= 0)  { $error = 'Price must be greater than zero.'; }
    elseif ($dur <= 0)    { $error = 'Duration must be greater than zero minutes.'; }
    else {
        $db = getDB();
        $db->prepare('INSERT INTO services (name,description,price,duration_minutes,category) VALUES (?,?,?,?,?)')
           ->execute([$name, $desc, $price, $dur, $cat]);
        SessionGuard::flashMessage('success', '"' . $name . '" has been added.');
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Add Service';
require_once __DIR__ . '/../includes/admin_header.php';

// Get existing categories for suggestions
$db = getDB();
$existingCats = $db->query("SELECT DISTINCT category FROM services ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$csrfToken = SessionGuard::generateCsrfToken();
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">Add New Service</h3>
    <p class="text-muted small mb-0">Service will be immediately visible to clients after saving.</p>
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
             value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
             required maxlength="100" placeholder="e.g. Brazilian Wax">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">
        Category <span class="text-danger">*</span>
        <span class="text-muted small fw-normal">— type or choose existing</span>
      </label>
      <input type="text" name="category" class="form-control"
             value="<?= htmlspecialchars($_POST['category'] ?? '') ?>"
             required placeholder="e.g. Hair, Facial, Nails"
             list="catList">
      <datalist id="catList">
        <?php foreach ($existingCats as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>">
        <?php endforeach; ?>
        <option value="Hair">
        <option value="Facial">
        <option value="Nails">
        <option value="Skin">
        <option value="Waxing">
        <option value="Makeup">
      </datalist>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="form-label fw-semibold">Price (₱) <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text">₱</span>
          <input type="number" name="price" class="form-control"
                 value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                 step="0.01" min="1" required placeholder="0.00">
        </div>
      </div>
      <div class="col-6">
        <label class="form-label fw-semibold">Duration <span class="text-danger">*</span></label>
        <div class="input-group">
          <input type="number" name="duration_minutes" class="form-control"
                 value="<?= htmlspecialchars($_POST['duration_minutes'] ?? '') ?>"
                 min="1" required placeholder="30">
          <span class="input-group-text">min</span>
        </div>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label fw-semibold">
        Description <span class="text-muted small fw-normal">(optional)</span>
      </label>
      <textarea name="description" class="form-control" rows="3"
                placeholder="Brief description shown to clients..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary flex-fill">Cancel</a>
      <button type="submit" class="btn btn-sa-primary flex-fill">
        <i class="bi bi-plus-circle me-1"></i>Add Service
      </button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
