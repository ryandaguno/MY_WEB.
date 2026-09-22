<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
$pageTitle = 'Transactions';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';
$db = getDB();

$filterStatus = $_GET['status'] ?? 'all';
$where  = '';
$params = [];
if ($filterStatus === 'pending') {
    $where  = "WHERE t.status = 'Pending Verification'";
} elseif ($filterStatus === 'verified') {
    $where  = "WHERE t.status IN ('Verified - Downpayment','Verified - Full','Verified - Partial','Paid')";
} elseif ($filterStatus === 'rejected') {
    $where  = "WHERE t.status = 'Rejected'";
}

$txns = $db->query(
    "SELECT t.*,
            c.username  AS client_name,
            sv.name     AS service_name,
            st.name     AS stylist_name,
            sc.slot_date, sc.start_time,
            b.id        AS booking_id,
            b.status    AS booking_status
     FROM transactions t
     JOIN bookings  b  ON t.booking_id = b.id
     JOIN clients   c  ON t.client_id  = c.id
     JOIN services  sv ON b.service_id = sv.id
     LEFT JOIN stylists  st ON b.stylist_id  = st.id
     LEFT JOIN schedules sc ON b.schedule_id = sc.id
     $where
     ORDER BY
       CASE t.status WHEN 'Pending Verification' THEN 0 ELSE 1 END,
       t.submitted_at DESC"
)->fetchAll();

$counts = $db->query(
    "SELECT
       COUNT(*) AS total,
       SUM(status = 'Pending Verification') AS pending,
       SUM(status IN ('Verified - Downpayment','Verified - Full','Verified - Partial','Paid')) AS verified,
       SUM(status = 'Rejected') AS rejected
     FROM transactions"
)->fetch();
?>

<style>
.receipt-thumb { width:48px; height:48px; object-fit:cover; border-radius:6px; border:2px solid #e0d5ea; cursor:zoom-in; }
.receipt-thumb:hover { transform:scale(1.1); box-shadow:0 4px 14px rgba(107,45,139,.3); }
.no-receipt { font-size:.72rem; color:#aaa; font-style:italic; }
.table-txn th { background:#6B2D8B; color:#fff; font-size:.75rem; white-space:nowrap; }
.table-txn td { vertical-align:middle; font-size:.82rem; }
.s-pv  { background:#f59e0b; color:#fff; }
.s-vd  { background:#10b981; color:#fff; }
.s-vf  { background:#0284c7; color:#fff; }
.s-vp  { background:#f59e0b; color:#fff; }
.s-pd  { background:#10b981; color:#fff; }
.s-rj  { background:#ef4444; color:#fff; }
.s-def { background:#6c757d; color:#fff; }
.filter-tab       { border-radius:50px; font-size:.8rem; padding:5px 16px; }
.filter-tab.active{ background:#6B2D8B !important; color:#fff !important; border-color:#6B2D8B !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
      <i class="bi bi-credit-card me-2"></i>Transaction Management
    </h3>
    <p class="text-muted mb-0 small">Review GCash receipts and approve or reject payments.</p>
  </div>
  <?php if ((int)$counts['pending'] > 0): ?>
    <span class="badge bg-warning text-dark fs-6">
      <i class="bi bi-hourglass-split me-1"></i><?= $counts['pending'] ?> Pending Review
    </span>
  <?php endif; ?>
</div>

<!-- Filter tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <?php
  $tabs = [
    'all'      => ['All',            $counts['total']],
    'pending'  => ['Pending Review', $counts['pending']],
    'verified' => ['Verified / Paid',$counts['verified']],
    'rejected' => ['Rejected',       $counts['rejected']],
  ];
  foreach ($tabs as $key => [$label, $cnt]):
    $active = ($filterStatus === $key) ? 'active' : '';
  ?>
  <a href="?status=<?= $key ?>"
     class="btn btn-sm btn-outline-secondary filter-tab <?= $active ?>">
    <?= $label ?>
    <span class="badge bg-white text-dark ms-1"><?= (int)$cnt ?></span>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($txns)): ?>
  <div class="text-center py-5 text-muted">
    <i class="bi bi-inbox" style="font-size:3rem"></i>
    <p class="mt-2">No transactions found.</p>
  </div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 table-txn">
      <thead>
        <tr>
          <th>#</th>
          <th>Client</th>
          <th>Booking</th>
          <th>Service</th>
          <th>Appointment</th>
          <th>Method</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Receipt</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($txns as $tx):
          // Status badge
          $s = $tx['status'];
          if ($s === 'Pending Verification')   { $cls = 's-pv';  $label = 'Pending Review'; }
          elseif ($s === 'Verified - Downpayment') { $cls = 's-vd'; $label = 'Verified (DP)'; }
          elseif ($s === 'Verified - Full')    { $cls = 's-vf';  $label = 'Fully Verified'; }
          elseif ($s === 'Verified - Partial') { $cls = 's-vp';  $label = 'Partial'; }
          elseif ($s === 'Paid')               { $cls = 's-pd';  $label = 'Paid (PayPal)'; }
          elseif ($s === 'Rejected')           { $cls = 's-rj';  $label = 'Rejected'; }
          else                                 { $cls = 's-def'; $label = htmlspecialchars($s); }
          $isPending  = ($s === 'Pending Verification');
          $hasReceipt = !empty($tx['receipt_image']);
          $rowStyle   = $isPending ? 'background:#fffbeb' : '';
        ?>
        <tr style="<?= $rowStyle ?>">
          <td class="fw-bold text-muted">#<?= $tx['id'] ?></td>
          <td><?= htmlspecialchars($tx['client_name']) ?></td>
          <td class="fw-semibold" style="color:var(--sa-purple)">#<?= $tx['booking_id'] ?></td>
          <td><?= htmlspecialchars($tx['service_name']) ?></td>
          <td>
            <?php if ($tx['slot_date']): ?>
              <?= date('M j, Y', strtotime($tx['slot_date'])) ?><br>
              <small class="text-muted"><?= date('g:i A', strtotime($tx['start_time'])) ?></small>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <?php if ($tx['payment_method'] === 'GCash'): ?>
              <i class="bi bi-qr-code me-1 text-success"></i>GCash
            <?php else: ?>
              <i class="bi bi-paypal me-1" style="color:#003087"></i>PayPal
            <?php endif; ?>
          </td>
          <td class="fw-semibold">₱<?= number_format($tx['amount'], 2) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $label ?></span></td>
          <td>
            <?= date('M j, Y', strtotime($tx['submitted_at'])) ?><br>
            <small class="text-muted"><?= date('g:i A', strtotime($tx['submitted_at'])) ?></small>
          </td>
          <td>
            <?php if ($hasReceipt): ?>
              <img src="receipt_proxy.php?id=<?= $tx['id'] ?>"
                   alt="Receipt" class="receipt-thumb"
                   onclick="viewReceipt('receipt_proxy.php?id=<?= $tx['id'] ?>')"
                   title="Click to view">
            <?php else: ?>
              <span class="no-receipt"><i class="bi bi-image"></i><br>None</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isPending && $tx['payment_method'] === 'GCash'): ?>
              <div class="d-flex gap-1 flex-wrap">
                <a href="verify.php?id=<?= $tx['id'] ?>"
                   class="btn btn-sm btn-success fw-bold">
                  <i class="bi bi-check-circle me-1"></i>Verify
                </a>
                <a href="reject.php?id=<?= $tx['id'] ?>"
                   class="btn btn-sm btn-danger fw-bold"
                   onclick="return confirm('Reject this payment? The client will be notified to resubmit.')">
                  <i class="bi bi-x-circle me-1"></i>Reject
                </a>
              </div>
            <?php elseif ($s === 'Verified - Partial'): ?>
              <a href="verify.php?id=<?= $tx['id'] ?>"
                 class="btn btn-sm btn-outline-warning fw-bold">
                <i class="bi bi-pencil me-1"></i>Review
              </a>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Receipt Viewer Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:560px">
    <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
      <div class="modal-header py-2 px-3" style="background:#6B2D8B">
        <h6 class="modal-title text-white fw-bold mb-0">
          <i class="bi bi-receipt me-2"></i>Payment Receipt
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3 text-center" style="background:#1a1a1a">
        <img id="receiptImg" src="" alt="Receipt"
             style="max-width:100%; max-height:75vh; border-radius:8px; object-fit:contain;">
      </div>
    </div>
  </div>
</div>

<script>
function viewReceipt(src) {
  document.getElementById('receiptImg').src = src;
  new bootstrap.Modal(document.getElementById('receiptModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
