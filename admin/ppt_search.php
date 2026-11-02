<?php
/* Cari nama arwah LINTAS SEMUA kategori (dipakai kotak search di atas tab
   kategori pada admin/ppt.php) — beda dari pencarian per-kategori yang
   sebelumnya cuma nyari di data yang sudah ke-render di satu tab aktif. */
require_once __DIR__ . '/auth.php';
requireAdminRole();   // khusus role admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (!$pdo) out(['ok' => false, 'error' => 'Database tidak tersedia']);

$q = trim($_GET['q'] ?? $_POST['q'] ?? '');
if (mb_strlen($q) < 1) out(['ok' => true, 'results' => []]);

try {
    ensureTicketTables($pdo);
    $stmt = $pdo->prepare("SELECT id, nama_arwah, hubungan_arwah FROM arwah
                            WHERE nama_arwah LIKE ? AND nama_arwah <> '' AND hubungan_arwah IS NOT NULL
                            ORDER BY nama_arwah LIMIT 20");
    $stmt->execute(['%' . $q . '%']);

    $results = array_map(function ($r) {
        return [
            'id'        => (int)$r['id'],
            'nama'      => $r['nama_arwah'],
            'cat'       => $r['hubungan_arwah'],
            'cat_label' => hubunganLabel($r['hubungan_arwah']),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    out(['ok' => true, 'results' => $results]);
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal mencari']);
}
