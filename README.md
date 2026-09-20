# Selah Aesthetics — Web-Based Salon Appointment and Scheduling System

A full-featured web-based salon appointment system built with PHP, MySQL, Bootstrap 5, and XAMPP.

---

## Tech Stack

- **Backend:** PHP 8.x (plain PHP, no framework)
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5
- **Database:** MySQL 8.x via phpMyAdmin
- **Local Server:** XAMPP (Apache + MySQL + PHP)
- **Email:** PHPMailer (SMTP)
- **Payment:** GCash (manual upload), PayPal Sandbox (redirect)

---

## Project Structure

```
selah-aesthetics/
├── index.php                  ← Root redirect (role-based)
├── config/
│   ├── config.php             ← App constants and settings
│   └── db.php                 ← PDO database connection
├── database/
│   ├── selah_aesthetics.sql   ← Full schema (run this first)
│   └── seed.sql               ← Sample data
├── public/                    ← Client-facing pages
│   ├── auth/                  ← Login, register, verify, forgot/reset password
│   ├── booking/               ← 5-step booking flow + AJAX endpoints
│   ├── home.php
│   ├── services.php
│   ├── my_bookings.php
│   ├── booking_detail.php
│   ├── cancel_booking.php
│   └── feedback.php
├── admin/                     ← Admin panel
│   ├── auth/                  ← Admin login/logout
│   ├── dashboard.php
│   ├── bookings/
│   ├── stylists/
│   ├── services/
│   ├── timeslots/
│   ├── transactions/
│   ├── ratings/
│   └── includes/              ← Admin header/footer layouts
├── modules/                   ← Core PHP classes
│   ├── Auth.php
│   ├── SessionGuard.php
│   ├── Validator.php
│   ├── BookingManager.php
│   ├── PaymentHandler.php
│   └── NotificationService.php
├── cron/
│   ├── send_reminders.php     ← 24h appointment reminders
│   └── auto_cancel_unpaid.php ← Auto-cancel unpaid pending bookings
├── uploads/receipts/          ← GCash receipt images (protected)
├── assets/
│   ├── css/style.css
│   ├── js/main.js
│   └── images/gcash_qr.png    ← Place your GCash QR here
└── vendor/phpmailer/          ← PHPMailer library
```

---

## Setup Instructions

### 1. Install XAMPP
Download and install XAMPP from https://www.apachefriends.org/  
Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 2. Copy Project Files
Copy the `selah-aesthetics` folder to:
```
C:\xampp\htdocs\selah-aesthetics\
```

### 3. Create the Database
1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click **New** → create database named `selah_aesthetics` (UTF8mb4)
3. Select the database → click **Import**
4. Import `database/selah_aesthetics.sql`
5. Then import `database/seed.sql` for sample data

### 4. Configure the App
Edit `config/config.php`:
```php
define('BASE_URL', 'http://localhost/selah-aesthetics');
define('DB_HOST',  'localhost');
define('DB_NAME',  'selah_aesthetics');
define('DB_USER',  'root');
define('DB_PASS',  '');        // Leave blank for default XAMPP
```

### 5. Configure Email (PHPMailer)
In `config/config.php`, update:
```php
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your_gmail@gmail.com');
define('MAIL_PASSWORD', 'your_app_password');  // Google App Password
define('MAIL_FROM',     'noreply@selahaesthetics.com');
```
To generate a Gmail App Password: Google Account → Security → 2-Step Verification → App Passwords

### 6. Add GCash QR Code
Place your salon's GCash QR code image at:
```
assets/images/gcash_qr.png
```
Update the number in `config/config.php`:
```php
define('GCASH_NUMBER', '09XXXXXXXXX');
```

### 7. PayPal Sandbox Setup
1. Go to https://developer.paypal.com/
2. Create a Sandbox business account
3. Update `config/config.php`:
```php
define('PAYPAL_CLIENT_ID', 'YOUR_SANDBOX_CLIENT_ID');
define('PAYPAL_MODE', 'sandbox');
```

---

## Default Credentials

### Admin Login
- URL: `http://localhost/selah-aesthetics/admin/auth/login.php`
- Username: `admin`
- Password: `password` *(bcrypt hash in seed.sql — change immediately)*

### Sample Clients (from seed.sql)
- Email: `joshua@example.com` / Password: `password`
- Email: `ryan@example.com`   / Password: `password`

> **Important:** Change all default passwords before deployment.

---

## Cron Scripts (Automated Tasks)

### Windows Task Scheduler Setup

**Appointment Reminders** (run every hour):
1. Open Task Scheduler → Create Basic Task
2. Trigger: Daily, repeat every 1 hour
3. Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\selah-aesthetics\cron\send_reminders.php`

**Auto-Cancel Unpaid Bookings** (run every hour):
- Same setup, Arguments: `C:\xampp\htdocs\selah-aesthetics\cron\auto_cancel_unpaid.php`

Or run manually:
```bash
php C:\xampp\htdocs\selah-aesthetics\cron\send_reminders.php
php C:\xampp\htdocs\selah-aesthetics\cron\auto_cancel_unpaid.php
```

---

## Access URLs

| Page | URL |
|------|-----|
| Client Home | `http://localhost/selah-aesthetics/` |
| Client Login | `http://localhost/selah-aesthetics/public/auth/login.php` |
| Client Register | `http://localhost/selah-aesthetics/public/auth/register.php` |
| Services | `http://localhost/selah-aesthetics/public/services.php` |
| Admin Login | `http://localhost/selah-aesthetics/admin/auth/login.php` |
| Admin Dashboard | `http://localhost/selah-aesthetics/admin/dashboard.php` |

---

## Features

### Client Side
- Account registration with email verification
- Secure login with brute-force lockout (5 attempts → 15 min lock)
- Forgot/reset password via email
- Browse services by category
- 5-step booking flow: Service → Stylist → Date/Time → Details → Payment
- GCash receipt upload or PayPal redirect for 50% downpayment
- My Bookings with tabs: Upcoming, Accepted, Past, Cancelled, Transactions
- Cancel bookings (48-hour forfeiture policy enforced)
- Rate completed appointments (1–5 stars + comment)

### Admin Side
- Secure admin login (separate from client)
- Dashboard with today's stats, revenue, top services
- Booking management: accept, cancel, delete
- Stylist management: add, edit, delete (with active booking guard)
- Service management: add, edit, soft-delete
- Time slot management: select date + stylist, add/delete slots
- Transaction management: view, verify/reject GCash, filter by method/status/date
- Ratings dashboard: aggregated averages per stylist and service

### System
- Automated email notifications (booking, payment, cancellation, reminders)
- Double-booking prevention (slot locking at acceptance)
- CSRF protection on all POST forms
- PDO prepared statements throughout (SQL injection safe)
- XSS prevention via htmlspecialchars on all output
- Role-based access control (client vs admin sessions)
- GCash receipt files served via PHP proxy (not directly accessible)

---

## Security Notes

- All passwords are hashed with `bcrypt` (cost 12)
- CSRF tokens on every form
- Sessions expire after 60 min (client) / 30 min (admin) inactivity
- Direct access to `uploads/receipts/` is blocked via `.htaccess`
- File uploads validated by MIME type (not just extension)
- No raw SQL string interpolation — all queries use PDO prepared statements
