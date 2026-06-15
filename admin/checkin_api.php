<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');

function out(array $data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

if (!$pdo) out(['ok' => false, 'error' => 'Database tidak tersedia.']);
try { ensureTicketTables($pdo); } catch (Exception $e) {}

$action = $_POST['action'] ?? $_GET['action'] ?? 'lookup';
$code   = $_POST['code']   ?? $_GET['code']   ?? '';

$p = parseTicketCode($code);
if (!$p) out(['ok' => false, 'error' => 'Kode tiket tidak valid. Format: FOAS13-XXXXNNN']);

// Cari registrasi
$stmt = $pdo->prepare("SELECT * FROM registrations WHERE kode_tiket = ? LIMIT 1");
$stmt->execute([$p['kode_utama']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) out(['ok' => false, 'error' => 'Tiket tidak ditemukan.']);

$jumlah = (int)$row['jumlah_tiket'];
if ($p['seq'] < 1 || $p['seq'] > $jumlah) {
    out(['ok' => false, 'error' => "Nomor tiket {$p['seq']} di luar jangkauan (1-{$jumlah})."]);
}

// Sudah check-in?
$cstmt = $pdo->prepare("SELECT checked_in_at FROM checkins WHERE kode_tiket = ? LIMIT 1");
$cstmt->execute([$p['full']]);
$existing = $cstmt->fetch(PDO::FETCH_ASSOC);

// Tiket dibatalkan?
$xstmt = $pdo->prepare("SELECT 1 FROM cancelled_tickets WHERE kode_tiket = ? LIMIT 1");
$xstmt->execute([$p['full']]);
if ($xstmt->fetchColumn()) {
    out(['ok' => false, 'error' => 'Tiket ini sudah DIBATALKAN, tidak bisa check-in.']);
}

$info = [
    'nama'       => $row['nama'],
    'no_hp'      => $row['no_hp'],
    'email'      => $row['email'],
    'code'       => $p['full'],
    'seq'        => $p['seq'],
    'jumlah'     => $jumlah,
    'total_in'   => countChecked($pdo, (int)$row['id']),
];

if ($action === 'lookup') {
    $info['ok']         = true;
    $info['already']    = (bool)$existing;
    $info['checked_at'] = $existing['checked_in_at'] ?? null;
    out($info);
}

if ($action === 'checkin') {
    if ($existing) {
        out(['ok' => false, 'error' => 'Tiket ini SUDAH check-in pada ' . $existing['checked_in_at'], 'already' => true] + $info);
    }
    try {
        $ins = $pdo->prepare("INSERT INTO checkins (registration_id, kode_tiket) VALUES (?, ?)");
        $ins->execute([(int)$row['id'], $p['full']]);
    } catch (PDOException $e) {
        // Kemungkinan duplikat (race) — anggap sudah check-in
        out(['ok' => false, 'error' => 'Tiket ini sudah check-in.', 'already' => true] + $info);
    }
    $info['ok']       = true;
    $info['message']  = 'Check-in berhasil!';
    $info['total_in'] = countChecked($pdo, (int)$row['id']);
    out($info);
}

out(['ok' => false, 'error' => 'Action tidak dikenal.']);
