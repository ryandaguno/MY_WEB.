<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modules/SessionGuard.php';
require_once __DIR__ . '/../../modules/BookingManager.php';
SessionGuard::start();
header('Content-Type: application/json');
if (!SessionGuard::isClientLoggedIn()) { echo json_encode([]); exit; }

$serviceId = (int)($_GET['service_id'] ?? 0);
if (!$serviceId) { echo json_encode([]); exit; }

$bm = new BookingManager();
echo json_encode($bm->getAvailableStylists($serviceId));
