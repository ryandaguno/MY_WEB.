<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/PaymentHandler.php';
SessionGuard::requireAdmin();
$id     = (int)($_GET['id'] ?? 0);
$ph     = new PaymentHandler();
$result = $ph->rejectGCash($id);
SessionGuard::flashMessage($result['success']?'success':'error',
    $result['success'] ? 'Payment rejected. Client has been notified.' : $result['message']);
header('Location: ' . BASE_URL . '/admin/transactions/index.php');
exit;
