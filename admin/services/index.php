<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
$pageTitle = 'Manage Services';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';
$db       = getDB();
$services = $db->query('SELECT * FROM services ORDER BY category, name')->fetchAll();

$grouped = [];
foreach ($services as $s) {
    $grouped[$s['category']][] = $s;
}

$catColors = ['Hair'=>'#6B2D8B','Facial'=>'#0d9488','Nails'=>'#db2777',
              'Skin'=>'#d97706','Waxing'=>'#2563eb','Makeup'=>'#7c3aed'];
?>

<style>
.svc-card { border-radius:12px; border:1.5px solid #e8e0f0; transition:box-shadow .15s, transform .15s; }
.svc-card:hover { box-shadow:0 5px 20px rgba(107,45,139,.12); transform:translateY(-2px); }
.cat-header { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:1.2px;
              padding:6px 14px; border-radius:50px; color:#fff; display:inline-block; margin-bottom:14px; }
.svc-price { font-size:1.1rem; font-weight:800; color:#6B2D8B; }
.svc-meta  { font-size:.78rem; color:#888; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
      <i class="bi bi-scissors me-2"></i>Manage Services
    </h3>
    <p class="text-muted small mb-0">Add, edit or delete services. Changes are visible to clients immediately.</p>
  </div>
  <a href="add.php" class="btn btn-sa-primary">
    <i class="bi bi-plus-circle me-1"></i>Add New Service
  </a>
</div>

<?php if (empty($services)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-scissors" style="font-size:3rem"></i>
    <p class="mt-2">No services yet. Add one to get started.</p>
  </div>
<?php else: ?>

<?php foreach ($grouped as $cat => $svcs):
  $catColor = $catColors[$cat] ?? '#6B2D8B';
?>
  <div class="mb-4">
    <span class="cat-header" style="background:<?= $catColor ?>">
      <i class="bi bi-tag me-1"></i><?= htmlspecialchars($cat) ?>
      <span class="ms-1 opacity-75">(<?= count($svcs) ?>)</span>
    </span>

    <div class="row g-3">
      <?php foreach ($svcs as $s): ?>
      <div class="col-md-4 col-sm-6">
        <div class="card svc-card p-3 h-100">

          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="fw-bold" style="font-size:.95rem"><?= htmlspecialchars($s['name']) ?></div>
          </div>

          <?php if ($s['description']): ?>
            <p class="svc-meta mb-2" style="line-height:1.4">
              <?= htmlspecialchars(mb_substr($s['description'], 0, 70)) ?><?= mb_strlen($s['description']) > 70 ? '…' : '' ?>
            </p>
          <?php endif; ?>

          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="svc-price">₱<?= number_format($s['price'], 2) ?></span>
            <span class="svc-meta">
              <i class="bi bi-clock me-1"></i><?= (int)$s['duration_minutes'] ?> min
            </span>
          </div>

          <div class="d-flex gap-2 mt-auto">
            <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
              <i class="bi bi-pencil me-1"></i>Edit
            </a>
            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')">
              <i class="bi bi-trash"></i>
            </button>
          </div>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php endif; ?>

<!-- ── Delete Confirmation Modal ── -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden">

      <!-- Red top bar -->
      <div style="background:linear-gradient(135deg,#dc2626,#ef4444);padding:28px 24px 20px;text-align:center">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.2);
                    display:flex;align-items:center;justify-content:center;
                    margin:0 auto 12px;font-size:2rem;color:white">
          <i class="bi bi-trash3-fill"></i>
        </div>
        <h5 style="color:white;font-weight:800;margin:0;font-size:1.15rem">Delete Service</h5>
      </div>

      <!-- Body -->
      <div style="padding:24px 28px;text-align:center">
        <p style="color:#374151;font-size:.95rem;margin-bottom:6px">
          Are you sure you want to delete
        </p>
        <p style="color:#111827;font-weight:800;font-size:1.05rem;margin-bottom:16px" id="deleteServiceName"></p>
        <p style="color:#6b7280;font-size:.82rem;margin-bottom:24px">
          <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
          This action <strong>cannot be undone</strong>. The service will be permanently removed.
        </p>

        <div class="d-flex gap-3">
          <button type="button"
                  class="btn btn-outline-secondary flex-fill fw-semibold"
                  data-bs-dismiss="modal"
                  style="border-radius:10px;padding:11px">
            <i class="bi bi-x-lg me-1"></i>Cancel
          </button>
          <a id="deleteConfirmBtn"
             href="#"
             class="btn flex-fill fw-bold text-white"
             style="background:linear-gradient(135deg,#dc2626,#ef4444);border:none;border-radius:10px;padding:11px">
            <i class="bi bi-trash3 me-1"></i>Yes, Delete
          </a>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function confirmDelete(id, name) {
  document.getElementById('deleteServiceName').textContent = '"' + name + '"';
  document.getElementById('deleteConfirmBtn').href = '<?= BASE_URL ?>/admin/services/delete.php?id=' + id;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
