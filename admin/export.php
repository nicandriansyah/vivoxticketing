<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/helpers.php';

if (!$pdo) {
    http_response_code(500);
    exit('Database tidak tersedia.');
}

$q      = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';

$conds  = [];
$params = [];
list($sc, $sp) = buildSearchClause($q);
if ($sc) { $conds[] = $sc; $params = array_merge($params, $sp); }
if ($filter === 'email_fail') { $conds[] = 'email_sent = 0'; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$stmt = $pdo->prepare("SELECT * FROM registrations $where ORDER BY id DESC");
$stmt->execute($params);

$filename = 'registrasi-foas13-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// BOM agar Excel membaca UTF-8 dengan benar
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'ID', 'Tanggal', 'Kode Tiket Utama', 'Nama', 'No WhatsApp', 'Email',
    'Jumlah Tiket', 'Sumbangan', 'Upload Arwah', 'Arwah #', 'Nama Arwah',
    'Tahun Lahir', 'Tahun Wafat', 'Hubungan', 'Email Terkirim'
]);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $base = [
        $r['id'],
        $r['created_at'],
        $r['kode_tiket'],
        $r['nama'],
        $r['no_hp'],
        $r['email'],
        $r['jumlah_tiket'],
        (float)$r['sumbangan_amount'],
        $r['upload_arwah'] ? 'Ya' : 'Tidak',
    ];
    $tail = [$r['email_sent'] ? 'Ya' : 'Tidak'];

    $arwahRows = getArwahForReg($pdo, (int)$r['id']);
    if (!$arwahRows) {
        // Tanpa arwah → satu baris dengan kolom arwah kosong
        fputcsv($out, array_merge($base, ['', '', '', '', ''], $tail));
        continue;
    }
    // Satu baris per arwah, data registrasi diulang
    foreach ($arwahRows as $i => $a) {
        fputcsv($out, array_merge($base, [
            $i + 1,
            $a['nama_arwah'],
            $a['tahun_lahir'],
            $a['tahun_wafat'],
            hubunganLabel($a['hubungan_arwah']),
        ], $tail));
    }
}
fclose($out);
exit;
