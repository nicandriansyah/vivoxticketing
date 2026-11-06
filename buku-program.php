<?php
// No-cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdfPath  = __DIR__ . '/uploads/program-book/book.pdf';
$hasBook  = is_file($pdfPath);
$pdfUrl   = 'uploads/program-book/book.pdf?t=' . ($hasBook ? filemtime($pdfPath) : '0');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Buku Program — FOAS 14</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100%;
            background: #0d0d0d;
            font-family: 'Inter', sans-serif;
            color: #f0f0f0;
            touch-action: manipulation;
        }
        .empty-state {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .empty-state .ico { font-size: 3rem; margin-bottom: 1rem; }
        .empty-state h1 { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: #e8c66e; margin-bottom: 0.5rem; }
        .empty-state p { color: #999; font-size: 0.9rem; max-width: 320px; line-height: 1.6; }

        #loadingScreen {
            position: fixed; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 1rem; z-index: 20; background: #0d0d0d;
        }
        #loadingScreen[hidden] { display: none; }
        .loading-spinner {
            width: 48px; height: 48px;
            border: 4px solid rgba(201,168,76,0.25);
            border-top-color: #c9a84c;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        #loadingText { font-size: 0.85rem; color: #ccc; text-align: center; padding: 0 1.5rem; }
        .loading-hint { font-size: 0.75rem; color: #777; margin-top: 0.3rem; }

        .book-topbar {
            position: fixed; top: 0; left: 0; right: 0;
            display: flex; align-items: center; justify-content: space-between;
            padding: max(0.7rem, env(safe-area-inset-top)) 1rem 0.7rem;
            background: rgba(13,13,13,0.92);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 10;
        }
        #pageIndicator { font-size: 0.8rem; color: #e8c66e; letter-spacing: 1px; font-weight: 600; }
        .topbar-logo {
            height: 24px;
            width: auto;
            -webkit-user-drag: none;
            user-select: none;
            -webkit-touch-callout: none;
        }
        #btnFullscreen {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(201,168,76,0.4);
            color: #e8c66e;
            width: 34px; height: 34px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
        }
        #readerShell[hidden] { display: none; }
        #readerPad {
            height: calc(3rem + env(safe-area-inset-top));
        }
        #reader {
            max-width: 900px;
            margin: 0 auto;
            padding-bottom: calc(1.5rem + env(safe-area-inset-bottom));
        }
        .page-block {
            margin-bottom: 1.1rem;
        }
        .page-label {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 1rem 0.5rem;
            font-size: 0.72rem;
            color: #a08a4e;
            letter-spacing: 1px;
        }
        .page-label::before,
        .page-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(201,168,76,0.25);
        }
        #reader img {
            display: block;
            width: 100%;
            height: auto;
            -webkit-user-drag: none;
        }
        #bottomLoader {
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 1.2rem 1rem;
            font-size: 0.8rem; color: #999;
        }
        #bottomLoader[hidden] { display: none; }
        .mini-spinner {
            width: 16px; height: 16px;
            border: 2px solid rgba(201,168,76,0.25);
            border-top-color: #c9a84c;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
    </style>
</head>
<body>

<?php if (!$hasBook): ?>
    <div class="empty-state">
        <div class="ico">📖</div>
        <h1>Buku Program Belum Tersedia</h1>
        <p>Panitia belum mengupload buku program. Silakan cek lagi nanti.</p>
    </div>
<?php else: ?>

    <div id="loadingScreen">
        <div class="loading-spinner"></div>
        <div id="loadingText">Menyiapkan buku program…</div>
        <div class="loading-hint">Mohon tunggu, lebih cepat kalau sinyal bagus</div>
    </div>

    <div id="readerShell" hidden>
        <div class="book-topbar">
            <img src="foas-logo.png" alt="FOAS 14" class="topbar-logo" draggable="false" oncontextmenu="return false">
            <span id="pageIndicator">1 / 1</span>
            <button type="button" id="btnFullscreen" title="Layar Penuh">⛶</button>
        </div>
        <div id="readerPad"></div>
        <div id="reader"></div>
        <div id="bottomLoader" hidden>
            <div class="mini-spinner"></div>
            <span id="bottomLoaderText">Memuat halaman…</span>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
    var PDF_URL = <?= json_encode($pdfUrl) ?>;
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    var loadingScreen   = document.getElementById('loadingScreen');
    var loadingText     = document.getElementById('loadingText');
    var readerShell      = document.getElementById('readerShell');
    var readerEl         = document.getElementById('reader');
    var pageIndicator    = document.getElementById('pageIndicator');
    var btnFullscreen    = document.getElementById('btnFullscreen');
    var bottomLoader     = document.getElementById('bottomLoader');
    var bottomLoaderText = document.getElementById('bottomLoaderText');

    var totalPages = 0;
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                pageIndicator.textContent = entry.target.dataset.page + ' / ' + totalPages;
            }
        });
    }, { threshold: 0.5 });

    async function init() {
        try {
            var pdf = await pdfjsLib.getDocument(PDF_URL).promise;
            totalPages = pdf.numPages;
            var scale = Math.min(4, Math.max(2.5, (window.devicePixelRatio || 1) * 2.5));

            for (var i = 1; i <= totalPages; i++) {
                loadingText.textContent = 'Memuat halaman ' + i + ' dari ' + totalPages + '…';
                bottomLoaderText.textContent = 'Memuat halaman ' + i + ' dari ' + totalPages + '…';

                var page = await pdf.getPage(i);
                var viewport = page.getViewport({ scale: scale });
                var canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;

                var block = document.createElement('div');
                block.className = 'page-block';

                var label = document.createElement('div');
                label.className = 'page-label';
                label.textContent = 'Halaman ' + i;
                block.appendChild(label);

                var img = document.createElement('img');
                img.src = canvas.toDataURL('image/png');
                img.alt = 'Halaman ' + i;
                img.loading = i <= 2 ? 'eager' : 'lazy';
                img.decoding = 'async';
                img.dataset.page = i;
                block.appendChild(img);

                readerEl.appendChild(block);
                observer.observe(img);

                if (i === 1) {
                    pageIndicator.textContent = '1 / ' + totalPages;
                    loadingScreen.hidden = true;
                    readerShell.hidden = false;
                    if (totalPages > 1) bottomLoader.hidden = false;
                }
            }
            bottomLoader.hidden = true;
        } catch (e) {
            loadingText.textContent = 'Gagal memuat buku program. Coba muat ulang halaman.';
            bottomLoaderText.textContent = 'Gagal memuat halaman berikutnya.';
        }
    }

    if (document.documentElement.requestFullscreen) {
        btnFullscreen.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function () {});
            } else {
                document.exitFullscreen();
            }
        });
    } else {
        btnFullscreen.style.display = 'none';
    }

    init();
    </script>
<?php endif; ?>

</body>
</html>
