<?php
/* Registrasi tiket walk-in/OTS oleh admin/petugas — versi simpel dari
   process.php (tanpa arwah, tanpa sumbangan, tanpa upload foto), lalu
   redirect ke halaman detail registrasi yang baru dibuat. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/../config/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register_ots.php');
    exit;
}

/* ---------- Sanitize & validasi ---------- */
$nama  = trim(htmlspecialchars($_POST['nama'] ?? ''));
$no_hp = preg_replace('/\D/', '', $_POST['no_hp'] ?? '');
$no_hp = preg_replace('/^0+/', '', $no_hp);
if (strpos($no_hp, '62') === 0) $no_hp = substr($no_hp, 2);
$email        = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$jumlah_tiket = max(1, (int)($_POST['jumlah_tiket'] ?? 1));

if (!$nama || !$no_hp || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register_ots.php?error=invalid');
    exit;
}

if (!$pdo) {
    header('Location: register_ots.php?error=save');
    exit;
}

/* ---------- Cek kuota (OTS sengaja TIDAK terikat toggle "Penjualan" —
   itu buat tutup penjualan online, sementara walk-in hari-H tetap boleh
   didaftarkan admin/petugas selama kuota venue masih ada) ---------- */
try {
    ensureTicketTables($pdo);
    $quota = (int)getSetting($pdo, 'ticket_quota', '0');
    if ($quota > 0) {
        $sold      = getTotalSold($pdo);
        $remaining = $quota - $sold;
        if ($remaining <= 0) {
            header('Location: register_ots.php?error=habis');
            exit;
        }
        if ($jumlah_tiket > $remaining) {
            header('Location: register_ots.php?error=sisa&n=' . $remaining);
            exit;
        }
    }
} catch (Exception $e) { /* jika gagal cek, lanjutkan saja */ }

/* ---------- Generate kode tiket (FOAS14-XXXXNNN) ---------- */
function otsGenerateBatchId(): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $id = '';
    for ($i = 0; $i < 4; $i++) $id .= $chars[random_int(0, strlen($chars) - 1)];
    return $id;
}

$batch = otsGenerateBatchId();
$ticket_codes = [];
for ($i = 0; $i < $jumlah_tiket; $i++) {
    $ticket_codes[] = 'FOAS14-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
}

try {
    // Hindari bentrok kode (sangat jarang, tapi sama seperti process.php)
    do {
        $ph   = implode(',', array_fill(0, count($ticket_codes), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE kode_tiket IN ($ph)");
        $stmt->execute($ticket_codes);
        if ((int)$stmt->fetchColumn() > 0) {
            $batch = otsGenerateBatchId();
            for ($j = 0; $j < $jumlah_tiket; $j++) {
                $ticket_codes[$j] = 'FOAS14-' . $batch . str_pad($j + 1, 3, '0', STR_PAD_LEFT);
            }
        } else {
            break;
        }
    } while (true);
    $kode_utama = $ticket_codes[0];

    $stmt = $pdo->prepare("
        INSERT INTO registrations (kode_tiket, nama, no_hp, email, jumlah_tiket, sumbangan_amount)
        VALUES (?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([$kode_utama, $nama, $no_hp, $email, $jumlah_tiket]);
    $regId = (int)$pdo->lastInsertId();
} catch (PDOException $e) {
    header('Location: register_ots.php?error=save');
    exit;
}

/* ---------- Kirim email tiket ---------- */
$basePath  = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['PHP_SELF']))), '/');   // admin/ -> root
$ticketUrl = publicTicketUrl($kode_utama, $basePath);

$htmlBody   = buildTicketEmailHtml($nama, $ticket_codes, $jumlah_tiket, $ticketUrl);
$mailResult = sendSmtpMail($email, $nama, 'Tiket FOAS 14 — Vita Voxa Choir', $htmlBody);

if ($mailResult === true) {
    try {
        $pdo->prepare("UPDATE registrations SET email_sent = 1 WHERE id = ?")->execute([$regId]);
    } catch (Exception $e) { /* abaikan */ }
}

header('Location: edit.php?id=' . $regId . '&msg=ots');
exit;
