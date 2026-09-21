<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modules/SessionGuard.php';
SessionGuard::start();

if (SessionGuard::isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
} elseif (SessionGuard::isClientLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/home.php');
} else {
    // Show landing page first before login
    header('Location: ' . BASE_URL . '/public/home.php');
}
exit;
