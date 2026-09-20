<?php
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
        <a href="delete.php?id=<?= $st['id'] ?>"
           class="btn btn-sm btn-outline-danger"
           onclick="return confirm('Delete <?= htmlspecialchars($st['name'], ENT_QUOTES) ?>? This cannot be undone.')">
          <i class="bi bi-trash"></i>
        </a>
      </div>

    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
