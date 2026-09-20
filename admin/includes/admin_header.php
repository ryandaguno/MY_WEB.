<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
$adminUsername = htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$currentPage   = basename($_SERVER['PHP_SELF']);
$currentDir    = basename(dirname($_SERVER['PHP_SELF']));

/* Redirect any stale request to the dead timeslots pages immediately */
if ($currentDir === 'timeslots') {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Location: ' . BASE_URL . '/admin/stylist_schedule/index.php', true, 302);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'Admin Panel' ?> – Selah Aesthetics</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="d-flex">

  <!-- ═══════════════════════════════════════════
       SIDEBAR
       "Time Slots" is permanently removed.
       Schedule management = Stylist Schedules page.
       ═══════════════════════════════════════════ -->
  <div class="admin-sidebar d-flex flex-column" style="width:230px;min-width:230px;">
    <div class="sidebar-brand"><i class="bi bi-scissors me-2"></i>Selah Admin</div>

    <nav class="nav flex-column flex-grow-1">

      <!-- Dashboard -->
      <a class="nav-link<?= $currentPage === 'dashboard.php' ? ' active' : '' ?>"
         href="<?= BASE_URL ?>/admin/dashboard.php">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
      </a>

      <!-- Transactions -->
      <a class="nav-link<?= $currentDir === 'transactions' ? ' active' : '' ?>"
         href="<?= BASE_URL ?>/admin/transactions/index.php">
        <i class="bi bi-credit-card me-2"></i>Transactions
      </a>

      <!-- Bookings -->
      <a class="nav-link<?= $currentDir === 'bookings' ? ' active' : '' ?>"
         href="<?= BASE_URL ?>/admin/bookings/index.php">
        <i class="bi bi-calendar3 me-2"></i>Bookings
      </a>

      <!-- Clients -->
      <?php
        $pendingClients = 0;
        try {
            require_once __DIR__ . '/../../config/db.php';
            $pcStmt = getDB()->query("SELECT COUNT(*) FROM clients WHERE is_verified=1 AND (is_approved IS NULL OR is_approved=0)");
            $pendingClients = (int)$pcStmt->fetchColumn();
        } catch (Exception $e) {}
      ?>
      <a class="nav-link<?= $currentDir === 'clients' ? ' active' : '' ?>"
         href="<?= BASE_URL ?>/admin/clients/index.php">
        <i class="bi bi-person-check me-2"></i>Clients
        <?php if ($pendingClients > 0): ?>
          <span class="badge bg-warning text-dark ms-1"><?= $pendingClients ?></span>
        <?php endif; ?>
      </a>

      <!-- Stylists -->
      <a class="nav-link<?= $currentDir === 'stylists' ? ' active' : '' ?>"
         href="<?= BASE_URL ?>/admin/stylists/index.php">
        <i class="bi bi-people me-2"></i>Stylists
      </a>

      <!-- ─── SCHEDULE SECTION ─────────────────────
           Stylist Schedules = per-stylist weekly recurring
             schedule (Mon–Sun, start/break/end times).
             Slots auto-generate from this — no manual entry.
           Operating Hours = salon-wide open/close per weekday.
           ──────────────────────────────────────────── -->
      <div class="px-3 pt-3 pb-1">
        <small class="text-uppercase fw-bold"
               style="color:#6c6c8a;font-size:.65rem;letter-spacing:1px">
          Schedule Management
        </small>
      </div>

      <a class="nav-link<?= $currentDir === 'stylist_schedule' ? ' active' : '' ?>"
         href="<?= BASE_URL ?>/admin/stylist_schedule/index.php">
        <i class="bi bi-calendar-week me-2"></i>Stylist Schedules
      </a>


      <!-- Services -->
      <div class="px-3 pt-3 pb-1">
        <small class="text-uppercase fw-bold"
               style="color:#6c6c8a;font-size:.65rem;letter-spacing:1px">
          Catalogue
        </small>
      </div>

      <a class="nav-link<?= $currentDir === 'services' ? ' active' : '' ?>"
         href="<?= BASE_URL ?>/admin/services/index.php">
        <i class="bi bi-scissors me-2"></i>Services
      </a>

      <!-- Ratings -->
      <a class="nav-link<?= $currentDir === 'ratings' ? ' active' : '' ?>"
         href="<?= BASE_URL ?>/admin/ratings/index.php">
        <i class="bi bi-star me-2"></i>Ratings
      </a>

    </nav>

    <!-- Logout -->
    <div class="p-3 mt-auto border-top border-secondary">
      <small class="text-secondary d-block mb-2"><?= $adminUsername ?></small>
      <a href="<?= BASE_URL ?>/admin/auth/logout.php"
         class="btn btn-sm btn-outline-danger w-100">
        <i class="bi bi-box-arrow-right me-1"></i>Log Out
      </a>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════
       MAIN CONTENT AREA
       ═══════════════════════════════════════════ -->
  <div class="flex-grow-1 p-4" style="background:#f8f9fa;min-height:100vh;">
<?php
/* Flash messages */
$successMsg = SessionGuard::getFlash('success');
$errorMsg   = SessionGuard::getFlash('error');
if ($successMsg): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif;
if ($errorMsg): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
