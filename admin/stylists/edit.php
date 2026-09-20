<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';

$db      = getDB();
$id      = (int)($_GET['id'] ?? 0);
$stmt    = $db->prepare('SELECT * FROM stylists WHERE id = ?');
$stmt->execute([$id]);
$stylist = $stmt->fetch();

if (!$stylist) {
    SessionGuard::flashMessage('error', 'Stylist not found.');
    header('Location: index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $name      = trim($_POST['name'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');
    $isActive  = (int)($_POST['is_active'] ?? 1);
    if (!$name || !$specialty) {
        $error = 'Name and specialty are required.';
    } else {
        $db->prepare('UPDATE stylists SET name=?, specialty=?, bio=?, is_active=? WHERE id=?')
           ->execute([$name, $specialty, $bio, $isActive, $id]);
        SessionGuard::flashMessage('success', $name . ' has been updated.');
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Edit Stylist';
require_once __DIR__ . '/../includes/admin_header.php';
$csrfToken = SessionGuard::generateCsrfToken();
$d = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $stylist;
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">Edit Stylist</h3>
    <p class="text-muted small mb-0">Update details for <strong><?= htmlspecialchars($stylist['name']) ?></strong></p>
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
             value="<?= htmlspecialchars($d['name']) ?>" required maxlength="100">
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">Specialty <span class="text-danger">*</span></label>
      <select name="specialty" class="form-select" required>
        <option value="" disabled>— Select Specialty —</option>
        <?php foreach (['Hair','Nails','Facial','Waxing','Makeup','Skin'] as $opt): ?>
          <option value="<?= $opt ?>" <?= ($d['specialty'] ?? '') === $opt ? 'selected' : '' ?>>
            <?= $opt ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">Bio <span class="text-muted small fw-normal">(optional)</span></label>
      <textarea name="bio" class="form-control" rows="3" maxlength="500"><?= htmlspecialchars($d['bio'] ?? '') ?></textarea>
    </div>

    <div class="mb-4">
      <div class="form-check form-switch">
        <input type="checkbox" name="is_active" value="1" class="form-check-input"
               id="activeToggle" <?= $d['is_active'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="activeToggle">
          Active — visible to clients for booking
        </label>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary flex-fill">Cancel</a>
      <a href="<?= BASE_URL ?>/admin/stylist_schedule/index.php?stylist_id=<?= $id ?>"
         class="btn btn-outline-secondary flex-fill">
        <i class="bi bi-calendar-week me-1"></i>Edit Schedule
      </a>
      <button type="submit" class="btn btn-sa-primary flex-fill">
        <i class="bi bi-check-circle me-1"></i>Save Changes
      </button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
