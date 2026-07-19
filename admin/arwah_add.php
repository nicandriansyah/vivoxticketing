<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo)                                 out(['ok' => false, 'error' => 'Database tidak tersedia']);

$regId = (int)($_POST['registration_id'] ?? 0);
$nama  = trim($_POST['nama_arwah'] ?? '');
$lahir = trim($_POST['tahun_lahir'] ?? '');
$wafat = trim($_POST['tahun_wafat'] ?? '');
$hub   = trim($_POST['hubungan_arwah'] ?? '');

if (!$regId)      out(['ok' => false, 'error' => 'Registrasi tidak valid']);
if ($nama === '') out(['ok' => false, 'error' => 'Nama arwah wajib diisi']);
$lahir = ($lahir !== '' && ctype_digit($lahir)) ? (int)$lahir : null;
$wafat = ($wafat !== '' && ctype_digit($wafat)) ? (int)$wafat : null;
if ($hub !== '' && !array_key_exists($hub, hubunganOptions())) $hub = '';

$stmt = $pdo->prepare("SELECT kode_tiket, jumlah_tiket FROM registrations WHERE id = ? LIMIT 1");
$stmt->execute([$regId]);
$reg = $stmt->fetch();
if (!$reg) out(['ok' => false, 'error' => 'Registrasi tidak ditemukan']);

// Batas slot: 1 arwah per tiket, maksimal 5
$cnt = $pdo->prepare("SELECT COUNT(*), COALESCE(MAX(urutan), 0) FROM arwah WHERE registration_id = ?");
$cnt->execute([$regId]);
list($count, $maxUrut) = $cnt->fetch(PDO::FETCH_NUM);
$maxSlots = min((int)$reg['jumlah_tiket'], 5);
if ((int)$count >= $maxSlots) out(['ok' => false, 'error' => 'Slot arwah sudah penuh (' . $maxSlots . ')']);

$urutan = (int)$maxUrut + 1;

/* ---------- Foto (opsional) — konvensi sama dengan process.php ---------- */
$fotoPath = null;
if (!empty($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['foto'];
    if ($file['error'] !== UPLOAD_ERR_OK) out(['ok' => false, 'error' => 'Upload foto gagal']);

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime]))          out(['ok' => false, 'error' => 'Foto harus JPG atau PNG']);
    if ($file['size'] > 2 * 1024 * 1024)  out(['ok' => false, 'error' => 'Ukuran foto maksimal 2MB']);

    $folder    = ($hub !== '') ? $hub : 'lainnya';
    $label     = hubunganLabel($hub !== '' ? $hub : null);
    $codeShort = preg_replace('/^FOAS14-/', '', $reg['kode_tiket']) . '-' . $urutan;
    $namaClean = html_entity_decode($nama, ENT_QUOTES, 'UTF-8');

    $parts = [$codeShort, $label, $namaClean, (string)($lahir ?? ''), (string)($wafat ?? '')];
    $name  = implode(' - ', $parts);
    $name  = str_replace(['/', '\\'], ' ', $name);
    $name  = preg_replace('#[:*?"<>|]+#', '', $name);
    $name  = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') $name = $codeShort;
    if (strlen($name) > 180) $name = substr($name, 0, 180);

    $dir = dirname(__DIR__) . '/uploads/' . $folder . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $filename = $name . '.' . $allowed[$mime];
    if (!@move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        out(['ok' => false, 'error' => 'Gagal menyimpan file foto']);
    }
    $fotoPath = $folder . '/' . $filename;
}

try {
    $pdo->prepare("INSERT INTO arwah (registration_id, urutan, nama_arwah, tahun_lahir, tahun_wafat, hubungan_arwah, foto_arwah)
                   VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$regId, $urutan, $nama, $lahir, $wafat, $hub !== '' ? $hub : null, $fotoPath]);
    // Tandai registrasi punya data arwah (dipakai flag di dashboard)
    $pdo->prepare("UPDATE registrations SET upload_arwah = 1 WHERE id = ?")->execute([$regId]);
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal menyimpan ke database']);
}

out(['ok' => true, 'urutan' => $urutan]);
