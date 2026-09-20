<?php
/* ============================================================
   admin/timeslots/index.php  –  PERMANENTLY RETIRED
   Manual daily time slots are replaced by the weekly
   recurring schedule system.
   ============================================================ */
if (function_exists('opcache_invalidate')) opcache_invalidate(__FILE__, true);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/../../config/config.php';
header('Location: ' . BASE_URL . '/admin/stylist_schedule/index.php', true, 302);
exit;
