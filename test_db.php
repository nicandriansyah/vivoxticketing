<?php
/* ============================================================
   Halaman diagnostik DB — buka di browser:
       /test_db.php
       /test_db.php?token=FOAS14-VCTP001
   HAPUS file ini setelah selesai.
   ============================================================ */
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Konfigurasi DB ===\n";
echo "HOST : $DB_HOST\n";
echo "USER : $DB_USER\n";
echo "DB   : $DB_NAME\n";
echo "PASS : " . ($DB_PASS ? '(terisi)' : '(KOSONG)') . "\n";
echo "local override: " . (file_exists(__DIR__ . '/config/db.local.php') ? 'ADA' : 'TIDAK ADA') . "\n\n";

if (!$pdo) {
    echo "STATUS: KONEKSI GAGAL (\$pdo = null)\n";
    echo "-> Buat file config/db.local.php dengan kredensial DB UAT yang benar,\n";
    echo "   atau pastikan database '$DB_NAME' dan user '$DB_USER' sudah ada.\n";
    exit;
}

echo "STATUS: KONEKSI BERHASIL\n\n";

try {
    $count = $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
    echo "Jumlah registrasi di tabel: $count\n\n";
} catch (Exception $e) {
    echo "ERROR query tabel: " . $e->getMessage() . "\n";
    echo "-> Tabel 'registrations' mungkin belum dibuat (jalankan config/setup_uat.sql)\n";
    exit;
}

$token = trim($_GET['token'] ?? '');
if ($token) {
    $stmt = $pdo->prepare("SELECT kode_tiket, nama, email, jumlah_tiket, email_sent, created_at FROM registrations WHERE kode_tiket = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "=== Lookup token: $token ===\n";
    if ($row) {
        echo "DITEMUKAN:\n";
        foreach ($row as $k => $v) echo "  $k : $v\n";
    } else {
        echo "TIDAK DITEMUKAN di database.\n";
        echo "-> Record tidak tersimpan. Cek apakah DB aktif saat registrasi dibuat.\n";
    }
} else {
    echo "Tambahkan ?token=FOAS14-XXXX001 untuk cek tiket tertentu.\n";
    echo "\n5 registrasi terakhir:\n";
    $rows = $pdo->query("SELECT kode_tiket, nama, created_at FROM registrations ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) echo "  {$r['kode_tiket']}  |  {$r['nama']}  |  {$r['created_at']}\n";
    if (!$rows) echo "  (kosong)\n";
}
