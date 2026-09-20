<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/Auth.php';
SessionGuard::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/home.php'); exit;
}
if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) {
    die('Session expired. Please refresh and try again.');
}

$login    = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';
$auth     = new Auth();

if (empty($login) || empty($password)) {
    SessionGuard::flashMessage('error', 'Please enter your username/email and password.');
    header('Location: ' . BASE_URL . '/public/home.php'); exit;
}

// Try admin first
$adminResult = $auth->adminLogin($login, $password);
if ($adminResult['success']) {
    SessionGuard::setAdminSession($adminResult['admin']);
    header('Location: ' . BASE_URL . '/admin/dashboard.php'); exit;
}

// Try client
$clientResult = $auth->login($login, $password);
if ($clientResult['success']) {
    SessionGuard::setClientSession($clientResult['client']);
    header('Location: ' . BASE_URL . '/public/home.php'); exit;
}

SessionGuard::flashMessage('error', 'The username/email or password is incorrect.');
header('Location: ' . BASE_URL . '/public/home.php');
exit;
