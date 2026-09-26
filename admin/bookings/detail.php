<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../modules/BookingManager.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/bookings/index.php'); exit; }

$bm      = new BookingManager();
$booking = $bm->getBooking($id);

if (!$booking) {
    SessionGuard::flashMessage('error', 'Booking not found.');
    header('Location: ' . BASE_URL . '/admin/bookings/index.php'); exit;
}

// Fetch transaction
$db      = getDB();
$txStmt  = $db->prepare(
    'SELECT * FROM transactions WHERE booking_id = ? ORDER BY submitted_at DESC LIMIT 1'
);
$txStmt->execute([$id]);
$tx = $txStmt->fetch();

$pageTitle = 'Booking #' . $id . ' Details';
require_once __DIR__ . '/../includes/admin_header.php';

$statusColors = [
    'Pending'   => ['bg' => '#f59e0b', 'text' => '#fff'],
    'Accepted'  => ['bg' => '#10b981', 'text' => '#fff'],
    'Cancelled' => ['bg' => '#ef4444', 'text' => '#fff'],
    'Completed' => ['bg' => '#3b82f6', 'text' => '#fff'],
];
$sc = $statusColors[$booking['status']] ?? ['bg' => '#6c757d', 'text' => '#fff'];
?>

<div class="mb-3 d-flex align-items-center gap-3">
  <a href="<?= BASE_URL ?>/admin/bookings/index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Bookings
  </a>
  <h3 class="fw-bold mb-0" style="color:var(--sa-purple)">
    <i class="bi bi-calendar-check me-2"></i>Booking #<?= $id ?> Details
  </h3>
</div>

<div class="row g-4" style="max-width:960px">

  <!-- LEFT: Booking Info -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold bg-white border-bottom">
        <i class="bi bi-info-circle me-2" style="color:var(--sa-purple)"></i>Booking Information
      </div>
      <div class="card-body p-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="fw-bold fs-5" style="color:var(--sa-purple)">Booking #<?= $id ?></span>
          <span class="badge fs-6 px-3 py-2"
                style="background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>">
            <?= $booking['status'] ?>
          </span>
        </div>

        <table class="table table-sm table-borderless mb-0" style="font-size:.88rem">
          <tr>
            <td class="text-muted fw-semibold" style="width:40%">Client</td>
            <td><?= htmlspecialchars($booking['client_name']) ?></td>
          </tr>
          <tr>
            <td class="text-muted fw-semibold">Phone</td>
            <td><?= htmlspecialchars($booking['client_phone']) ?></td>
          </tr>
          <tr>
            <td class="text-muted fw-semibold">Email</td>
            <td><?= htmlspecialchars($booking['client_email']) ?></td>
          </tr>
          <tr><td colspan="2"><hr class="my-2"></td></tr>
          <tr>
            <td class="text-muted fw-semibold">Service</td>
            <td><?= htmlspecialchars($booking['service_name']) ?></td>
          </tr>
          <tr>
            <td class="text-muted fw-semibold">Price</td>
            <td>₱<?= number_format($booking['price'], 2) ?></td>
          </tr>
          <tr>
            <td class="text-muted fw-semibold">Duration</td>
            <td><?= (int)$booking['duration_minutes'] ?> min</td>
          </tr>
          <tr><td colspan="2"><hr class="my-2"></td></tr>
          <tr>
            <td class="text-muted fw-semibold">Stylist</td>
            <td><?= htmlspecialchars($booking['stylist_name']) ?></td>
          </tr>
          <tr><td colspan="2"><hr class="my-2"></td></tr>
          <tr>
            <td class="text-muted fw-semibold">Date</td>
            <td><?= date('F j, Y', strtotime($booking['slot_date'])) ?></td>
          </tr>
          <tr>
            <td class="text-muted fw-semibold">Time</td>
            <td><?= date('g:i A', strtotime($booking['start_time'])) ?></td>
          </tr>
          <?php if (!empty($booking['notes'])): ?>
          <tr><td colspan="2"><hr class="my-2"></td></tr>
          <tr>
            <td class="text-muted fw-semibold">Notes</td>
            <td><?= htmlspecialchars($booking['notes']) ?></td>
          </tr>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>

  <!-- RIGHT: Payment Info -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header fw-semibold bg-white border-bottom">
        <i class="bi bi-cash-coin me-2" style="color:var(--sa-teal)"></i>Payment Information
      </div>
      <div class="card-body p-4">
        <table class="table table-sm table-borderless mb-0" style="font-size:.88rem">
          <tr>
            <td class="text-muted fw-semibold" style="width:45%">Downpayment Due</td>
            <td class="fw-bold" style="color:var(--sa-purple)">
              ₱<?= number_format($booking['downpayment_amount'] ?? 0, 2) ?>
            </td>
          </tr>
          <?php if ($tx): ?>
          <tr>
            <td class="text-muted fw-semibold">Method</td>
            <td>
              <?php if ($tx['payment_method'] === 'GCash'): ?>
                <i class="bi bi-qr-code me-1 text-success"></i>GCash
              <?php else: ?>
                <i class="bi bi-paypal me-1" style="color:#003087"></i>PayPal
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td class="text-muted fw-semibold">Amount Submitted</td>
            <td>₱<?= number_format($tx['amount'], 2) ?></td>
          </tr>
          <tr>
            <td class="text-muted fw-semibold">Payment Status</td>
            <td>
              <?php
              $ps = $tx['status'];
              if (in_array($ps, ['Verified - Downpayment','Verified - Full','Paid'])) {
                  echo '<span class="badge bg-success">' . htmlspecialchars($ps) . '</span>';
              } elseif ($ps === 'Pending Verification') {
                  echo '<span class="badge bg-warning text-dark">Pending Verification</span>';
              } elseif ($ps === 'Rejected') {
                  echo '<span class="badge bg-danger">Rejected</span>';
              } else {
                  echo '<span class="badge bg-secondary">' . htmlspecialchars($ps) . '</span>';
              }
              ?>
            </td>
          </tr>
          <tr>
            <td class="text-muted fw-semibold">Submitted</td>
            <td><?= date('M j, Y g:i A', strtotime($tx['submitted_at'])) ?></td>
          </tr>
          <?php if (!empty($tx['verified_at'])): ?>
          <tr>
            <td class="text-muted fw-semibold">Verified At</td>
            <td><?= date('M j, Y g:i A', strtotime($tx['verified_at'])) ?></td>
          </tr>
          <?php endif; ?>
          <?php else: ?>
          <tr>
            <td class="text-muted fw-semibold">Payment Status</td>
            <td><span class="badge bg-secondary">No Payment Submitted</span></td>
          </tr>
          <?php endif; ?>
        </table>

        <?php if ($tx && !empty($tx['receipt_image']) || ($tx && !empty($tx['receipt_data']))): ?>
        <div class="mt-3">
          <div class="fw-semibold small text-muted mb-2">Payment Receipt:</div>
          <img src="<?= BASE_URL ?>/admin/transactions/receipt_proxy.php?id=<?= $tx['id'] ?>"
               alt="Receipt"
               style="max-width:100%;border-radius:10px;border:2px solid #e0d5ea;cursor:zoom-in"
               onclick="window.open(this.src)"
               title="Click to zoom">
        </div>
        <?php endif; ?>

        <?php if ($tx && $tx['status'] === 'Pending Verification'): ?>
        <div class="mt-3 d-flex gap-2">
          <a href="<?= BASE_URL ?>/admin/transactions/verify.php?id=<?= $tx['id'] ?>"
             class="btn btn-success btn-sm fw-bold">
            <i class="bi bi-check-circle me-1"></i>Verify Payment
          </a>
          <a href="<?= BASE_URL ?>/admin/transactions/reject.php?id=<?= $tx['id'] ?>"
             class="btn btn-danger btn-sm fw-bold"
             onclick="return confirm('Reject this payment?')">
            <i class="bi bi-x-circle me-1"></i>Reject Payment
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
