<?php
/**
 * Cron Script: Send appointment reminder emails
 * Run every hour via Windows Task Scheduler:
 *   php C:\xampp\htdocs\selah-aesthetics\cron\send_reminders.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../modules/NotificationService.php';

$db = getDB();

// Find Accepted bookings 23–25 hours away that haven't been reminded
$stmt = $db->query(
    "SELECT b.id, CONCAT(sc.slot_date, ' ', sc.start_time) AS appt_dt
     FROM bookings b
     JOIN schedules sc ON b.schedule_id = sc.id
     WHERE b.status = 'Accepted'
       AND b.reminder_sent = 0
       AND CONCAT(sc.slot_date, ' ', sc.start_time) BETWEEN
           DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)"
);
$bookings = $stmt->fetchAll();

$ns   = new NotificationService();
$sent = 0;
$fail = 0;

foreach ($bookings as $b) {
    try {
        $ns->sendAppointmentReminder((int)$b['id']);
        $db->prepare("UPDATE bookings SET reminder_sent=1, reminder_sent_at=NOW() WHERE id=?")
           ->execute([$b['id']]);
        $sent++;
        echo "[OK] Reminder sent for booking #{$b['id']} (appt: {$b['appt_dt']})\n";
    } catch (Exception $e) {
        $fail++;
        echo "[FAIL] Booking #{$b['id']}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone. Sent: $sent | Failed: $fail\n";
