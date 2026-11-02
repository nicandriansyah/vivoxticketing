<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
if ($pdo) { try { ensureTicketTables($pdo); } catch (Exception $e) {} }

$pageTitle  = 'Register Tiket';
$activeMenu = 'register';
require __DIR__ . '/partials/header.php';

$err  = $_GET['error'] ?? '';
$msgs = [
    'invalid' => 'Nama, nomor WhatsApp, dan email wajib diisi dengan benar.',
    'habis'   => 'Kuota tiket sudah habis.',
    'sisa'    => 'Jumlah tiket melebihi sisa kuota (' . (int)($_GET['n'] ?? 0) . ' tersisa).',
    'save'    => 'Gagal menyimpan registrasi. Coba lagi.',
];
?>

        <div class="checkin-card" style="max-width:560px;margin:0 auto;">
            <h3 class="ci-title">🎟️ Register Tiket (Walk-in / OTS)</h3>
            <p class="ci-sub">Untuk penonton yang datang dadakan di hari-H. Tiket langsung dikirim ke email yang diisi.</p>

            <?php if ($err && isset($msgs[$err])): ?>
                <div class="ci-error" style="display:block;"><?= htmlspecialchars($msgs[$err]) ?></div>
            <?php endif; ?>

            <form id="otsForm" method="POST" action="register_ots_process.php" autocomplete="off" novalidate>

                <!-- STEP 1: Data -->
                <div id="ots-step1">
                    <label class="hc-label">Nama Lengkap</label>
                    <input type="text" name="nama" id="otsNama" class="hc-input" placeholder="Nama lengkap peserta">

                    <label class="hc-label">Nomor WhatsApp</label>
                    <div class="hc-wa-group">
                        <span class="hc-prefix">+62</span>
                        <input type="tel" name="no_hp" id="otsHp" class="hc-input" placeholder="812-3456-7890"
                               oninput="var v=this.value.replace(/\D/g,'').replace(/^0+/,''); if(v.indexOf('62')===0)v=v.slice(2); this.value=v;">
                    </div>

                    <label class="hc-label">Email Aktif</label>
                    <input type="email" name="email" id="otsEmail" class="hc-input" placeholder="email@contoh.com">

                    <label class="hc-label">Jumlah Tiket</label>
                    <div style="display:flex;align-items:center;gap:0.6rem;">
                        <button type="button" class="adm-btn-ghost" onclick="otsStep(-1)" style="width:38px;">−</button>
                        <input type="number" name="jumlah_tiket" id="otsJumlah" value="1" min="1" readonly style="width:70px;text-align:center;" class="hc-input">
                        <button type="button" class="adm-btn-ghost" onclick="otsStep(1)" style="width:38px;">+</button>
                    </div>

                    <div id="otsErr" class="ci-error" style="display:none;"></div>
                    <button type="button" class="adm-btn-primary" style="width:100%;margin-top:1.3rem;" onclick="otsReview()">Lanjut: Periksa Data →</button>
                </div>

                <!-- STEP 2: Review -->
                <div id="ots-step2" style="display:none;">
                    <h4 style="margin-bottom:0.6rem;">Periksa Data</h4>
                    <div class="m-section" id="otsReviewBox" style="margin-bottom:1.2rem;"></div>
                    <div style="display:flex;gap:0.6rem;">
                        <button type="button" class="adm-btn-ghost" style="flex:1;" onclick="otsBack()">← Kembali</button>
                        <button type="submit" class="adm-btn-primary" style="flex:2;" id="otsSubmitBtn">✓ Daftarkan &amp; Kirim Tiket</button>
                    </div>
                </div>
            </form>
        </div>

    <script>
    function otsStep(delta) {
        var inp = document.getElementById('otsJumlah');
        var v = Math.max(1, parseInt(inp.value || '1', 10) + delta);
        inp.value = v;
    }

    function escOts(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function otsReview() {
        var nama  = document.getElementById('otsNama').value.trim();
        var hp    = document.getElementById('otsHp').value.trim();
        var email = document.getElementById('otsEmail').value.trim();
        var jml   = document.getElementById('otsJumlah').value;
        var err   = document.getElementById('otsErr');

        if (!nama || !hp || !email) {
            err.textContent = 'Nama, WhatsApp, dan email wajib diisi.';
            err.style.display = 'block';
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            err.textContent = 'Format email tidak valid.';
            err.style.display = 'block';
            return;
        }
        err.style.display = 'none';

        document.getElementById('otsReviewBox').innerHTML =
            '<div class="m-row"><span>Nama Lengkap</span><strong>' + escOts(nama) + '</strong></div>' +
            '<div class="m-row"><span>Nomor WhatsApp</span><strong>+62 ' + escOts(hp) + '</strong></div>' +
            '<div class="m-row"><span>Email</span><strong>' + escOts(email) + '</strong></div>' +
            '<div class="m-row"><span>Jumlah Tiket</span><strong>' + jml + ' tiket</strong></div>';

        document.getElementById('ots-step1').style.display = 'none';
        document.getElementById('ots-step2').style.display = 'block';
    }

    function otsBack() {
        document.getElementById('ots-step2').style.display = 'none';
        document.getElementById('ots-step1').style.display = 'block';
    }

    // Cegah submit ganda (sama kayak form publik) — disable di event 'submit',
    // bukan 'click', supaya submission yang sudah di-trigger browser tetap jalan.
    (function () {
        var form = document.getElementById('otsForm');
        var submitting = false;
        form.addEventListener('submit', function (e) {
            if (submitting) { e.preventDefault(); return; }
            submitting = true;
            var btn = document.getElementById('otsSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Memproses...';
        });
    })();
    </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
