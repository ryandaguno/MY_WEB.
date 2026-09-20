<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
SessionGuard::destroySession();
header('Location: ' . BASE_URL . '/admin/auth/login.php');
exit;
