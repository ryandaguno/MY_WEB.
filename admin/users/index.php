<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';
$db = getDB();

// Auto-add columns if missing
try {
    $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS account_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
} catch (PDOException $e) {}

$search = trim($_GET['search'] ?? '');
$where  = '';
$params = [];
if ($search !== '') {
    $where    = 'WHERE username LIKE ? OR email LIKE ? OR phone LIKE ?';
    $params   = ["%$search%", "%$search%", "%$search%"];
}

$clients = $db->prepare(
    "SELECT id, username, email, phone, is_verified,
            COALESCE(is_approved,0) AS is_approved,
            COALESCE(account_status,'pending') AS account_status,
            created_at
     FROM clients
     $where
     ORDER BY created_at DESC"
);
$clients->execute($params);
$clients = $clients->fetchAll();
?>

<style>
.user-table th { background:#6B2D8B; color:#fff; font-size:.78rem; white-space:nowrap; }
.user-table td { font-size:.83rem; vertical-align:middle; }
.badge-pending   { background:#f59e0b; color:#fff; }
.badge-approved  { background:#10b981; color:#fff; }
.badge-rejected  { background:#ef4444; color:#fff; }
.badge-unverified{ background:#6c757d; color:#fff; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
      <i class="bi bi-person-gear me-2"></i>User Management
    </h3>
    <p class="text-muted small mb-0">View all client accounts, reset passwords, and manage access.</p>
  </div>
  <span class="badge bg-secondary fs-6"><?= count($clients) ?> Users</span>
</div>

<!-- Search -->
<form method="get" class="d-flex gap-2 mb-3" style="max-width:400px">
  <input type="text" name="search" class="form-control form-control-sm"
         placeholder="Search name, email, phone…"
         value="<?= htmlspecialchars($search) ?>">
  <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
  <?php if ($search): ?>
    <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a>
  <?php endif; ?>
</form>

<?php if (empty($clients)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-people" style="font-size:3rem"></i>
    <p class="mt-2">No users found.</p>
  </div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 user-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Registered</th>
          <th class="text-center">Verified</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c):
          $status = $c['account_status'];
          if ($status === 'approved') {
              $badge = '<span class="badge badge-approved">Approved</span>';
          } elseif ($status === 'rejected') {
              $badge = '<span class="badge badge-rejected">Rejected</span>';
          } else {
              $badge = '<span class="badge badge-pending">Pending</span>';
          }
        ?>
        <tr>
          <td class="text-muted fw-bold">#<?= $c['id'] ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($c['username']) ?></td>
          <td><?= htmlspecialchars($c['email']) ?></td>
          <td><?= htmlspecialchars($c['phone']) ?></td>
          <td>
            <?= date('M j, Y', strtotime($c['created_at'])) ?><br>
            <small class="text-muted"><?= date('g:i A', strtotime($c['created_at'])) ?></small>
          </td>
          <td class="text-center">
            <?php if ($c['is_verified']): ?>
              <span class="badge badge-approved"><i class="bi bi-check-circle me-1"></i>Yes</span>
            <?php else: ?>
              <span class="badge badge-unverified"><i class="bi bi-x-circle me-1"></i>No</span>
            <?php endif; ?>
          </td>
          <td><?= $badge ?></td>
          <td>
            <div class="d-flex gap-1 flex-wrap">
              <a href="<?= BASE_URL ?>/admin/users/edit.php?id=<?= $c['id'] ?>"
                 class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Edit
              </a>
              <a href="<?= BASE_URL ?>/admin/users/reset_password.php?id=<?= $c['id'] ?>"
                 class="btn btn-sm btn-outline-warning">
                <i class="bi bi-key me-1"></i>Reset PW
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
