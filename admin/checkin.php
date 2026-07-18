<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
if ($pdo) { try { ensureTicketTables($pdo); } catch (Exception $e) {} }

$pageTitle  = 'Check-in Tiket';
$activeMenu = 'checkin';
require __DIR__ . '/partials/header.php';
?>

        <div class="checkin-wrap">

            <!-- STEP 1: Scan / input kode -->
            <div class="checkin-card" id="step-scan">
                <h3 class="ci-title">Scan QR Tiket</h3>
                <p class="ci-sub">Arahkan kamera ke QR code tiket peserta.</p>

                <div id="reader" class="ci-reader"></div>
                <div class="ci-cam-btns">
                    <button type="button" class="adm-btn-primary" id="btnStartCam">📷 Mulai Kamera</button>
                    <button type="button" class="adm-btn-ghost" id="btnStopCam" style="display:none;">Stop Kamera</button>
                </div>

                <div class="ci-divider"><span>atau masukkan manual</span></div>

                <div class="ci-manual">
                    <span class="ci-prefix">FOAS14-</span>
                    <input type="text" id="manualCode" class="adm-input" placeholder="VCTP003" autocomplete="off"
                           style="text-transform:uppercase;" onkeydown="if(event.key==='Enter'){event.preventDefault();manualNext();}">
                </div>
                <button type="button" class="adm-btn-primary" style="width:100%;margin-top:0.9rem;" onclick="manualNext()">Lanjut →</button>

                <div id="lookupError" class="ci-error" style="display:none;"></div>
            </div>

            <!-- STEP 2: Konfirmasi -->
            <div class="checkin-card" id="step-confirm" style="display:none;">
                <div id="confirmBody"></div>
            </div>

            <!-- STEP 3: Hasil -->
            <div class="checkin-card" id="step-result" style="display:none;">
                <div id="resultBody"></div>
                <button type="button" class="adm-btn-primary" style="width:100%;margin-top:1rem;" onclick="resetScan()">Scan Berikutnya</button>
            </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
    var html5Qr = null;
    var currentCode = null;

    function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

    /* ---------- Kamera ---------- */
    document.getElementById('btnStartCam').addEventListener('click', startCam);
    document.getElementById('btnStopCam').addEventListener('click', stopCam);

    function startCam() {
        if (html5Qr) return;                                  // sudah jalan
        if (!window.Html5Qrcode) { showError('Library scanner gagal dimuat.'); return; }
        html5Qr = new Html5Qrcode('reader');
        html5Qr.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 },
            function(decoded) { stopCam(); lookup(decoded.trim()); },
            function() {}
        ).then(function() {
            document.getElementById('btnStartCam').style.display = 'none';
            document.getElementById('btnStopCam').style.display  = 'inline-flex';
        }).catch(function(err) {
            showError('Tidak bisa mengakses kamera: ' + err);
        });
    }
    function stopCam() {
        if (html5Qr) {
            html5Qr.stop().then(function(){ html5Qr.clear(); html5Qr = null; }).catch(function(){});
        }
        document.getElementById('btnStartCam').style.display = 'inline-flex';
        document.getElementById('btnStopCam').style.display  = 'none';
    }

    /* ---------- Manual ---------- */
    function manualNext() {
        var v = document.getElementById('manualCode').value.trim().toUpperCase();
        if (!v) { showError('Masukkan kode tiket.'); return; }
        var code = v.indexOf('FOAS14-') === 0 ? v : 'FOAS14-' + v;
        lookup(code);
    }

    function showError(msg) {
        var el = document.getElementById('lookupError');
        el.textContent = msg;
        el.style.display = 'block';
    }

    /* ---------- Lookup ---------- */
    function lookup(code) {
        document.getElementById('lookupError').style.display = 'none';
        var fd = new FormData();
        fd.append('action', 'lookup');
        fd.append('code', code);
        fetch('checkin_api.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.ok) { showError(d.error || 'Gagal memeriksa tiket.'); return; }
                currentCode = d.code;
                showConfirm(d);
            })
            .catch(function(){ showError('Koneksi gagal.'); });
    }

    function showConfirm(d) {
        var statusHtml = d.already
            ? '<div class="ci-badge-warn">⚠ Tiket ini SUDAH check-in' + (d.checked_at ? ' pada ' + esc(d.checked_at) : '') + '</div>'
            : '';
        var actionBtn = d.already
            ? ''
            : '<button type="button" class="adm-btn-secondary" style="width:100%;margin-top:1rem;" onclick="doCheckin()">✓ Check-in Tiket Ini</button>';

        document.getElementById('confirmBody').innerHTML =
            '<h3 class="ci-title">Konfirmasi Check-in</h3>' +
            '<div class="ci-ticket-badge">' + esc(d.code) + '</div>' +
            '<div class="m-section" style="margin-top:1rem;">' +
                '<div class="m-row"><span>Nama</span><strong>' + esc(d.nama) + '</strong></div>' +
                '<div class="m-row"><span>No. WhatsApp</span><strong>' + esc(d.no_hp) + '</strong></div>' +
                '<div class="m-row"><span>Tiket</span><strong>' + d.seq + ' dari ' + d.jumlah + '</strong></div>' +
                '<div class="m-row"><span>Sudah check-in</span><strong>' + d.total_in + ' / ' + d.jumlah + '</strong></div>' +
            '</div>' +
            statusHtml +
            actionBtn +
            '<button type="button" class="adm-btn-ghost" style="width:100%;margin-top:0.6rem;" onclick="resetScan()">Batal</button>';

        swap('step-confirm');
    }

    function doCheckin() {
        var fd = new FormData();
        fd.append('action', 'checkin');
        fd.append('code', currentCode);
        fetch('checkin_api.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.ok) {
                    document.getElementById('resultBody').innerHTML =
                        '<div class="ci-result-icon fail">✕</div>' +
                        '<h3 class="ci-title" style="text-align:center;">Gagal</h3>' +
                        '<p style="text-align:center;color:#c0392b;">' + esc(d.error || 'Gagal check-in') + '</p>';
                } else {
                    document.getElementById('resultBody').innerHTML =
                        '<div class="ci-result-icon ok">✓</div>' +
                        '<h3 class="ci-title" style="text-align:center;">Check-in Berhasil!</h3>' +
                        '<div class="ci-ticket-badge" style="margin:0 auto;">' + esc(d.code) + '</div>' +
                        '<p style="text-align:center;margin-top:0.75rem;font-size:1.05rem;"><strong>' + esc(d.nama) + '</strong></p>' +
                        '<p style="text-align:center;color:#6b7280;">Tiket ' + d.seq + ' dari ' + d.jumlah + ' &middot; Total masuk: ' + d.total_in + '/' + d.jumlah + '</p>';
                }
                swap('step-result');
            })
            .catch(function(){
                document.getElementById('resultBody').innerHTML = '<p style="text-align:center;color:#c0392b;">Koneksi gagal.</p>';
                swap('step-result');
            });
    }

    function swap(showId) {
        ['step-scan','step-confirm','step-result'].forEach(function(s){
            document.getElementById(s).style.display = (s === showId) ? 'block' : 'none';
        });
    }

    function resetScan() {
        currentCode = null;
        document.getElementById('manualCode').value = '';
        document.getElementById('lookupError').style.display = 'none';
        swap('step-scan');
        startCam();                                           // nyalakan lagi untuk scan berikutnya
    }

    // Kamera otomatis menyala saat masuk menu check-in
    window.addEventListener('load', startCam);
    </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
