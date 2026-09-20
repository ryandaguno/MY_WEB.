<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
// Redirect if already logged in
if (SessionGuard::isClientLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/home.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Pending – Selah Aesthetics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #4a1f62 0%, #6B2D8B 50%, #0D9488 100%);
      padding: 24px;
    }
    .pending-card {
      background: #fff;
      border-radius: 20px;
      padding: 48px 40px;
      max-width: 480px;
      width: 100%;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,.25);
    }
    .icon-circle {
      width: 90px; height: 90px;
      border-radius: 50%;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 24px;
      font-size: 2.5rem;
      color: white;
    }
    .pending-title {
      font-size: 1.5rem;
      font-weight: 800;
      color: #1a1a2e;
      margin-bottom: 12px;
    }
    .pending-msg {
      color: #555;
      font-size: .92rem;
      line-height: 1.7;
      margin-bottom: 28px;
    }
    .steps {
      background: #f8f4fb;
      border-radius: 12px;
      padding: 20px 24px;
      text-align: left;
      margin-bottom: 28px;
    }
    .step {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      margin-bottom: 14px;
      font-size: .85rem;
      color: #444;
    }
    .step:last-child { margin-bottom: 0; }
    .step-num {
      width: 28px; height: 28px;
      border-radius: 50%;
      background: #6B2D8B;
      color: white;
      font-weight: 800;
      font-size: .8rem;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .btn-home {
      display: inline-block;
      background: linear-gradient(135deg, #6B2D8B, #0D9488);
      color: white;
      border: none;
      border-radius: 30px;
      padding: 13px 36px;
      font-size: .9rem;
      font-weight: 700;
      text-decoration: none;
      transition: opacity .2s;
    }
    .btn-home:hover { opacity: .85; color: white; }
  </style>
</head>
<body>
  <div class="pending-card">
    <div class="icon-circle">
      <i class="bi bi-hourglass-split"></i>
    </div>

    <h1 class="pending-title">Account Under Review</h1>
    <p class="pending-msg">
      Thank you for registering with <strong>Selah Aesthetics</strong>!<br>
      Your account has been submitted and is currently waiting for admin approval.
    </p>

    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div><strong>Verify your email</strong> — Check your inbox and click the verification link we sent you.</div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div><strong>Wait for admin approval</strong> — Our team will review your account shortly.</div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div><strong>You're in!</strong> — Once approved, you can log in and book your appointment.</div>
      </div>
    </div>

    <a href="<?= BASE_URL ?>/public/home.php" class="btn-home">
      <i class="bi bi-house me-2"></i>Back to Home
    </a>
  </div>
</body>
</html>
