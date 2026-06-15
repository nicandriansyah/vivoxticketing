<?php
session_start();
require_once 'config/db.php';
require_once 'config/mail.php';

// Set true untuk menampilkan error DB/email saat debugging di UAT.
// Kembalikan ke false setelah selesai supaya error tidak bocor ke user.
$DEBUG = true;

function debugDie(string $title, string $detail): void {
    global $DEBUG;
    if ($DEBUG) {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<div style="font-family:monospace;max-width:760px;margin:40px auto;padding:24px;'
           . 'border:2px solid #e05555;border-radius:10px;background:#fff5f5;color:#2c0000;">'
           . '<h2 style="margin:0 0 12px;color:#c0392b;">DEBUG: ' . htmlspecialchars($title) . '</h2>'
           . '<pre style="white-space:pre-wrap;word-break:break-word;font-size:14px;">'
           . htmlspecialchars($detail) . '</pre>'
           . '<p style="color:#888;font-size:13px;">Matikan dengan set <code>$DEBUG = false;</code> di process.php</p>'
           . '</div>';
        exit;
    }
    // Production: jangan bocorkan detail, kembali ke form
    header('Location: form.php?error=save');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: form.php');
    exit;
}

/* ---------- Sanitize Inputs ---------- */
$nama           = trim(htmlspecialchars($_POST['nama']         ?? ''));
$no_hp          = trim(htmlspecialchars($_POST['no_hp']        ?? ''));
$email          = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$jumlah_tiket   = max(1, min(5, (int)($_POST['jumlah_tiket']  ?? 1)));
$upload_arwah   = isset($_POST['upload_arwah']) ? 1 : 0;
$sumbangan      = max(0, (float)preg_replace('/[^0-9]/', '', $_POST['sumbangan_amount'] ?? '0'));

$nama_arwah     = trim(htmlspecialchars($_POST['nama_arwah']   ?? ''));
$tahun_lahir    = (int)($_POST['tahun_lahir']                  ?? 0) ?: null;
$tahun_wafat    = (int)($_POST['tahun_wafat']                  ?? 0) ?: null;
$hubungan       = in_array($_POST['hubungan_arwah'] ?? '', ['orang_tua','anak','saudara'])
                  ? $_POST['hubungan_arwah'] : null;

/* ---------- Basic validation ---------- */
if (!$nama || !$no_hp || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: form.php?error=invalid');
    exit;
}

/* ---------- File Upload ---------- */
$foto_path = null;
if ($upload_arwah && isset($_FILES['foto_arwah']) && $_FILES['foto_arwah']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $_FILES['foto_arwah']['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, $allowed) && $_FILES['foto_arwah']['size'] <= 2 * 1024 * 1024) {
        $ext       = pathinfo($_FILES['foto_arwah']['name'], PATHINFO_EXTENSION);
        $filename  = 'arwah_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (move_uploaded_file($_FILES['foto_arwah']['tmp_name'], $uploadDir . $filename)) {
            $foto_path = $filename;
        }
    }
}

/* ---------- Generate Ticket Codes ---------- */
// Format: FOAS13-XXXXNNN  (XXXX = batch acak, NNN = nomor urut 001, 002, ...)
function generateBatchId(): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $id = '';
    for ($i = 0; $i < 4; $i++) {
        $id .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $id;
}

$batch = generateBatchId();
$ticket_codes = [];
for ($i = 0; $i < $jumlah_tiket; $i++) {
    $ticket_codes[] = 'FOAS13-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
}
$kode_utama = $ticket_codes[0];

/* ---------- Save to DB ---------- */
if (!$pdo) {
    // Koneksi DB gagal — tampilkan error koneksi saat debug
    debugDie('Koneksi database GAGAL', $dbError ?? 'Penyebab tidak diketahui. Cek config/db.local.php (host/user/password/db).');
}

try {
    // Jika ada kode yang bentrok, ganti seluruh batch dengan batch ID baru
    do {
        $ph   = implode(',', array_fill(0, count($ticket_codes), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE kode_tiket IN ($ph)");
        $stmt->execute($ticket_codes);
        if ((int)$stmt->fetchColumn() > 0) {
            $batch = generateBatchId();
            for ($j = 0; $j < $jumlah_tiket; $j++) {
                $ticket_codes[$j] = 'FOAS13-' . $batch . str_pad($j + 1, 3, '0', STR_PAD_LEFT);
            }
        } else {
            break;
        }
    } while (true);
    $kode_utama = $ticket_codes[0];

    $stmt = $pdo->prepare("
        INSERT INTO registrations
            (kode_tiket, nama, no_hp, email, jumlah_tiket,
             upload_arwah, foto_arwah, nama_arwah, tahun_lahir, tahun_wafat,
             hubungan_arwah, sumbangan_amount)
        VALUES
            (?, ?, ?, ?, ?,  ?, ?, ?, ?, ?,  ?, ?)
    ");
    $stmt->execute([
        $kode_utama, $nama, $no_hp, $email, $jumlah_tiket,
        $upload_arwah, $foto_path, $upload_arwah ? $nama_arwah : null,
        $upload_arwah ? $tahun_lahir : null, $upload_arwah ? $tahun_wafat : null,
        $upload_arwah ? $hubungan : null,
        $sumbangan
    ]);
} catch (PDOException $e) {
    debugDie('Gagal menyimpan ke database', $e->getMessage());
}

/* ---------- Store in Session for Ticket Page ---------- */
$_SESSION['ticket'] = [
    'kode_utama'    => $kode_utama,
    'ticket_codes'  => $ticket_codes,          // array, 1 kode per tiket
    'nama'          => $nama,
    'no_hp'         => '+62' . $no_hp,
    'email'         => $email,
    'jumlah_tiket'  => $jumlah_tiket,
    'upload_arwah'  => $upload_arwah,
    'nama_arwah'    => $upload_arwah ? $nama_arwah   : null,
    'tahun_lahir'   => $upload_arwah ? $tahun_lahir  : null,
    'tahun_wafat'   => $upload_arwah ? $tahun_wafat  : null,
    'hubungan'      => $upload_arwah ? $hubungan      : null,
    'sumbangan'     => $sumbangan,
    'foto_path'     => $foto_path,
];

/* ---------- Send Email (SMTP) ---------- */
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$basePath  = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$ticketUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath . '/ticket.php?token=' . urlencode($kode_utama);

$rows = '';
foreach ($ticket_codes as $i => $kode) {
    $rows .= '<tr><td style="padding:6px 14px;border:1px solid #e0d8c4;font-size:14px;color:#6b4c2a;">Tiket ' . ($i + 1) . '</td>'
           . '<td style="padding:6px 14px;border:1px solid #e0d8c4;font-family:Consolas,monospace;font-weight:700;letter-spacing:1px;color:#1a0800;">' . htmlspecialchars($kode) . '</td></tr>';
}

$subject  = 'Tiket FOAS 13 — Vita Voxa Choir';
$htmlBody = '
<div style="background:#f4efe4;padding:24px;font-family:Arial,Helvetica,sans-serif;color:#2c1500;">
  <div style="max-width:560px;margin:0 auto;background:#fffdf8;border:1px solid #e0d8c4;border-radius:8px;overflow:hidden;">
    <div style="background:#1a0800;padding:24px;text-align:center;">
      <div style="color:#e8c66e;font-size:13px;letter-spacing:3px;">VITA VOXA CHOIR &middot; JAKARTA</div>
      <div style="color:#fff;font-size:30px;font-weight:900;letter-spacing:1px;margin-top:6px;">FOAS 13</div>
      <div style="color:#c9a84c;font-size:11px;letter-spacing:2px;margin-top:4px;">MENSANA IN CORPORE SANO</div>
    </div>
    <div style="padding:28px 26px;">
      <p style="font-size:16px;margin:0 0 14px;">Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
      <p style="font-size:15px;line-height:1.6;margin:0 0 18px;">Reservasi tiket Anda untuk <strong>FOAS 13</strong> telah <strong style="color:#1a7a40;">berhasil</strong>. Berikut detail tiket Anda:</p>
      <table style="border-collapse:collapse;width:100%;margin-bottom:20px;">' . $rows . '</table>
      <p style="font-size:14px;line-height:1.6;margin:0 0 6px;"><strong>Acara:</strong> Sabtu, 7 November 2026 &middot; 19.00 WIB</p>
      <p style="font-size:14px;line-height:1.6;margin:0 0 22px;"><strong>Jumlah:</strong> ' . $jumlah_tiket . ' tiket</p>
      <div style="text-align:center;margin:24px 0;">
        <a href="' . htmlspecialchars($ticketUrl) . '" style="display:inline-block;background:#c9a84c;color:#1a0800;text-decoration:none;font-weight:700;padding:13px 30px;border-radius:8px;font-size:15px;">Lihat &amp; Simpan Tiket Saya</a>
      </div>
      <p style="font-size:13px;color:#888;line-height:1.6;margin:18px 0 0;">Simpan email ini. Anda bisa membuka tiket kapan saja melalui tautan di atas, lalu menyimpannya sebagai gambar/PDF atau membagikannya ke WhatsApp.</p>
      <p style="font-size:13px;color:#888;line-height:1.6;margin:14px 0 0;">Tunjukkan QR code tiket saat memasuki venue.</p>
    </div>
    <div style="background:#f4efe4;padding:16px;text-align:center;font-size:12px;color:#9a7a55;">Sampai jumpa di FOAS 13!<br>&mdash; Vita Voxa Choir</div>
  </div>
</div>';

$mailResult = sendSmtpMail($email, $nama, $subject, $htmlBody);
$emailSent  = ($mailResult === true) ? 1 : 0;

// Update flag email_sent di DB
if ($pdo && $emailSent) {
    try {
        $upd = $pdo->prepare("UPDATE registrations SET email_sent = 1 WHERE kode_tiket = ?");
        $upd->execute([$kode_utama]);
    } catch (Exception $e) { /* abaikan */ }
}

header('Location: ticket.php?token=' . urlencode($kode_utama) . '&new=1');
exit;
