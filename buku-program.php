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
    <?php if ($hasBook): ?>
    <link href="https://cdn.jsdelivr.net/npm/page-flip@2.0.7/src/Style/stPageFlip.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            background: #0d0d0d;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            color: #f0f0f0;
            touch-action: manipulation;
        }
        .empty-state {
            height: 100%;
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
        /* ID selector di atas ngalahin default UA [hidden]{display:none} kalau nggak ditegaskan lagi di sini */
        #loadingScreen[hidden], #bookShell[hidden] { display: none; }
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

        #bookShell {
            position: fixed; inset: 0;
            display: flex; flex-direction: column;
        }
        .book-topbar {
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: space-between;
            padding: max(0.7rem, env(safe-area-inset-top)) 1rem 0.7rem;
            background: linear-gradient(to bottom, rgba(0,0,0,0.6), transparent);
            position: relative; z-index: 10;
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
        .book-area {
            flex: 1; min-height: 0;
            position: relative;
            display: flex; align-items: center; justify-content: center;
            padding: 0.5rem 0.75rem calc(0.75rem + env(safe-area-inset-bottom));
        }
        #book { width: 100%; height: 100%; }
        .navzone {
            position: absolute; top: 0; bottom: 0;
            width: 22%;
            background: transparent;
            border: none;
            cursor: pointer;
            z-index: 5;
        }
        .navzone-left  { left: 0; }
        .navzone-right { right: 0; }
        .swipe-hint {
            position: fixed;
            bottom: calc(1rem + env(safe-area-inset-bottom));
            left: 50%; transform: translateX(-50%);
            font-size: 0.72rem; color: #888;
            background: rgba(0,0,0,0.5);
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            z-index: 6;
            pointer-events: none;
            transition: opacity 0.6s ease;
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

    <div id="bookShell" hidden>
        <div class="book-topbar">
            <img src="foas-logo.png" alt="FOAS 14" class="topbar-logo" draggable="false" oncontextmenu="return false">
            <span id="pageIndicator">1 / 1</span>
            <button type="button" id="btnFullscreen" title="Layar Penuh">⛶</button>
        </div>
        <div class="book-area">
            <div id="book"></div>
            <button type="button" class="navzone navzone-left" id="btnPrev" aria-label="Halaman sebelumnya"></button>
            <button type="button" class="navzone navzone-right" id="btnNext" aria-label="Halaman berikutnya"></button>
        </div>
    </div>
    <div class="swipe-hint" id="swipeHint">👆 Geser buat buka halaman</div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/page-flip@2.0.7/dist/js/page-flip.browser.min.js"></script>
    <script>
    var PDF_URL = <?= json_encode($pdfUrl) ?>;
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    var loadingScreen = document.getElementById('loadingScreen');
    var loadingText   = document.getElementById('loadingText');
    var bookShell     = document.getElementById('bookShell');
    var bookEl        = document.getElementById('book');
    var pageIndicator = document.getElementById('pageIndicator');
    var btnFullscreen = document.getElementById('btnFullscreen');
    var swipeHint     = document.getElementById('swipeHint');

    var pageFlip = null;

    function updateIndicator() {
        if (!pageFlip) return;
        pageIndicator.textContent = (pageFlip.getCurrentPageIndex() + 1) + ' / ' + pageFlip.getPageCount();
    }

    function buildFlipbook(images, w, h) {
        loadingScreen.hidden = true;
        bookShell.hidden = false;

        pageFlip = new St.PageFlip(bookEl, {
            width: w, height: h,
            size: 'stretch',
            minWidth: 240, maxWidth: 1600,
            minHeight: 320, maxHeight: 2200,
            showCover: false,
            usePortrait: true,
            mobileScrollSupport: false,
            maxShadowOpacity: 0.5,
            drawShadow: true,
            flippingTime: 650
        });
        pageFlip.loadFromImages(images);
        updateIndicator();
        pageFlip.on('flip', updateIndicator);

        setTimeout(function () { swipeHint.style.opacity = '0'; }, 3500);
    }

    async function init() {
        try {
            var pdf = await pdfjsLib.getDocument(PDF_URL).promise;
            var total = pdf.numPages;
            var images = [];
            var w = 0, h = 0;
            var scale = Math.min(4, Math.max(2.5, (window.devicePixelRatio || 1) * 2.5));

            for (var i = 1; i <= total; i++) {
                loadingText.textContent = 'Memuat halaman ' + i + ' dari ' + total + '…';
                var page = await pdf.getPage(i);
                var viewport = page.getViewport({ scale: scale });
                var canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                if (i === 1) { w = viewport.width; h = viewport.height; }
                images.push(canvas.toDataURL('image/png'));
            }
            buildFlipbook(images, w, h);
        } catch (e) {
            loadingText.textContent = 'Gagal memuat buku program. Coba muat ulang halaman.';
        }
    }

    document.getElementById('btnPrev').addEventListener('click', function () { if (pageFlip) pageFlip.flipPrev(); });
    document.getElementById('btnNext').addEventListener('click', function () { if (pageFlip) pageFlip.flipNext(); });

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
