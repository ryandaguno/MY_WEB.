<?php
// ── Handle POST (save amount + accept/cancel) BEFORE any output ──────
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/BookingManager.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $action    = $_POST['action'] ?? '';
    $db        = getDB();

    // Save amount received if provided
    if (!empty($_POST['amount_received']) && !empty($_POST['txn_id'])) {
        $ph             = new PaymentHandler();
        $amountReceived = (float)str_replace(',', '', $_POST['amount_received']);
        $ph->verifyGCash((int)$_POST['txn_id'], (int)$_SESSION['admin_id'], $amountReceived);
    }

    // Accept or Cancel booking
    $bm = new BookingManager();
    if ($action === 'accept') {
        $result = $bm->acceptBooking($bookingId);
        SessionGuard::flashMessage(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Booking #' . $bookingId . ' accepted. Client notified.' : $result['message']
        );
    } elseif ($action === 'cancel') {
        $result = $bm->cancelBooking($bookingId, 'admin');
        SessionGuard::flashMessage(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Booking #' . $bookingId . ' cancelled. Client notified.' : $result['message']
        );
    }

    header('Location: ' . BASE_URL . '/admin/bookings/index.php'); exit;
}

// ── Page output ──────────────────────────────────────────────────────
$pageTitle = 'Booking Management';
require_once __DIR__ . '/../includes/admin_header.php';
$db = getDB();

$query = 'SELECT b.id, c.username AS client, sv.name AS service, sv.price AS service_price,
                 st.name AS stylist, sc.slot_date, sc.start_time,
                 b.status, b.downpayment_amount,
                 t.id AS txn_id, t.status AS payment_status, t.payment_method,
                 t.amount AS txn_amount, t.receipt_image
          FROM bookings b
          JOIN clients   c  ON b.client_id   = c.id
          JOIN services  sv ON b.service_id  = sv.id
          JOIN stylists  st ON b.stylist_id  = st.id
          JOIN schedules sc ON b.schedule_id = sc.id
          LEFT JOIN transactions t ON t.booking_id = b.id
          WHERE b.status = ?
          ORDER BY sc.slot_date ASC, sc.start_time ASC';

$pending   = $db->prepare($query); $pending->execute(['Pending']);    $pending   = $pending->fetchAll();
$accepted  = $db->prepare($query); $accepted->execute(['Accepted']);  $accepted  = $accepted->fetchAll();
$cancelled = $db->prepare($query); $cancelled->execute(['Cancelled']); $cancelled = $cancelled->fetchAll();

$csrfToken = SessionGuard::generateCsrfToken();
?>

<style>
.bk-thumb {
    width:46px; height:46px; object-fit:cover;
    border-radius:6px; border:2px solid #e0d5ea;
    cursor:zoom-in; transition:transform .15s, box-shadow .15s; vertical-align:middle;
}
.bk-thumb:hover { transform:scale(1.1); box-shadow:0 4px 12px rgba(107,45,139,.3); }

/* Status badges */
.badge-pv { background:#f59e0b; color:#fff; }
.badge-dp { background:#10b981; color:#fff; }
.badge-fp { background:#0284c7; color:#fff; }
.badge-pp { background:#f59e0b; color:#fff; }
.badge-pd { background:#10b981; color:#fff; }
.badge-rj { background:#ef4444; color:#fff; }
.badge-np { background:#6c757d; color:#fff; }

/* Amount input in modal */
.amt-input-wrap { position:relative; }
.amt-input-wrap .prefix { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-weight:700; color:#6B2D8B; }
.amt-input { padding-left:28px; border:2px solid #d5c7e8; border-radius:8px; font-size:1.1rem; font-weight:700; }
.amt-input:focus { border-color:#6B2D8B; box-shadow:0 0 0 3px rgba(107,45,139,.12); }

/* Detail modal header */
#bookingDetailModal .modal-header { background:linear-gradient(135deg,#6B2D8B,#9b4dca); }
#bookingDetailModal .modal-dialog  { max-width:780px; }

/* Receipt modal */
#bkReceiptModal .modal-header { background:linear-gradient(135deg,#6B2D8B,#9b4dca); }
</style>

<h3 class="fw-bold mb-4" style="color:var(--sa-purple)">
  <i class="bi bi-calendar3 me-2"></i>Booking Management
</h3>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#newBookings">New Bookings <span class="badge bg-warning text-dark"><?= count($pending) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#acceptedTab">Accepted <span class="badge bg-success"><?= count($accepted) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#cancelledTab">Cancelled <span class="badge bg-danger"><?= count($cancelled) ?></span></a></li>
</ul>

<div class="tab-content">
<?php
$tabs = [
    'newBookings'  => [$pending,   'pending'],
    'acceptedTab'  => [$accepted,  'accepted'],
    'cancelledTab' => [$cancelled, 'cancelled'],
];
foreach ($tabs as $tabId => [$list, $type]):
?>
<div class="tab-pane fade <?= $tabId === 'newBookings' ? 'show active' : '' ?>" id="<?= $tabId ?>">
  <?php if (empty($list)): ?>
    <p class="text-muted">No <?= $type ?> bookings.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover table-sa">
      <thead>
        <tr>
          <th>#</th><th>Client</th><th>Service</th><th>Stylist</th>
          <th>Date &amp; Time</th><th>Downpayment</th><th>Paid</th>
          <th>Payment</th><th>Receipt</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($list as $b):
        $date      = date('M j, Y', strtotime($b['slot_date']));
        $time      = date('g:i A',  strtotime($b['start_time']));
        $ps        = $b['payment_status'] ?? '';
        $badgeCls  = match(true) {
            $ps === 'Pending Verification' => 'badge-pv',
            $ps === 'Downpayment Paid'     => 'badge-dp',
            $ps === 'Fully Paid'           => 'badge-fp',
            $ps === 'Partial Payment'      => 'badge-pp',
            $ps === 'Paid'                 => 'badge-pd',
            $ps === 'Rejected'             => 'badge-rj',
            default                        => 'badge-np',
        };
        $psLabel    = $ps ?: 'No Payment';
        $paidAmt    = !empty($b['txn_amount'])
                        ? '₱' . number_format($b['txn_amount'], 2)
                        : '—';
        $hasReceipt = !empty($b['receipt_image']) && !empty($b['txn_id']);
        $clientEsc  = htmlspecialchars($b['client'], ENT_QUOTES);

        // Build JSON for modal
        $modalData = json_encode([
            'bookingId'   => $b['id'],
            'client'      => $b['client'],
            'service'     => $b['service'],
            'servicePrice'=> $b['service_price'],
            'stylist'     => $b['stylist'],
            'date'        => $date,
            'time'        => $time,
            'downpayment' => $b['downpayment_amount'],
            'paidAmt'     => $b['txn_amount'] ?? '',
            'txnId'       => $b['txn_id'] ?? '',
            'payStatus'   => $psLabel,
            'hasReceipt'  => $hasReceipt,
            'receiptSrc'  => $hasReceipt ? '../transactions/receipt_proxy.php?id=' . $b['txn_id'] : '',
            'type'        => $type,
        ]);
      ?>
      <tr>
        <td class="fw-bold text-muted">#<?= $b['id'] ?></td>
        <td><?= htmlspecialchars($b['client']) ?></td>
        <td><?= htmlspecialchars($b['service']) ?></td>
        <td><?= htmlspecialchars($b['stylist']) ?></td>
        <td><?= $date ?><br><small class="text-muted"><?= $time ?></small></td>
        <td>₱<?= number_format($b['downpayment_amount'], 2) ?></td>
        <td class="fw-semibold"><?= $paidAmt ?></td>
        <td><span class="badge <?= $badgeCls ?> small"><?= htmlspecialchars($psLabel) ?></span></td>
        <td>
          <?php if ($hasReceipt): ?>
            <img src="../transactions/receipt_proxy.php?id=<?= $b['txn_id'] ?>"
                 alt="Receipt" class="bk-thumb"
                 onclick="openReceiptOnly('../transactions/receipt_proxy.php?id=<?= $b['txn_id'] ?>', '<?= $clientEsc ?>')"
                 title="Click to view receipt">
          <?php else: ?>
            <span class="text-muted small">—</span>
          <?php endif; ?>
        </td>
        <td class="text-center">
          <?php if ($type === 'pending'): ?>
            <div class="d-flex justify-content-center gap-1">
              <button class="btn btn-sm btn-success"
                      onclick='openBookingDetail(<?= htmlspecialchars($modalData, ENT_QUOTES) ?>)'>
                <i class="bi bi-check-circle me-1"></i>Accept
              </button>
              <button class="btn btn-sm btn-outline-danger"
                      onclick="confirmAction('reject', <?= $b['id'] ?>)">Reject</button>
            </div>
          <?php elseif ($type === 'accepted'): ?>
            <div class="d-flex justify-content-center gap-1">
              <button class="btn btn-sm btn-danger"
                      onclick="confirmAction('cancel', <?= $b['id'] ?>)">Cancel</button>
              <a href="../../public/booking_detail.php?id=<?= $b['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
            </div>
          <?php else: ?>
            <div class="d-flex justify-content-center">
              <button class="btn btn-sm btn-outline-danger"
                      onclick="confirmAction('delete', <?= $b['id'] ?>)">Delete</button>
            </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>


<!-- ══════════════════════════════════════
     BOOKING DETAIL MODAL
     Review receipt, edit amount, accept/cancel
     ══════════════════════════════════════ -->
<div class="modal fade" id="bookingDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:780px">
    <div class="modal-content border-0 shadow">

      <div class="modal-header">
        <h5 class="modal-title text-white fw-bold">
          <i class="bi bi-calendar-check me-2"></i>Review Booking
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-4">
        <div class="row g-4">

          <!-- LEFT: Info + Amount Entry -->
          <div class="col-md-5">

            <!-- Booking info -->
            <div class="mb-3 p-3 rounded" style="background:#f8f4fb; border:1px solid #e0d5ea;">
              <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:.85rem">
                <span class="text-muted">Client</span><b id="md-client"></b>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:.85rem">
                <span class="text-muted">Service</span><b id="md-service"></b>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:.85rem">
                <span class="text-muted">Stylist</span><b id="md-stylist"></b>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:.85rem">
                <span class="text-muted">Date &amp; Time</span><b id="md-datetime"></b>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:.85rem">
                <span class="text-muted">Expected Downpayment</span>
                <b style="color:#6B2D8B" id="md-downpayment"></b>
              </div>
              <div class="d-flex justify-content-between py-1" style="font-size:.85rem">
                <span class="text-muted">Payment Status</span>
                <b id="md-paystatus"></b>
              </div>
            </div>

            <!-- Amount entry form -->
            <form method="post" id="bookingActionForm">
              <input type="hidden" name="csrf_token"  value="<?= $csrfToken ?>">
              <input type="hidden" name="booking_id"  id="md-booking-id">
              <input type="hidden" name="txn_id"      id="md-txn-id">

              <label class="form-label small fw-bold" style="color:#6B2D8B; text-transform:uppercase; letter-spacing:.6px;">
                Actual Amount Received (₱)
              </label>
              <div class="amt-input-wrap mb-3">
                <span class="prefix">₱</span>
                <input type="number" name="amount_received" id="md-amount"
                       class="form-control amt-input"
                       min="0.01" step="0.01" placeholder="0.00">
              </div>

              <div class="d-grid gap-2">
                <button type="submit" name="action" value="accept"
                        class="btn btn-success fw-bold">
                  <i class="bi bi-check-circle me-2"></i>Accept Booking
                </button>
                <button type="submit" name="action" value="cancel"
                        class="btn btn-danger fw-bold"
                        onclick="return confirm('Reject this booking?')">
                  <i class="bi bi-x-circle me-2"></i>Reject Booking
                </button>
              </div>
            </form>

          </div><!-- /left -->

          <!-- RIGHT: Receipt -->
          <div class="col-md-7 text-center">
            <p class="small fw-bold mb-2" style="color:#6B2D8B; text-transform:uppercase; letter-spacing:.6px;">
              <i class="bi bi-receipt me-1"></i>Payment Receipt
            </p>
            <div id="md-receipt-wrap">
              <img id="md-receipt-img" src="" alt="Receipt"
                   class="img-fluid rounded shadow-sm"
                   style="max-height:380px; object-fit:contain; width:100%; cursor:zoom-in;"
                   onclick="window.open(this.src,'_blank')"
                   title="Click to open full size">
              <p class="text-muted small mt-2">
                <i class="bi bi-zoom-in me-1"></i>Click to open full size
              </p>
            </div>
            <div id="md-no-receipt" style="display:none" class="py-4 text-muted">
              <i class="bi bi-image" style="font-size:3.5rem"></i>
              <p class="mt-2">No receipt uploaded by client.</p>
            </div>
          </div>

        </div>
      </div><!-- /modal-body -->

    </div>
  </div>
</div>


<!-- ── Receipt-only modal (thumbnail click) ── -->
<div class="modal fade" id="bkReceiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:600px">
    <div class="modal-content border-0 shadow">
      <div class="modal-header" style="background:linear-gradient(135deg,#6B2D8B,#9b4dca);">
        <h5 class="modal-title text-white fw-bold">
          <i class="bi bi-receipt me-2"></i>GCash Receipt
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <img id="bkReceiptImg" src="" alt="Receipt"
             class="img-fluid rounded shadow-sm"
             style="max-height:500px; object-fit:contain; width:100%">
      </div>
      <div class="modal-footer">
        <a id="bkReceiptNewTab" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-box-arrow-up-right me-1"></i>Open in New Tab
        </a>
        <button type="button" class="btn btn-sm btn-sa-primary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<script>
function openBookingDetail(data) {
    document.getElementById('md-client').textContent      = data.client;
    document.getElementById('md-service').textContent     = data.service;
    document.getElementById('md-stylist').textContent     = data.stylist;
    document.getElementById('md-datetime').textContent    = data.date + ' ' + data.time;
    document.getElementById('md-downpayment').textContent = '₱' + parseFloat(data.downpayment).toFixed(2);
    document.getElementById('md-paystatus').textContent   = data.payStatus;
    document.getElementById('md-booking-id').value        = data.bookingId;
    document.getElementById('md-txn-id').value            = data.txnId || '';
    document.getElementById('md-amount').value            = data.paidAmt || '';

    if (data.hasReceipt) {
        document.getElementById('md-receipt-img').src    = data.receiptSrc;
        document.getElementById('md-receipt-wrap').style.display = '';
        document.getElementById('md-no-receipt').style.display   = 'none';
    } else {
        document.getElementById('md-receipt-wrap').style.display = 'none';
        document.getElementById('md-no-receipt').style.display   = '';
    }

    new bootstrap.Modal(document.getElementById('bookingDetailModal')).show();
}

function openReceiptOnly(src, clientName) {
    document.getElementById('bkReceiptImg').src      = src;
    document.getElementById('bkReceiptNewTab').href  = src;
    new bootstrap.Modal(document.getElementById('bkReceiptModal')).show();
}
</script>

<!-- ── Custom Confirm Modal ── -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
    <div class="modal-content border-0 shadow">
      <div class="modal-body text-center py-4 px-4">
        <div id="confirmIcon" class="mb-3" style="font-size:2.8rem"></div>
        <h5 class="fw-bold mb-2" id="confirmTitle"></h5>
        <p class="text-muted mb-4" id="confirmMessage"></p>
        <div class="d-flex justify-content-center gap-3">
          <button type="button" class="btn btn-outline-secondary px-4"
                  data-bs-dismiss="modal">No, go back</button>
          <a id="confirmOkBtn" href="#" class="btn px-4 fw-bold" id="confirmOkBtn"></a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function confirmAction(type, id) {
    const cfg = {
        reject: {
            icon:    '🚫',
            title:   'Reject this booking?',
            message: 'The booking will be moved to Cancelled. Are you sure?',
            btnCls:  'btn-danger',
            btnText: 'Yes, reject it',
            url:     'delete.php?id=' + id,
        },
        cancel: {
            icon:    '⚠️',
            title:   'Cancel this booking?',
            message: 'The client will be notified. This cannot be undone.',
            btnCls:  'btn-warning text-dark',
            btnText: 'Yes, cancel it',
            url:     'cancel.php?id=' + id,
        },
        delete: {
            icon:    '🗑️',
            title:   'Delete this booking?',
            message: 'This will permanently remove the record.',
            btnCls:  'btn-danger',
            btnText: 'Yes, delete it',
            url:     'delete.php?id=' + id,
        },
    };
    const c = cfg[type];
    document.getElementById('confirmIcon').textContent    = c.icon;
    document.getElementById('confirmTitle').textContent   = c.title;
    document.getElementById('confirmMessage').textContent = c.message;
    const btn = document.getElementById('confirmOkBtn');
    btn.className   = 'btn px-4 fw-bold ' + c.btnCls;
    btn.textContent = c.btnText;
    btn.href        = c.url;
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
