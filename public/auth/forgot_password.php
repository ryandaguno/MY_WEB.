<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/Auth.php';
SessionGuard::start();
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $auth = new Auth();
    $auth->sendForgotPassword($_POST['email'] ?? '');
    $sent = true; // Always show same message
}
$csrfToken = SessionGuard::generateCsrfToken();
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><title>Forgot Password – Selah Aesthetics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head><body>
<div class="container">
  <div class="auth-card card shadow">
    <div class="card-header"><i class="bi bi-key me-2"></i>Forgot Password</div>
    <div class="card-body p-4">
      <?php if ($sent): ?>
        <div class="alert alert-success">If that email is registered, a password reset link has been sent. Please check your inbox.</div>
        <a href="<?= BASE_URL ?>/public/auth/login.php" class="btn btn-sa-primary w-100">Back to Login</a>
      <?php else: ?>
        <p class="text-muted small">Enter your registered email address and we'll send you a reset link.</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" name="email" class="form-control" required autofocus>
          </div>
          <button type="submit" class="btn btn-sa-primary w-100">Send Reset Link</button>
        </form>
        <hr>
        <p class="text-center mb-0"><a href="<?= BASE_URL ?>/public/auth/login.php">Back to Login</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
