<?php
require_once __DIR__ . '/auth.php';
requireAdminRole();   // khusus role admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo)                                 out(['ok' => false, 'error' => 'Database tidak tersedia']);

$pin = trim($_POST['pin'] ?? '');
if ($pin !== '' && strlen($pin) < 4) out(['ok' => false, 'error' => 'PIN minimal 4 karakter']);

try {
    ensureTicketTables($pdo);
    setSetting($pdo, 'checkin_pin', $pin);
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal menyimpan ke database']);
}

out(['ok' => true]);
