<?php
/* ============================================================
   fetch_slots.php  –  Dynamic slot generator
   Source of truth: stylist_weekly_schedules + salon_operating_hours
   NO dependency on manually created daily time slots.

   Logic:
   1. Validate inputs.
   2. Determine weekday for the requested date.
   3. Load Salon Operating Hours for that weekday.
      → Salon closed → {"closed":true,"reason":"salon"}
   4a. Specific stylist:
       → Load stylist_weekly_schedules row for that weekday.
       → is_working = 0 → {"closed":true,"reason":"stylist"}
       → Intersect stylist window with salon window.
       → Skip slots inside the stylist's break window (if set).
       → Skip slots already booked (Pending/Accepted).
       → Upsert schedule rows and return slot list.
   4b. "Any available" stylist:
       → Same per-stylist window + break check for every active
         stylist; pick the first free one for each slot.
   ============================================================ */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::start();
header('Content-Type: application/json');

if (!SessionGuard::isClientLoggedIn()) {
    echo json_encode(['closed' => false, 'slots' => []]);
    exit;
}

/* ── Input ── */
$date      = trim($_GET['date']        ?? '');
$stylistId = (int)($_GET['stylist_id'] ?? 0);
$serviceId = (int)($_GET['service_id'] ?? 0);

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    echo json_encode(['closed' => false, 'slots' => []]);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
$db = getDB();

/* ── Auto-migrate: ensure table + break columns exist ── */
ensureWeeklyScheduleTable($db);

/* ── Weekday ── */
$dayName = date('l', strtotime($date));

/* ── Salon Operating Hours ── */
$oh = null;
try {
    $ohStmt = $db->prepare('SELECT * FROM salon_operating_hours WHERE day_of_week = ?');
    $ohStmt->execute([$dayName]);
    $oh = $ohStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table not yet migrated */ }

if (!$oh) {
    $oh = ['is_open' => 1, 'open_time' => '09:00:00',
           'close_time' => '19:00:00', 'slot_duration_minutes' => 30];
}

if (!(int)$oh['is_open']) {
    echo json_encode(['closed' => true, 'reason' => 'salon', 'slots' => []]);
    exit;
}

$salonOpen  = $oh['open_time'];
$salonClose = $oh['close_time'];
$durMins    = max(15, (int)$oh['slot_duration_minutes']);
$durSecs    = $durMins * 60;
$now        = time();

/* ================================================================
   Helper: build effective window + break for a stylist row.
   Returns null if the stylist is off, or an array:
     ['openTs','closeTs','breakStartTs','breakEndTs']
   breakStartTs/breakEndTs are 0 when no break is configured.
   ================================================================ */
function stylistWindow(array $sw, string $date,
                       string $salonOpen, string $salonClose): ?array
{
    if (!(int)$sw['is_working']) return null;

    $effOpen  = max($salonOpen,  $sw['start_time']);
    $effClose = min($salonClose, $sw['end_time']);
    if ($effClose <= $effOpen) return null;

    $breakStart = $sw['break_start'] ?? null;
    $breakEnd   = $sw['break_end']   ?? null;
    $bsTs = ($breakStart && $breakStart !== '00:00:00')
              ? strtotime($date . ' ' . $breakStart) : 0;
    $beTs = ($breakEnd   && $breakEnd   !== '00:00:00')
              ? strtotime($date . ' ' . $breakEnd)   : 0;
    // Only use break if both are set and break_end > break_start
    if ($bsTs && $beTs && $beTs <= $bsTs) { $bsTs = 0; $beTs = 0; }

    return [
        'openTs'       => strtotime($date . ' ' . $effOpen),
        'closeTs'      => strtotime($date . ' ' . $effClose),
        'breakStartTs' => $bsTs,
        'breakEndTs'   => $beTs,
    ];
}

/* Helper: true if slot [ts, ts+durSecs) overlaps the break window */
function inBreak(int $ts, int $durSecs, int $bsTs, int $beTs): bool
{
    if (!$bsTs || !$beTs) return false;
    $slotEnd = $ts + $durSecs;
    // overlap: slot starts before break ends AND slot ends after break starts
    return ($ts < $beTs) && ($slotEnd > $bsTs);
}

/* ================================================================
   PATH A – Specific stylist
   ================================================================ */
if ($stylistId > 0) {

    $swStmt = $db->prepare(
        'SELECT * FROM stylist_weekly_schedules WHERE stylist_id = ? AND day_of_week = ?'
    );
    $swStmt->execute([$stylistId, $dayName]);
    $sw = $swStmt->fetch(PDO::FETCH_ASSOC);

    $openTs       = strtotime($date . ' ' . $salonOpen);
    $closeTs      = strtotime($date . ' ' . $salonClose);
    $breakStartTs = 0;
    $breakEndTs   = 0;

    if ($sw !== false) {
        if (!(int)$sw['is_working']) {
            echo json_encode(['closed' => true, 'reason' => 'stylist', 'slots' => []]);
            exit;
        }
        $win = stylistWindow($sw, $date, $salonOpen, $salonClose);
        if ($win === null) {
            echo json_encode(['closed' => false, 'slots' => []]);
            exit;
        }
        $openTs       = $win['openTs'];
        $closeTs      = $win['closeTs'];
        $breakStartTs = $win['breakStartTs'];
        $breakEndTs   = $win['breakEndTs'];
    }

    /* Pre-load booked times */
    $bookedTimes = [];
    $bkStmt = $db->prepare(
        "SELECT sc.start_time FROM schedules sc
         JOIN bookings b ON b.schedule_id = sc.id
         WHERE sc.stylist_id = ? AND sc.slot_date = ?
           AND b.status IN ('Pending','Accepted')"
    );
    $bkStmt->execute([$stylistId, $date]);
    foreach ($bkStmt->fetchAll(PDO::FETCH_COLUMN) as $bt) {
        $bookedTimes[$bt] = true;
    }

    $slots = [];
    for ($ts = $openTs; $ts + $durSecs <= $closeTs; $ts += $durSecs) {
        if ($ts <= $now + 300) continue;
        if (inBreak($ts, $durSecs, $breakStartTs, $breakEndTs)) continue;
        $startTime = date('H:i:s', $ts);
        $endTime   = date('H:i:s', $ts + $durSecs);
        if (isset($bookedTimes[$startTime])) continue;

        $schedId = ensureSchedule($db, $stylistId, $date, $startTime, $endTime);
        $slots[] = [
            'id'         => $schedId,
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'label'      => date('g:i A', $ts),
            'stylist_id' => $stylistId,
        ];
    }

    echo json_encode(['closed' => false, 'slots' => $slots]);
    exit;
}

/* ================================================================
   PATH B – "Any available" stylist
   ================================================================ */
$allStylists = $db->query(
    'SELECT id FROM stylists WHERE is_active = 1 ORDER BY id'
)->fetchAll(PDO::FETCH_COLUMN);

if (empty($allStylists)) {
    echo json_encode(['closed' => false, 'slots' => []]);
    exit;
}

/* Build window map for every active stylist */
$stylistMeta = []; // sid => window array or null
foreach ($allStylists as $sid) {
    $swStmt = $db->prepare(
        'SELECT * FROM stylist_weekly_schedules WHERE stylist_id = ? AND day_of_week = ?'
    );
    $swStmt->execute([$sid, $dayName]);
    $sw = $swStmt->fetch(PDO::FETCH_ASSOC);

    if ($sw === false) {
        /* No row yet → use full salon window, no break */
        $stylistMeta[$sid] = [
            'openTs' => strtotime($date . ' ' . $salonOpen),
            'closeTs' => strtotime($date . ' ' . $salonClose),
            'breakStartTs' => 0, 'breakEndTs' => 0,
        ];
    } else {
        $stylistMeta[$sid] = stylistWindow($sw, $date, $salonOpen, $salonClose);
    }
}

/* Pre-load all bookings for this date */
$placeholders  = implode(',', array_fill(0, count($allStylists), '?'));
$allBkStmt     = $db->prepare(
    "SELECT sc.stylist_id, sc.start_time FROM schedules sc
     JOIN bookings b ON b.schedule_id = sc.id
     WHERE sc.slot_date = ? AND sc.stylist_id IN ($placeholders)
       AND b.status IN ('Pending','Accepted')"
);
$allBkStmt->execute(array_merge([$date], $allStylists));
$bookedByStylist = [];
foreach ($allBkStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $bookedByStylist[$row['stylist_id']][$row['start_time']] = true;
}

$salonOpenTs  = strtotime($date . ' ' . $salonOpen);
$salonCloseTs = strtotime($date . ' ' . $salonClose);

$slots = [];
for ($ts = $salonOpenTs; $ts + $durSecs <= $salonCloseTs; $ts += $durSecs) {
    if ($ts <= $now + 300) continue;

    $startTime = date('H:i:s', $ts);
    $endTime   = date('H:i:s', $ts + $durSecs);

    $freeStylistId = null;
    foreach ($allStylists as $sid) {
        $win = $stylistMeta[$sid] ?? null;
        if ($win === null) continue;
        if ($ts <  $win['openTs'])  continue;
        if ($ts + $durSecs > $win['closeTs']) continue;
        if (inBreak($ts, $durSecs, $win['breakStartTs'], $win['breakEndTs'])) continue;
        if (isset($bookedByStylist[$sid][$startTime])) continue;
        $freeStylistId = $sid;
        break;
    }

    if (!$freeStylistId) continue;

    $schedId = ensureSchedule($db, $freeStylistId, $date, $startTime, $endTime);
    $slots[] = [
        'id'         => $schedId,
        'start_time' => $startTime,
        'end_time'   => $endTime,
        'label'      => date('g:i A', $ts),
        'stylist_id' => $freeStylistId,
    ];
}

echo json_encode(['closed' => false, 'slots' => $slots]);

/* ============================================================
   ensureSchedule() – upsert a schedule anchor row
   ============================================================ */
function ensureSchedule(PDO $db, int $stylistId, string $date,
                        string $start, string $end): int
{
    $stmt = $db->prepare(
        'SELECT id FROM schedules WHERE stylist_id = ? AND slot_date = ? AND start_time = ?'
    );
    $stmt->execute([$stylistId, $date, $start]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int)$row['id'];

    $db->prepare(
        'INSERT INTO schedules (stylist_id, slot_date, start_time, end_time, is_available)
         VALUES (?, ?, ?, ?, 1)'
    )->execute([$stylistId, $date, $start, $end]);
    return (int)$db->lastInsertId();
}

/* ============================================================
   ensureWeeklyScheduleTable()
   Creates + seeds stylist_weekly_schedules, and adds break
   columns if the table was created by an earlier migration
   that did not include them.
   ============================================================ */
function ensureWeeklyScheduleTable(PDO $db): void
{
    /* Create table with break columns */
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

    /* Add break columns to existing tables that predate this change */
    try {
        $db->exec("ALTER TABLE stylist_weekly_schedules
                   ADD COLUMN IF NOT EXISTS break_start TIME NULL DEFAULT NULL
                   AFTER start_time");
        $db->exec("ALTER TABLE stylist_weekly_schedules
                   ADD COLUMN IF NOT EXISTS break_end TIME NULL DEFAULT NULL
                   AFTER break_start");
    } catch (PDOException $e) { /* columns already exist */ }

    /* Seed default Mon–Sat 9AM–6PM, Sun OFF for any unseed stylist */
    $db->exec("
        INSERT IGNORE INTO stylist_weekly_schedules
            (stylist_id, day_of_week, is_working, start_time, end_time)
        SELECT s.id, d.day_of_week,
               CASE d.day_of_week WHEN 'Sunday' THEN 0 ELSE 1 END,
               '09:00:00',
               CASE d.day_of_week WHEN 'Sunday' THEN '09:00:00' ELSE '18:00:00' END
        FROM stylists s
        JOIN (
            SELECT 'Monday'    AS day_of_week UNION ALL
            SELECT 'Tuesday'                  UNION ALL
            SELECT 'Wednesday'                UNION ALL
            SELECT 'Thursday'                 UNION ALL
            SELECT 'Friday'                   UNION ALL
            SELECT 'Saturday'                 UNION ALL
            SELECT 'Sunday'
        ) d ON 1=1
        WHERE s.is_active = 1
    ");
}
