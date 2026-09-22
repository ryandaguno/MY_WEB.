<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
register_shutdown_function(function(){
    $e = error_get_last();
    if($e && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])){
        ob_clean();
        die("<pre style='background:red;color:white;padding:20px'>FATAL: {$e['message']}\nFile: {$e['file']}\nLine: {$e['line']}</pre>");
    }
});
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';
$db = getDB();

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/admin_header.php';

$filterStatus = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');
$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(c.username LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($filterStatus === 'pending') {
    $where[] = "(COALESCE(c.account_status,'pending') = 'pending')";
} elseif ($filterStatus === 'approved') {
    $where[] = "c.account_status = 'approved'";
} elseif ($filterStatus === 'rejected') {
    $where[] = "c.account_status = 'rejected'";
} elseif ($filterStatus === 'unverified') {
    $where[] = 'c.is_verified = 0';
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare(
    "SELECT c.id, c.username, c.email, c.phone, c.is_verified,
            COALESCE(c.is_approved, 0)             AS is_approved,
            COALESCE(c.account_status, 'pending')  AS account_status,
            c.created_at,
            COUNT(DISTINCT b.id) AS booking_count
     FROM clients c
     LEFT JOIN bookings b ON b.client_id = c.id
     {$whereSQL}
     GROUP BY c.id
     ORDER BY FIELD(COALESCE(c.account_status,'pending'),'pending','rejected','approved'),
              c.created_at DESC"
);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$counts = $db->query(
    "SELECT
       COUNT(*) AS total,
       SUM(is_verified = 0) AS unverified,
       SUM(COALESCE(account_status,'pending') = 'pending') AS pending,
       SUM(account_status = 'approved')  AS approved,
       SUM(account_status = 'rejected')  AS rejected
     FROM clients"
)->fetch();
?>
<style>
.user-table th          { background:#6B2D8B; color:#fff; font-size:.78rem; white-space:nowrap; }
.user-table td          { font-size:.83rem; vertical-align:middle; }
.badge-pending          { background:#f59e0b; color:#fff; }
.badge-approved         { background:#10b981; color:#fff; }
.badge-rejected         { background:#ef4444; color:#fff; }
.badge-unverified       { background:#6c757d; color:#fff; }
.filter-btn.active      { background:#6B2D8B !important; color:#fff !important; border-color:#6B2D8B !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
      <i class="bi bi-people-fill me-2"></i>User Management
    </h3>
    <p class="text-muted small mb-0">View, edit, and manage all registered client accounts.</p>
  </div>
  <span class="badge bg-secondary fs-6"><?= (int)$counts['total'] ?> Total Users</span>
</div>

<!-- Filter bar -->
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
  <div class="d-flex gap-1 flex-wrap">
    <?php
    $filters = [
      'all'        => ['label' => 'All',       'count' => $counts['total']],
      'pending'    => ['label' => 'Pending',    'count' => $counts['pending']],
      'approved'   => ['label' => 'Approved',   'count' => $counts['approved']],
      'rejected'   => ['label' => 'Rejected',   'count' => $counts['rejected']],
      'unverified' => ['label' => 'Unverified', 'count' => $counts['unverified']],
    ];
    foreach ($filters as $key => $f):
        $active = ($filterStatus === $key) ? 'active' : '';
        $qs     = '?status=' . $key . ($search !== '' ? '&search=' . urlencode($search) : '');
    ?>
    <a href="<?= BASE_URL . '/admin/users/index.php' . $qs ?>"
       class="btn btn-sm btn-outline-secondary filter-btn <?= $active ?>">
      <?= $f['label'] ?>
      <span class="badge bg-white text-dark ms-1"><?= (int)$f['count'] ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Search -->
  <form method="get" class="ms-auto d-flex gap-2" style="min-width:260px">
    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
    <input type="text"  name="search" class="form-control form-control-sm"
           placeholder="Search username, email, phone…"
           value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    <?php if ($search !== ''): ?>
      <a href="<?= BASE_URL . '/admin/users/index.php?status=' . urlencode($filterStatus) ?>"
         class="btn btn-sm btn-outline-danger" title="Clear search"><i class="bi bi-x-lg"></i></a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($clients)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-people" style="font-size:3rem"></i>
    <p class="mt-2">No users match the current filter.</p>
  </div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 user-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Username</th>
          <th>Email</th>
          <th>Phone</th>
          <th class="text-center">Bookings</th>
          <th>Registered</th>
          <th class="text-center">Email Verified</th>
          <th>Account Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c):
          $acctStatus = $c['account_status'];
          if ($acctStatus === 'approved') {
              $badge    = '<span class="badge badge-approved">Approved</span>';
              $rowStyle = '';
          } elseif ($acctStatus === 'rejected') {
              $badge    = '<span class="badge badge-rejected">Rejected</span>';
              $rowStyle = 'style="background:#fff5f5"';
          } else {
              $badge    = '<span class="badge badge-pending">Pending</span>';
              $rowStyle = 'style="background:#fffbeb"';
          }
        ?>
        <tr <?= $rowStyle ?>>
          <td class="text-muted fw-bold">#<?= $c['id'] ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($c['username']) ?></td>
          <td><?= htmlspecialchars($c['email']) ?></td>
          <td><?= htmlspecialchars($c['phone']) ?></td>
          <td class="text-center">
            <span class="badge bg-secondary"><?= (int)$c['booking_count'] ?></span>
          </td>
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
                 class="btn btn-primary btn-sm" title="Edit profile">
                <i class="bi bi-pencil-square me-1"></i>Edit
              </a>
              <a href="<?= BASE_URL ?>/admin/users/reset_password.php?id=<?= $c['id'] ?>"
                 class="btn btn-outline-warning btn-sm" title="Reset password">
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