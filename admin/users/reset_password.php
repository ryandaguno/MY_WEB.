<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/Validator.php';
$db = getDB();

$id = (int)($_GET['id'] ?? $_POST['client_id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/users/index.php'); exit; }

$client = $db->prepare('SELECT id, username, email FROM clients WHERE id = ?');
$client->execute([$id]);
$client = $client->fetch();
if (!$client) { header('Location: ' . BASE_URL . '/admin/users/index.php'); exit; }

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');

    $newPassword = $_POST['new_password']     ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    // Validate strength using the existing Validator
    $pwErrors = Validator::validatePassword($newPassword);
    if (!empty($pwErrors)) {
        $errors = $pwErrors;
    } elseif ($newPassword !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare(
            'UPDATE clients SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?'
        )->execute([$hash, $id]);

        SessionGuard::flashMessage('success',
            'Password for ' . $client['username'] . ' has been reset successfully.');
        header('Location: ' . BASE_URL . '/admin/users/index.php'); exit;
    }
}

$pageTitle = 'Reset Password – ' . $client['username'];
$csrfToken = SessionGuard::generateCsrfToken();
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
  <a href="<?= BASE_URL ?>/admin/users/index.php" class="text-decoration-none text-muted small">
    <i class="bi bi-arrow-left me-1"></i>Back to User Management
  </a>
  <h3 class="fw-bold mt-1 mb-0" style="color:var(--sa-purple)">
    <i class="bi bi-key-fill me-2"></i>Reset Password
  </h3>
  <p class="text-muted small mb-0">
    Setting a new password for <strong><?= htmlspecialchars($client['username']) ?></strong>
    (<?= htmlspecialchars($client['email']) ?>)
  </p>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold">New Password</h5>
      </div>
      <div class="card-body p-4">
        <form method="post" id="resetForm">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="client_id"  value="<?= $id ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold" for="new_password">New Password</label>
            <div class="input-group">
              <input type="password" id="new_password" name="new_password"
                     class="form-control" autocomplete="new-password"
                     placeholder="Enter new password" required>
              <button type="button" class="btn btn-outline-secondary" id="togglePw"
                      title="Show/hide password">
                <i class="bi bi-eye" id="eyeIcon"></i>
              </button>
            </div>
            <!-- Strength meter -->
            <div class="progress mt-2" style="height:5px">
              <div id="strengthBar" class="progress-bar" role="progressbar"
                   style="width:0%;transition:width .3s"></div>
            </div>
            <small id="strengthLabel" class="form-text text-muted"></small>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold" for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   class="form-control" autocomplete="new-password"
                   placeholder="Repeat new password" required>
            <div id="matchMsg" class="form-text"></div>
          </div>

          <!-- Requirements checklist -->
          <div class="mb-4 p-3 rounded" style="background:#f8f4fb;font-size:.82rem">
            <p class="fw-semibold mb-2">Password requirements:</p>
            <ul class="list-unstyled mb-0" id="reqList">
              <li id="req-len"><i class="bi bi-x-circle text-danger me-1"></i>At least 8 characters</li>
              <li id="req-upper"><i class="bi bi-x-circle text-danger me-1"></i>One uppercase letter</li>
              <li id="req-lower"><i class="bi bi-x-circle text-danger me-1"></i>One lowercase letter</li>
              <li id="req-digit"><i class="bi bi-x-circle text-danger me-1"></i>One number</li>
            </ul>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger px-4" id="submitBtn">
              <i class="bi bi-shield-lock me-1"></i>Reset Password
            </button>
            <a href="<?= BASE_URL ?>/admin/users/edit.php?id=<?= $id ?>"
               class="btn btn-outline-secondary">
              Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Info panel -->
  <div class="col-lg-4 mt-4 mt-lg-0">
    <div class="card border-0 shadow-sm border-start border-warning border-3">
      <div class="card-body p-4">
        <h6 class="fw-bold text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Admin Note</h6>
        <p class="small text-muted mb-2">
          You are setting this password <strong>directly</strong> on the client's account.
          The client will not receive an automated email about this change.
        </p>
        <p class="small text-muted mb-2">
          If you want the client to choose their own password, use the
          <strong>Forgot Password</strong> flow on the client-facing login page instead.
        </p>
        <p class="small text-muted mb-0">
          This action also clears any active lockout on the account.
        </p>
      </div>
    </div>
  </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePw').addEventListener('click', function () {
    const inp  = document.getElementById('new_password');
    const icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

// Live strength + requirements
document.getElementById('new_password').addEventListener('input', function () {
    const v     = this.value;
    const len   = v.length >= 8;
    const upper = /[A-Z]/.test(v);
    const lower = /[a-z]/.test(v);
    const digit = /\d/.test(v);

    const mark = (id, pass) => {
        const el = document.getElementById(id);
        el.innerHTML = pass
            ? '<i class="bi bi-check-circle text-success me-1"></i>' + el.innerText.replace(/.*\s/, '') + el.innerText.match(/\s.*/)[0]
            : '<i class="bi bi-x-circle text-danger me-1"></i>'   + el.innerText.replace(/.*\s/, '') + el.innerText.match(/\s.*/)[0];
    };

    // Simpler approach: replace whole text
    const setReq = (id, pass, text) => {
        const el = document.getElementById(id);
        el.innerHTML = (pass
            ? '<i class="bi bi-check-circle text-success me-1"></i>'
            : '<i class="bi bi-x-circle text-danger me-1"></i>') + text;
    };
    setReq('req-len',   len,   'At least 8 characters');
    setReq('req-upper', upper, 'One uppercase letter');
    setReq('req-lower', lower, 'One lowercase letter');
    setReq('req-digit', digit, 'One number');

    const score = [len, upper, lower, digit].filter(Boolean).length;
    const bar   = document.getElementById('strengthBar');
    const lbl   = document.getElementById('strengthLabel');
    const pct   = score * 25;
    bar.style.width = pct + '%';
    bar.className   = 'progress-bar ' + ['','bg-danger','bg-warning','bg-info','bg-success'][score];
    lbl.textContent = ['','Weak','Fair','Good','Strong'][score];
});

// Confirm match
document.getElementById('confirm_password').addEventListener('input', function () {
    const pw  = document.getElementById('new_password').value;
    const msg = document.getElementById('matchMsg');
    if (this.value === '') { msg.textContent = ''; return; }
    if (this.value === pw) {
        msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</span>';
    } else {
        msg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>