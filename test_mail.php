<?php
/* ============================================================
   Halaman test SMTP — buka di browser:
       /test_mail.php?to=alamat@email.com
   Tampilkan hasil pengiriman. HAPUS file ini setelah selesai.
   ============================================================ */
require_once __DIR__ . '/config/mail.php';

header('Content-Type: text/plain; charset=UTF-8');

$to = filter_var($_GET['to'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$to) {
    echo "Gunakan: test_mail.php?to=alamat@email.com\n";
    echo "Config saat ini:\n";
    echo "  HOST   : $MAIL_HOST:$MAIL_PORT ($MAIL_SECURE)\n";
    echo "  USER   : $MAIL_USER\n";
    echo "  PASS   : " . ($MAIL_PASS ? '(terisi, ' . strlen($MAIL_PASS) . ' karakter)' : '(KOSONG — buat config/mail.local.php)') . "\n";
    exit;
}

$result = sendSmtpMail(
    $to,
    'Test',
    'Test Email FOAS 13',
    '<h2>Test berhasil!</h2><p>Konfigurasi SMTP Vita Voxa Choir sudah jalan.</p>'
);

if ($result === true) {
    echo "BERHASIL — email terkirim ke $to\n";
    echo "Cek inbox (dan folder Spam).\n";
} else {
    echo "GAGAL: $result\n";
}
