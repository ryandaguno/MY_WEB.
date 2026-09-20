<?php
$pageTitle = 'My Bookings';
require_once __DIR__ . '/../includes/header.php';
SessionGuard::requireClient();
require_once __DIR__ . '/../modules/BookingManager.php';
require_once __DIR__ . '/../config/db.php';

$bm       = new BookingManager();
$bookings = $bm->getClientBookings($_SESSION['client_id']);
$now      = time();

$upcoming   = [];
$past       = [];
$accepted   = [];
$cancelled  = [];
$transactions = [];

foreach ($bookings as $b) {
    $apptTime = strtotime($b['slot_date'] . ' ' . $b['start_time']);
    switch ($b['status']) {
        case 'Pending':
            if ($apptTime > $now) $upcoming[] = $b; else $past[] = $b;
            break;
        case 'Accepted':
            $accepted[] = $b;
            break;
        case 'Cancelled':
            $cancelled[] = $b;
            break;
        case 'Completed':
            $past[] = $b;
            break;
    }
    if (!empty($b['payment_status'])) $transactions[] = $b;
}

// Transaction history query
$db = getDB();
$txStmt = $db->prepare(
    'SELECT t.*, b.id AS booking_id, sv.name AS service_name, sc.slot_date
     FROM transactions t
     JOIN bookings b  ON t.booking_id = b.id
     JOIN services sv ON b.service_id = sv.id
     JOIN schedules sc ON b.schedule_id = sc.id
     WHERE t.client_id = ?
     ORDER BY t.submitted_at DESC'
);
$txStmt->execute([$_SESSION['client_id']]);
$txHistory = $txStmt->fetchAll();

function statusBadge(string $status): string {
    $map = ['Pending'=>'warning','Accepted'=>'success','Cancelled'=>'danger','Completed'=>'primary'];
    $cls = $map[$status] ?? 'secondary';
    return "<span class='badge bg-$cls'>$status</span>";
}
function bookingRow(array $b, bool $showCancel = false): string {
    $date = date('M j, Y', strtotime($b['slot_date']));
    $time = date('g:i A', strtotime($b['start_time']));

    // Show downpayment amount with payment status
    $downpayment = '₱' . number_format($b['downpayment_amount'] ?? 0, 2);
    if ($b['payment_status'] === 'Paid' || $b['payment_status'] === 'Downpayment Paid' || $b['payment_status'] === 'Fully Paid') {
        $pay = "<span class='badge bg-success'>{$b['payment_status']}</span><br><small class='text-muted'>$downpayment</small>";
    } elseif ($b['payment_status'] === 'Pending Verification') {
        $pay = "<span class='badge bg-warning text-dark'>Pending</span><br><small class='text-muted'>$downpayment</small>";
    } elseif ($b['payment_status'] === 'Rejected') {
        $pay = "<span class='badge bg-danger'>Rejected</span><br><small class='text-muted'>$downpayment</small>";
    } else {
        $pay = "<span class='badge bg-secondary'>Unpaid</span><br><small class='text-muted'>Due: $downpayment</small>";
    }
    $cancel = '';
    if ($showCancel) {
        $apptTime  = strtotime($b['slot_date'].' '.$b['start_time']);
        $hoursAway = ($apptTime - time()) / 3600;
        if (in_array($b['status'],['Pending','Accepted']) && $hoursAway > 0) {
            $cancel = "<a href='".BASE_URL."/public/cancel_booking.php?id={$b['id']}' class='btn btn-sm btn-outline-danger'>Cancel</a>";
        }
    }
    return "<tr>
      <td>#" . htmlspecialchars($b['id']) . "</td>
      <td>" . htmlspecialchars($b['service_name']) . "</td>
      <td>" . htmlspecialchars($b['stylist_name']) . "</td>
      <td>$date $time</td>
      <td>" . statusBadge($b['status']) . "</td>
      <td>$pay</td>
      <td>
        <a href='".BASE_URL."/public/booking_detail.php?id={$b['id']}' class='btn btn-sm btn-sa-primary me-1'>View</a>
        $cancel
      </td>
    </tr>";
}
?>

<div class="container my-4">
  <h2 class="fw-bold mb-4" style="color:var(--sa-purple)"><i class="bi bi-calendar-check me-2"></i>My Bookings</h2>

  <ul class="nav nav-tabs mb-3" id="bookingTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#upcoming">Upcoming (<?= count($upcoming) ?>)</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#accepted">Accepted (<?= count($accepted) ?>)</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#past">Past (<?= count($past) ?>)</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#cancelled">Cancelled (<?= count($cancelled) ?>)</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#transactions">Transactions (<?= count($txHistory) ?>)</a></li>
  </ul>

  <div class="tab-content">
    <?php foreach (['upcoming'=>[$upcoming,true],'accepted'=>[$accepted,true],'past'=>[$past,false],'cancelled'=>[$cancelled,false]] as $tabId=>[$list,$canCancel]): ?>
    <div class="tab-pane fade <?= $tabId==='upcoming'?'show active':'' ?>" id="<?= $tabId ?>">
      <?php if (empty($list)): ?>
        <p class="text-muted">No <?= $tabId ?> bookings.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover table-sa">
            <thead><tr><th>#</th><th>Service</th><th>Stylist</th><th>Date &amp; Time</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
            <tbody><?php foreach ($list as $b) echo bookingRow($b, $canCancel); ?></tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Transaction History Tab -->
    <div class="tab-pane fade" id="transactions">
      <?php if (empty($txHistory)): ?>
        <p class="text-muted">No transactions yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover table-sa">
            <thead><tr><th>#</th><th>Booking</th><th>Service</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($txHistory as $tx): ?>
              <tr>
                <td>#<?= $tx['id'] ?></td>
                <td>#<?= $tx['booking_id'] ?></td>
                <td><?= htmlspecialchars($tx['service_name']) ?></td>
                <td><?= htmlspecialchars($tx['payment_method']) ?></td>
                <td>₱<?= number_format($tx['amount'],2) ?></td>
                <td><span class="badge bg-<?= $tx['status']==='Paid'?'success':($tx['status']==='Rejected'?'danger':'warning') ?>"><?= htmlspecialchars($tx['status']) ?></span></td>
                <td><?= date('M j, Y', strtotime($tx['submitted_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="mt-3">
    <a href="<?= BASE_URL ?>/public/booking/step1_service.php" class="btn btn-sa-teal">
      <i class="bi bi-calendar-plus me-1"></i>Book New Appointment
    </a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
