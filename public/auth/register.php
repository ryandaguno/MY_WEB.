<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/Auth.php';
SessionGuard::start();
if (SessionGuard::isClientLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/home.php'); exit;
}
$errors = [];
$formData = ['username' => '', 'email' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Session expired. Please refresh and try again.');
    }
    $auth   = new Auth();
    $result = $auth->register($_POST);
    if ($result['success']) {
        SessionGuard::flashMessage('success', 'Account created! Please check your email and click the verification link to activate your account.');
        header('Location: ' . BASE_URL . '/public/auth/login.php'); exit;
    } else {
        $errors = $result['errors'];
        $formData = ['username' => htmlspecialchars($_POST['username'] ?? ''),
                     'email'    => htmlspecialchars($_POST['email'] ?? ''),
                     'phone'    => htmlspecialchars($_POST['phone'] ?? '')];
    }
}
$csrfToken = SessionGuard::generateCsrfToken();
$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – Selah Aesthetics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="container">
  <div class="auth-card card shadow">
    <div class="card-header">
      <i class="bi bi-person-plus me-2"></i>Create Your Account
    </div>
    <div class="card-body p-4">
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="mb-3">
          <label class="form-label fw-semibold">Username</label>
          <input type="text" name="username" class="form-control <?= isset($errors['username'])?'is-invalid':'' ?>"
                 value="<?= $formData['username'] ?>" maxlength="50" required>
          <div class="invalid-feedback"><?= htmlspecialchars($errors['username'] ?? '') ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Email Address</label>
          <input type="email" name="email" class="form-control <?= isset($errors['email'])?'is-invalid':'' ?>"
                 value="<?= $formData['email'] ?>" maxlength="254" required>
          <div class="invalid-feedback"><?= htmlspecialchars($errors['email'] ?? '') ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Phone Number</label>
          <input type="text" name="phone" class="form-control <?= isset($errors['phone'])?'is-invalid':'' ?>"
                 value="<?= $formData['phone'] ?>" maxlength="20" required>
          <div class="invalid-feedback"><?= htmlspecialchars($errors['phone'] ?? '') ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Password</label>
          <input type="password" name="password" class="form-control <?= isset($errors['password'])?'is-invalid':'' ?>"
                 minlength="8" maxlength="128" required>
          <div class="invalid-feedback"><?= htmlspecialchars($errors['password'] ?? '') ?></div>
          <div class="form-text">Minimum 8 characters.</div>
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="terms" required>
          <label class="form-check-label" for="terms">I agree to the Terms &amp; Conditions</label>
        </div>
        <button type="submit" class="btn btn-sa-primary w-100 py-2">Create Account</button>
      </form>
      <hr>
      <p class="text-center mb-0">Already have an account? <a href="<?= BASE_URL ?>/public/auth/login.php">Sign In</a></p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
