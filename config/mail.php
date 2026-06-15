<?php
/* ============================================================
   Email (SMTP) configuration + minimal SMTP sender
   ------------------------------------------------------------
   Default values below are placeholders. Override the real
   credentials in config/mail.local.php (gitignored) — same
   pattern as db.local.php.
   ============================================================ */

$MAIL_HOST       = 'mail.vitavoxa.my.id';
$MAIL_PORT       = 465;                       // 465 = SSL (implicit TLS)
$MAIL_SECURE     = 'ssl';                     // 'ssl' for 465, 'tls' for 587
$MAIL_USER       = 'ticketing@vitavoxa.my.id';
$MAIL_PASS       = '';                        // set in mail.local.php
$MAIL_FROM       = 'ticketing@vitavoxa.my.id';
$MAIL_FROM_NAME  = 'Vita Voxa Choir';

$localCfg = __DIR__ . '/mail.local.php';
if (file_exists($localCfg)) require_once $localCfg;

/**
 * Send an HTML email via SMTP (AUTH LOGIN).
 * Returns true on success, or an error string on failure.
 */
function sendSmtpMail(string $toEmail, string $toName, string $subject, string $htmlBody) {
    global $MAIL_HOST, $MAIL_PORT, $MAIL_SECURE, $MAIL_USER, $MAIL_PASS, $MAIL_FROM, $MAIL_FROM_NAME;

    if (empty($MAIL_PASS)) return 'SMTP password not configured';

    $remote = ($MAIL_SECURE === 'ssl' ? 'ssl://' : '') . $MAIL_HOST . ':' . $MAIL_PORT;
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
    ]);

    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return "Connect failed: $errstr ($errno)";
    stream_set_timeout($fp, 15);

    // Read a (possibly multiline) SMTP reply; verify expected code.
    $read = function () use ($fp) {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            // last line has a space at position 3 (e.g. "250 OK"), continuation has "-"
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $expect = function (string $resp, string $code) {
        return strncmp($resp, $code, 3) === 0;
    };
    $write = function (string $cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

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
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
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

    $boundary = '=_' . bin2hex(random_bytes(12));
    $headers  = 'From: ' . encodeHeader($MAIL_FROM_NAME) . ' <' . $MAIL_FROM . ">\r\n";
    $headers .= 'To: ' . encodeHeader($toName) . ' <' . $toEmail . ">\r\n";
    $headers .= 'Subject: ' . encodeHeader($subject) . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $headers .= 'Content-Transfer-Encoding: base64' . "\r\n";

    // Dot-stuffing: any line starting with "." must be doubled
    $bodyEnc = chunk_split(base64_encode($htmlBody));
    $message = $headers . "\r\n" . $bodyEnc;
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
