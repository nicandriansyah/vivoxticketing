<?php
/* Dipakai oleh admin/checkin.php (login admin) MAUPUN admin/checkin_staff.php
   (akses petugas lewat PIN) — jadi auth-nya terima salah satu dari dua sesi,
   bukan require_once auth.php yang cuma mengizinkan login admin penuh. */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function out(array $data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['checkin_pin_ok'])) {
    out(['ok' => false, 'error' => 'Sesi berakhir, silakan masuk ulang.']);
}
if (!$pdo) out(['ok' => false, 'error' => 'Database tidak tersedia.']);
try { ensureTicketTables($pdo); } catch (Exception $e) {}

$action = $_POST['action'] ?? $_GET['action'] ?? 'lookup';

/* ---------- Cari pemesan: nama atau nomor HP ---------- */
if ($action === 'search') {
    $q = trim($_POST['q'] ?? $_GET['q'] ?? '');
    if (mb_strlen($q) < 2) out(['ok' => true, 'results' => []]);
    $like = '%' . $q . '%';
    // Ambil lebih banyak kandidat dari yang ditampilkan — sebagian bakal
    // kesaring di bawah (pemesan yang semua tiketnya sudah check-in).
    $stmt = $pdo->prepare("SELECT id, nama, no_hp, jumlah_tiket FROM registrations
                            WHERE nama LIKE ? OR no_hp LIKE ?
                            ORDER BY created_at DESC LIMIT 40");
    $stmt->execute([$like, $like]);

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $regId  = (int)$r['id'];
        $jumlah = (int)$r['jumlah_tiket'];
        $aktif  = $jumlah - countCancelled($pdo, $regId);
        // Semua tiket aktifnya sudah check-in → tidak perlu muncul di pencarian lagi.
        if ($aktif > 0 && countChecked($pdo, $regId) >= $aktif) continue;

        $results[] = [
            'id'           => $regId,
            'nama'         => $r['nama'],
            'no_hp'        => $r['no_hp'],
            'jumlah_tiket' => $jumlah,
        ];
        if (count($results) >= 15) break;
    }
    out(['ok' => true, 'results' => $results]);
}

/* ---------- Daftar tiket milik 1 registrasi (buat checklist) ---------- */
if ($action === 'person') {
    $regId = (int)($_POST['reg_id'] ?? $_GET['reg_id'] ?? 0);
    if (!$regId) out(['ok' => false, 'error' => 'Registrasi tidak valid']);

    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
    $stmt->execute([$regId]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reg) out(['ok' => false, 'error' => 'Registrasi tidak ditemukan']);

    $jumlah    = (int)$reg['jumlah_tiket'];
    $allCodes  = deriveAllCodes($reg['kode_tiket'], $jumlah);
    $checked   = getCheckedMap($pdo, [$regId])[$regId]   ?? [];
    $cancelled = getCancelledMap($pdo, [$regId])[$regId] ?? [];

    $tickets = [];
    foreach ($allCodes as $i => $c) {
        if (in_array($c, $cancelled, true)) continue;   // tiket dibatalkan — tidak bisa check-in
        $tickets[] = [
            'code'    => $c,
            'seq'     => $i + 1,
            'checked' => in_array($c, $checked, true),
        ];
    }

    out([
        'ok'      => true,
        'nama'    => $reg['nama'],
        'no_hp'   => $reg['no_hp'],
        'jumlah'  => $jumlah,
        'tickets' => $tickets,
    ]);
}

$code = $_POST['code'] ?? $_GET['code'] ?? '';

$p = parseTicketCode($code);
if (!$p) out(['ok' => false, 'error' => 'Kode tiket tidak valid. Format: FOAS14-XXXXNNN']);

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
