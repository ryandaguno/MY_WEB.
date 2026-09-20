<?php
$pageTitle = 'Ratings & Feedback';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/db.php';
$db = getDB();

// Avg per stylist
$stylistRatings = $db->query(
    'SELECT st.name, AVG(r.stars) AS avg_stars, COUNT(*) AS total_reviews
     FROM ratings r JOIN stylists st ON r.stylist_id=st.id
     GROUP BY st.id ORDER BY avg_stars DESC'
)->fetchAll();

// Avg per service
$serviceRatings = $db->query(
    'SELECT sv.name, AVG(r.stars) AS avg_stars, COUNT(*) AS total_reviews
     FROM ratings r JOIN services sv ON r.service_id=sv.id
     GROUP BY sv.id ORDER BY avg_stars DESC'
)->fetchAll();

// Recent comments
$comments = $db->query(
    'SELECT r.stars, r.comment, r.submitted_at,
            c.username AS client_name, st.name AS stylist_name, sv.name AS service_name,
            sc.slot_date
     FROM ratings r
     JOIN clients c  ON r.client_id=c.id
     JOIN stylists st ON r.stylist_id=st.id
     JOIN services sv ON r.service_id=sv.id
     JOIN bookings b  ON r.booking_id=b.id
     JOIN schedules sc ON b.schedule_id=sc.id
     ORDER BY r.submitted_at DESC LIMIT 50'
)->fetchAll();

function stars(float $avg): string {
    $full  = floor($avg);
    $html  = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<i class="bi bi-star' . ($i <= $full ? '-fill' : '') . '" style="color:#f59e0b"></i>';
    }
    return $html . ' <span class="ms-1 fw-bold">' . number_format($avg,1) . '</span>';
}
?>
<h3 class="fw-bold mb-4" style="color:var(--sa-purple)">Ratings &amp; Feedback</h3>

<div class="row g-4 mb-4">
  <!-- Per Stylist -->
  <div class="col-md-6">
    <div class="card p-3 h-100">
      <h6 class="fw-bold mb-3" style="color:var(--sa-teal)"><i class="bi bi-people me-2"></i>Stylist Ratings</h6>
      <?php if (empty($stylistRatings)): ?>
        <p class="text-muted">No ratings yet.</p>
      <?php else: ?>
      <table class="table table-sm">
        <thead><tr><th>Stylist</th><th>Avg Rating</th><th>Reviews</th></tr></thead>
        <tbody>
          <?php foreach ($stylistRatings as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= stars((float)$r['avg_stars']) ?></td>
            <td><span class="badge bg-secondary"><?= $r['total_reviews'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Per Service -->
  <div class="col-md-6">
    <div class="card p-3 h-100">
      <h6 class="fw-bold mb-3" style="color:var(--sa-teal)"><i class="bi bi-scissors me-2"></i>Service Ratings</h6>
      <?php if (empty($serviceRatings)): ?>
        <p class="text-muted">No ratings yet.</p>
      <?php else: ?>
      <table class="table table-sm">
        <thead><tr><th>Service</th><th>Avg Rating</th><th>Reviews</th></tr></thead>
        <tbody>
          <?php foreach ($serviceRatings as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= stars((float)$r['avg_stars']) ?></td>
            <td><span class="badge bg-secondary"><?= $r['total_reviews'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Comments -->
<div class="card p-3">
  <h6 class="fw-bold mb-3" style="color:var(--sa-teal)"><i class="bi bi-chat-quote me-2"></i>Recent Feedback</h6>
  <?php if (empty($comments)): ?>
    <p class="text-muted">No comments yet.</p>
  <?php else: ?>
    <?php foreach ($comments as $c): ?>
    <div class="border rounded p-3 mb-2">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <?php for ($i=1;$i<=5;$i++): ?>
            <i class="bi bi-star<?= $i<=$c['stars']?'-fill':'' ?>" style="color:#f59e0b"></i>
          <?php endfor; ?>
          <span class="ms-2 small text-muted"><?= htmlspecialchars($c['client_name']) ?> &bull; <?= htmlspecialchars($c['service_name']) ?> &bull; <?= htmlspecialchars($c['stylist_name']) ?></span>
        </div>
        <small class="text-muted"><?= date('M j, Y', strtotime($c['submitted_at'])) ?></small>
      </div>
      <?php if ($c['comment']): ?>
        <p class="mb-0 mt-1 fst-italic text-muted">"<?= htmlspecialchars($c['comment']) ?>"</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
