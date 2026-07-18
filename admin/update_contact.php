<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo)                                 out(['ok' => false, 'error' => 'Database tidak tersedia']);

$id       = (int)($_POST['id'] ?? 0);
$phoneRaw = $_POST['no_hp'] ?? '';
$email    = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));

if (!$id) out(['ok' => false, 'error' => 'ID tidak valid']);

// Normalisasi no HP → murni lokal (sama seperti process.php)
$no_hp = preg_replace('/\D/', '', $phoneRaw);
$no_hp = preg_replace('/^0+/', '', $no_hp);
if (strpos($no_hp, '62') === 0) $no_hp = substr($no_hp, 2);

// Validasi
if ($no_hp === '')                                   out(['ok' => false, 'error' => 'Nomor WhatsApp wajib diisi']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL))      out(['ok' => false, 'error' => 'Email tidak valid']);

try {
    $stmt = $pdo->prepare("UPDATE registrations SET no_hp = ?, email = ? WHERE id = ?");
    $stmt->execute([$no_hp, $email, $id]);
    if ($stmt->rowCount() === 0) {
        // Cek apakah id memang ada (rowCount 0 bisa berarti nilai sama)
        $chk = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE id = ?");
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() === 0) out(['ok' => false, 'error' => 'Registrasi tidak ditemukan']);
    }
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal menyimpan ke database']);
}

out(['ok' => true, 'no_hp' => phoneDisplay($no_hp), 'email' => $email]);
