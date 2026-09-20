<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/SessionGuard.php';
SessionGuard::start();
$currentUsername = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'Selah Aesthetics' ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-sa shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/public/home.php">
      <i class="bi bi-scissors me-1"></i> Selah Aesthetics
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item"><a class="nav-link<?= $currentPage==='home.php'?' active':'' ?>" href="<?= BASE_URL ?>/public/home.php">Home</a></li>
        <li class="nav-item"><a class="nav-link<?= $currentPage==='services.php'?' active':'' ?>" href="<?= BASE_URL ?>/public/services.php">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/public/home.php#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/public/home.php#contact">Contact</a></li>
        <?php if (SessionGuard::isClientLoggedIn()): ?>
        <li class="nav-item"><a class="nav-link<?= $currentPage==='my_bookings.php'?' active':'' ?>" href="<?= BASE_URL ?>/public/my_bookings.php"><i class="bi bi-calendar-check me-1"></i>My Bookings</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= $currentUsername ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/public/auth/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
          </ul>
        </li>
        <?php else: ?>
        <li class="nav-item"><a class="btn btn-sa-teal ms-2 px-3" href="<?= BASE_URL ?>/public/auth/login.php">Log In</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<div id="flash-messages" class="container mt-2">
<?php
$successMsg = SessionGuard::getFlash('success');
$errorMsg   = SessionGuard::getFlash('error');
if ($successMsg): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif;
if ($errorMsg): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
</div>
