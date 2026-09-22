<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();

$pageTitle = 'Reset Password';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? $_POST['client_id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/users/index.php'); exit; }

$row = $db->prepare('SELECT id, username, email FROM clients WHERE id = ?');
$row->execute([$id]);
$client = $row->fetch();
if (!$client) { header('Location: ' . BASE_URL . '/admin/users/index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');

    $newPw   = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPw) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($newPw !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare(
            'UPDATE clients SET password_hash=?, failed_login_attempts=0, locked_until=NULL WHERE id=?'
        )->execute([$hash, $id]);

        SessionGuard::flashMessage('success', 'Password for ' . $client['username'] . ' has been reset.');
        header('Location: ' . BASE_URL . '/admin/users/index.php'); exit;
    }
}
$csrfToken = SessionGuard::generateCsrfToken();
?>

<div class="mb-3">
  <a href="<?= BASE_URL ?>/admin/users/index.php" class="text-decoration-none text-muted small">
    <i class="bi bi-arrow-left me-1"></i>Back to Users
  </a>
  <h3 class="fw-bold mt-1" style="color:var(--sa-purple)">
    <i class="bi bi-key-fill me-2"></i>Reset Password — <?= htmlspecialchars($client['username']) ?>
  </h3>
  <p class="text-muted small"><?= htmlspecialchars($client['email']) ?></p>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Set New Password</div>
      <div class="card-body p-4">
        <form method="post" id="pwForm">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="client_id"  value="<?= $id ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold">New Password</label>
            <div class="input-group">
              <input type="password" id="newPw" name="new_password"
                     class="form-control" placeholder="Min. 8 characters" required>
              <button type="button" class="btn btn-outline-secondary" id="togglePw">
                <i class="bi bi-eye" id="eyeIcon"></i>
              </button>
            </div>
            <div class="progress mt-2" style="height:5px">
              <div id="strengthBar" class="progress-bar" style="width:0%;transition:width .3s"></div>
            </div>
            <small id="strengthLabel" class="text-muted"></small>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Confirm Password</label>
            <input type="password" id="confirmPw" name="confirm_password"
                   class="form-control" placeholder="Repeat password" required>
            <div id="matchMsg" class="form-text"></div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger px-4">
              <i class="bi bi-shield-lock me-1"></i>Reset Password
            </button>
            <a href="<?= BASE_URL ?>/admin/users/edit.php?id=<?= $id ?>"
               class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mt-4 mt-lg-0">
    <div class="card border-0 shadow-sm border-start border-warning border-3">
      <div class="card-body p-4">
        <h6 class="fw-bold text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Note</h6>
        <p class="small text-muted mb-0">
          You are setting this password directly. The client will <strong>not</strong> receive an email.
          This also clears any account lockout.
        </p>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('togglePw').addEventListener('click', function() {
  var inp = document.getElementById('newPw');
  var ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text'; ico.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password'; ico.className = 'bi bi-eye';
  }
});

document.getElementById('newPw').addEventListener('input', function() {
  var v = this.value;
  var score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  var bar = document.getElementById('strengthBar');
  var lbl = document.getElementById('strengthLabel');
  var colors = ['','bg-danger','bg-warning','bg-info','bg-success'];
  var labels = ['','Weak','Fair','Good','Strong'];
  bar.style.width = (score * 25) + '%';
  bar.className = 'progress-bar ' + (colors[score] || '');
  lbl.textContent = labels[score] || '';
});

document.getElementById('confirmPw').addEventListener('input', function() {
  var msg = document.getElementById('matchMsg');
  if (!this.value) { msg.textContent = ''; return; }
  if (this.value === document.getElementById('newPw').value) {
    msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</span>';
  } else {
    msg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
