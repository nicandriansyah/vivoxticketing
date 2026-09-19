<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
try { ensureTicketTables($pdo); } catch (Exception $e) {}

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo)                                 out(['ok' => false, 'error' => 'Database tidak tersedia']);

// registration_id kosong/0 = arwah "lepas" (upload langsung dari PPT Generator,
// tidak terikat tiket manapun).
$regId = (int)($_POST['registration_id'] ?? 0);
$nama  = trim($_POST['nama_arwah'] ?? '');
$lahir = trim($_POST['tahun_lahir'] ?? '');
$wafat = trim($_POST['tahun_wafat'] ?? '');
$hub   = trim($_POST['hubungan_arwah'] ?? '');

if ($nama === '') out(['ok' => false, 'error' => 'Nama arwah wajib diisi']);
$lahir = ($lahir !== '' && ctype_digit($lahir)) ? (int)$lahir : null;
$wafat = ($wafat !== '' && ctype_digit($wafat)) ? (int)$wafat : null;
if ($hub !== '' && !array_key_exists($hub, hubunganOptions())) $hub = '';

$kodeTiket = null;
if ($regId) {
    $stmt = $pdo->prepare("SELECT kode_tiket FROM registrations WHERE id = ? LIMIT 1");
    $stmt->execute([$regId]);
    $reg = $stmt->fetch();
    if (!$reg) out(['ok' => false, 'error' => 'Registrasi tidak ditemukan']);
    $kodeTiket = $reg['kode_tiket'];
} else {
    // Arwah lepas wajib punya kategori — itu satu-satunya penentu tab PPT tempat dia muncul.
    if ($hub === '') out(['ok' => false, 'error' => 'Kategori wajib dipilih untuk foto tanpa tiket']);
}

// Jumlah arwah tidak dibatasi
if ($regId) {
    $cnt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) FROM arwah WHERE registration_id = ?");
    $cnt->execute([$regId]);
} else {
    $cnt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) FROM arwah WHERE registration_id IS NULL");
    $cnt->execute();
}
$urutan = (int)$cnt->fetchColumn() + 1;

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

    $folder    = ($hub !== '') ? $hub : 'lainnya';
    $label     = hubunganLabel($hub !== '' ? $hub : null);
    $codeShort = $kodeTiket
        ? preg_replace('/^FOAS14-/', '', $kodeTiket) . '-' . $urutan
        : 'MANUAL-' . str_pad((string)$urutan, 3, '0', STR_PAD_LEFT);
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
    if (!compressUploadedImage($file['tmp_name'], $mime, $dir . $filename)) {
        out(['ok' => false, 'error' => 'Gagal menyimpan file foto']);
    }
    $fotoPath = $folder . '/' . $filename;
}

try {
    $pdo->prepare("INSERT INTO arwah (registration_id, urutan, nama_arwah, tahun_lahir, tahun_wafat, hubungan_arwah, foto_arwah)
                   VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$regId ?: null, $urutan, $nama, $lahir, $wafat, $hub !== '' ? $hub : null, $fotoPath]);
    // Tandai registrasi punya data arwah (dipakai flag di dashboard) — hanya bila terikat registrasi
    if ($regId) {
        $pdo->prepare("UPDATE registrations SET upload_arwah = 1 WHERE id = ?")->execute([$regId]);
    }
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal menyimpan ke database']);
}

out(['ok' => true, 'urutan' => $urutan]);
