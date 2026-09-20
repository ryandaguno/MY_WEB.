<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/NotificationService.php';

class BookingManager {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
        $this->ensureWeeklyScheduleTable();
    }

    // ----------------------------------------------------------------
    // Auto-migrate: create & seed stylist_weekly_schedules if needed.
    // Safe to call on every request — CREATE TABLE IF NOT EXISTS and
    // INSERT IGNORE make it a no-op after the first run.
    // ----------------------------------------------------------------
    private function ensureWeeklyScheduleTable(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS stylist_weekly_schedules (
                  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  stylist_id  INT UNSIGNED NOT NULL,
                  day_of_week ENUM('Monday','Tuesday','Wednesday',
                                   'Thursday','Friday','Saturday','Sunday') NOT NULL,
                  is_working  TINYINT(1)   NOT NULL DEFAULT 1,
                  start_time  TIME         NOT NULL DEFAULT '09:00:00',
                  end_time    TIME         NOT NULL DEFAULT '18:00:00',
                  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (id),
                  UNIQUE KEY uq_stylist_day (stylist_id, day_of_week),
                  FOREIGN KEY (stylist_id) REFERENCES stylists(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->db->exec("
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
        } catch (PDOException $e) {
            // Silently ignore — stylists table may not be set up in test env
        }
    }

    // ----------------------------------------------------------------
    // GET AVAILABLE STYLISTS for Step 2
    // Returns ALL active stylists with working-day data pulled from
    // stylist_weekly_schedules (the new recurring schedule table).
    // Falls back gracefully if the table is not yet migrated.
    // ----------------------------------------------------------------
    public function getAvailableStylists(int $serviceId): array {
        // $serviceId kept as param for backward compatibility but not
        // used for filtering — all active stylists are shown.
        $stylists = $this->db->query(
            "SELECT id, name, specialty, bio, photo_url, is_active,
                    1 AS avail_status
             FROM stylists
             WHERE is_active = 1
             ORDER BY name"
        )->fetchAll();

        // Attempt to enrich with weekly schedule data
        try {
            foreach ($stylists as &$s) {
                $stmt = $this->db->prepare(
                    "SELECT day_of_week FROM stylist_weekly_schedules
                     WHERE stylist_id = ? AND is_working = 1
                     ORDER BY FIELD(day_of_week,
                       'Monday','Tuesday','Wednesday',
                       'Thursday','Friday','Saturday','Sunday')"
                );
                $stmt->execute([$s['id']]);
                $workingDays = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Convert full day names to 3-letter abbreviations for badge display
                $abbr = ['Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed',
                         'Thursday'=>'Thu','Friday'=>'Fri',
                         'Saturday'=>'Sat','Sunday'=>'Sun'];
                $s['working_days'] = implode(',', array_map(
                    fn($d) => $abbr[$d] ?? $d,
                    $workingDays
                ));
            }
            unset($s);
        } catch (PDOException $e) {
            // Table not yet created — working_days will be empty; that is fine.
            foreach ($stylists as &$s) {
                $s['working_days'] = '';
            }
            unset($s);
        }

        return $stylists;
    }

    // ----------------------------------------------------------------
    // GET AVAILABLE SLOTS for a stylist on a date
    // ----------------------------------------------------------------
    public function getAvailableSlots(int $stylistId, string $date): array {
        $stmt = $this->db->prepare(
            'SELECT sc.* FROM schedules sc
             WHERE sc.stylist_id = ? AND sc.slot_date = ? AND sc.is_available = 1
             AND sc.id NOT IN (
               SELECT schedule_id FROM bookings
               WHERE status IN (\'Pending\',\'Accepted\') AND schedule_id = sc.id
             )
             ORDER BY sc.start_time'
        );
        $stmt->execute([$stylistId, $date]);
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // GET SLOTS for "Any Available" (across all stylists)
    // ----------------------------------------------------------------
    public function getAnyAvailableSlots(int $serviceId, string $date): array {
        $stmt = $this->db->prepare(
            'SELECT s.category FROM services s WHERE s.id = ?'
        );
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch();
        if (!$service) return [];

        $stmt = $this->db->prepare(
            'SELECT sc.*, st.name AS stylist_name FROM schedules sc
             JOIN stylists st ON sc.stylist_id = st.id
             WHERE st.specialty = ? AND sc.slot_date = ? AND sc.is_available = 1
             AND sc.id NOT IN (
               SELECT schedule_id FROM bookings WHERE status IN (\'Pending\',\'Accepted\')
             )
             AND st.is_active = 1
             ORDER BY sc.start_time'
        );
        $stmt->execute([$service['category'], $date]);
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // CHECK CONFLICT for a specific schedule slot
    // ----------------------------------------------------------------
    public function checkConflict(int $scheduleId): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bookings
             WHERE schedule_id = ? AND status IN (\'Pending\',\'Accepted\')'
        );
        $stmt->execute([$scheduleId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ----------------------------------------------------------------
    // DETECT CLIENT OVERLAP (prevents double-booking same client)
    // ----------------------------------------------------------------
    public function detectOverlap(int $clientId, int $scheduleId, int $durationMinutes): bool {
        $stmt = $this->db->prepare('SELECT slot_date, start_time FROM schedules WHERE id = ?');
        $stmt->execute([$scheduleId]);
        $newSlot = $stmt->fetch();
        if (!$newSlot) return false;

        $newStart = strtotime($newSlot['slot_date'] . ' ' . $newSlot['start_time']);
        $newEnd   = $newStart + ($durationMinutes * 60);

        $stmt = $this->db->prepare(
            'SELECT sc.slot_date, sc.start_time, sv.duration_minutes
             FROM bookings b
             JOIN schedules sc ON b.schedule_id = sc.id
             JOIN services sv  ON b.service_id  = sv.id
             WHERE b.client_id = ? AND b.status IN (\'Pending\',\'Accepted\')'
        );
        $stmt->execute([$clientId]);
        $existing = $stmt->fetchAll();

        foreach ($existing as $row) {
            $exStart = strtotime($row['slot_date'] . ' ' . $row['start_time']);
            $exEnd   = $exStart + ($row['duration_minutes'] * 60);
            if ($newStart < $exEnd && $newEnd > $exStart) return true;
        }
        return false;
    }

    // ----------------------------------------------------------------
    // CREATE BOOKING
    // ----------------------------------------------------------------
    public function createBooking(array $data): array {
        // Final conflict check at submission time
        if ($this->checkConflict((int)$data['schedule_id'])) {
            return ['success' => false, 'conflict' => true,
                    'message' => 'This time slot is no longer available. Please choose another.'];
        }

        // Client overlap check
        $stmt = $this->db->prepare('SELECT duration_minutes FROM services WHERE id = ?');
        $stmt->execute([(int)$data['service_id']]);
        $svc = $stmt->fetch();
        if ($this->detectOverlap((int)$data['client_id'], (int)$data['schedule_id'], (int)$svc['duration_minutes'])) {
            return ['success' => false, 'conflict' => false,
                    'message' => 'You already have an overlapping appointment at this time.'];
        }

        $stmt = $this->db->prepare('SELECT price FROM services WHERE id = ?');
        $stmt->execute([(int)$data['service_id']]);
        $price = (float)$stmt->fetchColumn();
        $downpayment = (int)ceil($price * 0.5);

        $stmt = $this->db->prepare(
            'INSERT INTO bookings (client_id, service_id, stylist_id, schedule_id, notes, downpayment_amount)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$data['client_id'],
            (int)$data['service_id'],
            (int)$data['stylist_id'],
            (int)$data['schedule_id'],
            $data['notes'] ?? '',
            $downpayment
        ]);
        $bookingId = (int)$this->db->lastInsertId();

        $ns = new NotificationService();
        $ns->sendBookingConfirmation($bookingId);

        return ['success' => true, 'booking_id' => $bookingId, 'downpayment' => $downpayment];
    }

    // ----------------------------------------------------------------
    // CANCEL BOOKING
    // ----------------------------------------------------------------
    public function cancelBooking(int $bookingId, string $cancelledBy, int $clientId = 0): array {
        $stmt = $this->db->prepare(
            'SELECT b.*, sc.slot_date, sc.start_time FROM bookings b
             JOIN schedules sc ON b.schedule_id = sc.id WHERE b.id = ?'
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) return ['success' => false, 'message' => 'Booking not found.'];
        if (!in_array($booking['status'], ['Pending', 'Accepted'])) {
            return ['success' => false, 'message' => 'This booking cannot be cancelled.'];
        }

        // Determine refund status
        $apptTime    = strtotime($booking['slot_date'] . ' ' . $booking['start_time']);
        $hoursAway   = ($apptTime - time()) / 3600;
        $refundStatus = 'eligible';
        if ($cancelledBy === 'client' && $hoursAway < 48) {
            $refundStatus = 'forfeited';
        }

        $this->db->prepare(
            'UPDATE bookings SET status = \'Cancelled\', cancelled_by = ?, refund_status = ? WHERE id = ?'
        )->execute([$cancelledBy, $refundStatus, $bookingId]);

        // Release time slot
        $this->db->prepare('UPDATE schedules SET is_available = 1 WHERE id = ?')
                 ->execute([$booking['schedule_id']]);

        $ns = new NotificationService();
        $ns->sendBookingCancelled($bookingId, $cancelledBy);

        return ['success' => true];
    }

    // ----------------------------------------------------------------
    // ACCEPT BOOKING (Admin)
    // ----------------------------------------------------------------
    public function acceptBooking(int $bookingId): array {
        $stmt = $this->db->prepare('SELECT * FROM bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        if (!$booking) return ['success' => false, 'message' => 'Booking not found.'];

        // Re-check conflict
        if ($this->checkConflict((int)$booking['schedule_id'])) {
            // Check if the conflict is caused by a *different* booking
            $stmt2 = $this->db->prepare(
                'SELECT COUNT(*) FROM bookings
                 WHERE schedule_id = ? AND status = \'Accepted\' AND id != ?'
            );
            $stmt2->execute([$booking['schedule_id'], $bookingId]);
            if ((int)$stmt2->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'This slot has been taken by another booking.'];
            }
        }

        $this->db->prepare('UPDATE bookings SET status = \'Accepted\' WHERE id = ?')
                 ->execute([$bookingId]);
        $this->db->prepare('UPDATE schedules SET is_available = 0 WHERE id = ?')
                 ->execute([$booking['schedule_id']]);

        $ns = new NotificationService();
        $ns->sendBookingAccepted($bookingId);

        return ['success' => true];
    }

    // ----------------------------------------------------------------
    // COMPLETE BOOKING (Admin)
    // ----------------------------------------------------------------
    public function completeBooking(int $bookingId): void {
        $this->db->prepare('UPDATE bookings SET status = \'Completed\' WHERE id = ?')
                 ->execute([$bookingId]);
    }

    // ----------------------------------------------------------------
    // GET CLIENT BOOKINGS
    // ----------------------------------------------------------------
    public function getClientBookings(int $clientId): array {
        $stmt = $this->db->prepare(
            'SELECT b.*, s.name AS service_name, st.name AS stylist_name,
                    sc.slot_date, sc.start_time,
                    t.status AS payment_status, t.payment_method
             FROM bookings b
             JOIN services s   ON b.service_id  = s.id
             JOIN stylists st  ON b.stylist_id  = st.id
             JOIN schedules sc ON b.schedule_id = sc.id
             LEFT JOIN transactions t
               ON t.booking_id = b.id
               AND t.id = (SELECT id FROM transactions WHERE booking_id = b.id ORDER BY submitted_at DESC LIMIT 1)
             WHERE b.client_id = ?
             ORDER BY sc.slot_date DESC, sc.start_time DESC'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // GET SINGLE BOOKING
    // ----------------------------------------------------------------
    public function getBooking(int $bookingId): ?array {
        $stmt = $this->db->prepare(
            'SELECT b.*, s.name AS service_name, s.price, s.duration_minutes,
                    st.name AS stylist_name, sc.slot_date, sc.start_time,
                    c.username AS client_name, c.email AS client_email, c.phone AS client_phone,
                    t.id AS transaction_id, t.status AS payment_status,
                    t.payment_method, t.receipt_image, t.amount AS paid_amount
             FROM bookings b
             JOIN services s   ON b.service_id  = s.id
             JOIN stylists st  ON b.stylist_id  = st.id
             JOIN schedules sc ON b.schedule_id = sc.id
             JOIN clients c    ON b.client_id   = c.id
             LEFT JOIN transactions t ON t.booking_id = b.id
             WHERE b.id = ?'
        );
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: null;
    }
}
