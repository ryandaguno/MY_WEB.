<?php
// ── Bootstrap BEFORE any output so header() redirects work ──────────
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';

$id = (int)($_POST['txn_id'] ?? $_GET['id'] ?? 0);
$db = getDB();

// ── Handle POST first — before any HTML is sent ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    $ph = new PaymentHandler();
    if (($_POST['action'] ?? '') === 'verify') {
        $amountReceived = (float)str_replace(',', '', $_POST['amount_received'] ?? '0');
        $result = $ph->verifyGCash($id, (int)$_SESSION['admin_id'], $amountReceived);
        SessionGuard::flashMessage(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? 'Payment verified as ' . $result['payment_status'] . '. Client has been notified.'
                : $result['message']
        );
    } else {
        $result = $ph->rejectGCash($id);
        SessionGuard::flashMessage(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? 'Payment rejected. Client has been notified to resubmit.'
                : $result['message']
        );
    }
    header('Location: index.php'); exit;
}

// ── Fetch transaction ────────────────────────────────────────────────
$stmt = $db->prepare(
    'SELECT t.*,
            c.username   AS client_name,
            c.email      AS client_email,
            c.phone      AS client_phone,
            sv.name      AS service_name,
            sv.price     AS service_price,
            st.name      AS stylist_name,
            sc.slot_date, sc.start_time,
            b.id         AS booking_id,
            b.downpayment_amount,
            b.status     AS booking_status
     FROM transactions t
     JOIN bookings  b  ON t.booking_id  = b.id
     JOIN clients   c  ON t.client_id   = c.id
     JOIN services  sv ON b.service_id  = sv.id
     LEFT JOIN stylists  st ON b.stylist_id  = st.id
     LEFT JOIN schedules sc ON b.schedule_id = sc.id
     WHERE t.id = ?'
);
$stmt->execute([$id]);
$tx = $stmt->fetch();

if (!$tx) {
    SessionGuard::flashMessage('error', 'Transaction not found.');
    header('Location: index.php'); exit;
}

// ── Now safe to include admin header (outputs HTML) ──────────────────
$pageTitle  = 'Verify Payment';
require_once __DIR__ . '/../includes/admin_header.php';

$csrfToken  = SessionGuard::generateCsrfToken();
$hasReceipt = !empty($tx['receipt_image']);
$expected   = (float)$tx['downpayment_amount'];
$svcPrice   = (float)$tx['service_price'];
$isPending  = ($tx['status'] === 'Pending Verification');
?>

<style>
.vp-wrap { max-width:1100px; }
.vp-card { background:#fff; border-radius:14px; box-shadow:0 4px 24px rgba(107,45,139,.10); overflow:hidden; margin-bottom:20px; }
.vp-card-header { background:linear-gradient(135deg,#6B2D8B,#9b4dca); color:#fff; padding:14px 20px; font-size:.82rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; }
.vp-card-body { padding:20px; }
.vp-row { display:flex; justify-content:space-between; align-items:baseline; padding:7px 0; border-bottom:1px solid #f0eaf7; font-size:.875rem; }
.vp-row:last-child { border-bottom:none; }
.vp-row .label { color:#666; }
.vp-row .value { font-weight:600; color:#1e1e1e; text-align:right; max-width:60%; }
.vp-amount-box { background:#f8f4fb; border:2px solid #6B2D8B; border-radius:10px; padding:16px 18px; margin-bottom:16px; }
.vp-amount-box label { font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:.8px; color:#6B2D8B; display:block; margin-bottom:8px; }
.vp-amount-input-wrap { position:relative; }
.vp-amount-input-wrap .prefix { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:1.1rem; font-weight:700; color:#6B2D8B; }
.vp-amount-input { width:100%; padding:11px 12px 11px 32px; font-size:1.3rem; font-weight:800; border:2px solid #d5c7e8; border-radius:8px; color:#1e1e1e; background:#fff; transition:border-color .15s; }
.vp-amount-input:focus { border-color:#6B2D8B; outline:none; box-shadow:0 0 0 3px rgba(107,45,139,.12); }
.vp-summary { border-radius:10px; padding:14px 16px; margin-bottom:16px; font-size:.875rem; }
.vp-summary.status-downpayment { background:#e6f4ea; border:1.5px solid #10b981; }
.vp-summary.status-full        { background:#e0f2fe; border:1.5px solid #0284c7; }
.vp-summary.status-partial     { background:#fff8e1; border:1.5px solid #f59e0b; }
.vp-summary.status-none        { background:#f5f5f5; border:1.5px solid #ddd; }
.vp-summary-row { display:flex; justify-content:space-between; padding:4px 0; }
.vp-summary-row .s-label { color:#555; }
.vp-summary-row .s-value { font-weight:700; }
.vp-status-badge { display:inline-block; padding:4px 12px; border-radius:50px; font-size:.76rem; font-weight:800; letter-spacing:.3px; }
.badge-dp { background:#10b981; color:#fff; }
.badge-fp { background:#0284c7; color:#fff; }
.badge-pp { background:#f59e0b; color:#fff; }
.badge-na { background:#aaa;    color:#fff; }
.btn-verify    { background:#10b981; border-color:#10b981; color:#fff; font-weight:700; padding:11px; }
.btn-verify:hover:not(:disabled) { background:#059669; border-color:#059669; color:#fff; }
.btn-verify:disabled { background:#a8e0cc; border-color:#a8e0cc; cursor:not-allowed; }
.btn-reject-tx { background:#ef4444; border-color:#ef4444; color:#fff; font-weight:700; padding:11px; }
.btn-reject-tx:hover { background:#dc2626; border-color:#dc2626; color:#fff; }
.vp-receipt-img { width:100%; border-radius:10px; object-fit:contain; max-height:500px; cursor:zoom-in; transition:transform .15s; }
.vp-receipt-img:hover { transform:scale(1.01); }
.vp-no-receipt { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:200px; color:#bbb; }
.vp-warn { background:#fff3cd; border:1.5px solid #f59e0b; border-radius:8px; padding:10px 14px; font-size:.82rem; color:#856404; }
</style>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div>
    <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
      <i class="bi bi-shield-check me-2"></i>Verify GCash Payment
    </h3>
    <p class="text-muted mb-0 small">Transaction #<?= $id ?> — review the receipt and enter the actual amount received</p>
  </div>
</div>

<div class="row g-4 vp-wrap">

  <!-- LEFT: Details + Amount Entry -->
  <div class="col-lg-5">

    <div class="vp-card">
      <div class="vp-card-header"><i class="bi bi-person-circle me-2"></i>Client & Booking</div>
      <div class="vp-card-body">
        <div class="vp-row"><span class="label">Client</span><span class="value"><?= htmlspecialchars($tx['client_name']) ?></span></div>
        <div class="vp-row"><span class="label">Email</span><span class="value"><?= htmlspecialchars($tx['client_email']) ?></span></div>
        <div class="vp-row"><span class="label">Phone</span><span class="value"><?= htmlspecialchars($tx['client_phone']) ?></span></div>
        <div class="vp-row"><span class="label">Booking #</span><span class="value">#<?= $tx['booking_id'] ?></span></div>
        <div class="vp-row"><span class="label">Service</span><span class="value"><?= htmlspecialchars($tx['service_name']) ?></span></div>
        <div class="vp-row"><span class="label">Stylist</span><span class="value"><?= htmlspecialchars($tx['stylist_name'] ?? '—') ?></span></div>
        <?php if ($tx['slot_date']): ?>
        <div class="vp-row"><span class="label">Appointment</span>
          <span class="value"><?= date('M j, Y', strtotime($tx['slot_date'])) ?> <?= date('g:i A', strtotime($tx['start_time'])) ?></span>
        </div>
        <?php endif; ?>
        <div class="vp-row"><span class="label">Submitted</span><span class="value"><?= date('M j, Y g:i A', strtotime($tx['submitted_at'])) ?></span></div>
        <div class="vp-row"><span class="label">Status</span>
          <span class="value"><span class="badge bg-warning text-dark"><?= htmlspecialchars($tx['status']) ?></span></span>
        </div>
      </div>
    </div>

    <div class="vp-card">
      <div class="vp-card-header"><i class="bi bi-cash-coin me-2"></i>Payment Details</div>
      <div class="vp-card-body">
        <div class="vp-row"><span class="label">Service Price</span><span class="value">₱<?= number_format($svcPrice, 2) ?></span></div>
        <div class="vp-row"><span class="label">Expected Downpayment</span>
          <span class="value" style="color:#6B2D8B">₱<?= number_format($expected, 2) ?></span>
        </div>
      </div>
    </div>

    <?php if ($isPending): ?>
    <div class="vp-card">
      <div class="vp-card-header"><i class="bi bi-pencil-square me-2"></i>Enter Amount Received</div>
      <div class="vp-card-body">

        <?php if (!$hasReceipt): ?>
        <div class="vp-warn mb-3">
          <i class="bi bi-exclamation-triangle me-1"></i>
          <strong>No receipt uploaded.</strong> Cannot verify without a payment screenshot.
        </div>
        <?php endif; ?>

        <form method="post" id="verifyForm">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="txn_id"    value="<?= $id ?>">

          <div class="vp-amount-box">
            <label for="amount_received">Actual Amount Received (₱)</label>
            <div class="vp-amount-input-wrap">
              <span class="prefix">₱</span>
              <input type="number" id="amount_received" name="amount_received"
                     class="vp-amount-input" min="0.01" step="0.01"
                     placeholder="0.00" oninput="updateSummary(this.value)" required>
            </div>
          </div>

          <div id="paymentSummary" class="vp-summary status-none">
            <div class="vp-summary-row"><span class="s-label">Expected Downpayment</span><span class="s-value">₱<?= number_format($expected, 2) ?></span></div>
            <div class="vp-summary-row"><span class="s-label">Amount Received</span><span class="s-value" id="sumReceived">—</span></div>
            <div class="vp-summary-row"><span class="s-label">Remaining Balance</span><span class="s-value" id="sumBalance">—</span></div>
            <div class="vp-summary-row" style="margin-top:6px">
              <span class="s-label">Payment Status</span>
              <span id="sumStatus"><span class="vp-status-badge badge-na">Enter amount above</span></span>
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" name="action" value="verify"
                    class="btn btn-verify w-100" id="verifyBtn"
                    <?= !$hasReceipt ? 'disabled' : '' ?>>
              <i class="bi bi-check-circle me-2"></i>Verify & Approve Payment
            </button>
            <button type="button"
                    class="btn btn-reject-tx w-100"
                    onclick="confirmRejectPayment()">
              <i class="bi bi-x-circle me-2"></i>Reject Payment
            </button>
          </div>
        </form>
      </div>
    </div>

    <?php else: ?>
    <div class="vp-card">
      <div class="vp-card-header"><i class="bi bi-info-circle me-2"></i>Verification Result</div>
      <div class="vp-card-body">
        <?php
          $cls = $tx['status'] === 'Downpayment Paid' ? 'badge-dp'
               : ($tx['status'] === 'Fully Paid'      ? 'badge-fp'
               : ($tx['status'] === 'Partial Payment' ? 'badge-pp'
               : 'badge-na'));
        ?>
        <div class="vp-row"><span class="label">Final Status</span>
          <span class="value"><span class="vp-status-badge <?= $cls ?>"><?= htmlspecialchars($tx['status']) ?></span></span>
        </div>
        <?php if (!empty($tx['amount_received'])): ?>
        <div class="vp-row"><span class="label">Amount Received</span><span class="value">₱<?= number_format($tx['amount_received'], 2) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($tx['verified_at'])): ?>
        <div class="vp-row"><span class="label">Verified At</span><span class="value"><?= date('M j, Y g:i A', strtotime($tx['verified_at'])) ?></span></div>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary w-100 mt-3">← Back to Transactions</a>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /left -->

  <!-- RIGHT: Receipt -->
  <div class="col-lg-7">
    <div class="vp-card" style="height:100%">
      <div class="vp-card-header"><i class="bi bi-receipt me-2"></i>Uploaded Payment Receipt</div>
      <div class="vp-card-body text-center">
        <?php if ($hasReceipt): ?>
          <img src="receipt_proxy.php?id=<?= $id ?>" alt="GCash Receipt"
               class="vp-receipt-img" onclick="window.open(this.src,'_blank')"
               title="Click to open full size in new tab">
          <p class="text-muted small mt-2">
            <i class="bi bi-zoom-in me-1"></i>Click image to open full size
          </p>
        <?php else: ?>
          <div class="vp-no-receipt">
            <i class="bi bi-image" style="font-size:5rem"></i>
            <p class="mt-3 fw-semibold">No receipt uploaded by client.</p>
            <p class="small text-muted">The client must upload a GCash screenshot before payment can be verified.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<script>
const EXPECTED  = <?= $expected ?>;
const SVC_PRICE = <?= $svcPrice ?>;

function formatMoney(n) {
    return '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function updateSummary(val) {
    const received  = parseFloat(val);
    const sumDiv    = document.getElementById('paymentSummary');
    const btnVerify = document.getElementById('verifyBtn');

    if (isNaN(received) || received <= 0) {
        document.getElementById('sumReceived').textContent = '—';
        document.getElementById('sumBalance').textContent  = '—';
        document.getElementById('sumStatus').innerHTML     = '<span class="vp-status-badge badge-na">Enter amount above</span>';
        sumDiv.className = 'vp-summary status-none';
        if (btnVerify) btnVerify.disabled = true;
        return;
    }

    document.getElementById('sumReceived').textContent = formatMoney(received);

    let statusLabel, statusClass, summaryClass, remaining;

    if (received >= SVC_PRICE) {
        statusLabel  = 'Fully Paid';
        statusClass  = 'badge-fp';
        summaryClass = 'status-full';
        remaining    = 0;
    } else if (received >= EXPECTED) {
        statusLabel  = 'Downpayment Paid';
        statusClass  = 'badge-dp';
        summaryClass = 'status-downpayment';
        remaining    = SVC_PRICE - received;
    } else {
        statusLabel  = 'Partial Payment';
        statusClass  = 'badge-pp';
        summaryClass = 'status-partial';
        remaining    = SVC_PRICE - received;
    }

    document.getElementById('sumBalance').textContent = remaining > 0 ? formatMoney(remaining) : '₱0.00 (Fully Paid)';
    document.getElementById('sumStatus').innerHTML    = '<span class="vp-status-badge ' + statusClass + '">' + statusLabel + '</span>';
    sumDiv.className = 'vp-summary ' + summaryClass;
    if (btnVerify) btnVerify.disabled = false;
}
</script>

<!-- Reject Payment Confirmation Modal -->
<div class="modal fade" id="rejectPaymentModal" tabindex="-1" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden">
      <div style="background:linear-gradient(135deg,#dc2626,#ef4444);padding:28px 24px 20px;text-align:center">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.2);
                    display:flex;align-items:center;justify-content:center;
                    margin:0 auto 12px;font-size:2rem;color:white">
          <i class="bi bi-x-circle-fill"></i>
        </div>
        <h5 style="color:white;font-weight:800;margin:0;font-size:1.15rem">Reject Payment</h5>
      </div>
      <div style="padding:24px 28px;text-align:center">
        <p style="color:#374151;font-size:.95rem;margin-bottom:14px">
          Are you sure you want to <strong>reject this payment</strong>?
        </p>
        <p style="color:#6b7280;font-size:.82rem;margin-bottom:24px">
          <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
          The client will be notified to resubmit their payment receipt.
        </p>
        <div class="d-flex gap-3">
          <button type="button" class="btn btn-outline-secondary flex-fill fw-semibold"
                  data-bs-dismiss="modal" style="border-radius:10px;padding:11px">
            <i class="bi bi-x-lg me-1"></i>Cancel
          </button>
          <button type="button" id="rejectPaymentConfirmBtn"
                  class="btn flex-fill fw-bold text-white"
                  style="background:linear-gradient(135deg,#dc2626,#ef4444);border:none;border-radius:10px;padding:11px">
            <i class="bi bi-x-circle me-1"></i>Yes, Reject
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function confirmRejectPayment() {
  new bootstrap.Modal(document.getElementById('rejectPaymentModal')).show();
}
document.getElementById('rejectPaymentConfirmBtn').addEventListener('click', function() {
  var btn = document.querySelector('[name="action"][value="reject"]') ||
            document.createElement('input');
  if (!btn.form) {
    btn.type  = 'hidden';
    btn.name  = 'action';
    btn.value = 'reject';
    document.querySelector('form').appendChild(btn);
  }
  btn.closest('form') ? btn.closest('form').submit() : document.querySelector('form').submit();
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
