<?php
/**
 * Cron Script: Auto-cancel Pending bookings with no payment after 24 hours
 * Run every hour via Windows Task Scheduler:
 *   php C:\xampp\htdocs\selah-aesthetics\cron\auto_cancel_unpaid.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../modules/NotificationService.php';

$db = getDB();

// Pending bookings older than 24h with no Paid transaction
$stmt = $db->query(
    "SELECT b.id, b.schedule_id FROM bookings b
     WHERE b.status = 'Pending'
       AND b.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
       AND b.id NOT IN (
         SELECT booking_id FROM transactions WHERE status = 'Paid'
       )"
);
$bookings = $stmt->fetchAll();

$ns         = new NotificationService();
$cancelled  = 0;

foreach ($bookings as $b) {
    // Update status
    $db->prepare("UPDATE bookings SET status='Cancelled', cancelled_by='admin', refund_status='not_applicable' WHERE id=?")
       ->execute([$b['id']]);
    // Release slot
    $db->prepare("UPDATE schedules SET is_available=1 WHERE id=?")
       ->execute([$b['schedule_id']]);
    // Notify client
    $ns->sendBookingCancelled((int)$b['id'], 'admin');
    $cancelled++;
    echo "[AUTO-CANCEL] Booking #{$b['id']} cancelled (no payment within 24h)\n";
}

echo "\nDone. Auto-cancelled: $cancelled booking(s)\n";
