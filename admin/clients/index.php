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
    } elseif ($action === 'reset_password' && $clientId) {
        $newPw = $_POST['new_password'] ?? '';
        if (strlen($newPw) >= 8) {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE clients SET password_hash=?, failed_login_attempts=0, locked_until=NULL WHERE id=?')
               ->execute([$hash, $clientId]);
            SessionGuard::flashMessage('success', 'Password reset successfully for client #' . $clientId . '.');
        } else {
            SessionGuard::flashMessage('error', 'Password must be at least 8 characters.');
        }
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
                <button type="button" class="btn btn-danger btn-sm"
                        onclick="confirmClientAction('reject', <?= $c['id'] ?>, '<?= htmlspecialchars($c['username'], ENT_QUOTES) ?>')">
                  <i class="bi bi-x-circle me-1"></i>Reject
                </button>
              <?php endif; ?>
              <?php if ($acctStatus === 'approved'): ?>
                <button type="button" class="btn btn-outline-warning btn-sm"
                        onclick="confirmClientAction('revoke', <?= $c['id'] ?>, '<?= htmlspecialchars($c['username'], ENT_QUOTES) ?>')">
                  <i class="bi bi-slash-circle me-1"></i>Revoke
                </button>
              <?php endif; ?>
              <button type="button" class="btn btn-outline-danger btn-sm"
                      onclick="confirmClientAction('delete', <?= $c['id'] ?>, '<?= htmlspecialchars($c['username'], ENT_QUOTES) ?>')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <!-- Reset Password button (outside the approve/reject form) -->
            <button type="button"
                    class="btn btn-outline-secondary btn-sm mt-1"
                    onclick="openResetModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['username'], ENT_QUOTES) ?>')">
              <i class="bi bi-key me-1"></i>Reset PW
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Hidden form for modal-triggered actions -->
<form method="post" id="clientActionForm">
  <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
  <input type="hidden" name="client_id"  id="modalClientId">
  <input type="hidden" name="action"     id="modalAction">
</form>

<!-- Client Action Confirmation Modal -->
<div class="modal fade" id="clientActionModal" tabindex="-1" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden">
      <div id="clientModalHeader" style="padding:28px 24px 20px;text-align:center">
        <div id="clientModalIcon"
             style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.2);
                    display:flex;align-items:center;justify-content:center;
                    margin:0 auto 12px;font-size:2rem;color:white">
        </div>
        <h5 id="clientModalTitle" style="color:white;font-weight:800;margin:0;font-size:1.15rem"></h5>
      </div>
      <div style="padding:24px 28px;text-align:center">
        <p style="color:#374151;font-size:.95rem;margin-bottom:6px" id="clientModalBody"></p>
        <p style="color:#111827;font-weight:800;font-size:1.05rem;margin-bottom:14px" id="clientModalName"></p>
        <p style="color:#6b7280;font-size:.82rem;margin-bottom:24px" id="clientModalWarning"></p>
        <div class="d-flex gap-3">
          <button type="button" class="btn btn-outline-secondary flex-fill fw-semibold"
                  data-bs-dismiss="modal" style="border-radius:10px;padding:11px">
            <i class="bi bi-x-lg me-1"></i>Cancel
          </button>
          <button type="button" id="clientModalConfirm"
                  class="btn flex-fill fw-bold text-white"
                  style="border:none;border-radius:10px;padding:11px"
                  onclick="document.getElementById('clientActionForm').submit()">
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function confirmClientAction(action, id, name) {
  var cfg = {
    reject: {
      title:   'Reject Client',
      icon:    'bi bi-x-circle-fill',
      bg:      'linear-gradient(135deg,#dc2626,#ef4444)',
      body:    'Are you sure you want to reject',
      warning: '<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>The client will be notified by email.',
      btnBg:   'linear-gradient(135deg,#dc2626,#ef4444)',
      btnTxt:  '<i class="bi bi-x-circle me-1"></i>Yes, Reject'
    },
    revoke: {
      title:   'Revoke Access',
      icon:    'bi bi-slash-circle-fill',
      bg:      'linear-gradient(135deg,#d97706,#f59e0b)',
      body:    'Are you sure you want to revoke access for',
      warning: '<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>The client will no longer be able to log in.',
      btnBg:   'linear-gradient(135deg,#d97706,#f59e0b)',
      btnTxt:  '<i class="bi bi-slash-circle me-1"></i>Yes, Revoke'
    },
    delete: {
      title:   'Delete Account',
      icon:    'bi bi-trash3-fill',
      bg:      'linear-gradient(135deg,#7f1d1d,#dc2626)',
      body:    'Are you sure you want to permanently delete',
      warning: '<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>This <strong>cannot be undone</strong>. All data for this client will be removed.',
      btnBg:   'linear-gradient(135deg,#7f1d1d,#dc2626)',
      btnTxt:  '<i class="bi bi-trash3 me-1"></i>Yes, Delete'
    }
  };

  var c = cfg[action];
  document.getElementById('clientModalHeader').style.background = c.bg;
  document.getElementById('clientModalIcon').innerHTML = '<i class="' + c.icon + '"></i>';
  document.getElementById('clientModalTitle').textContent = c.title;
  document.getElementById('clientModalBody').textContent  = c.body;
  document.getElementById('clientModalName').textContent  = '"' + name + '"';
  document.getElementById('clientModalWarning').innerHTML = c.warning;
  document.getElementById('clientModalConfirm').style.background = c.btnBg;
  document.getElementById('clientModalConfirm').innerHTML = c.btnTxt;

  // Map action to form values
  var formAction = (action === 'revoke') ? 'reject' : action;
  document.getElementById('modalClientId').value = id;
  document.getElementById('modalAction').value   = formAction;

  new bootstrap.Modal(document.getElementById('clientActionModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?> -->
<div class="modal fade" id="resetPwModal" tabindex="-1" aria-labelledby="resetPwModalLabel" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden">
      <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#6B2D8B,#0D9488)">
        <h5 class="modal-title fw-bold" id="resetPwModalLabel">
          <i class="bi bi-key-fill me-2"></i>Reset Password
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted small mb-3">
          Setting a new password for <strong id="resetClientName"></strong>.
          The client will not receive a notification.
        </p>
        <form method="post" id="resetPwForm">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="action"    value="reset_password">
          <input type="hidden" name="client_id" id="resetClientId">

          <div class="mb-3">
            <label class="form-label fw-semibold">New Password</label>
            <div class="input-group">
              <input type="password" name="new_password" id="resetNewPw"
                     class="form-control" placeholder="Min. 8 characters" required>
              <button type="button" class="btn btn-outline-secondary" id="toggleResetPw">
                <i class="bi bi-eye" id="resetEyeIcon"></i>
              </button>
            </div>
            <div class="progress mt-2" style="height:4px">
              <div id="resetStrengthBar" class="progress-bar" style="width:0%;transition:width .3s"></div>
            </div>
            <small id="resetStrengthLabel" class="text-muted"></small>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Confirm Password</label>
            <input type="password" id="resetConfirmPw" class="form-control"
                   placeholder="Repeat password">
            <div id="resetMatchMsg" class="form-text"></div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger px-4 fw-bold">
              <i class="bi bi-shield-lock me-1"></i>Reset Password
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function openResetModal(id, name) {
  document.getElementById('resetClientId').value = id;
  document.getElementById('resetClientName').textContent = name;
  document.getElementById('resetNewPw').value    = '';
  document.getElementById('resetConfirmPw').value = '';
  document.getElementById('resetStrengthBar').style.width = '0%';
  document.getElementById('resetStrengthLabel').textContent = '';
  document.getElementById('resetMatchMsg').innerHTML = '';
  new bootstrap.Modal(document.getElementById('resetPwModal')).show();
}

document.getElementById('toggleResetPw').addEventListener('click', function() {
  var inp = document.getElementById('resetNewPw');
  var ico = document.getElementById('resetEyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text'; ico.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password'; ico.className = 'bi bi-eye';
  }
});

document.getElementById('resetNewPw').addEventListener('input', function() {
  var v = this.value, score = 0;
  if (v.length >= 8)        score++;
  if (/[A-Z]/.test(v))      score++;
  if (/[0-9]/.test(v))      score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  var colors = ['','bg-danger','bg-warning','bg-info','bg-success'];
  var labels = ['','Weak','Fair','Good','Strong'];
  var bar = document.getElementById('resetStrengthBar');
  bar.style.width = (score * 25) + '%';
  bar.className   = 'progress-bar ' + (colors[score] || '');
  document.getElementById('resetStrengthLabel').textContent = labels[score] || '';
  // re-check confirm match
  document.getElementById('resetConfirmPw').dispatchEvent(new Event('input'));
});

document.getElementById('resetConfirmPw').addEventListener('input', function() {
  var msg = document.getElementById('resetMatchMsg');
  if (!this.value) { msg.innerHTML = ''; return; }
  if (this.value === document.getElementById('resetNewPw').value) {
    msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</span>';
  } else {
    msg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
  }
});

document.getElementById('resetPwForm').addEventListener('submit', function(e) {
  var pw  = document.getElementById('resetNewPw').value;
  var cpw = document.getElementById('resetConfirmPw').value;
  if (pw.length < 8) {
    e.preventDefault();
    alert('Password must be at least 8 characters.');
    return;
  }
  if (pw !== cpw) {
    e.preventDefault();
    alert('Passwords do not match.');
  }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
