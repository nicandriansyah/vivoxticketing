<?php
/* ============================================================
   Email (SMTP) configuration + minimal SMTP sender
   ------------------------------------------------------------
   Default values below are placeholders. Override the real
   credentials in config/mail.local.php (gitignored) — same
   pattern as db.local.php.
   ============================================================ */

$MAIL_HOST       = 'smtp.gmail.com';
$MAIL_PORT       = 587;                       // 465 = SSL implicit, 587 = STARTTLS
$MAIL_SECURE     = 'tls';                     // 'ssl' untuk 465, 'tls' untuk 587
$MAIL_USER       = 'sandbox@parokigrogolkaj.or.id';                        // diisi di config/mail.local.php
$MAIL_PASS       = 'bozn jyto ieym tpgi';                        // diisi di config/mail.local.php
$MAIL_FROM       = 'sandbox@parokigrogolkaj.or.id';                        // diisi di config/mail.local.php
$MAIL_FROM_NAME  = 'Email Broadcaster';


$localCfg = __DIR__ . '/mail.local.php';
if (file_exists($localCfg)) require_once $localCfg;

/**
 * Send an HTML email via SMTP (AUTH LOGIN).
 * Returns true on success, or an error string on failure.
 */
function sendSmtpMail(string $toEmail, string $toName, string $subject, string $htmlBody) {
    global $MAIL_HOST, $MAIL_PORT, $MAIL_SECURE, $MAIL_USER, $MAIL_PASS, $MAIL_FROM, $MAIL_FROM_NAME;

    // Transcript handshake SMTP untuk debugging (lihat $GLOBALS['smtp_transcript'])
    $GLOBALS['smtp_transcript'] = '';
    $log = function ($text) { $GLOBALS['smtp_transcript'] .= $text; };

    if (empty($MAIL_PASS)) return 'SMTP password not configured';

    $remote = ($MAIL_SECURE === 'ssl' ? 'ssl://' : '') . $MAIL_HOST . ':' . $MAIL_PORT;
    $log("CONNECT $remote\n");
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
    ]);

    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return "Connect failed: $errstr ($errno)";
    stream_set_timeout($fp, 15);

    // Read a (possibly multiline) SMTP reply; verify expected code.
    $read = function () use ($fp, $log) {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            // last line has a space at position 3 (e.g. "250 OK"), continuation has "-"
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $log('S: ' . $data);
        return $data;
    };
    $expect = function (string $resp, string $code) {
        return strncmp($resp, $code, 3) === 0;
    };
    $write = function (string $cmd) use ($fp, $log) {
        // Jangan log isi base64 kredensial
        $shown = (strlen($cmd) > 40 && ctype_print($cmd) && strpos($cmd, ' ') === false) ? '(base64 hidden)' : $cmd;
        $log('C: ' . $shown . "\n");
        fwrite($fp, $cmd . "\r\n");
    };

    $fail = function (string $msg) use ($fp) {
        @fclose($fp);
        return $msg;
    };

    if (!$expect($read(), '220')) return $fail('No 220 greeting');

    $ehloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $write('EHLO ' . $ehloHost);
    if (!$expect($read(), '250')) return $fail('EHLO rejected');

    // STARTTLS for port 587
    if ($MAIL_SECURE === 'tls') {
        $write('STARTTLS');
        if (!$expect($read(), '220')) return $fail('STARTTLS rejected');
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!stream_socket_enable_crypto($fp, true, $crypto)) {
            return $fail('TLS negotiation failed');
        }
        $write('EHLO ' . $ehloHost);
        if (!$expect($read(), '250')) return $fail('EHLO (post-TLS) rejected');
    }

    $write('AUTH LOGIN');
    if (!$expect($read(), '334')) return $fail('AUTH LOGIN rejected');
    $write(base64_encode($MAIL_USER));
    if (!$expect($read(), '334')) return $fail('Username rejected');
    $write(base64_encode($MAIL_PASS));
    if (!$expect($read(), '235')) return $fail('Authentication failed (cek user/password)');

    $write('MAIL FROM:<' . $MAIL_FROM . '>');
    if (!$expect($read(), '250')) return $fail('MAIL FROM rejected');
    $write('RCPT TO:<' . $toEmail . '>');
    $rcpt = $read();
    if (!$expect($rcpt, '250') && !$expect($rcpt, '251')) return $fail('RCPT TO rejected');

    $write('DATA');
    if (!$expect($read(), '354')) return $fail('DATA rejected');

    $boundary  = '=_alt_' . bin2hex(random_bytes(12));
    $fromDomain = substr(strrchr($MAIL_FROM, '@'), 1) ?: 'localhost';
    $messageId  = '<' . bin2hex(random_bytes(16)) . '@' . $fromDomain . '>';

    // Plain-text alternative (strip HTML) — multipart/alternative is far less
    // likely to be flagged as spam than HTML-only.
    $textBody = preg_replace('/<a [^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i', '$2: $1', $htmlBody);
    $textBody = preg_replace('/<(br|tr|\/div|\/p|\/h[1-6])[^>]*>/i', "\n", $textBody);
    $textBody = html_entity_decode(strip_tags($textBody), ENT_QUOTES, 'UTF-8');
    $textBody = trim(preg_replace("/[ \t]*\n{3,}/", "\n\n", $textBody));

    $headers  = 'From: ' . encodeHeader($MAIL_FROM_NAME) . ' <' . $MAIL_FROM . ">\r\n";
    $headers .= 'To: ' . encodeHeader($toName) . ' <' . $toEmail . ">\r\n";
    $headers .= 'Reply-To: ' . encodeHeader($MAIL_FROM_NAME) . ' <' . $MAIL_FROM . ">\r\n";
    $headers .= 'Subject: ' . encodeHeader($subject) . "\r\n";
    $headers .= 'Message-ID: ' . $messageId . "\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";

    $body  = '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($textBody)) . "\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= '--' . $boundary . "--\r\n";

    $message = $headers . "\r\n" . $body;
    // Dot-stuffing: any line starting with "." must be doubled
    $message = preg_replace('/^\./m', '..', $message);

    fwrite($fp, $message . "\r\n.\r\n");
    if (!$expect($read(), '250')) return $fail('Message not accepted');

    $write('QUIT');
    @fclose($fp);
    return true;
}

/** RFC 2047 encode header values that may contain non-ASCII */
function encodeHeader(string $str): string {
    if (preg_match('/[^\x20-\x7E]/', $str)) {
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }
    return $str;
}

/**
 * Bangun body HTML email tiket. Dipakai bersama oleh process.php & admin resend.
 */
function buildTicketEmailHtml(string $nama, array $ticketCodes, int $jumlah, string $ticketUrl): string {
    $rows = '';
    foreach ($ticketCodes as $i => $kode) {
        $rows .= '<tr><td style="padding:6px 14px;border:1px solid #e0d8c4;font-size:14px;color:#6b4c2a;">Tiket ' . ($i + 1) . '</td>'
               . '<td style="padding:6px 14px;border:1px solid #e0d8c4;font-family:Consolas,monospace;font-weight:700;letter-spacing:1px;color:#1a0800;">' . htmlspecialchars($kode) . '</td></tr>';
    }

    return '
<div style="background:#f4efe4;padding:24px;font-family:Arial,Helvetica,sans-serif;color:#2c1500;">
  <div style="max-width:560px;margin:0 auto;background:#fffdf8;border:1px solid #e0d8c4;border-radius:8px;overflow:hidden;">
    <div style="background:#1a0800;padding:24px;text-align:center;">
      <div style="color:#e8c66e;font-size:13px;letter-spacing:3px;">VITA VOXA CHOIR &middot; JAKARTA</div>
      <div style="color:#fff;font-size:30px;font-weight:900;letter-spacing:1px;margin-top:6px;">FOAS 14</div>
      <div style="color:#c9a84c;font-size:11px;letter-spacing:2px;margin-top:4px;">MENSANA IN CORPORE SANO</div>
    </div>
    <div style="padding:28px 26px;">
      <p style="font-size:16px;margin:0 0 14px;">Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
      <p style="font-size:15px;line-height:1.6;margin:0 0 18px;">Reservasi tiket Anda untuk <strong>FOAS 14</strong> telah <strong style="color:#1a7a40;">berhasil</strong>. Berikut detail tiket Anda:</p>
      <table style="border-collapse:collapse;width:100%;margin-bottom:20px;">' . $rows . '</table>
      <p style="font-size:14px;line-height:1.6;margin:0 0 6px;"><strong>Acara:</strong> Sabtu, 7 November 2026 &middot; 19.00 WIB</p>
      <p style="font-size:14px;line-height:1.6;margin:0 0 22px;"><strong>Jumlah:</strong> ' . $jumlah . ' tiket</p>
      <table border="0" cellspacing="0" cellpadding="0" role="presentation" style="margin:24px auto;">
        <tr>
          <td align="center" bgcolor="#c9a84c" style="border-radius:8px;">
            <a href="' . htmlspecialchars($ticketUrl) . '" target="_blank" style="display:inline-block;padding:14px 32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#1a0800;text-decoration:none;border-radius:8px;">Lihat &amp; Simpan Tiket Saya</a>
          </td>
        </tr>
      </table>
      <p style="font-size:13px;color:#666;line-height:1.6;margin:8px 0 0;text-align:center;">Atau buka tautan ini:<br><a href="' . htmlspecialchars($ticketUrl) . '" target="_blank" style="color:#8B6914;word-break:break-all;">' . htmlspecialchars($ticketUrl) . '</a></p>
      <p style="font-size:13px;color:#888;line-height:1.6;margin:18px 0 0;">Simpan email ini. Anda bisa membuka tiket kapan saja melalui tautan di atas, lalu menyimpannya sebagai gambar/PDF atau membagikannya ke WhatsApp.</p>
      <p style="font-size:13px;color:#888;line-height:1.6;margin:14px 0 0;">Tunjukkan QR code tiket saat memasuki venue.</p>
    </div>
    <div style="background:#f4efe4;padding:16px;text-align:center;font-size:12px;color:#9a7a55;">Sampai jumpa di FOAS 14!<br>&mdash; Vita Voxa Choir</div>
  </div>
</div>';
}

/**
 * Bangun & kirim email tiket untuk satu baris registrasi (dari DB).
 * Mengembalikan true atau string error.
 */
function sendTicketEmailForRow(array $row, string $ticketUrl) {
    $jumlah = (int)$row['jumlah_tiket'];
    $batch  = substr($row['kode_tiket'], 7, 4);
    $codes  = [];
    for ($i = 0; $i < $jumlah; $i++) {
        $codes[] = 'FOAS14-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
    }
    $html = buildTicketEmailHtml($row['nama'], $codes, $jumlah, $ticketUrl);
    return sendSmtpMail($row['email'], $row['nama'], 'Tiket FOAS 14 — Vita Voxa Choir', $html);
}
