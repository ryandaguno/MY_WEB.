<?php
/* Step 3 – Select Date & Time */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
SessionGuard::requireClient();

if (empty($_SESSION['booking']['service_id'])) {
    header('Location: ' . BASE_URL . '/public/booking/step1_service.php'); exit;
}
if (empty($_SESSION['booking']['stylist_id'])) {
    header('Location: ' . BASE_URL . '/public/booking/step2_stylist.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) die('Session expired.');
    if (!empty($_POST['schedule_id']) && !empty($_POST['slot_date'])) {
        $_SESSION['booking']['schedule_id'] = (int)$_POST['schedule_id'];
        $_SESSION['booking']['slot_date']   = $_POST['slot_date'];
        $_SESSION['booking']['slot_time']   = $_POST['slot_time'] ?? '';
        header('Location: ' . BASE_URL . '/public/booking/step4_details.php'); exit;
    }
}

$stylistId = $_SESSION['booking']['stylist_id'];
$serviceId = (int)$_SESSION['booking']['service_id'];
$selected  = $_SESSION['booking']['schedule_id'] ?? 0;
$csrfToken = SessionGuard::generateCsrfToken();
$today     = date('Y-m-d');
$maxDate   = date('Y-m-d', strtotime('+60 days'));
$pageTitle = 'Step 3: Select Date & Time';
require_once __DIR__ . '/../../includes/header.php';
<style>
.s3-page  { background:#e8eaed; min-height:calc(100vh - 56px); padding:32px 16px 60px; font-family:'Segoe UI',Arial,sans-serif; }
.s3-card  { background:#f5f5f5; border-radius:14px; max-width:820px; margin:0 auto; padding:28px 28px 36px; box-shadow:0 4px 24px rgba(0,0,0,.10); }
.s3-title { text-align:center; font-size:.92rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#111; margin-bottom:22px; }

/* Buttons */
.s3-btn-row  { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:26px; }
.s3-btn-back { display:block; padding:14px 10px; border-radius:10px; border:none; background:#d6d6d6; color:#222; font-size:.92rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; text-align:center; text-decoration:none; transition:background .15s; }
.s3-btn-back:hover { background:#c2c2c2; color:#222; }
.s3-btn-next { display:block; width:100%; padding:14px 10px; border-radius:10px; border:none; background:#2ecc40; color:#fff; font-size:.92rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; cursor:pointer; transition:background .15s; }
.s3-btn-next:hover:not(:disabled) { background:#27ae36; }
.s3-btn-next:disabled { background:#a8d5ab; color:#fff; cursor:not-allowed; }

/* Layout */
.s3-body  { display:grid; grid-template-columns:1fr 1fr; gap:20px; }

/* Date panel */
.s3-panel { background:#e0e0e0; border-radius:10px; padding:20px; }
.s3-panel-title { font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#555; margin-bottom:12px; }

.s3-date-input {
  width:100%; padding:12px 14px; border-radius:8px;
  border:2px solid #ccc; background:#fff; font-size:.95rem; font-weight:600; color:#222;
  cursor:pointer; transition:border-color .15s;
}
.s3-date-input:focus { outline:none; border-color:#2ecc40; }

/* Time slots */
.s3-slots { display:flex; flex-wrap:wrap; gap:8px; margin-top:4px; }
.s3-slot-btn {
  padding:9px 16px; border-radius:8px; border:2px solid #ccc;
  background:#fff; color:#333; font-size:.8rem; font-weight:700;
  cursor:pointer; transition:background .15s, border-color .15s; letter-spacing:.3px;
}
.s3-slot-btn:hover   { border-color:#2ecc40; background:#f0fff0; }
.s3-slot-btn.selected { background:#2ecc40; border-color:#27ae36; color:#fff; }
.s3-slot-placeholder { color:#888; font-size:.85rem; font-style:italic; }

@media (max-width:600px) {
  .s3-body { grid-template-columns:1fr; }
  .s3-card { padding:20px 14px 28px; }
}
</style>

<div class="s3-page">
  <div class="s3-card">
    <div class="s3-title">Step 3: Select Date &amp; Time</div>

    <form method="post" id="step3Form">
      <input type="hidden" name="csrf_token"   value="<?= $csrfToken ?>">
      <input type="hidden" name="schedule_id"  id="selectedScheduleId" value="<?= $selected ?>">
      <input type="hidden" name="slot_date"    id="selectedDate"  value="">
      <input type="hidden" name="slot_time"    id="selectedTime"  value="">

      <!-- BACK / NEXT -->
      <div class="s3-btn-row">
        <a href="<?= BASE_URL ?>/public/booking/step2_stylist.php" class="s3-btn-back">BACK</a>
        <button type="submit" class="s3-btn-next" id="nextBtn" disabled>NEXT</button>
      </div>

      <div class="s3-body">
        <!-- Date picker -->
        <div class="s3-panel">
          <div class="s3-panel-title"><i class="bi bi-calendar3 me-1"></i>Select Date</div>
          <input type="date" id="datePicker" class="s3-date-input"
                 min="<?= $today ?>" max="<?= $maxDate ?>" required>
        </div>

        <!-- Time slots -->
        <div class="s3-panel">
          <div class="s3-panel-title"><i class="bi bi-clock me-1"></i>Available Time Slots</div>
          <div id="slotsContainer">
            <span class="s3-slot-placeholder">Please select a date first.</span>
          </div>
        </div>
      </div>

    </form>
  </div>
</div>

<script>
const stylistId = '<?= htmlspecialchars($stylistId) ?>';
const serviceId = <?= $serviceId ?>;
const baseUrl   = '<?= BASE_URL ?>';

document.getElementById('datePicker').addEventListener('change', function () {
  const date = this.value;
  document.getElementById('selectedDate').value = date;
  document.getElementById('slotsContainer').innerHTML = '<span class="s3-slot-placeholder">Loading…</span>';
  document.getElementById('nextBtn').disabled = true;
  document.getElementById('selectedScheduleId').value = '';

  let url = baseUrl + '/public/booking/fetch_slots.php?date=' + date;
  if (stylistId === 'any') {
    url += '&service_id=' + serviceId;
  } else {
    url += '&stylist_id=' + stylistId + '&service_id=' + serviceId;
  }

  fetch(url)
    .then(r => r.json())
    .then(data => {
      const container = document.getElementById('slotsContainer');

      // Handle closed day
      if (data.closed) {
        const isStylistOff = (data.reason === 'stylist');
        const headline = isStylistOff
          ? 'No available schedule.'
          : 'Sorry, the salon is closed on this day.';
        const sub = isStylistOff
          ? 'This stylist is not working on the selected day. Please choose another date or stylist.'
          : 'Please choose another date.';
        container.innerHTML =
          '<div style="background:#fff3cd;border:1.5px solid #f59e0b;border-radius:8px;' +
          'padding:14px 16px;font-size:.88rem;color:#7c4f00;">' +
          '<i class="bi bi-x-circle-fill me-2" style="color:#f59e0b"></i>' +
          '<strong>' + headline + '</strong><br>' +
          '<span style="font-size:.8rem">' + sub + '</span></div>';
        return;
      }

      const slots = data.slots || data; // backward compat
      if (!slots.length) {
        container.innerHTML = '<span class="s3-slot-placeholder">No available slots on this date.</span>';
        return;
      }
      container.innerHTML = '';
      const wrap = document.createElement('div');
      wrap.className = 's3-slots';
      slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 's3-slot-btn';
        btn.textContent = formatTime(slot.start_time);
        btn.dataset.id   = slot.id;
        btn.dataset.time = slot.start_time;
        btn.addEventListener('click', function () {
          document.querySelectorAll('.s3-slot-btn').forEach(b => b.classList.remove('selected'));
          this.classList.add('selected');
          document.getElementById('selectedScheduleId').value = slot.id;
          document.getElementById('selectedTime').value = slot.start_time;
          document.getElementById('nextBtn').disabled = false;
        });
        wrap.appendChild(btn);
      });
      container.appendChild(wrap);
    });
});

function formatTime(t) {
  const [h, m] = t.split(':');
  const hr = parseInt(h);
  return (hr > 12 ? hr - 12 : (hr === 0 ? 12 : hr)) + ':' + m + ' ' + (hr >= 12 ? 'PM' : 'AM');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
