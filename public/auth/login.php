<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/Auth.php';
SessionGuard::start();

// Already logged in — redirect to appropriate dashboard
if (SessionGuard::isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php'); exit;
}
if (SessionGuard::isClientLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/home.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Session expired. Please refresh and try again.');
    }

    $login    = trim($_POST['login'] ?? '');    // accepts email OR username
    $password = $_POST['password'] ?? '';
    $auth     = new Auth();

    if (empty($login) || empty($password)) {
        $error = 'Please enter your username/email and password.';
    } else {
        // Try admin first (by username)
        $adminResult = $auth->adminLogin($login, $password);
        if ($adminResult['success']) {
            SessionGuard::setAdminSession($adminResult['admin']);
            header('Location: ' . BASE_URL . '/admin/dashboard.php'); exit;
        }

        // Try client (by email)
        $clientResult = $auth->login($login, $password);
        if ($clientResult['success']) {
            SessionGuard::setClientSession($clientResult['client']);
            header('Location: ' . BASE_URL . '/public/home.php'); exit;
        }

        // Both failed — show generic error
        $error = 'The username/email or password is incorrect.';
    }
}

$csrfToken  = SessionGuard::generateCsrfToken();
$timeout    = isset($_GET['timeout']) ? 'Your session expired. Please log in again.' : '';
$successMsg = SessionGuard::getFlash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – Selah Aesthetics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body {
      background: linear-gradient(135deg, #6B2D8B 0%, #0D9488 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-card {
      width: 420px;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .login-header {
      background: linear-gradient(135deg, #6B2D8B, #0D9488);
      padding: 36px 32px 24px;
      text-align: center;
      color: white;
    }
    .login-header .logo {
      font-size: 2.4rem;
      font-weight: 800;
      letter-spacing: -1px;
    }
    .login-header p {
      opacity: 0.85;
      margin: 4px 0 0;
      font-size: .95rem;
    }
    .login-body {
      background: white;
      padding: 32px;
    }
    .form-control {
      border-radius: 10px;
      padding: 12px 16px;
      border: 1.5px solid #e0d5ea;
    }
    .form-control:focus {
      border-color: #6B2D8B;
      box-shadow: 0 0 0 3px rgba(107,45,139,.12);
    }
    .input-group-text {
      border-radius: 10px 0 0 10px;
      background: #f3e8fb;
      border: 1.5px solid #e0d5ea;
      border-right: none;
      color: #6B2D8B;
    }
    .input-group .form-control {
      border-radius: 0 10px 10px 0;
      border-left: none;
    }
    .btn-login {
      background: linear-gradient(135deg, #6B2D8B, #0D9488);
      color: white;
      border: none;
      border-radius: 10px;
      padding: 13px;
      font-weight: 700;
      font-size: 1rem;
      letter-spacing: .5px;
      transition: opacity .2s;
    }
    .btn-login:hover { opacity: .9; color: white; }
    .divider {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #aaa;
      font-size: .85rem;
      margin: 20px 0;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #e0d5ea;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <!-- Header -->
    <div class="login-header">
      <div class="logo"><i class="bi bi-scissors me-2"></i>Selah</div>
      <p>Aesthetics Appointment System</p>
    </div>

    <!-- Body -->
    <div class="login-body">
      <?php if ($timeout): ?>
        <div class="alert alert-warning py-2 small"><?= htmlspecialchars($timeout) ?></div>
      <?php endif; ?>
      <?php if ($successMsg): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($successMsg) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold text-secondary small">USERNAME OR EMAIL</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
            <input type="text" name="login" class="form-control"
                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                   placeholder="Enter username or email" required autofocus>
          </div>
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between">
            <label class="form-label fw-semibold text-secondary small">PASSWORD</label>
            <a href="<?= BASE_URL ?>/public/auth/forgot_password.php"
               class="small text-decoration-none" style="color:#6B2D8B">Forgot Password?</a>
          </div>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" name="password" class="form-control"
                   placeholder="Enter password" required>
          </div>
        </div>

        <button type="submit" class="btn btn-login w-100 mt-2">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>

      <div class="divider">or</div>

      <div class="text-center">
        <p class="mb-0 text-muted small">Don't have an account?
          <a href="<?= BASE_URL ?>/public/auth/register.php"
             class="fw-bold text-decoration-none" style="color:#6B2D8B">Register Here</a>
        </p>
      </div>
    </div>

    <!-- Footer note -->
    <div class="text-center py-3" style="background:#f8f4fb;font-size:.75rem;color:#999">
      &copy; <?= date('Y') ?> Selah Aesthetics. All rights reserved.
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
