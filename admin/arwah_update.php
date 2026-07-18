<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo)                                 out(['ok' => false, 'error' => 'Database tidak tersedia']);

$id    = (int)($_POST['id'] ?? 0);
$nama  = trim($_POST['nama_arwah'] ?? '');
$lahir = trim($_POST['tahun_lahir'] ?? '');
$wafat = trim($_POST['tahun_wafat'] ?? '');
$hub   = trim($_POST['hubungan_arwah'] ?? '');

if (!$id)           out(['ok' => false, 'error' => 'ID arwah tidak valid']);
if ($nama === '')   out(['ok' => false, 'error' => 'Nama arwah wajib diisi']);
$lahir = ($lahir !== '' && ctype_digit($lahir)) ? (int)$lahir : null;
$wafat = ($wafat !== '' && ctype_digit($wafat)) ? (int)$wafat : null;
if ($hub !== '' && !array_key_exists($hub, hubunganOptions())) $hub = '';

// Ambil row arwah + kode registrasi (untuk nama file foto)
$stmt = $pdo->prepare("SELECT a.*, r.kode_tiket FROM arwah a
                       JOIN registrations r ON r.id = a.registration_id
                       WHERE a.id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) out(['ok' => false, 'error' => 'Data arwah tidak ditemukan']);

/* ---------- Foto baru (opsional) — konvensi sama dengan process.php ---------- */
$fotoPath = $row['foto_arwah'];   // default: tetap yang lama
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
    $codeShort = preg_replace('/^FOAS14-/', '', $row['kode_tiket']) . '-' . (int)$row['urutan'];
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
    $newPath = $folder . '/' . $filename;

    // Hapus foto lama bila berbeda agar tidak jadi file yatim
    if ($fotoPath && $fotoPath !== $newPath) {
        @unlink(dirname(__DIR__) . '/uploads/' . $fotoPath);
    }
    $fotoPath = $newPath;
}

/* ---------- Sinkron slide_layout (dipakai PPT Generator) ---------- */
$layoutJson = $row['slide_layout'];
$L = json_decode($layoutJson ?? '', true);
if (is_array($L)) {
    // Teks di layout meng-override data row — samakan agar PPT ikut berubah
    if (isset($L['nama']))  $L['nama'] = $nama;
    if (isset($L['years'])) {
        $l = $lahir ? (string)$lahir : '';
        $w = $wafat ? (string)$wafat : '';
        $L['years'] = ($l && $w) ? "$l – $w" : ($l ?: $w);
    }
    $layoutJson = json_encode($L, JSON_UNESCAPED_UNICODE);
}

try {
    $upd = $pdo->prepare("UPDATE arwah SET nama_arwah = ?, tahun_lahir = ?, tahun_wafat = ?,
                          hubungan_arwah = ?, foto_arwah = ?, slide_layout = ? WHERE id = ?");
    $upd->execute([$nama, $lahir, $wafat, $hub !== '' ? $hub : null, $fotoPath, $layoutJson, $id]);
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal menyimpan ke database']);
}

out(['ok' => true]);
