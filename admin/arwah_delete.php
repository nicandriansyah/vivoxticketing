<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

header('Content-Type: application/json; charset=UTF-8');
function out(array $d) { echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method']);
if (!$pdo)                                 out(['ok' => false, 'error' => 'Database tidak tersedia']);

$id = (int)($_POST['id'] ?? 0);
if (!$id) out(['ok' => false, 'error' => 'ID arwah tidak valid']);

$stmt = $pdo->prepare("SELECT registration_id, foto_arwah FROM arwah WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) out(['ok' => false, 'error' => 'Data arwah tidak ditemukan']);

try {
    $pdo->prepare("DELETE FROM arwah WHERE id = ?")->execute([$id]);

    // Hapus file foto agar tidak jadi file yatim
    if ($row['foto_arwah']) {
        @unlink(dirname(__DIR__) . '/uploads/' . $row['foto_arwah']);
    }

    // Bila tak ada arwah tersisa, matikan flag upload_arwah registrasi
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM arwah WHERE registration_id = ?");
    $cnt->execute([(int)$row['registration_id']]);
    if ((int)$cnt->fetchColumn() === 0) {
        $pdo->prepare("UPDATE registrations SET upload_arwah = 0 WHERE id = ?")
            ->execute([(int)$row['registration_id']]);
    }
} catch (Exception $e) {
    out(['ok' => false, 'error' => 'Gagal menghapus dari database']);
}

out(['ok' => true]);
