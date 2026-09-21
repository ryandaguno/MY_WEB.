<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/Auth.php';
SessionGuard::start();

header('Content-Type: application/json');

if (SessionGuard::isClientLoggedIn()) {
    echo json_encode(['success' => false, 'errors' => [], 'message' => 'Already logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'errors' => [], 'message' => 'Invalid request.']);
    exit;
}

if (!SessionGuard::validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'errors' => [], 'message' => 'Session expired. Please refresh the page and try again.']);
    exit;
}

$auth   = new Auth();
$result = $auth->register($_POST);

if ($result['success']) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'errors' => $result['errors']]);
}
