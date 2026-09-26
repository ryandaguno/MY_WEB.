<?php
// Application Configuration
// Reads from Railway environment variables in production, falls back to localhost for XAMPP

// Set timezone to Philippines time — ensures all date/time operations are correct
date_default_timezone_set('Asia/Manila');

define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/selah-aesthetics-Backup');

// Database — Railway injects these automatically when you add a MySQL service
define('DB_HOST',    getenv('MYSQLHOST')     ?: 'localhost');
define('DB_NAME',    getenv('MYSQLDATABASE') ?: 'selah_aesthetics');
define('DB_USER',    getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS',    getenv('MYSQLPASSWORD') ?: '');
define('DB_PORT',    getenv('MYSQLPORT')     ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// Session timeouts (seconds)
define('SESSION_TIMEOUT_CLIENT', 3600);   // 60 minutes
define('SESSION_TIMEOUT_ADMIN',  1800);   // 30 minutes

// Login lockout
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION_MINUTES', 15);

// File uploads
define('UPLOAD_PATH', __DIR__ . '/../uploads/receipts/');
define('MAX_FILE_SIZE', 5242880); // 5 MB
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Email (PHPMailer SMTP) — set MAIL_PASSWORD as a Railway env variable, never hardcode
define('MAIL_HOST',      getenv('MAIL_HOST')     ?: 'smtp.gmail.com');
define('MAIL_PORT',      getenv('MAIL_PORT')     ?: 587);
define('MAIL_USERNAME',  getenv('MAIL_USERNAME') ?: 'joshuanasayre310@gmail.com');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM',      getenv('MAIL_FROM')     ?: 'joshuanasayre310@gmail.com');
define('MAIL_FROM_NAME', 'Selah Aesthetics');

// GCash
define('GCASH_NUMBER',  '09635711520');
define('GCASH_QR_PATH', BASE_URL . '/assets/images/gcash_qr.png');

// PayPal — set these as Railway env variables
define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID') ?: 'YOUR_PAYPAL_CLIENT_ID');
define('PAYPAL_SECRET',    getenv('PAYPAL_SECRET')    ?: 'YOUR_PAYPAL_SECRET');
define('PAYPAL_MODE',      getenv('PAYPAL_MODE')      ?: 'sandbox'); // 'sandbox' or 'live'

// Error display — always false in production
define('SHOW_ERRORS', getenv('SHOW_ERRORS') === 'true');

if (SHOW_ERRORS) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
