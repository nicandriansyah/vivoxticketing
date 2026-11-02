<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
if ($pdo) { try { ensureTicketTables($pdo); } catch (Exception $e) {} }

$pageTitle  = 'Check-in Tiket';
$activeMenu = 'checkin';
require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/checkin_body.php';
require __DIR__ . '/partials/footer.php';
