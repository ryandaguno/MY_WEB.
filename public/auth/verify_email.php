<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/Auth.php';
$token  = trim($_GET['token'] ?? '');
$result = ['success' => false, 'message' => 'Invalid verification link.'];
if ($token) {
    $auth   = new Auth();
    $result = $auth->verifyEmail($token);
}
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><title>Email Verification – Selah Aesthetics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head><body>
<div class="container">
  <div class="auth-card card shadow">
    <div class="card-header"><i class="bi bi-envelope-check me-2"></i>Email Verification</div>
    <div class="card-body p-4 text-center">
      <?php if ($result['success']): ?>
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
        <h4 class="mt-3 text-success">Verified!</h4>
        <p><?= htmlspecialchars($result['message']) ?></p>
        <a href="<?= BASE_URL ?>/public/auth/login.php" class="btn btn-sa-primary mt-2">Go to Login</a>
      <?php else: ?>
        <i class="bi bi-x-circle-fill text-danger" style="font-size:3rem"></i>
        <h4 class="mt-3 text-danger">Verification Failed</h4>
        <p><?= htmlspecialchars($result['message']) ?></p>
        <a href="<?= BASE_URL ?>/public/auth/login.php" class="btn btn-sa-primary mt-2">Back to Login</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body></html>
