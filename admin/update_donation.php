<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo)                                 out(['ok' => false, 'error' => 'Database tidak tersedia']);

$bankShort = trim($_POST['bank_short'] ?? '');
$bankName  = trim($_POST['bank_name'] ?? '');
$account   = preg_replace('/\D/', '', $_POST['account'] ?? '');
$holder    = trim($_POST['holder'] ?? '');

if ($bankShort === '')       out(['ok' => false, 'error' => 'Nama singkat bank wajib diisi']);
if ($bankName === '')        out(['ok' => false, 'error' => 'Nama bank wajib diisi']);
if (strlen($account) < 5)    out(['ok' => false, 'error' => 'Nomor rekening tidak valid']);
if ($holder === '')          out(['ok' => false, 'error' => 'Nama pemilik rekening wajib diisi']);

try {
    ensureTicketTables($pdo);
    setSetting($pdo, 'don_bank_short', $bankShort);
    setSetting($pdo, 'don_bank_name',  $bankName);
    setSetting($pdo, 'don_account',    $account);
    setSetting($pdo, 'don_holder',     $holder);
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal menyimpan ke database']);
}

out(['ok' => true, 'account' => $account]);
