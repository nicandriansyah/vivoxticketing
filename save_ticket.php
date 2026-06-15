<?php
/* ============================================================
   Simpan 1 tiket (JPG) ke folder /tickets saat reservasi dibuat.
   Dipanggil dari ticket.php (client-side html2canvas → upload).
   Nama file: <kode tiket>-<nama lengkap>.jpg
   ============================================================ */
session_start();
require_once 'config/db.php';
require_once 'config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo) out(['ok' => false, 'error' => 'no-db']);

$code  = trim($_POST['code'] ?? '');
$image = $_POST['image'] ?? '';

$p = parseTicketCode($code);
if (!$p) out(['ok' => false, 'error' => 'bad-code']);

// Validasi: kode harus milik registrasi yang ada
$stmt = $pdo->prepare("SELECT nama, jumlah_tiket FROM registrations WHERE kode_tiket = ? LIMIT 1");
$stmt->execute([$p['kode_utama']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) out(['ok' => false, 'error' => 'not-found']);
if ($p['seq'] < 1 || $p['seq'] > (int)$row['jumlah_tiket']) out(['ok' => false, 'error' => 'seq']);

// Decode data URL → biner JPEG
if (!preg_match('/^data:image\/jpeg;base64,/', $image)) out(['ok' => false, 'error' => 'bad-image']);
$raw = base64_decode(substr($image, strpos($image, ',') + 1), true);
if ($raw === false || strlen($raw) > 6 * 1024 * 1024) out(['ok' => false, 'error' => 'decode']);

// Pastikan benar-benar JPEG
$info = @getimagesizefromstring($raw);
if (!$info || $info[2] !== IMAGETYPE_JPEG) out(['ok' => false, 'error' => 'not-jpeg']);

// Bersihkan nama untuk dipakai di nama file
$nama = $row['nama'];
$nama = preg_replace('#[\\\\/:*?"<>|]+#', '', $nama);   // buang karakter ilegal nama file
$nama = trim(preg_replace('/\s+/', ' ', $nama));
if ($nama === '') $nama = 'peserta';

$dir = __DIR__ . '/tickets/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = $p['full'] . '-' . $nama . '.jpg';
$path     = $dir . $filename;

if (file_put_contents($path, $raw) === false) out(['ok' => false, 'error' => 'write']);

out(['ok' => true, 'file' => $filename]);
