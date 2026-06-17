<?php
session_start();
require_once 'config/db.php';
require_once 'config/mail.php';
require_once 'config/checkin.php';

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

/* ---------- Cek penjualan dibuka/ditutup + kuota (war tiket saat submit) ---------- */
try {
    ensureTicketTables($pdo);
    // Penjualan ditutup manual oleh admin
    if ((int)getSetting($pdo, 'sales_open', '1') !== 1) {
        header('Location: form.php?error=closed');
        exit;
    }
    $quota = (int)getSetting($pdo, 'ticket_quota', '0');
    if ($quota > 0) {
        $sold      = getTotalSold($pdo);
        $remaining = $quota - $sold;
        if ($remaining <= 0) {
            header('Location: form.php?error=habis');
            exit;
        }
        if ($jumlah_tiket > $remaining) {
            header('Location: form.php?error=sisa&n=' . $remaining);
            exit;
        }
    }
} catch (Exception $e) { /* jika gagal cek, lanjutkan saja */ }

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
require_once 'config/app.php';
$basePath  = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$ticketUrl = publicTicketUrl($kode_utama, $basePath);

$htmlBody   = buildTicketEmailHtml($nama, $ticket_codes, $jumlah_tiket, $ticketUrl);
$mailResult = sendSmtpMail($email, $nama, 'Tiket FOAS 13 — Vita Voxa Choir', $htmlBody);
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
