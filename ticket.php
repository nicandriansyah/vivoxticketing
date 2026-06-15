<?php
session_start();
// No-cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

if (empty($_SESSION['ticket'])) {
    header('Location: index.php');
    exit;
}

// Ambil data lalu clear session — tidak bisa kembali ke halaman ini setelah refresh
$t             = $_SESSION['ticket'];
$ticket_codes  = $t['ticket_codes'];
$jumlah_tiket  = (int)$t['jumlah_tiket'];
unset($_SESSION['ticket']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tiket FOAS 13 — <?= htmlspecialchars($t['nama']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=Inter:wght@300;400;600;700&family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body class="ticket-page">

    <!-- Success Header -->
    <div class="ticket-success-header">
        <div class="success-icon">✓</div>
        <h2>Reservasi Berhasil!</h2>
        <p>Tiket dikirim ke <span style="color:#8B6914;"><?= htmlspecialchars($t['email']) ?></span></p>
    </div>

    <!-- Download wrap — semua tiket dirender di sini -->
    <div id="ticketDownloadWrap">

        <?php foreach ($ticket_codes as $i => $kode): ?>
        <div class="ticket-card">

            <!-- Header: Logo + Choir -->
            <div class="tc-top">
                <img src="logo.png" alt="Vita Voxa Choir" class="tc-logo">
                <div class="tc-choir-text">
                    <span>PADUAN SUARA</span>
                    <strong>VITA VOXA CHOIR</strong>
                    <span>JAKARTA</span>
                </div>
            </div>

            <div class="tc-rule"></div>

            <p class="tc-undangan">Undangan</p>
            <h2 class="tc-title">FOAS 13</h2>
            <p class="tc-subtitle">MENSANA IN CORPORE SANO</p>
            <p class="tc-presents">FESTIVAL OF ARTS &amp; SONGS &nbsp;·&nbsp; VITA VOXA CHOIR</p>

            <div class="tc-rule"></div>

            <!-- Tanggal + Detail -->
            <div class="tc-info-row">
                <div class="tc-date">
                    <span class="tc-month">NOVEMBER</span>
                    <span class="tc-day">7</span>
                    <span class="tc-year">2026</span>
                </div>
                <div class="tc-vline"></div>
                <div class="tc-detail">
                    <p class="tc-dayname">SABTU</p>
                    <p class="tc-time">19.00 WIB</p>
                    <div class="tc-rule-sm"></div>
                    <p class="tc-peserta-label">PEMESAN</p>
                    <p class="tc-peserta-name"><?= htmlspecialchars($t['nama']) ?></p>
                    <p class="tc-ticket-num">Tiket <?= $i + 1 ?> dari <?= $jumlah_tiket ?></p>
                </div>
            </div>

            <div class="tc-rule"></div>

            <!-- QR Code -->
            <div class="tc-qr-section">
                <div class="tc-qr-wrap">
                    <div id="qr-<?= $i ?>"></div>
                </div>
                <p class="tc-qr-code"><?= htmlspecialchars($kode) ?></p>
            </div>

        </div>
        <?php endforeach; ?>

    </div>
    <!-- /ticketDownloadWrap -->

    <!-- Actions -->
    <div class="ticket-actions">
        <button class="btn-download" onclick="downloadTicket()">⬇ Simpan Semua Tiket</button>
        <a href="index.php" class="btn-new-reg">Kembali ke Beranda</a>
    </div>

    <p class="email-note">
        <?= $jumlah_tiket ?> tiket dikirimkan ke <span><?= htmlspecialchars($t['email']) ?></span><br>
        <small style="font-size:.8rem; color:#aaa;">Cek folder Spam jika tidak menemukan email</small>
    </p>

    <script>
        const codes = <?= json_encode($ticket_codes) ?>;
        codes.forEach(function(code, i) {
            new QRCode(document.getElementById('qr-' + i), {
                text: code,
                width:  130,
                height: 130,
                colorDark:  '#1a0800',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });

        function downloadTicket() {
            const wrap = document.getElementById('ticketDownloadWrap');
            html2canvas(wrap, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            }).then(function(canvas) {
                const link = document.createElement('a');
                link.download = 'tiket-foas13-<?= addslashes($t['nama']) ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
</body>
</html>
