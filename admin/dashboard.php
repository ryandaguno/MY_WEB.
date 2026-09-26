<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../config/db.php';
$db    = getDB();
$today = date('Y-m-d');

// ── Booking counts ─────────────────────────────────────────────────
$pendingCount   = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='Pending'")->fetchColumn();
$acceptedCount  = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='Accepted'")->fetchColumn();
$completedCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='Completed'")->fetchColumn();
$cancelledCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='Cancelled'")->fetchColumn();

$doneTodayStmt = $db->prepare(
    "SELECT COUNT(*) FROM bookings b JOIN schedules sc ON b.schedule_id=sc.id
     WHERE b.status='Completed' AND sc.slot_date=?"
);
$doneTodayStmt->execute([$today]);
$doneTodayCount = (int)$doneTodayStmt->fetchColumn();

// ── Schedule summary counts ────────────────────────────────────────
$todayCountStmt = $db->prepare(
    "SELECT COUNT(*) FROM bookings b JOIN schedules sc ON b.schedule_id=sc.id
     WHERE sc.slot_date=? AND b.status NOT IN ('Cancelled')"
);
$todayCountStmt->execute([$today]);
$todayCount = (int)$todayCountStmt->fetchColumn();

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$tomorrowStmt = $db->prepare(
    "SELECT COUNT(*) FROM bookings b JOIN schedules sc ON b.schedule_id=sc.id
     WHERE sc.slot_date=? AND b.status NOT IN ('Cancelled')"
);
$tomorrowStmt->execute([$tomorrow]);
$tomorrowCount = (int)$tomorrowStmt->fetchColumn();

$weekEnd = date('Y-m-d', strtotime('+6 days'));
$weekStmt = $db->prepare(
    "SELECT COUNT(*) FROM bookings b JOIN schedules sc ON b.schedule_id=sc.id
     WHERE sc.slot_date BETWEEN ? AND ? AND b.status NOT IN ('Cancelled')"
);
$weekStmt->execute([$today, $weekEnd]);
$thisWeekCount = (int)$weekStmt->fetchColumn();

$nextWeekStart = date('Y-m-d', strtotime('+7 days'));
$nextWeekEnd   = date('Y-m-d', strtotime('+13 days'));
$nextWeekStmt  = $db->prepare(
    "SELECT COUNT(*) FROM bookings b JOIN schedules sc ON b.schedule_id=sc.id
     WHERE sc.slot_date BETWEEN ? AND ? AND b.status NOT IN ('Cancelled')"
);
$nextWeekStmt->execute([$nextWeekStart, $nextWeekEnd]);
$nextWeekCount = (int)$nextWeekStmt->fetchColumn();

$monthStmt = $db->prepare(
    "SELECT COUNT(*) FROM bookings b JOIN schedules sc ON b.schedule_id=sc.id
     WHERE MONTH(sc.slot_date)=MONTH(?) AND YEAR(sc.slot_date)=YEAR(?)
     AND b.status NOT IN ('Cancelled')"
);
$monthStmt->execute([$today, $today]);
$thisMonthCount = (int)$monthStmt->fetchColumn();

// ── Revenue this month ─────────────────────────────────────────────
$revStmt = $db->prepare(
    "SELECT COALESCE(SUM(t.amount),0) FROM transactions t
     JOIN bookings b ON t.booking_id=b.id
     JOIN schedules sc ON b.schedule_id=sc.id
     WHERE t.status IN ('Paid','Downpayment Paid','Fully Paid')
       AND MONTH(sc.slot_date)=MONTH(?) AND YEAR(sc.slot_date)=YEAR(?)"
);
$revStmt->execute([$today, $today]);
$revenue = (float)$revStmt->fetchColumn();

// ── Top services ──────────────────────────────────────────────────
$topSvc = $db->query(
    "SELECT sv.name, COUNT(*) AS cnt FROM bookings b
     JOIN services sv ON b.service_id=sv.id
     GROUP BY sv.id ORDER BY cnt DESC LIMIT 5"
)->fetchAll();

// ── Bookings for inline table (all statuses) ───────────────────────
$allBookings = $db->query(
    "SELECT b.id, c.username AS client, c.phone, c.email,
            sv.name AS service, st.name AS stylist,
            sc.slot_date, sc.start_time, b.status,
            b.downpayment_amount,
            t.status AS payment_status, t.amount AS paid_amount
     FROM bookings b
     JOIN clients   c  ON b.client_id=c.id
     JOIN services  sv ON b.service_id=sv.id
     JOIN stylists  st ON b.stylist_id=st.id
     JOIN schedules sc ON b.schedule_id=sc.id
     LEFT JOIN transactions t
       ON t.booking_id=b.id
       AND t.id=(SELECT id FROM transactions WHERE booking_id=b.id ORDER BY submitted_at DESC LIMIT 1)
     ORDER BY sc.slot_date ASC, sc.start_time ASC"
)->fetchAll();

// Group by status
$grouped = ['Pending'=>[], 'Accepted'=>[], 'Completed'=>[], 'Cancelled'=>[]];
foreach ($allBookings as $b) {
    $s = $b['status'];
    if (isset($grouped[$s])) $grouped[$s][] = $b;
}

$csrfToken = SessionGuard::generateCsrfToken();
?>

<style>
/* ── Stat cards ── */
.dash-card {
    border-radius:14px; padding:20px 24px; color:#fff;
    cursor:pointer; transition:transform .15s, box-shadow .15s;
    position:relative; overflow:hidden;
}
.dash-card:hover { transform:translateY(-3px); box-shadow:0 8px 28px rgba(0,0,0,.18); }
.dash-card h2  { font-size:2.4rem; font-weight:900; margin:0; }
.dash-card p   { margin:4px 0 0; font-size:.82rem; opacity:.92; font-weight:600; }
.dash-card .icon { position:absolute; right:16px; bottom:12px; font-size:3rem; opacity:.18; }
.dash-card.c-pending   { background:linear-gradient(135deg,#f59e0b,#d97706); }
.dash-card.c-completed { background:linear-gradient(135deg,#10b981,#059669); }
.dash-card.c-cancelled { background:linear-gradient(135deg,#ef4444,#dc2626); }
.dash-card.c-today     { background:linear-gradient(135deg,#3b82f6,#1d4ed8); }
.dash-card.c-active { box-shadow: 0 0 0 3px #fff, 0 0 0 5px #6B2D8B !important; }

/* ── Schedule pills ── */
.sched-pill {
    border-radius:12px; padding:14px 16px; text-align:center;
    background:#fff; border:1.5px solid #e2e8f0;
    cursor:pointer; transition:all .15s;
    box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.sched-pill:hover { border-color:#6B2D8B; box-shadow:0 4px 12px rgba(107,45,139,.15); }
.sched-pill .num  { font-size:1.6rem; font-weight:900; color:#6B2D8B; }
.sched-pill .lbl  { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#64748b; }

/* ── Booking table ── */
.bk-table th { background:#6B2D8B; color:#fff; font-size:.78rem; white-space:nowrap; }
.bk-table td { font-size:.83rem; vertical-align:middle; }
.bk-panel    { display:none; }
.bk-panel.visible { display:block; }

/* ── Alert banner ── */
.new-alert {
    background:linear-gradient(135deg,#fef3c7,#fde68a);
    border:2px solid #f59e0b; border-radius:12px;
    padding:14px 20px;
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:10px;
}
</style>

<!-- ── New Booking Alert ── -->
<?php if ($pendingCount > 0): ?>
<div class="new-alert mb-4">
  <div class="d-flex align-items-center gap-3">
    <span style="font-size:1.8rem">🔔</span>
    <div>
      <div class="fw-bold" style="color:#92400e;font-size:1rem">
        <?= $pendingCount ?> New Booking<?= $pendingCount > 1 ? 's' : '' ?> Waiting for Review
      </div>
      <div style="color:#78350f;font-size:.82rem">
        Review and accept or reject each booking below.
      </div>
    </div>
  </div>
  <a href="<?= BASE_URL ?>/admin/bookings/index.php"
     class="btn btn-sm fw-bold"
     style="background:#f59e0b;color:#fff;border:none">
    <i class="bi bi-arrow-right-circle me-1"></i>Go to Bookings
  </a>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">Appointment Overview</h3>
  <small class="text-muted">Click a card to view bookings</small>
</div>

<!-- ── 4 Main Stat Cards ── -->
<div class="row g-3 mb-4">
  <div class="col-md-3 col-6">
    <div class="dash-card c-pending" onclick="showPanel('Pending')">
      <h2><?= $pendingCount ?></h2>
      <p>Pending</p>
      <i class="bi bi-hourglass-split icon"></i>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="dash-card c-completed" onclick="showPanel('Accepted')">
      <h2><?= $acceptedCount ?></h2>
      <p>Confirmed</p>
      <i class="bi bi-check-circle icon"></i>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="dash-card c-cancelled" onclick="showPanel('Cancelled')">
      <h2><?= $cancelledCount ?></h2>
      <p>Cancelled</p>
      <i class="bi bi-x-circle icon"></i>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="dash-card c-today" onclick="showPanel('Completed')">
      <h2><?= $doneTodayCount ?></h2>
      <p>Done Today</p>
      <i class="bi bi-calendar-check icon"></i>
    </div>
  </div>
</div>

<!-- ── Inline Booking Panel (shown on card click) ── -->
<?php foreach (['Pending','Accepted','Cancelled','Completed'] as $status):
  $list = $grouped[$status];
  $colors = ['Pending'=>'warning','Accepted'=>'success','Cancelled'=>'danger','Completed'=>'primary'];
?>
<div class="bk-panel mb-4" id="panel-<?= $status ?>">
  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center py-2"
         style="background:#6B2D8B">
      <span class="text-white fw-bold">
        <i class="bi bi-list-ul me-2"></i><?= $status ?> Bookings
        <span class="badge bg-white text-dark ms-2"><?= count($list) ?></span>
      </span>
      <button class="btn btn-sm btn-outline-light" onclick="hidePanel('<?= $status ?>')">
        <i class="bi bi-x"></i> Close
      </button>
    </div>
    <?php if (empty($list)): ?>
      <div class="card-body text-muted py-4 text-center">
        No <?= strtolower($status) ?> bookings.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 bk-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Client</th>
            <th>Phone</th>
            <th>Service</th>
            <th>Stylist</th>
            <th>Date & Time</th>
            <th>Downpayment</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $b): ?>
          <tr>
            <td class="text-muted fw-bold">#<?= $b['id'] ?></td>
            <td><?= htmlspecialchars($b['client']) ?></td>
            <td><?= htmlspecialchars($b['phone']) ?></td>
            <td><?= htmlspecialchars($b['service']) ?></td>
            <td><?= htmlspecialchars($b['stylist']) ?></td>
            <td>
              <?= date('M j, Y', strtotime($b['slot_date'])) ?><br>
              <small class="text-muted"><?= date('g:i A', strtotime($b['start_time'])) ?></small>
            </td>
            <td>₱<?= number_format($b['downpayment_amount'], 2) ?></td>
            <td>
              <?php
                $ps = $b['payment_status'] ?? '';
                if ($ps === 'Paid' || $ps === 'Downpayment Paid' || $ps === 'Fully Paid') {
                    $pCls = 'success';
                } elseif ($ps === 'Pending Verification') {
                    $pCls = 'warning';
                } elseif ($ps === 'Rejected') {
                    $pCls = 'danger';
                } else {
                    $pCls = 'secondary';
                }
                $psLabel = $ps ?: 'Unpaid';
              ?>
              <span class="badge bg-<?= $pCls ?>"><?= htmlspecialchars($psLabel) ?></span>
              <?php if ($b['paid_amount']): ?>
                <br><small>₱<?= number_format($b['paid_amount'], 2) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge bg-<?= $colors[$b['status']] ?? 'secondary' ?>">
                <?= $b['status'] ?>
              </span>
            </td>
            <td>
              <a href="<?= BASE_URL ?>/admin/bookings/detail.php?id=<?= $b['id'] ?>"
                 class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- ── Schedule Summary Pills ── -->
<div class="row g-2 mb-4">
  <?php
  $pills = [
    ['Today',      $todayCount],
    ['Tomorrow',   $tomorrowCount],
    ['This Week',  $thisWeekCount],
    ['Next Week',  $nextWeekCount],
    ['This Month', $thisMonthCount],
  ];
  foreach ($pills as [$lbl, $cnt]):
  ?>
  <div class="col">
    <a href="<?= BASE_URL ?>/admin/bookings/index.php" class="text-decoration-none">
      <div class="sched-pill">
        <div class="num"><?= $cnt ?></div>
        <div class="lbl"><?= $lbl ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Bottom Row: Today's Appointments + Revenue ── -->
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm p-3">
      <h6 class="fw-bold mb-3" style="color:var(--sa-purple)">
        <i class="bi bi-calendar-day me-2"></i>Today's Appointments — <?= date('F j, Y') ?>
      </h6>
      <?php
        $todayAppts = array_filter($allBookings, fn($b) => $b['slot_date'] === $today);
      ?>
      <?php if (empty($todayAppts)): ?>
        <p class="text-muted small">No appointments scheduled for today.</p>
      <?php else: ?>
      <table class="table table-sm mb-0 bk-table">
        <thead>
          <tr><th>Client</th><th>Service</th><th>Stylist</th><th>Time</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php
          $bkColors = ['Pending'=>'warning','Accepted'=>'success','Cancelled'=>'danger','Completed'=>'primary'];
          foreach ($todayAppts as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['client']) ?></td>
            <td><?= htmlspecialchars($a['service']) ?></td>
            <td><?= htmlspecialchars($a['stylist']) ?></td>
            <td><?= date('g:i A', strtotime($a['start_time'])) ?></td>
            <td>
              <span class="badge bg-<?= $bkColors[$a['status']] ?? 'secondary' ?>">
                <?= $a['status'] ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-3 mb-3">
      <h6 class="fw-bold" style="color:var(--sa-purple)">
        <i class="bi bi-cash-stack me-2"></i>Revenue This Month
      </h6>
      <h2 class="fw-bold" style="color:var(--sa-teal)">₱<?= number_format($revenue, 2) ?></h2>
    </div>
    <div class="card border-0 shadow-sm p-3">
      <h6 class="fw-bold mb-3" style="color:var(--sa-purple)">
        <i class="bi bi-trophy me-2"></i>Top Services
      </h6>
      <?php foreach ($topSvc as $i => $svc): ?>
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span><?= $i+1 ?>. <?= htmlspecialchars($svc['name']) ?></span>
        <span class="badge" style="background:var(--sa-teal)"><?= $svc['cnt'] ?> bookings</span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function showPanel(status) {
    // Hide all panels first
    document.querySelectorAll('.bk-panel').forEach(p => p.classList.remove('visible'));
    const panel = document.getElementById('panel-' + status);
    if (panel) {
        panel.classList.add('visible');
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
function hidePanel(status) {
    const panel = document.getElementById('panel-' + status);
    if (panel) panel.classList.remove('visible');
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
