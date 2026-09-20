<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::destroySession();
header('Location: ' . BASE_URL . '/public/auth/login.php');
exit;
