<?php
require_once __DIR__ . '/auth.php';
requireAdminRole();   // khusus role admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo)                                 out(['ok' => false, 'error' => 'Database tidak tersedia']);

$name  = trim($_POST['name'] ?? '');
$waRaw = $_POST['wa'] ?? '';

// Normalisasi nomor WA → internasional tanpa + (format wa.me), mis. 0812... → 62812...
$wa = preg_replace('/\D/', '', $waRaw);
$wa = preg_replace('/^0+/', '', $wa);
if ($wa !== '' && strpos($wa, '62') !== 0) $wa = '62' . $wa;

if ($name === '')       out(['ok' => false, 'error' => 'Nama kontak wajib diisi']);
if (strlen($wa) < 10)   out(['ok' => false, 'error' => 'Nomor WhatsApp tidak valid, isi format 62xxxxxxxxxx']);

try {
    ensureTicketTables($pdo);
    setSetting($pdo, 'help_name', $name);
    setSetting($pdo, 'help_wa',   $wa);
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal menyimpan ke database']);
}

out(['ok' => true, 'name' => $name, 'wa' => $wa]);
