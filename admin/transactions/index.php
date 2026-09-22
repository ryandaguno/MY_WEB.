<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
$pageTitle = 'Transactions';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';
$db = getDB();

$sql = 'SELECT t.*,
               c.username  AS client_name,
               sv.name     AS service_name,
               st.name     AS stylist_name,
               sc.slot_date, sc.start_time,
               b.id        AS booking_id
        FROM transactions t
        JOIN bookings  b  ON t.booking_id  = b.id
        JOIN clients   c  ON t.client_id   = c.id
        JOIN services  sv ON b.service_id  = sv.id
        LEFT JOIN stylists  st ON b.stylist_id  = st.id
        LEFT JOIN schedules sc ON b.schedule_id = sc.id
        ORDER BY t.submitted_at DESC';

$txns = $db->query($sql)->fetchAll();
?>

<style>
.receipt-thumb {
    width:54px; height:54px; object-fit:cover;
    border-radius:6px; border:2px solid #e0d5ea;
    cursor:zoom-in; transition:transform .15s, box-shadow .15s;
}
.receipt-thumb:hover { transform:scale(1.08); box-shadow:0 4px 14px rgba(107,45,139,.3); }
.no-receipt { font-size:.75rem; color:#aaa; font-style:italic; }
.table-txn th { background:#6B2D8B; color:#fff; font-size:.78rem; white-space:nowrap; }
.table-txn td { vertical-align:middle; font-size:.83rem; }
</style>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
      <i class="bi bi-credit-card me-2"></i>Transaction Management
    </h3>
    <p class="text-muted mb-0 small">Review GCash receipts and approve or reject payments.</p>
  </div>
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
          <th>Stylist</th>
          <th>Appointment</th>
          <th>Amount</th>
          <th>Submitted</th>
          <th>Receipt</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($txns as $tx):
          $hasReceipt = !empty($tx['receipt_image']);
        ?>
        <tr>
          <td class="fw-bold text-muted">#<?= $tx['id'] ?></td>
          <td><?= htmlspecialchars($tx['client_name']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/bookings/index.php" class="text-decoration-none fw-semibold" style="color:var(--sa-purple)">
              #<?= $tx['booking_id'] ?>
            </a>
          </td>
          <td><?= htmlspecialchars($tx['service_name']) ?></td>
          <td><?= htmlspecialchars($tx['stylist_name'] ?? '—') ?></td>
          <td>
            <?php if ($tx['slot_date']): ?>
              <span class="d-block"><?= date('M j, Y', strtotime($tx['slot_date'])) ?></span>
              <small class="text-muted"><?= date('g:i A', strtotime($tx['start_time'])) ?></small>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="fw-semibold">₱<?= number_format($tx['amount'], 2) ?></td>
          <td>
            <span class="d-block"><?= date('M j, Y', strtotime($tx['submitted_at'])) ?></span>
            <small class="text-muted"><?= date('g:i A', strtotime($tx['submitted_at'])) ?></small>
          </td>
          <td>
            <?php if ($hasReceipt): ?>
              <img src="receipt_proxy.php?id=<?= $tx['id'] ?>"
                   alt="Receipt"
                   class="receipt-thumb"
                   onclick="viewReceipt('receipt_proxy.php?id=<?= $tx['id'] ?>')"
                   title="Click to view receipt">
            <?php else: ?>
              <span class="no-receipt"><i class="bi bi-image text-muted"></i><br>No receipt</span>
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
