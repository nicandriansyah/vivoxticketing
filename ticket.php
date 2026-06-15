<?php
session_start();

if (empty($_SESSION['ticket'])) {
    header('Location: index.php');
    exit;
}

$t = $_SESSION['ticket'];
$ticket_codes   = $t['ticket_codes'];
$jumlah_tiket   = (int)$t['jumlah_tiket'];
$hubunganLabel  = ['orang_tua' => 'Orang Tua', 'anak' => 'Anak', 'saudara' => 'Saudara'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

    <!-- Semua yang di-screenshot untuk download -->
    <div id="ticketDownloadWrap">

        <!-- Concert Invitation Card -->
        <div class="ticket-summary-card">

            <!-- Top: Logo + Choir Name -->
            <div class="tsc-top">
                <img src="logo.png" alt="Vita Voxa Choir" class="tsc-logo">
                <div class="tsc-choir-text">
                    <span>PADUAN SUARA</span>
                    <strong>VITA VOXA CHOIR</strong>
                    <span>JAKARTA</span>
                </div>
            </div>

            <div class="tsc-rule"></div>

            <!-- Undangan -->
            <p class="tsc-undangan">Undangan</p>

            <!-- Event Title -->
            <h2 class="tsc-title">FOAS 13</h2>
            <p class="tsc-subtitle">MENSANA IN CORPORE SANO</p>
            <p class="tsc-presents">FESTIVAL OF ARTS &amp; SONGS · VITA VOXA CHOIR</p>

            <div class="tsc-rule"></div>

            <!-- Date + Event Details -->
            <div class="tsc-info-row">
                <div class="tsc-date">
                    <span class="tsc-month">NOVEMBER</span>
                    <span class="tsc-day">7</span>
                    <span class="tsc-year">2026</span>
                </div>
                <div class="tsc-vline"></div>
                <div class="tsc-detail">
                    <p class="tsc-dayname">SABTU</p>
                    <p class="tsc-time">19.00 WIB</p>
                    <div class="tsc-rule-sm"></div>
                    <p class="tsc-peserta-label">PEMESAN</p>
                    <p class="tsc-peserta-name"><?= htmlspecialchars($t['nama']) ?></p>
                    <p class="tsc-peserta-tiket"><?= $jumlah_tiket ?> Tiket</p>
                </div>
            </div>

            <?php if ($t['upload_arwah'] && $t['nama_arwah']): ?>
            <div class="tsc-rule"></div>
            <div class="tsc-arwah">
                <p class="tsc-arwah-label">MENDOAKAN</p>
                <p class="tsc-arwah-name"><?= htmlspecialchars($t['nama_arwah']) ?></p>
                <p class="tsc-arwah-years">
                    <?= $t['tahun_lahir'] ?> – <?= $t['tahun_wafat'] ?>
                    &nbsp;·&nbsp;
                    <?= htmlspecialchars($hubunganLabel[$t['hubungan']] ?? '') ?>
                </p>
            </div>
            <?php endif; ?>

        </div>

        <!-- QR Code Grid -->
        <p class="qr-section-title">QR Code — <?= $jumlah_tiket ?> Tiket</p>

        <div class="qr-grid">
            <?php foreach ($ticket_codes as $i => $kode): ?>
            <div class="qr-tile">
                <span class="qr-tile-num">Tiket <?= $i + 1 ?>/<?= $jumlah_tiket ?></span>
                <div class="qr-canvas-wrap">
                    <div id="qr-<?= $i ?>"></div>
                </div>
                <p class="qr-code-val"><?= htmlspecialchars($kode) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
    <!-- /ticketDownloadWrap -->

    <!-- Actions -->
    <div class="ticket-actions">
        <button class="btn-download" onclick="downloadTicket()">⬇ Simpan sebagai Gambar</button>
        <a href="index.php" class="btn-new-reg">Kembali ke Beranda</a>
    </div>

    <p class="email-note">
        Tiket juga dikirimkan ke <span><?= htmlspecialchars($t['email']) ?></span><br>
        <small style="font-size:.8rem; color:#aaa;">Cek folder Spam jika tidak menemukan email</small>
    </p>

    <script>
        const codes = <?= json_encode($ticket_codes) ?>;
        codes.forEach(function(code, i) {
            new QRCode(document.getElementById('qr-' + i), {
                text: code,
                width:  120,
                height: 120,
                colorDark:  '#000000',
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
