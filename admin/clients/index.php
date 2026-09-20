<?php
// ── All POST handling BEFORE any output ──────────────────────────
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/NotificationService.php';
$db = getDB();

// Auto-add columns if not exists
try {
    $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS account_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    // Sync account_status from existing is_approved/is_verified values
    $db->exec("UPDATE clients SET account_status='approved' WHERE is_approved=1 AND account_status='pending'");
    $db->exec("UPDATE clients SET is_approved = 1 WHERE is_verified = 1 AND is_approved = 0 AND created_at < NOW() - INTERVAL 1 MINUTE AND account_status != 'rejected'");
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $clientId = (int)($_POST['client_id'] ?? 0);
    $action   = $_POST['action'] ?? '';

    $clientStmt = $db->prepare("SELECT username, email FROM clients WHERE id = ?");
    $clientStmt->execute([$clientId]);
    $clientInfo = $clientStmt->fetch();

    $ns = new NotificationService();

    if ($action === 'approve' && $clientId) {
        $db->prepare("UPDATE clients SET is_approved = 1, is_verified = 1, account_status = 'approved' WHERE id = ?")->execute([$clientId]);
        if ($clientInfo) $ns->sendAccountApproved($clientInfo['email'], $clientInfo['username']);
        SessionGuard::flashMessage('success', 'Client approved and notified via email.');
    } elseif ($action === 'reject' && $clientId) {
        $db->prepare("UPDATE clients SET is_approved = 0, is_verified = 0, account_status = 'rejected' WHERE id = ?")->execute([$clientId]);
        if ($clientInfo) $ns->sendAccountRejected($clientInfo['email'], $clientInfo['username']);
        SessionGuard::flashMessage('success', 'Client rejected and notified via email.');
    } elseif ($action === 'delete' && $clientId) {
        $db->prepare("DELETE FROM clients WHERE id = ?")->execute([$clientId]);
        SessionGuard::flashMessage('success', 'Client account deleted.');
    }
    header('Location: ' . BASE_URL . '/admin/clients/index.php'); exit;
}

// ── Page output ───────────────────────────────────────────────────
$pageTitle = 'Client Accounts';
require_once __DIR__ . '/../includes/admin_header.php';

$clients = $db->query(
    "SELECT id, username, email, phone, is_verified, is_approved, account_status, created_at
     FROM clients
     ORDER BY is_approved ASC, is_verified DESC, created_at DESC"
)->fetchAll();

$pendingCount = 0;
foreach ($clients as $c) {
    if (($c['account_status'] ?? 'pending') === 'pending') $pendingCount++;
}

$csrfToken = SessionGuard::generateCsrfToken();
?>

<style>
.client-table th { background:#6B2D8B; color:#fff; font-size:.78rem; white-space:nowrap; }
.client-table td { font-size:.83rem; vertical-align:middle; }
.badge-pending    { background:#f59e0b; color:#fff; }
.badge-approved   { background:#10b981; color:#fff; }
.badge-rejected   { background:#ef4444; color:#fff; }
.badge-unverified { background:#6c757d; color:#fff; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
      <i class="bi bi-people me-2"></i>Client Accounts
    </h3>
    <p class="text-muted small mb-0">Approve or reject newly registered clients.</p>
  </div>
  <?php if ($pendingCount > 0): ?>
  <span class="badge bg-warning text-dark fs-6">
    <i class="bi bi-hourglass-split me-1"></i><?= $pendingCount ?> Pending Approval
  </span>
  <?php endif; ?>
</div>

<?php if (empty($clients)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-people" style="font-size:3rem"></i>
    <p class="mt-2">No registered clients yet.</p>
  </div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 client-table">
      <thead>
        <tr>
          <th>#</th><th>Username</th><th>Email</th><th>Phone</th>
          <th>Registered</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c):
          $isVerified   = (bool)$c['is_verified'];
          $isApproved   = (bool)$c['is_approved'];
          $acctStatus   = $c['account_status'] ?? 'pending';

          if ($acctStatus === 'approved') {
              $statusBadge = '<span class="badge badge-approved">Approved</span>';
          } elseif ($acctStatus === 'rejected') {
              $statusBadge = '<span class="badge badge-rejected">Rejected</span>';
          } else {
              $statusBadge = '<span class="badge badge-pending">Pending</span>';
          }
        ?>
        <tr <?= $acctStatus === 'pending' ? 'style="background:#fffbeb"' : ($acctStatus === 'rejected' ? 'style="background:#fff5f5"' : '') ?>>
          <td class="text-muted fw-bold">#<?= $c['id'] ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($c['username']) ?></td>
          <td><?= htmlspecialchars($c['email']) ?></td>
          <td><?= htmlspecialchars($c['phone']) ?></td>
          <td>
            <?= date('M j, Y', strtotime($c['created_at'])) ?><br>
            <small class="text-muted"><?= date('g:i A', strtotime($c['created_at'])) ?></small>
          </td>
          <td><?= $statusBadge ?></td>
          <td>
            <form method="post" class="d-flex gap-1 flex-wrap">
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
              <input type="hidden" name="client_id"  value="<?= $c['id'] ?>">
              <?php if ($acctStatus !== 'approved'): ?>
                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                  <i class="bi bi-check-circle me-1"></i>Approve
                </button>
              <?php endif; ?>
              <?php if ($acctStatus !== 'rejected'): ?>
                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"
                        onclick="return confirm('Reject this client?')">
                  <i class="bi bi-x-circle me-1"></i>Reject
                </button>
              <?php endif; ?>
              <?php if ($acctStatus === 'approved'): ?>
                <button type="submit" name="action" value="reject" class="btn btn-outline-warning btn-sm"
                        onclick="return confirm('Revoke access for this client?')">
                  <i class="bi bi-slash-circle me-1"></i>Revoke
                </button>
              <?php endif; ?>
              <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm"
                      onclick="return confirm('Delete this client account permanently?')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
