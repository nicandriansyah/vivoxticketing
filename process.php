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
// Normalisasi no HP → murni lokal (buang +, spasi, kode negara 62, dan 0 di depan)
$no_hp          = preg_replace('/\D/', '', $_POST['no_hp'] ?? '');
$no_hp          = preg_replace('/^0+/', '', $no_hp);
if (strpos($no_hp, '62') === 0) $no_hp = substr($no_hp, 2);
$email          = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$jumlah_tiket   = max(1, min(5, (int)($_POST['jumlah_tiket']  ?? 1)));
$upload_arwah   = isset($_POST['upload_arwah']) ? 1 : 0;
$sumbangan      = max(0, (float)preg_replace('/[^0-9]/', '', $_POST['sumbangan_amount'] ?? '0'));

/* ---------- Basic validation ---------- */
if (!$nama || !$no_hp || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: form.php?error=invalid');
    exit;
}

/* ---------- Parse & validasi data arwah (server-side, maks 5 arwah) ---------- */
// Field dikirim sebagai array paralel: nama_arwah[], tahun_lahir[], tahun_wafat[],
// hubungan_arwah[], dan file foto_arwah[]. Client (form.js) memvalidasi hal yang
// sama, tapi server tetap jadi sumber kebenaran (bisa dilewati).
const MAX_ARWAH = 5;
$arwahList = []; // entri tervalidasi: ['nama','lahir','wafat','hubungan','file']

if ($upload_arwah) {
    $names  = $_POST['nama_arwah']     ?? [];
    $lahirs = $_POST['tahun_lahir']    ?? [];
    $wafats = $_POST['tahun_wafat']    ?? [];
    $hubs   = $_POST['hubungan_arwah'] ?? [];
    // Kompat: bila klien lama mengirim nilai tunggal (bukan array), bungkus.
    if (!is_array($names))  $names  = [$names];
    if (!is_array($lahirs)) $lahirs = [$lahirs];
    if (!is_array($wafats)) $wafats = [$wafats];
    if (!is_array($hubs))   $hubs   = [$hubs];

    $count    = min(MAX_ARWAH, max(count($names), count($lahirs), count($wafats), count($hubs)));
    $thisYear = (int)date('Y');

    for ($i = 0; $i < $count; $i++) {
        $nm = trim(htmlspecialchars($names[$i]  ?? ''));
        $lh = (int)($lahirs[$i] ?? 0) ?: null;
        $wf = (int)($wafats[$i] ?? 0) ?: null;
        $hb = array_key_exists($hubs[$i] ?? '', hubunganOptions()) ? $hubs[$i] : null;

        $file    = fileAtIndex($_FILES['foto_arwah'] ?? null, $i);
        $hasFile = $file !== null && $file['error'] !== UPLOAD_ERR_NO_FILE;

        // Baris kosong sepenuhnya → lewati (mis. entri sisa yang tak diisi)
        if ($nm === '' && $lh === null && $wf === null && $hb === null && !$hasFile) continue;

        // Nama wajib
        if ($nm === '') { header('Location: form.php?error=arwah_nama'); exit; }
        // Tahun lahir & wafat wajib dan dalam rentang wajar
        if ($lh === null || $wf === null
            || $lh < 1900 || $lh > $thisYear || $wf < 1900 || $wf > $thisYear) {
            header('Location: form.php?error=arwah_tahun'); exit;
        }
        // Wafat tidak boleh sebelum lahir
        if ($wf < $lh) { header('Location: form.php?error=arwah_tahun_urut'); exit; }
        // Hubungan wajib
        if ($hb === null) { header('Location: form.php?error=arwah_hubungan'); exit; }
        // Foto opsional: kalau dikirim, wajib valid (jangan gagal diam-diam)
        if ($hasFile) {
            $fotoErr = validateArwahPhoto($file);
            if ($fotoErr !== null) { header('Location: form.php?error=' . $fotoErr); exit; }
        }

        $arwahList[] = [
            'nama'     => $nm,
            'lahir'    => $lh,
            'wafat'    => $wf,
            'hubungan' => $hb,
            'file'     => $hasFile ? $file : null,
        ];
    }

    // Upload dicentang tapi tidak ada satupun arwah terisi → tolak
    if (!$arwahList) { header('Location: form.php?error=arwah_nama'); exit; }
    // Konsistenkan flag: hanya 1 jika benar ada arwah
    $upload_arwah = 1;
} else {
    $upload_arwah = 0;
}

/**
 * Validasi file foto arwah yang diunggah.
 * Return kode error (string) untuk ?error=... atau null jika valid.
 */
function validateArwahPhoto(array $file): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        // Gagal di level upload (mis. melebihi limit PHP, transfer terputus)
        return ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
             ? 'foto_besar' : 'foto_gagal';
    }
    if ($file['size'] > 2 * 1024 * 1024) return 'foto_besar';

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) return 'foto_format';

    return null;
}

/**
 * Ambil satu file dari $_FILES['foto_arwah'] pada index $i.
 * Mendukung input multiple (foto_arwah[]) maupun tunggal (kompat lama).
 * Return array file bergaya $_FILES atau null bila tidak ada.
 */
function fileAtIndex($files, int $i): ?array {
    if (!is_array($files) || !isset($files['name'])) return null;
    if (is_array($files['name'])) {                       // input multiple
        if (!array_key_exists($i, $files['name'])) return null;
        return [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i]     ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i]     ?? 0,
        ];
    }
    return $i === 0 ? $files : null;                      // input tunggal
}

/**
 * Simpan foto arwah ke folder per-kategori dengan nama file deskriptif:
 *   uploads/<kategori>/<KODE>-<n> - <Label Hubungan> - <Nama> - <Lahir> - <Wafat>.jpg
 * Return path relatif (kategori/namafile) atau null. $file diasumsikan sudah
 * lolos validateArwahPhoto() sebelum dipanggil.
 */
function saveArwahPhoto(array $file, string $kodeUtama, int $urutan, ?string $hubKey, string $namaArwah, $lahir, $wafat): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime]) || $file['size'] > 2 * 1024 * 1024) return null;

    $folder    = ($hubKey && array_key_exists($hubKey, hubunganOptions())) ? $hubKey : 'lainnya';
    $label     = hubunganLabel($hubKey);
    // Sertakan urutan agar nama file unik antar arwah dalam 1 registrasi
    $codeShort = preg_replace('/^FOAS14-/', '', $kodeUtama) . '-' . $urutan;   // 24LH001-1
    $namaClean = html_entity_decode($namaArwah, ENT_QUOTES, 'UTF-8');

    $parts = [$codeShort, $label, $namaClean, (string)($lahir ?? ''), (string)($wafat ?? '')];
    $name  = implode(' - ', $parts);
    $name  = str_replace(['/', '\\'], ' ', $name);                        // hapus pemisah path
    $name  = preg_replace('#[:*?"<>|]+#', '', $name);                     // karakter ilegal lain
    $name  = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') $name = $codeShort;
    if (strlen($name) > 180) $name = substr($name, 0, 180);              // batasi panjang nama file

    $dir = __DIR__ . '/uploads/' . $folder . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $filename = $name . '.' . $allowed[$mime];
    if (@move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return $folder . '/' . $filename;
    }
    return null;
}

/* ---------- Generate Ticket Codes ---------- */
// Format: FOAS14-XXXXNNN  (XXXX = batch acak, NNN = nomor urut 001, 002, ...)
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
    $ticket_codes[] = 'FOAS14-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
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
                $ticket_codes[$j] = 'FOAS14-' . $batch . str_pad($j + 1, 3, '0', STR_PAD_LEFT);
            }
        } else {
            break;
        }
    } while (true);
    $kode_utama = $ticket_codes[0];

    // Simpan foto tiap arwah (butuh kode tiket final) & siapkan baris
    $savedArwah = [];
    foreach ($arwahList as $idx => $a) {
        $urut = $idx + 1;
        $fp   = $a['file']
              ? saveArwahPhoto($a['file'], $kode_utama, $urut, $a['hubungan'], $a['nama'], $a['lahir'], $a['wafat'])
              : null;
        $savedArwah[] = $a + ['urutan' => $urut, 'foto_path' => $fp];
    }
    $first = $savedArwah[0] ?? null; // untuk mengisi kolom arwah legacy (kompat)

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
        $upload_arwah,
        $first['foto_path'] ?? null,
        $first['nama']      ?? null,
        $first['lahir']     ?? null,
        $first['wafat']     ?? null,
        $first['hubungan']  ?? null,
        $sumbangan
    ]);
    $regId = (int)$pdo->lastInsertId();

    // Simpan tiap arwah ke tabel anak
    if ($savedArwah) {
        $insA = $pdo->prepare("
            INSERT INTO arwah
                (registration_id, urutan, nama_arwah, tahun_lahir, tahun_wafat, hubungan_arwah, foto_arwah)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($savedArwah as $a) {
            $insA->execute([$regId, $a['urutan'], $a['nama'], $a['lahir'], $a['wafat'], $a['hubungan'], $a['foto_path']]);
        }
    }
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
    'arwah'         => array_map(fn($a) => [
        'nama'      => $a['nama'],
        'lahir'     => $a['lahir'],
        'wafat'     => $a['wafat'],
        'hubungan'  => $a['hubungan'],
        'foto_path' => $a['foto_path'],
    ], $savedArwah),
    'sumbangan'     => $sumbangan,
];

/* ---------- Send Email (SMTP) ---------- */
require_once 'config/app.php';
$basePath  = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$ticketUrl = publicTicketUrl($kode_utama, $basePath);

$htmlBody   = buildTicketEmailHtml($nama, $ticket_codes, $jumlah_tiket, $ticketUrl);
$mailResult = sendSmtpMail($email, $nama, 'Tiket FOAS 14 — Vita Voxa Choir', $htmlBody);
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
