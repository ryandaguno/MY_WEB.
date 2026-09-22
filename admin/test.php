<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/SessionGuard.php';
SessionGuard::start();

echo "<pre style='font-size:16px;padding:20px'>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "Session Data:\n";
print_r($_SESSION);
echo "\n\nCookies:\n";
print_r($_COOKIE);
echo "</pre>";
