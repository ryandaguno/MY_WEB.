<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/Auth.php';
SessionGuard::start();
$token  = trim($_GET['token'] ?? '');
$error  = '';
$done   = false;
if (!$token) { header('Location: ' . BASE_URL . '/public/auth/login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['password_confirm'] ?? '';
    if ($pw !== $pw2) {
        $error = 'Passwords do not match.';
    } else {
        $auth   = new Auth();
        $result = $auth->resetPassword($token, $pw);
        if ($result['success']) {
            $done = true;
        } else {
            $error = $result['message'];
        }
    }
}
$csrfToken = SessionGuard::generateCsrfToken();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><title>Reset Password – Selah Aesthetics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head><body>
<div class="container">
  <div class="auth-card card shadow">
    <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Reset Password</div>
    <div class="card-body p-4">
      <?php if ($done): ?>
        <div class="alert alert-success">Your password has been reset successfully!</div>
        <a href="<?= BASE_URL ?>/public/auth/login.php" class="btn btn-sa-primary w-100">Log In</a>
      <?php else: ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">New Password</label>
            <input type="password" name="password" class="form-control" minlength="8" maxlength="128" required>
            <div class="form-text">Minimum 8 characters.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Confirm New Password</label>
            <input type="password" name="password_confirm" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-sa-primary w-100">Reset Password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
