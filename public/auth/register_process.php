<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/Auth.php';
SessionGuard::start();

if (SessionGuard::isClientLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/home.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/home.php'); exit;
}

if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) {
    die('Session expired. Please refresh and try again.');
}

$auth   = new Auth();
$result = $auth->register($_POST);

if ($result['success']) {
    header('Location: ' . BASE_URL . '/public/auth/pending_approval.php'); exit;
} else {
    // Save form data and errors to session, redirect back to home with register modal open
    $_SESSION['reg_form']   = $_POST;
    $_SESSION['reg_errors'] = $result['errors'];
    header('Location: ' . BASE_URL . '/public/home.php?modal=register'); exit;
}
