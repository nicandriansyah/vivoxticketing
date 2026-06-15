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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body class="ticket-page">

    <!-- Success Header -->
    <div class="ticket-success-header">
        <div class="success-icon">✓</div>
        <h2>Reservasi Berhasil!</h2>
        <p>Tiket dikirim ke <span style="color:#c9a84c;"><?= htmlspecialchars($t['email']) ?></span></p>
    </div>

    <!-- Semua yang di-screenshot untuk download -->
    <div id="ticketDownloadWrap">

        <!-- Summary Card -->
        <div class="ticket-summary-card">
            <p class="summary-choir-name">Vita Voxa Choir</p>
            <h2 class="summary-event-title">FOAS 13</h2>
            <p class="summary-event-sub">Festival of Arts &amp; Songs</p>

            <div class="summary-meta">
                <div class="summary-meta-item">
                    <label>Pemesan</label>
                    <span><?= htmlspecialchars($t['nama']) ?></span>
                </div>
                <div class="summary-meta-item">
                    <label>Tanggal</label>
                    <span>Sabtu, 7 November</span>
                </div>
                <div class="summary-meta-item">
                    <label>Jam</label>
                    <span>19.00 WIB</span>
                </div>
                <div class="summary-meta-item">
                    <label>Jumlah Tiket</label>
                    <span><?= $jumlah_tiket ?> tiket</span>
                </div>
                <?php if ($t['upload_arwah'] && $t['nama_arwah']): ?>
                <div class="summary-meta-item">
                    <label>Mendoakan</label>
                    <span>
                        <?= htmlspecialchars($t['nama_arwah']) ?>
                        <small style="display:block; font-size:.8rem; color:#888; font-weight:400;">
                            <?= $t['tahun_lahir'] ?> – <?= $t['tahun_wafat'] ?>
                            · <?= $hubunganLabel[$t['hubungan']] ?? '' ?>
                        </small>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- QR Code Grid -->
        <p class="qr-section-title">
            QR Code — <?= $jumlah_tiket ?> Tiket
        </p>

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
        <small style="font-size:.8rem; color:#555;">Cek folder Spam jika tidak menemukan email</small>
    </p>

    <script>
        // Generate QR untuk setiap tiket
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

        // Download seluruh wrap sebagai satu gambar
        function downloadTicket() {
            const wrap = document.getElementById('ticketDownloadWrap');
            html2canvas(wrap, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#0d0d0d',
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
