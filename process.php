<?php
session_start();
require_once 'config/db.php';

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
$sumbangan      = max(0, (float)($_POST['sumbangan_amount']    ?? 0));

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

/* ---------- Generate Ticket Codes (satu per tiket) ---------- */
function generateCode(): string {
    return 'FOAS13-' . strtoupper(bin2hex(random_bytes(4)));
}

// Generate N unique codes sesuai jumlah tiket
$ticket_codes = [];
for ($i = 0; $i < $jumlah_tiket; $i++) {
    $ticket_codes[] = generateCode();
}
$kode_utama = $ticket_codes[0]; // dipakai sebagai primary key di DB

/* ---------- Save to DB ---------- */
if ($pdo) {
    // Pastikan kode-kode unik
    foreach ($ticket_codes as &$kode) {
        do {
            $stmt = $pdo->prepare("SELECT id FROM registrations WHERE kode_tiket = ?");
            $stmt->execute([$kode]);
            if ($stmt->fetch()) $kode = generateCode(); else break;
        } while (true);
    }
    unset($kode);
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

/* ---------- Send Email (basic) ---------- */
$kode_list_text = implode("\n             ", $ticket_codes);
$subject = "Tiket FOAS 13 — Vita Voxa Choir";
$body    = "Halo $nama,\n\n"
         . "Reservasi tiket Anda untuk FOAS 13 telah berhasil!\n\n"
         . "Jumlah     : $jumlah_tiket tiket\n"
         . "Kode Tiket : $kode_list_text\n"
         . "Acara      : Sabtu, 7 November — 19.00 WIB\n\n"
         . "Tunjukkan kode tiket ini saat memasuki venue.\n\n"
         . "Sampai jumpa di FOAS 13!\n"
         . "— Vita Voxa Choir";

@mail($email, $subject, $body, "From: noreply@vitavoxachoir.com\r\nContent-Type: text/plain; charset=UTF-8");

header('Location: ticket.php');
exit;
