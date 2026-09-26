<?php
/* ============================================================
   Admin – Stylist Weekly Recurring Schedule
   ============================================================ */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
if (function_exists('opcache_invalidate')) opcache_invalidate(__FILE__, true);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$pageTitle = 'Stylist Weekly Schedule';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';

$db   = getDB();
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

/* ── Auto-migrate: create/update stylist_weekly_schedules ── */
$db->exec("
    CREATE TABLE IF NOT EXISTS stylist_weekly_schedules (
      id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
      stylist_id  INT UNSIGNED NOT NULL,
      day_of_week ENUM('Monday','Tuesday','Wednesday',
                       'Thursday','Friday','Saturday','Sunday') NOT NULL,
      is_working  TINYINT(1)   NOT NULL DEFAULT 1,
      start_time  TIME         NOT NULL DEFAULT '09:00:00',
      break_start TIME         NULL DEFAULT NULL,
      break_end   TIME         NULL DEFAULT NULL,
      end_time    TIME         NOT NULL DEFAULT '18:00:00',
      updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_stylist_day (stylist_id, day_of_week),
      FOREIGN KEY (stylist_id) REFERENCES stylists(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* Add break columns to tables created before this version */
try {
    $db->exec("ALTER TABLE stylist_weekly_schedules
               ADD COLUMN break_start TIME NULL DEFAULT NULL AFTER start_time");
} catch (PDOException $e) { /* already exists */ }
try {
    $db->exec("ALTER TABLE stylist_weekly_schedules
               ADD COLUMN break_end TIME NULL DEFAULT NULL AFTER break_start");
} catch (PDOException $e) { /* already exists */ }

/* Seed default rows for any active stylist that has none */
$db->exec("
    INSERT IGNORE INTO stylist_weekly_schedules
        (stylist_id, day_of_week, is_working, start_time, end_time)
    SELECT s.id, d.day_of_week,
           CASE d.day_of_week WHEN 'Sunday' THEN 0 ELSE 1 END,
           '09:00:00',
           CASE d.day_of_week WHEN 'Sunday' THEN '09:00:00' ELSE '18:00:00' END
    FROM stylists s
    JOIN (
        SELECT 'Monday'    AS day_of_week UNION ALL SELECT 'Tuesday' UNION ALL
        SELECT 'Wednesday' UNION ALL SELECT 'Thursday' UNION ALL SELECT 'Friday' UNION ALL
        SELECT 'Saturday'  UNION ALL SELECT 'Sunday'
    ) d ON 1=1
    WHERE s.is_active = 1
");

$stylists = $db->query('SELECT id, name FROM stylists WHERE is_active = 1 ORDER BY name')->fetchAll();

/* ── Which stylist are we editing? ── */
$stylistId = (int)($_GET['stylist_id'] ?? ($stylists[0]['id'] ?? 0));
$stylist   = null;
foreach ($stylists as $s) {
    if ((int)$s['id'] === $stylistId) { $stylist = $s; break; }
}
if (!$stylist && !empty($stylists)) {
    $stylist   = $stylists[0];
    $stylistId = (int)$stylist['id'];
}

/* ── Handle Save ── */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');

    $postStylistId = (int)($_POST['stylist_id'] ?? 0);
    if (!$postStylistId) {
        $error = 'No stylist selected.';
    } else {
        foreach ($days as $day) {
            $key       = strtolower($day);
            $isWorking = isset($_POST['is_working'][$key]) ? 1 : 0;
            $startRaw  = $_POST['start_time'][$key]   ?? '09:00';
            $endRaw    = $_POST['end_time'][$key]     ?? '18:00';
            $bsRaw     = trim($_POST['break_start'][$key] ?? '');
            $beRaw     = trim($_POST['break_end'][$key]   ?? '');

            if ($isWorking) {
                if ($endRaw <= $startRaw) {
                    $error = "End time must be after start time for $day.";
                    break;
                }
                if ($bsRaw !== '' && $beRaw !== '' && $beRaw <= $bsRaw) {
                    $error = "Break end must be after break start for $day.";
                    break;
                }
            }

            $start      = $startRaw . ':00';
            $end        = $endRaw   . ':00';
            $breakStart = ($bsRaw !== '') ? $bsRaw . ':00' : null;
            $breakEnd   = ($beRaw !== '') ? $beRaw . ':00' : null;

            $db->prepare(
                'INSERT INTO stylist_weekly_schedules
                     (stylist_id, day_of_week, is_working, start_time, break_start, break_end, end_time)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     is_working  = VALUES(is_working),
                     start_time  = VALUES(start_time),
                     break_start = VALUES(break_start),
                     break_end   = VALUES(break_end),
                     end_time    = VALUES(end_time)'
            )->execute([$postStylistId, $day, $isWorking, $start, $breakStart, $breakEnd, $end]);
        }

        if (!$error) {
            SessionGuard::flashMessage('success', 'Schedule for ' . htmlspecialchars($stylist['name']) . ' updated successfully.');
            header('Location: index.php?stylist_id=' . $postStylistId); exit;
        }
        $stylistId = $postStylistId;
    }
}

/* ── Load current schedule ── */
$rows = $db->prepare(
    'SELECT * FROM stylist_weekly_schedules WHERE stylist_id = ?
     ORDER BY FIELD(day_of_week,
       "Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday")'
);
$rows->execute([$stylistId]);
$schedule = [];
foreach ($rows->fetchAll() as $r) { $schedule[$r['day_of_week']] = $r; }

$csrfToken = SessionGuard::generateCsrfToken();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
    <i class="bi bi-calendar-week me-2"></i>Stylist Weekly Schedule
  </h3>
  <span class="text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Set once — applies automatically every week. No manual slots needed.
  </span>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
  <?= htmlspecialchars($error) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stylist switcher -->
<div class="card border-0 shadow-sm p-3 mb-4">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <label class="fw-semibold mb-0" style="white-space:nowrap">Select Stylist:</label>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($stylists as $s): ?>
      <a href="index.php?stylist_id=<?= $s['id'] ?>"
         class="btn btn-sm <?= (int)$s['id'] === $stylistId ? 'btn-sa-primary' : 'btn-outline-secondary' ?>">
        <i class="bi bi-person me-1"></i><?= htmlspecialchars($s['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (!$stylist): ?>
  <div class="alert alert-warning">No active stylists found. <a href="../stylists/add.php">Add a stylist first.</a></div>
<?php else: ?>

<form method="post" id="scheduleForm">
  <input type="hidden" name="csrf_token"    value="<?= $csrfToken ?>">
  <input type="hidden" name="stylist_id"    value="<?= $stylistId ?>">
  <input type="hidden" name="save_schedule" value="1">

  <div class="card border-0 shadow-sm">

    <!-- Header -->
    <div class="card-header py-3 d-flex justify-content-between align-items-center"
         style="background:var(--sa-purple)">
      <div class="fw-bold text-white">
        <i class="bi bi-person-badge me-2"></i>
        <?= htmlspecialchars($stylist['name']) ?> — Weekly Recurring Schedule
      </div>
      <button type="button" class="btn btn-sm btn-light" id="copyMonBtn"
              title="Copy Monday's schedule to Tuesday–Saturday">
        <i class="bi bi-files me-1"></i>Copy Monday → Tue–Sat
      </button>
    </div>

    <!-- Column labels -->
    <div class="card-header py-2 d-none d-md-block" style="background:#f8f4fb;border-top:none">
      <div class="row fw-bold text-muted small text-uppercase" style="letter-spacing:.5px">
        <div class="col-md-2">Day</div>
        <div class="col-md-1 text-center">Status</div>
        <div class="col-md-2">Start</div>
        <div class="col-md-2">Break Start</div>
        <div class="col-md-2">Break End</div>
        <div class="col-md-2">End</div>
        <div class="col-md-1"></div>
      </div>
    </div>

    <div class="card-body p-0">
      <?php foreach ($days as $i => $day):
        $key      = strtolower($day);
        $row      = $schedule[$day] ?? [];
        $working  = isset($row['is_working']) ? (int)$row['is_working'] : ($day !== 'Sunday' ? 1 : 0);
        $st       = isset($row['start_time'])  ? substr($row['start_time'],  0, 5) : '09:00';
        $et       = isset($row['end_time'])    ? substr($row['end_time'],    0, 5) : '18:00';
        $bs       = (!empty($row['break_start']) && $row['break_start'] !== '00:00:00')
                      ? substr($row['break_start'], 0, 5) : '';
        $be       = (!empty($row['break_end'])   && $row['break_end']   !== '00:00:00')
                      ? substr($row['break_end'],   0, 5) : '';
        $isWeekend = in_array($day, ['Saturday','Sunday']);
        $rowBg    = $i % 2 === 0 ? '#fff' : '#fafafa';
      ?>
      <div class="row align-items-center px-3 py-3 day-row"
           id="row-<?= $key ?>"
           style="border-bottom:1px solid #f0f0f0;background:<?= $rowBg ?>">

        <!-- Day -->
        <div class="col-md-2 col-6">
          <div class="d-flex align-items-center gap-2">
            <div style="width:36px;height:36px;border-radius:50%;flex-shrink:0;
                        background:<?= $isWeekend ? '#e8f5e9' : 'var(--sa-light)' ?>;
                        display:flex;align-items:center;justify-content:center">
              <i class="bi bi-calendar-day"
                 style="color:<?= $isWeekend ? '#2e7d32' : 'var(--sa-purple)' ?>"></i>
            </div>
            <div>
              <div class="fw-bold" style="font-size:.9rem"><?= $day ?></div>
              <div class="text-muted" style="font-size:.68rem"><?= $isWeekend ? 'Weekend' : 'Weekday' ?></div>
            </div>
          </div>
        </div>

        <!-- Working toggle -->
        <div class="col-md-1 col-6 text-center">
          <div class="form-check form-switch d-flex justify-content-center align-items-center gap-1 m-0">
            <input class="form-check-input day-toggle" type="checkbox"
                   name="is_working[<?= $key ?>]"
                   id="work_<?= $key ?>"
                   <?= $working ? 'checked' : '' ?>
                   data-day="<?= $key ?>"
                   style="width:2.2em;height:1.1em;cursor:pointer">
            <label class="form-check-label fw-semibold day-label-<?= $key ?>"
                   for="work_<?= $key ?>"
                   style="font-size:.78rem;min-width:42px;
                          color:<?= $working ? '#2e7d32' : '#b71c1c' ?>">
              <?= $working ? 'Working' : 'OFF' ?>
            </label>
          </div>
        </div>

        <!-- Start time -->
        <div class="col-md-2 col-6 day-fields-<?= $key ?> mt-2 mt-md-0"
             <?= !$working ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
          <label class="form-label mb-1 small text-muted d-md-none">Start</label>
          <input type="time" name="start_time[<?= $key ?>]"
                 class="form-control form-control-sm start-input"
                 data-day="<?= $key ?>"
                 value="<?= htmlspecialchars($st) ?>">
        </div>

        <!-- Break start -->
        <div class="col-md-2 col-6 day-fields-<?= $key ?> mt-2 mt-md-0"
             <?= !$working ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
          <label class="form-label mb-1 small text-muted d-md-none">Break Start</label>
          <input type="time" name="break_start[<?= $key ?>]"
                 class="form-control form-control-sm break-start-input"
                 data-day="<?= $key ?>"
                 placeholder="optional"
                 value="<?= htmlspecialchars($bs) ?>">
        </div>

        <!-- Break end -->
        <div class="col-md-2 col-6 day-fields-<?= $key ?> mt-2 mt-md-0"
             <?= !$working ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
          <label class="form-label mb-1 small text-muted d-md-none">Break End</label>
          <input type="time" name="break_end[<?= $key ?>]"
                 class="form-control form-control-sm break-end-input"
                 data-day="<?= $key ?>"
                 placeholder="optional"
                 value="<?= htmlspecialchars($be) ?>">
        </div>

        <!-- End time -->
        <div class="col-md-2 col-6 day-fields-<?= $key ?> mt-2 mt-md-0"
             <?= !$working ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
          <label class="form-label mb-1 small text-muted d-md-none">End</label>
          <input type="time" name="end_time[<?= $key ?>]"
                 class="form-control form-control-sm end-input"
                 data-day="<?= $key ?>"
                 value="<?= htmlspecialchars($et) ?>">
        </div>

        <!-- Summary badge -->
        <div class="col-md-1 col-12 text-md-end mt-2 mt-md-0">
          <span class="day-summary-<?= $key ?> badge rounded-pill"
                style="background:<?= $working ? '#e8f5e9' : '#fce4ec' ?>;
                       color:<?= $working ? '#2e7d32' : '#b71c1c' ?>;
                       font-size:.65rem;font-weight:700;white-space:normal;
                       text-align:center;max-width:90px">
            <?php if ($working):
                $label = date('g:ia', strtotime($st)) . '–' . date('g:ia', strtotime($et));
                if ($bs && $be) $label .= ' (brk ' . date('g:ia', strtotime($bs)) . '–' . date('g:ia', strtotime($be)) . ')';
                echo $label;
            else: ?>OFF<?php endif; ?>
          </span>
        </div>

      </div>
      <?php endforeach; ?>
    </div>

    <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <small class="text-muted">
        <i class="bi bi-lightbulb me-1"></i>
        This schedule repeats every week automatically.
        Leave Break Start/End blank if there is no break.
        Previously booked appointments are not affected.
      </small>
      <button type="submit" class="btn btn-sa-primary px-4">
        <i class="bi bi-check2-circle me-1"></i>Save Schedule
      </button>
    </div>
  </div>
</form>

<!-- Schedule Preview -->
<div class="card border-0 shadow-sm mt-4">
  <div class="card-header py-2" style="background:#f8f4fb">
    <span class="fw-bold small" style="color:var(--sa-purple)">
      <i class="bi bi-eye me-1"></i>
      Schedule Preview — <?= htmlspecialchars($stylist['name']) ?>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2">
      <?php foreach ($days as $day):
        $row     = $schedule[$day] ?? [];
        $working = (int)($row['is_working'] ?? ($day !== 'Sunday' ? 1 : 0));
        $st      = isset($row['start_time']) ? date('g:i A', strtotime($row['start_time'])) : '9:00 AM';
        $et      = isset($row['end_time'])   ? date('g:i A', strtotime($row['end_time']))   : '6:00 PM';
        $bs      = (!empty($row['break_start']) && $row['break_start'] !== '00:00:00')
                     ? date('g:i A', strtotime($row['break_start'])) : '';
        $be      = (!empty($row['break_end'])   && $row['break_end']   !== '00:00:00')
                     ? date('g:i A', strtotime($row['break_end']))   : '';
      ?>
      <div class="col-md-3 col-sm-4 col-6">
        <div class="p-2 rounded text-center"
             style="background:<?= $working ? '#e8f5e9' : '#fce4ec' ?>;
                    border:1.5px solid <?= $working ? '#a5d6a7' : '#ef9a9a' ?>">
          <div class="fw-bold small"><?= $day ?></div>
          <?php if ($working): ?>
            <div style="color:#2e7d32;font-size:.78rem"><?= $st ?> – <?= $et ?></div>
            <?php if ($bs && $be): ?>
            <div style="color:#b45309;font-size:.7rem">
              <i class="bi bi-cup-hot me-1"></i>Break <?= $bs ?> – <?= $be ?>
            </div>
            <?php endif; ?>
          <?php else: ?>
            <div style="color:#b71c1c;font-size:.78rem">Day Off</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
/* ── Toggle working/off ── */
document.querySelectorAll('.day-toggle').forEach(function(toggle) {
  toggle.addEventListener('change', function() {
    const day    = this.dataset.day;
    const on     = this.checked;
    const label  = document.querySelector('.day-label-' + day);
    const fields = document.querySelectorAll('.day-fields-' + day);
    label.textContent = on ? 'Working' : 'OFF';
    label.style.color = on ? '#2e7d32' : '#b71c1c';
    fields.forEach(function(f) {
      f.style.opacity       = on ? '1'    : '0.4';
      f.style.pointerEvents = on ? 'auto' : 'none';
    });
    updateSummary(day);
  });
});

/* ── Live summary badge ── */
function updateSummary(day) {
  const toggle = document.querySelector('[name="is_working[' + day + ']"]');
  const sInput = document.querySelector('[name="start_time[' + day + ']"]');
  const eInput = document.querySelector('[name="end_time['   + day + ']"]');
  const bsIn   = document.querySelector('[name="break_start[' + day + ']"]');
  const beIn   = document.querySelector('[name="break_end['   + day + ']"]');
  const badge  = document.querySelector('.day-summary-' + day);
  if (!badge) return;
  const on = toggle && toggle.checked;
  if (!on) {
    badge.textContent      = 'OFF';
    badge.style.background = '#fce4ec';
    badge.style.color      = '#b71c1c';
    return;
  }
  const s  = sInput ? formatT(sInput.value) : '';
  const e  = eInput ? formatT(eInput.value) : '';
  const bs = bsIn   ? formatT(bsIn.value)   : '';
  const be = beIn   ? formatT(beIn.value)   : '';
  let txt = (s && e) ? s + '–' + e : 'Working';
  if (bs && be) txt += '\n(brk ' + bs + '–' + be + ')';
  badge.textContent      = txt;
  badge.style.background = '#e8f5e9';
  badge.style.color      = '#2e7d32';
}

document.querySelectorAll('.start-input,.end-input,.break-start-input,.break-end-input')
  .forEach(function(inp) {
    inp.addEventListener('change', function() { updateSummary(this.dataset.day); });
  });

function formatT(val) {
  if (!val) return '';
  const [h, m] = val.split(':');
  const hr = parseInt(h);
  return (hr > 12 ? hr - 12 : (hr === 0 ? 12 : hr)) + ':' + m + (hr >= 12 ? 'pm' : 'am');
}

/* ── Copy Monday → Tue–Sat ── */
document.getElementById('copyMonBtn').addEventListener('click', function() {
  const fields = ['start_time','break_start','break_end','end_time'];
  const monToggle = document.querySelector('[name="is_working[monday]"]');
  const days = ['tuesday','wednesday','thursday','friday','saturday'];

  days.forEach(function(day) {
    fields.forEach(function(f) {
      const src = document.querySelector('[name="' + f + '[monday]"]');
      const dst = document.querySelector('[name="' + f + '[' + day + ']"]');
      if (src && dst) dst.value = src.value;
    });
    const toggle = document.querySelector('[name="is_working[' + day + ']"]');
    const label  = document.querySelector('.day-label-' + day);
    const flds   = document.querySelectorAll('.day-fields-' + day);
    if (toggle && monToggle) {
      toggle.checked = monToggle.checked;
      const on = monToggle.checked;
      if (label) { label.textContent = on ? 'Working' : 'OFF'; label.style.color = on ? '#2e7d32' : '#b71c1c'; }
      flds.forEach(function(f) { f.style.opacity = on ? '1' : '0.4'; f.style.pointerEvents = on ? 'auto' : 'none'; });
    }
    updateSummary(day);
  });

  const btn = document.getElementById('copyMonBtn');
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied!';
  btn.classList.replace('btn-light','btn-success');
  setTimeout(function() { btn.innerHTML = orig; btn.classList.replace('btn-success','btn-light'); }, 1800);
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
