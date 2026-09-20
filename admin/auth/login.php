<?php
// Admin login is now unified with client login
require_once __DIR__ . '/../../config/config.php';
header('Location: ' . BASE_URL . '/public/auth/login.php');
exit;
