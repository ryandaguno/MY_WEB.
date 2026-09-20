<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $name      = trim($_POST['name'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');
    if (!$name || !$specialty) {
        $error = 'Name and specialty are required.';
    } else {
        $db = getDB();
        $db->prepare('INSERT INTO stylists (name, specialty, bio) VALUES (?,?,?)')
           ->execute([$name, $specialty, $bio]);
        SessionGuard::flashMessage('success', $name . ' has been added successfully.');
        header('Location: ' . BASE_URL . '/admin/stylists/index.php'); exit;
    }
}

$pageTitle = 'Add Stylist';
require_once __DIR__ . '/../includes/admin_header.php';
$csrfToken = SessionGuard::generateCsrfToken();
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">Add New Stylist</h3>
    <p class="text-muted small mb-0">After adding, set their weekly schedule from the Stylists page.</p>
  </div>
</div>

<div class="card border-0 shadow-sm p-4" style="max-width:560px">
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <div class="mb-3">
      <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control"
             value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
             required maxlength="100" placeholder="e.g. Charlotte Santos">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">Specialty <span class="text-danger">*</span></label>
      <select name="specialty" class="form-select" required>
        <option value="" disabled <?= empty($_POST['specialty']) ? 'selected' : '' ?>>— Select Specialty —</option>
        <?php foreach (['Hair','Nails','Facial','Waxing','Makeup','Skin'] as $opt): ?>
          <option value="<?= $opt ?>" <?= ($_POST['specialty'] ?? '') === $opt ? 'selected' : '' ?>>
            <?= $opt ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-4">
      <label class="form-label fw-semibold">Bio <span class="text-muted small fw-normal">(optional)</span></label>
      <textarea name="bio" class="form-control" rows="3" maxlength="500"
                placeholder="Short description about this stylist..."><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
    </div>

    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary flex-fill">Cancel</a>
      <button type="submit" class="btn btn-sa-primary flex-fill">
        <i class="bi bi-plus-circle me-1"></i>Add Stylist
      </button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
