<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
$pageTitle = 'Manage Stylists';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';
$db = getDB();

$stylists = $db->query('SELECT * FROM stylists ORDER BY name')->fetchAll();

// Load working days for each stylist
$schedRows = $db->query(
    "SELECT stylist_id, day_of_week FROM stylist_weekly_schedules
     WHERE is_working = 1
     ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')"
)->fetchAll();

$workingDays = [];
foreach ($schedRows as $r) {
    $abbr = ['Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed',
             'Thursday'=>'Thu','Friday'=>'Fri','Saturday'=>'Sat','Sunday'=>'Sun'];
    $workingDays[$r['stylist_id']][] = $abbr[$r['day_of_week']] ?? $r['day_of_week'];
}
?>

<style>
.stylist-card { border-radius:14px; border:1.5px solid #e8e0f0; transition:box-shadow .15s, transform .15s; }
.stylist-card:hover { box-shadow:0 6px 24px rgba(107,45,139,.13); transform:translateY(-2px); }
.stylist-avatar { width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,#6B2D8B,#9b4dca);
                  display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.day-badge { font-size:.68rem; padding:2px 7px; border-radius:50px; background:#f3e8ff; color:#6B2D8B;
             font-weight:700; border:1px solid #ddd6fe; }
.day-badge.off { background:#f3f4f6; color:#9ca3af; border-color:#e5e7eb; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
      <i class="bi bi-people me-2"></i>Manage Stylists
    </h3>
    <p class="text-muted small mb-0">Add or edit stylists and set their weekly availability.</p>
  </div>
  <a href="add.php" class="btn btn-sa-primary">
    <i class="bi bi-plus-circle me-1"></i>Add New Stylist
  </a>
</div>

<?php if (empty($stylists)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-people" style="font-size:3rem"></i>
    <p class="mt-2">No stylists yet. Add one to get started.</p>
  </div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($stylists as $st):
    $days = $workingDays[$st['id']] ?? [];
    $allDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  ?>
  <div class="col-md-4 col-sm-6">
    <div class="card stylist-card p-3 h-100">

      <!-- Header -->
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="stylist-avatar">
          <i class="bi bi-person-fill text-white" style="font-size:1.6rem"></i>
        </div>
        <div class="flex-grow-1">
          <div class="fw-bold fs-6"><?= htmlspecialchars($st['name']) ?></div>
          <div class="text-muted small"><?= htmlspecialchars($st['specialty']) ?></div>
        </div>
        <?php if ($st['is_active']): ?>
          <span class="badge" style="background:#10b981">Active</span>
        <?php else: ?>
          <span class="badge bg-secondary">Inactive</span>
        <?php endif; ?>
      </div>

      <!-- Bio -->
      <?php if ($st['bio']): ?>
        <p class="small text-muted mb-2" style="line-height:1.4">
          <?= htmlspecialchars(mb_substr($st['bio'], 0, 80)) ?><?= mb_strlen($st['bio']) > 80 ? '…' : '' ?>
        </p>
      <?php endif; ?>

      <!-- Working days -->
      <div class="mb-3">
        <div class="small text-muted mb-1 fw-semibold">Available Days:</div>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach ($allDays as $d): ?>
            <span class="day-badge <?= in_array($d, $days) ? '' : 'off' ?>">
              <?= $d ?>
            </span>
          <?php endforeach; ?>
        </div>
        <?php if (empty($days)): ?>
          <small class="text-muted fst-italic">No schedule set yet</small>
        <?php endif; ?>
      </div>

      <!-- Actions -->
      <div class="d-flex gap-2 mt-auto">
        <a href="edit.php?id=<?= $st['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
          <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="<?= BASE_URL ?>/admin/stylist_schedule/index.php?stylist_id=<?= $st['id'] ?>"
           class="btn btn-sm btn-outline-secondary flex-fill">
          <i class="bi bi-calendar-week me-1"></i>Schedule
        </a>
        <button type="button" class="btn btn-sm btn-outline-danger"
                onclick="confirmDeleteStylist(<?= $st['id'] ?>, '<?= htmlspecialchars($st['name'], ENT_QUOTES) ?>')">
          <i class="bi bi-trash"></i>
        </button>
      </div>

    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteStylistModal" tabindex="-1" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden">
      <div style="background:linear-gradient(135deg,#dc2626,#ef4444);padding:28px 24px 20px;text-align:center">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.2);
                    display:flex;align-items:center;justify-content:center;
                    margin:0 auto 12px;font-size:2rem;color:white">
          <i class="bi bi-person-dash-fill"></i>
        </div>
        <h5 style="color:white;font-weight:800;margin:0;font-size:1.15rem">Delete Stylist</h5>
      </div>
      <div style="padding:24px 28px;text-align:center">
        <p style="color:#374151;font-size:.95rem;margin-bottom:6px">Are you sure you want to delete</p>
        <p style="color:#111827;font-weight:800;font-size:1.05rem;margin-bottom:14px" id="deleteStylistName"></p>
        <p style="color:#6b7280;font-size:.82rem;margin-bottom:24px">
          <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
          This <strong>cannot be undone</strong>. All schedules for this stylist will also be removed.
        </p>
        <div class="d-flex gap-3">
          <button type="button" class="btn btn-outline-secondary flex-fill fw-semibold"
                  data-bs-dismiss="modal" style="border-radius:10px;padding:11px">
            <i class="bi bi-x-lg me-1"></i>Cancel
          </button>
          <a id="deleteStylistBtn" href="#" class="btn flex-fill fw-bold text-white"
             style="background:linear-gradient(135deg,#dc2626,#ef4444);border:none;border-radius:10px;padding:11px">
            <i class="bi bi-trash3 me-1"></i>Yes, Delete
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function confirmDeleteStylist(id, name) {
  document.getElementById('deleteStylistName').textContent = '"' + name + '"';
  document.getElementById('deleteStylistBtn').href = 'delete.php?id=' + id;
  new bootstrap.Modal(document.getElementById('deleteStylistModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
