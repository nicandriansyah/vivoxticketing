<?php
session_start();
require_once 'config/db.php';
require_once 'config/checkin.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

function show404(string $msg = ''): void {
    $GLOBALS['notfound_msg'] = $msg;
    require __DIR__ . '/404.php';
    exit;
}

$t            = null;
$ticket_codes = [];
$jumlah_tiket = 0;
$regId        = null;
$isNew        = isset($_GET['new']);
$token        = trim($_GET['token'] ?? '');

/* ---------- Load via token + DB ---------- */
if ($token && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM registrations WHERE kode_tiket = ? LIMIT 1");
        $stmt->execute([$token]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $regId    = (int)$row['id'];
            $original = (int)$row['jumlah_tiket'];
            $batch    = substr($row['kode_tiket'], 7, 4);

            $allCodes = [];
            for ($i = 0; $i < $original; $i++) {
                $allCodes[] = 'FOAS14-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
            }
            // Buang tiket yang sudah dibatalkan
            $cancelledMap   = getCancelledMap($pdo, [$regId]);
            $cancelledCodes = $cancelledMap[$regId] ?? [];
            $ticket_codes   = array_values(array_filter($allCodes, function ($c) use ($cancelledCodes) {
                return !in_array($c, $cancelledCodes, true);
            }));
            $jumlah_tiket = count($ticket_codes);

            $t = [
                'kode_utama'   => $row['kode_tiket'],
                'ticket_codes' => $ticket_codes,
                'nama'         => $row['nama'],
                'email'        => $row['email'],
                'jumlah_tiket' => $jumlah_tiket,
            ];
        }
    } catch (Exception $e) { /* fall through to session */ }
}

/* ---------- Fallback: session ---------- */
if (!$t && !empty($_SESSION['ticket'])) {
    $t            = $_SESSION['ticket'];
    $ticket_codes = $t['ticket_codes'];
    $jumlah_tiket = (int)$t['jumlah_tiket'];
}
unset($_SESSION['ticket']); // always clear

// Link tidak valid / tidak ketemu
if (!$t) {
    show404('Tiket tidak ditemukan. Periksa kembali link tiket yang sudah diberikan.');
}

// Semua tiket dibatalkan / sudah check-in → link tidak dapat diakses lagi
if ($regId && $pdo) {
    if ($jumlah_tiket <= 0) {
        show404('Semua tiket pada link ini sudah dibatalkan. Periksa kembali link tiket yang sudah diberikan.');
    }
    if (countChecked($pdo, $regId) >= $jumlah_tiket) {
        show404('Semua tiket pada link ini sudah digunakan untuk check-in. Periksa kembali link tiket yang sudah diberikan.');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tiket FOAS 14 — <?= htmlspecialchars($t['nama']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=Inter:wght@300;400;600;700&family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css?v=5" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body class="ticket-page">

    <!-- Header -->
    <div class="ticket-success-header">
        <?php if ($isNew): ?>
        <div class="success-icon">&#10003;</div>
        <h2>Reservasi Berhasil!</h2>
        <p>Tiket dikirim ke <span style="color:#8B6914;"><?= htmlspecialchars($t['email']) ?></span></p>
        <?php else: ?>
        <h2>Tiket Anda</h2>
        <p>Selamat datang kembali, <span style="color:#8B6914;"><?= htmlspecialchars($t['nama']) ?></span></p>
        <?php endif; ?>
    </div>

    <!-- Ticket cards -->
    <?php foreach ($ticket_codes as $i => $kode): ?>

    <div class="ticket-card" id="card-<?= $i ?>">

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
        <h2 class="tc-title">FOAS 14</h2>
        <p class="tc-subtitle">MENSANA IN CORPORE SANO</p>
        <p class="tc-presents">FESTIVAL OF ARTS &amp; SONGS &nbsp;&middot;&nbsp; VITA VOXA CHOIR</p>

        <div class="tc-rule"></div>

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
            </div>
        </div>

        <div class="tc-rule"></div>

        <div class="tc-qr-section">
            <p class="tc-ticket-num">Tiket <?= $i + 1 ?> dari <?= $jumlah_tiket ?></p>
            <div class="tc-qr-wrap">
                <div id="qr-<?= $i ?>"></div>
            </div>
            <p class="tc-qr-code"><?= htmlspecialchars($kode) ?></p>
        </div>

    </div>

    <!-- Share + sent badge per tiket -->
    <div class="ticket-share-row">
        <button class="btn-share-wa" id="share-btn-<?= $i ?>" onclick="shareToWA(<?= $i ?>, '<?= htmlspecialchars($kode, ENT_QUOTES) ?>')">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.867-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.345.223-.643.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            Share ke WhatsApp
        </button>
        <span class="wa-sent-badge" id="sent-<?= $i ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
            Sudah dibagikan ke WhatsApp
        </span>
    </div>

    <?php endforeach; ?>

    <!-- Actions -->
    <div class="ticket-actions">
        <button class="btn-download" onclick="downloadPDF()">Simpan Semua Tiket sebagai PDF</button>
        <a href="index.php" class="btn-new-reg">Kembali ke Beranda</a>
    </div>

    <p class="email-note">
        <?= $jumlah_tiket ?> tiket dikirimkan ke <span><?= htmlspecialchars($t['email']) ?></span><br>
        <small style="font-size:.8rem; color:#aaa;">Cek folder Spam jika tidak menemukan email</small>
    </p>

    <!-- Kontak bantuan (sama dengan halaman 404) -->
    <?php
        $helpC  = helpContact($pdo);
        $waText = rawurlencode('Halo saya ada kendala dalam pemesanan ticket');
    ?>
    <div class="ticket-help">
        Apabila terdapat kendala dalam pemesanan tiket dapat menghubungi<br>
        <strong><?= htmlspecialchars($helpC['name']) ?> &mdash; <?= htmlspecialchars($helpC['wa']) ?></strong><br>
        atau klik WhatsApp berikut ini
        <br>
        <a class="ticket-help-wa" href="https://wa.me/<?= htmlspecialchars($helpC['wa']) ?>?text=<?= $waText ?>" target="_blank" rel="noopener">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.867-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.345.223-.643.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            Chat WhatsApp
        </a>
        <p class="ticket-help-brand">Vita Voxa Choir &middot; FOAS 14</p>
    </div>

    <script>
    var codes = <?= json_encode($ticket_codes) ?>;

    // Generate QR codes
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

    // Restore WA sent status from localStorage
    codes.forEach(function(code, i) {
        if (localStorage.getItem('wa_' + code)) showWaSent(i);
    });

<?php if ($isNew): ?>
    // Reservasi baru → simpan tiap tiket sebagai JPG ke server (sekali saja)
    window.addEventListener('load', function () {
        setTimeout(function () {
            var cards = document.querySelectorAll('.ticket-card');
            (function saveNext(i) {
                if (i >= cards.length) return;
                html2canvas(cards[i], captureOpts()).then(function (canvas) {
                    var img = canvas.toDataURL('image/jpeg', 0.92);
                    var fd  = new FormData();
                    fd.append('code', codes[i]);
                    fd.append('image', img);
                    fetch('save_ticket.php', { method: 'POST', body: fd })
                        .catch(function () {})
                        .finally(function () { saveNext(i + 1); });
                }).catch(function () { saveNext(i + 1); });
            })(0);
        }, 1200);
    });
<?php endif; ?>

    function showWaSent(i) {
        // Tampilkan badge penanda, TAPI tombol tetap terlihat & bisa diklik
        // supaya bisa dibagikan ulang berkali-kali.
        var badge = document.getElementById('sent-' + i);
        if (badge) badge.style.display = 'flex';
    }

    // Shared html2canvas options. onclone strips the fadeInUp animation so the
    // capture isn't taken while the cloned card is still faded (washed out).
    function captureOpts() {
        return {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#f8f4ec',
            logging: false,
            onclone: function(doc) {
                doc.querySelectorAll('.ticket-card').forEach(function(el) {
                    el.style.animation = 'none';
                    el.style.opacity   = '1';
                    el.style.transform = 'none';
                });
            }
        };
    }

    async function shareToWA(idx, code) {
        var btn     = document.getElementById('share-btn-' + idx);
        var origHTML = btn.innerHTML;
        btn.innerHTML = 'Menyiapkan...';
        btn.disabled  = true;

        try {
            var card   = document.querySelectorAll('.ticket-card')[idx];
            var canvas = await html2canvas(card, captureOpts());

            var fileName = 'tiket-foas13-' + (idx + 1) + '.jpg';
            var blob = await new Promise(function(resolve) {
                canvas.toBlob(resolve, 'image/jpeg', 0.92);
            });
            var file = new File([blob], fileName, { type: 'image/jpeg' });

            // Try Web Share API (mobile share sheet — user picks WhatsApp)
            if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({ files: [file], title: 'Tiket FOAS 14' });
                } catch (shareErr) {
                    if (shareErr.name === 'AbortError') {
                        // User cancelled share — restore button, don't mark as sent
                        btn.innerHTML = origHTML;
                        btn.disabled  = false;
                        return;
                    }
                    // Other error — fall through to download
                    downloadBlob(blob, fileName);
                }
            } else {
                // Fallback: download JPG, user shares manually
                downloadBlob(blob, fileName);
            }

            localStorage.setItem('wa_' + code, '1');
            showWaSent(idx);
        } catch (e) {
            // abaikan error, lanjut pulihkan tombol
        }
        // Selalu pulihkan tombol agar bisa dibagikan lagi
        btn.innerHTML = origHTML;
        btn.disabled  = false;
    }

    function downloadBlob(blob, fileName) {
        var url  = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        link.click();
        setTimeout(function() { URL.revokeObjectURL(url); }, 1500);
    }

    async function downloadPDF() {
        var btn  = document.querySelector('.btn-download');
        var orig = btn.textContent;
        btn.textContent = 'Menyiapkan PDF...';
        btn.disabled = true;

        try {
            var jsPDF = window.jspdf.jsPDF;
            var pdf   = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            var cards = document.querySelectorAll('.ticket-card');
            var pageW = pdf.internal.pageSize.getWidth();
            var pageH = pdf.internal.pageSize.getHeight();

            var margin = 10;                       // mm
            var maxW   = pageW - margin * 2;
            var maxH   = pageH - margin * 2;

            for (var i = 0; i < cards.length; i++) {
                if (i > 0) pdf.addPage();
                var canvas  = await html2canvas(cards[i], captureOpts());
                var imgData = canvas.toDataURL('image/jpeg', 0.92);

                // Fit proporsional (contain): skala terbatas oleh lebar ATAU tinggi
                var ratio = Math.min(maxW / canvas.width, maxH / canvas.height);
                var imgW  = canvas.width  * ratio;
                var imgH  = canvas.height * ratio;
                var xOff  = (pageW - imgW) / 2;
                var yOff  = (pageH - imgH) / 2;

                pdf.addImage(imgData, 'JPEG', xOff, yOff, imgW, imgH);
            }
            pdf.save('tiket-foas13-<?= addslashes($t['nama']) ?>.pdf');
        } catch (e) {
            alert('Gagal membuat PDF: ' + e.message);
        }

        btn.textContent = orig;
        btn.disabled = false;
    }

    var _lt = 0;
    document.addEventListener('touchend', function(e) {
        var now = Date.now();
        if (now - _lt < 300) { e.preventDefault(); }
        _lt = now;
    }, { passive: false });
    </script>
</body>
</html>
