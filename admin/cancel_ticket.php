<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (!$pdo) out(['ok' => false, 'error' => 'Database tidak tersedia.']);
try { ensureTicketTables($pdo); } catch (Exception $e) {}

$code = $_POST['code'] ?? '';
$p = parseTicketCode($code);
if (!$p) out(['ok' => false, 'error' => 'Kode tiket tidak valid.']);

$stmt = $pdo->prepare("SELECT * FROM registrations WHERE kode_tiket = ? LIMIT 1");
$stmt->execute([$p['kode_utama']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) out(['ok' => false, 'error' => 'Tiket tidak ditemukan.']);

$jumlah = (int)$row['jumlah_tiket'];
if ($p['seq'] < 1 || $p['seq'] > $jumlah) out(['ok' => false, 'error' => 'Nomor tiket di luar jangkauan.']);

// Sudah check-in → tidak boleh dibatalkan
$c = $pdo->prepare("SELECT 1 FROM checkins WHERE kode_tiket = ? LIMIT 1");
$c->execute([$p['full']]);
if ($c->fetchColumn()) out(['ok' => false, 'error' => 'Tiket sudah check-in, tidak bisa dibatalkan.']);

// Sudah dibatalkan?
$x = $pdo->prepare("SELECT 1 FROM cancelled_tickets WHERE kode_tiket = ? LIMIT 1");
$x->execute([$p['full']]);
if ($x->fetchColumn()) out(['ok' => false, 'error' => 'Tiket ini sudah dibatalkan.']);

try {
    $pdo->prepare("INSERT INTO cancelled_tickets (registration_id, kode_tiket) VALUES (?, ?)")
        ->execute([(int)$row['id'], $p['full']]);
} catch (PDOException $e) {
    out(['ok' => false, 'error' => 'Gagal membatalkan tiket.']);
}

out(['ok' => true, 'message' => 'Tiket dibatalkan.', 'code' => $p['full']]);
