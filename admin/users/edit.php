<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/NotificationService.php';
$db = getDB();

$id = (int)($_GET['id'] ?? $_POST['client_id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/users/index.php'); exit; }

// Fetch client
$client = $db->prepare('SELECT * FROM clients WHERE id = ?');
$client->execute([$id]);
$client = $client->fetch();
if (!$client) { header('Location: ' . BASE_URL . '/admin/users/index.php'); exit; }

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');

    $username   = trim($_POST['username']   ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $phone      = trim($_POST['phone']      ?? '');
    $isVerified = isset($_POST['is_verified']) ? 1 : 0;
    $acctStatus = $_POST['account_status']  ?? 'pending';

    // Validate
    if ($username === '')       $errors[] = 'Username is required.';
    if (strlen($username) > 50) $errors[] = 'Username must be 50 characters or fewer.';
    if ($email === '')          $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if ($phone === '')          $errors[] = 'Phone is required.';
    if (!in_array($acctStatus, ['pending','approved','rejected'])) $acctStatus = 'pending';

    // Uniqueness checks (exclude self)
    if (empty($errors)) {
        $dup = $db->prepare('SELECT id FROM clients WHERE username = ? AND id != ?');
        $dup->execute([$username, $id]);
        if ($dup->fetch()) $errors[] = 'That username is already taken.';

        $dup2 = $db->prepare('SELECT id FROM clients WHERE email = ? AND id != ?');
        $dup2->execute([$email, $id]);
        if ($dup2->fetch()) $errors[] = 'That email is already in use.';
    }

    if (empty($errors)) {
        $isApproved = ($acctStatus === 'approved') ? 1 : 0;

        $db->prepare(
            'UPDATE clients
             SET username = ?, email = ?, phone = ?,
                 is_verified = ?, is_approved = ?, account_status = ?
             WHERE id = ?'
        )->execute([$username, $email, $phone, $isVerified, $isApproved, $acctStatus, $id]);

        // Send notification if status changed to approved or rejected
        $prevStatus = $client['account_status'] ?? 'pending';
        if ($prevStatus !== $acctStatus) {
            $ns = new NotificationService();
            if ($acctStatus === 'approved') {
                $ns->sendAccountApproved($email, $username);
            } elseif ($acctStatus === 'rejected') {
                $ns->sendAccountRejected($email, $username);
            }
        }

        SessionGuard::flashMessage('success', "Client #{$id} updated successfully.");
        header('Location: ' . BASE_URL . '/admin/users/index.php'); exit;
    }

    // Re-populate for re-render on error
    $client['username']       = $username;
    $client['email']          = $email;
    $client['phone']          = $phone;
    $client['is_verified']    = $isVerified;
    $client['account_status'] = $acctStatus;
}

$pageTitle  = 'Edit Client #' . $id;
$csrfToken  = SessionGuard::generateCsrfToken();
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
  <a href="<?= BASE_URL ?>/admin/users/index.php" class="text-decoration-none text-muted small">
    <i class="bi bi-arrow-left me-1"></i>Back to User Management
  </a>
  <h3 class="fw-bold mt-1 mb-0" style="color:var(--sa-purple)">
    <i class="bi bi-person-gear me-2"></i>Edit Client
  </h3>
  <p class="text-muted small mb-0">Editing account for <strong><?= htmlspecialchars($client['username']) ?></strong></p>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold">Profile Details</h5>
      </div>
      <div class="card-body p-4">
        <form method="post">
          <input type="hidden" name="csrf_token"  value="<?= $csrfToken ?>">
          <input type="hidden" name="client_id"   value="<?= $id ?>">

          <!-- Username -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Username</label>
            <input type="text" name="username" class="form-control"
                   maxlength="50" required
                   value="<?= htmlspecialchars($client['username']) ?>">
          </div>

          <!-- Email -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" name="email" class="form-control"
                   maxlength="254" required
                   value="<?= htmlspecialchars($client['email']) ?>">
          </div>

          <!-- Phone -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Phone</label>
            <input type="text" name="phone" class="form-control"
                   maxlength="20" required
                   value="<?= htmlspecialchars($client['phone']) ?>">
          </div>

          <!-- Email Verified toggle -->
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox"
                     id="is_verified" name="is_verified" role="switch"
                     <?= $client['is_verified'] ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="is_verified">
                Email Verified
              </label>
              <div class="form-text">Enable this to mark the email as confirmed without the client re-verifying.</div>
            </div>
          </div>

          <!-- Account Status -->
          <div class="mb-4">
            <label class="form-label fw-semibold">Account Status</label>
            <select name="account_status" class="form-select">
              <?php
              $statuses = ['pending' => 'Pending Approval', 'approved' => 'Approved', 'rejected' => 'Rejected'];
              foreach ($statuses as $val => $label):
              ?>
              <option value="<?= $val ?>"
                <?= ($client['account_status'] ?? 'pending') === $val ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Changing to Approved or Rejected will trigger an email notification to the client.</div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
            <a href="<?= BASE_URL ?>/admin/users/index.php" class="btn btn-outline-secondary">
              Cancel
            </a>
            <a href="<?= BASE_URL ?>/admin/users/reset_password.php?id=<?= $id ?>"
               class="btn btn-outline-warning ms-auto">
              <i class="bi bi-key me-1"></i>Reset Password
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Side info card -->
  <div class="col-lg-5 mt-4 mt-lg-0">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold">Account Info</h5>
      </div>
      <div class="card-body p-4">
        <dl class="row mb-0" style="font-size:.85rem">
          <dt class="col-sm-5 text-muted">Client ID</dt>
          <dd class="col-sm-7">#<?= $client['id'] ?></dd>

          <dt class="col-sm-5 text-muted">Registered</dt>
          <dd class="col-sm-7"><?= date('M j, Y g:i A', strtotime($client['created_at'])) ?></dd>

          <dt class="col-sm-5 text-muted">Login Failures</dt>
          <dd class="col-sm-7"><?= (int)$client['failed_login_attempts'] ?></dd>

          <dt class="col-sm-5 text-muted">Locked Until</dt>
          <dd class="col-sm-7">
            <?= $client['locked_until']
                ? '<span class="text-danger">' . date('M j, Y g:i A', strtotime($client['locked_until'])) . '</span>'
                : '<span class="text-muted">—</span>' ?>
          </dd>
        </dl>

        <?php if ($client['locked_until'] && strtotime($client['locked_until']) > time()): ?>
        <form method="post" class="mt-3">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="client_id"  value="<?= $id ?>">
          <input type="hidden" name="account_status"
                 value="<?= htmlspecialchars($client['account_status'] ?? 'pending') ?>">
          <input type="hidden" name="username"    value="<?= htmlspecialchars($client['username']) ?>">
          <input type="hidden" name="email"       value="<?= htmlspecialchars($client['email']) ?>">
          <input type="hidden" name="phone"       value="<?= htmlspecialchars($client['phone']) ?>">
          <?php if ($client['is_verified']): ?><input type="hidden" name="is_verified" value="1"><?php endif; ?>
          <input type="hidden" name="unlock" value="1">
          <button type="submit" class="btn btn-sm btn-outline-danger w-100">
            <i class="bi bi-unlock me-1"></i>Unlock Account Now
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>