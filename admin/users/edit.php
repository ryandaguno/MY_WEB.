<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();

$pageTitle = 'Edit Client';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/NotificationService.php';
$db = getDB();

$id = (int)($_GET['id'] ?? $_POST['client_id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/users/index.php'); exit; }

$row = $db->prepare('SELECT * FROM clients WHERE id = ?');
$row->execute([$id]);
$client = $row->fetch();
if (!$client) { header('Location: ' . BASE_URL . '/admin/users/index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');

    $username   = trim($_POST['username']  ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $phone      = trim($_POST['phone']     ?? '');
    $isVerified = isset($_POST['is_verified']) ? 1 : 0;
    $acctStatus = $_POST['account_status'] ?? 'pending';
    if (!in_array($acctStatus, ['pending','approved','rejected'])) $acctStatus = 'pending';

    if ($username === '')                     $errors[] = 'Name is required.';
    if ($email === '')                        $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    if ($phone === '')                        $errors[] = 'Phone is required.';

    if (empty($errors)) {
        // Check duplicates excluding self
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
            'UPDATE clients SET username=?, email=?, phone=?, is_verified=?, is_approved=?, account_status=? WHERE id=?'
        )->execute([$username, $email, $phone, $isVerified, $isApproved, $acctStatus, $id]);

        // Send email if status changed
        $prevStatus = $client['account_status'] ?? 'pending';
        if ($prevStatus !== $acctStatus) {
            $ns = new NotificationService();
            if ($acctStatus === 'approved') $ns->sendAccountApproved($email, $username);
            elseif ($acctStatus === 'rejected') $ns->sendAccountRejected($email, $username);
        }

        SessionGuard::flashMessage('success', "Client #{$id} updated.");
        header('Location: ' . BASE_URL . '/admin/users/index.php'); exit;
    }

    // Re-populate on error
    $client['username']       = $username;
    $client['email']          = $email;
    $client['phone']          = $phone;
    $client['is_verified']    = $isVerified;
    $client['account_status'] = $acctStatus;
}
$csrfToken = SessionGuard::generateCsrfToken();
?>

<div class="mb-3">
  <a href="<?= BASE_URL ?>/admin/users/index.php" class="text-decoration-none text-muted small">
    <i class="bi bi-arrow-left me-1"></i>Back to Users
  </a>
  <h3 class="fw-bold mt-1" style="color:var(--sa-purple)">
    <i class="bi bi-person-gear me-2"></i>Edit Client — <?= htmlspecialchars($client['username']) ?>
  </h3>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Profile Details</div>
      <div class="card-body p-4">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="client_id"  value="<?= $id ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold">Name</label>
            <input type="text" name="username" class="form-control" maxlength="50" required
                   value="<?= htmlspecialchars($client['username']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" name="email" class="form-control" maxlength="254" required
                   value="<?= htmlspecialchars($client['email']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Phone</label>
            <input type="text" name="phone" class="form-control" maxlength="20" required
                   value="<?= htmlspecialchars($client['phone']) ?>">
          </div>
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="is_verified" name="is_verified"
                     <?= $client['is_verified'] ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="is_verified">Email Verified</label>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Account Status</label>
            <select name="account_status" class="form-select">
              <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($client['account_status'] ?? 'pending') === $v ? 'selected' : '' ?>>
                <?= $l ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-check-lg me-1"></i>Save
            </button>
            <a href="<?= BASE_URL ?>/admin/users/reset_password.php?id=<?= $id ?>"
               class="btn btn-outline-warning ms-auto">
              <i class="bi bi-key me-1"></i>Reset Password
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mt-4 mt-lg-0">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Account Info</div>
      <div class="card-body p-4" style="font-size:.85rem">
        <div class="mb-2"><span class="text-muted">Client ID:</span> #<?= $client['id'] ?></div>
        <div class="mb-2"><span class="text-muted">Registered:</span> <?= date('M j, Y g:i A', strtotime($client['created_at'])) ?></div>
        <div class="mb-2"><span class="text-muted">Login Failures:</span> <?= (int)$client['failed_login_attempts'] ?></div>
        <?php if (!empty($client['locked_until']) && strtotime($client['locked_until']) > time()): ?>
          <div class="text-danger fw-semibold">Locked until <?= date('M j, Y g:i A', strtotime($client['locked_until'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
