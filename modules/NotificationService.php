<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationService {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ----------------------------------------------------------------
    // SEND helpers
    // ----------------------------------------------------------------
    private function send(string $to, string $subject, string $body, ?int $bookingId, string $type): void {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->Timeout    = 15;
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            $this->log($bookingId, $type, $to, 'sent', null);
        } catch (Exception $e) {
            $this->log($bookingId, $type, $to, 'failed', $mail->ErrorInfo);
        }
    }

    private function log(?int $bookingId, string $type, string $email, string $status, ?string $error): void {
        try {
            $this->db->prepare(
                'INSERT INTO notification_log (booking_id, notification_type, recipient_email, status, error_message)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$bookingId, $type, $email, $status, $error]);
        } catch (Exception $e) {
            error_log('Notification log failed: ' . $e->getMessage());
        }
    }

    private function getBookingDetails(int $bookingId): ?array {
        $stmt = $this->db->prepare(
            'SELECT b.*, c.email AS client_email, c.username AS client_name,
                    s.name AS service_name, st.name AS stylist_name,
                    sc.slot_date, sc.start_time
             FROM bookings b
             JOIN clients c   ON b.client_id   = c.id
             JOIN services s  ON b.service_id  = s.id
             JOIN stylists st ON b.stylist_id  = st.id
             JOIN schedules sc ON b.schedule_id = sc.id
             WHERE b.id = ?'
        );
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: null;
    }

    private function baseTemplate(string $title, string $content): string {
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;background:#f8f4fb;margin:0;padding:0;}
  .wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1);}
  .header{background:linear-gradient(135deg,#6B2D8B,#0D9488);color:#fff;padding:28px 24px;text-align:center;}
  .header h2{margin:0;font-size:1.4rem;}
  .body{padding:28px 24px;}
  .footer{background:#f3e8fb;padding:16px 24px;text-align:center;font-size:.8rem;color:#666;}
  .btn{display:inline-block;padding:12px 28px;background:#6B2D8B;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-top:16px;}
  .info-box{background:#f3e8fb;border-radius:8px;padding:16px;margin:16px 0;}
  .info-row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #e0d5ea;}
  .info-row:last-child{border-bottom:none;}
</style></head><body>
<div class="wrap">
  <div class="header"><h2>$title</h2><p style="margin:4px 0 0;opacity:.9">Selah Aesthetics</p></div>
  <div class="body">$content</div>
  <div class="footer">
    Selah Aesthetics &bull; Midsayap, Cotabato<br>
    Phone: +63 9XX XXX XXXX &bull; Email: info@selahaesthetics.com
  </div>
</div></body></html>
HTML;
    }

    // ----------------------------------------------------------------
    // PUBLIC METHODS
    // ----------------------------------------------------------------
    public function sendEmailVerification(string $email, string $token): void {
        $link    = BASE_URL . '/public/auth/verify_email.php?token=' . urlencode($token);
        $content = "<p>Thank you for registering with Selah Aesthetics!</p>
                    <p>Please click the button below to verify your email address. This link expires in 24 hours.</p>
                    <a href='$link' class='btn'>Verify My Email</a>";
        $body = $this->baseTemplate('Verify Your Email', $content);
        $this->send($email, 'Verify your email – Selah Aesthetics', $body, null, 'email_verification');
    }

    public function sendPasswordReset(string $email, string $token): void {
        $link    = BASE_URL . '/public/auth/reset_password.php?token=' . urlencode($token);
        $content = "<p>We received a request to reset your password.</p>
                    <p>Click the button below to set a new password. This link expires in 1 hour.</p>
                    <a href='$link' class='btn'>Reset My Password</a>
                    <p style='margin-top:16px;font-size:.85rem;color:#666'>If you did not request this, please ignore this email.</p>";
        $body = $this->baseTemplate('Reset Your Password', $content);
        $this->send($email, 'Reset your password – Selah Aesthetics', $body, null, 'password_reset');
    }

    public function sendBookingConfirmation(int $bookingId): void {
        $b = $this->getBookingDetails($bookingId);
        if (!$b) return;
        $date = date('F j, Y', strtotime($b['slot_date']));
        $time = date('g:i A', strtotime($b['start_time']));
        $content = "<p>Hi {$b['client_name']}, we received your booking request!</p>
                    <div class='info-box'>
                      <div class='info-row'><span><b>Service</b></span><span>{$b['service_name']}</span></div>
                      <div class='info-row'><span><b>Stylist</b></span><span>{$b['stylist_name']}</span></div>
                      <div class='info-row'><span><b>Date</b></span><span>$date</span></div>
                      <div class='info-row'><span><b>Time</b></span><span>$time</span></div>
                      <div class='info-row'><span><b>Downpayment</b></span><span>₱" . number_format($b['downpayment_amount'], 2) . "</span></div>
                    </div>
                    <p>Our team will review your booking and payment. You will receive a confirmation email once accepted.</p>";
        $body = $this->baseTemplate('Booking Received', $content);
        $this->send($b['client_email'], 'Booking Received – Selah Aesthetics', $body, $bookingId, 'booking_confirmation');
    }

    public function sendBookingAccepted(int $bookingId): void {
        $b = $this->getBookingDetails($bookingId);
        if (!$b) return;
        $date = date('F j, Y', strtotime($b['slot_date']));
        $time = date('g:i A', strtotime($b['start_time']));
        $content = "<p>Great news, {$b['client_name']}! Your appointment has been <b>confirmed</b>.</p>
                    <div class='info-box'>
                      <div class='info-row'><span><b>Service</b></span><span>{$b['service_name']}</span></div>
                      <div class='info-row'><span><b>Stylist</b></span><span>{$b['stylist_name']}</span></div>
                      <div class='info-row'><span><b>Date</b></span><span>$date</span></div>
                      <div class='info-row'><span><b>Time</b></span><span>$time</span></div>
                    </div>
                    <p>We look forward to seeing you! Please arrive 5–10 minutes early.</p>";
        $body = $this->baseTemplate('Appointment Confirmed ✓', $content);
        $this->send($b['client_email'], 'Appointment Confirmed – Selah Aesthetics', $body, $bookingId, 'booking_accepted');
    }

    public function sendBookingCancelled(int $bookingId, string $cancelledBy = 'client'): void {
        $b = $this->getBookingDetails($bookingId);
        if (!$b) return;
        $refundNote = ($b['refund_status'] === 'forfeited')
            ? '<p style="color:#ef4444"><b>Note:</b> As the cancellation was made within 48 hours of your appointment, the downpayment is forfeited per our cancellation policy.</p>'
            : '<p style="color:#10b981"><b>Note:</b> Your downpayment is eligible for refund review. Please contact us for details.</p>';
        $content = "<p>Hi {$b['client_name']}, your booking has been <b>cancelled</b>.</p>
                    <div class='info-box'>
                      <div class='info-row'><span><b>Service</b></span><span>{$b['service_name']}</span></div>
                      <div class='info-row'><span><b>Cancelled by</b></span><span>" . ucfirst($cancelledBy) . "</span></div>
                    </div>
                    $refundNote
                    <p>You may book a new appointment anytime through our website.</p>";
        $body = $this->baseTemplate('Booking Cancelled', $content);
        $this->send($b['client_email'], 'Booking Cancelled – Selah Aesthetics', $body, $bookingId, 'booking_cancelled');
    }

    public function sendPaymentConfirmation(int $bookingId): void {
        $b = $this->getBookingDetails($bookingId);
        if (!$b) return;
        $content = "<p>Hi {$b['client_name']}, your payment has been <b>confirmed</b>!</p>
                    <div class='info-box'>
                      <div class='info-row'><span><b>Service</b></span><span>{$b['service_name']}</span></div>
                      <div class='info-row'><span><b>Amount Paid</b></span><span>₱" . number_format($b['downpayment_amount'], 2) . "</span></div>
                      <div class='info-row'><span><b>Booking ID</b></span><span>#$bookingId</span></div>
                    </div>
                    <p>Thank you for your payment. Your slot is now secured!</p>";
        $body = $this->baseTemplate('Payment Confirmed', $content);
        $this->send($b['client_email'], 'Payment Confirmed – Selah Aesthetics', $body, $bookingId, 'payment_confirmation');
    }

    public function sendAccountApproved(string $email, string $username): void {
        $loginUrl = BASE_URL . '/public/auth/login.php';
        $content  = "
            <p>Hi <strong>" . htmlspecialchars($username) . "</strong>,</p>
            <p>Great news! Your <strong>Selah Aesthetics</strong> account has been
            <span style='color:#10b981;font-weight:700'>approved</span> by our admin.</p>
            <p>You can now log in and book your appointment.</p>
            <a href='$loginUrl' class='btn'>Log In Now</a>
            <p style='margin-top:20px;font-size:.85rem;color:#666'>
              Welcome to Selah Aesthetics — we look forward to serving you!
            </p>";
        $body = $this->baseTemplate('Account Approved ✓', $content);
        $this->send($email, 'Your Selah Aesthetics account is approved!', $body, null, 'account_approved');
    }

    public function sendAccountRejected(string $email, string $username): void {
        $content = "
            <p>Hi <strong>" . htmlspecialchars($username) . "</strong>,</p>
            <p>We're sorry, but your <strong>Selah Aesthetics</strong> account registration has been
            <span style='color:#ef4444;font-weight:700'>rejected</span>.</p>
            <p>If you believe this is a mistake or would like more information, please contact us directly.</p>
            <div class='info-box'>
              <div class='vp-row'><span>Email</span><span>info@selahaesthetics.com</span></div>
            </div>
            <p style='margin-top:16px;font-size:.85rem;color:#666'>
              We apologize for any inconvenience.
            </p>";
        $body = $this->baseTemplate('Account Registration Update', $content);
        $this->send($email, 'Selah Aesthetics – Account Registration Update', $body, null, 'account_rejected');
    }

    public function sendGCashRejected(int $bookingId): void {
        $b = $this->getBookingDetails($bookingId);
        if (!$b) return;
        $resubmitUrl = BASE_URL . '/public/booking_detail.php?id=' . $bookingId;
        $content = "<p>Hi {$b['client_name']}, unfortunately your GCash payment proof could not be verified.</p>
                    <p>Please resubmit a clear screenshot of your payment receipt to confirm your booking.</p>
                    <a href='$resubmitUrl' class='btn'>Resubmit Payment Proof</a>";
        $body = $this->baseTemplate('Payment Resubmission Required', $content);
        $this->send($b['client_email'], 'Payment Resubmission Required – Selah Aesthetics', $body, $bookingId, 'gcash_rejected');
    }

    public function sendAppointmentReminder(int $bookingId): void {
        $b = $this->getBookingDetails($bookingId);
        if (!$b) return;
        $date = date('F j, Y', strtotime($b['slot_date']));
        $time = date('g:i A', strtotime($b['start_time']));
        $content = "<p>Hi {$b['client_name']}, just a friendly reminder about your appointment <b>tomorrow</b>!</p>
                    <div class='info-box'>
                      <div class='info-row'><span><b>Service</b></span><span>{$b['service_name']}</span></div>
                      <div class='info-row'><span><b>Stylist</b></span><span>{$b['stylist_name']}</span></div>
                      <div class='info-row'><span><b>Date</b></span><span>$date</span></div>
                      <div class='info-row'><span><b>Time</b></span><span>$time</span></div>
                    </div>
                    <p>Please arrive 5–10 minutes early. We look forward to seeing you!</p>";
        $body = $this->baseTemplate('Reminder: Appointment Tomorrow', $content);
        $this->send($b['client_email'], 'Reminder: Your appointment tomorrow – Selah Aesthetics', $body, $bookingId, 'appointment_reminder');
    }
}
