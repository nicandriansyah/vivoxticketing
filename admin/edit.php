<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/helpers.php';

if (!$pdo) { header('Location: index.php'); exit; }
try { ensureTicketTables($pdo); } catch (Exception $e) {}

$id  = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { header('Location: index.php'); exit; }

$jumlah    = (int)$row['jumlah_tiket'];
$codes     = deriveAllCodes($row['kode_tiket'], $jumlah);
$checked   = getCheckedMap($pdo, [$id])[$id] ?? [];
$cancelled = getCancelledMap($pdo, [$id])[$id] ?? [];
$aktif     = $jumlah - count($cancelled);
$ticketUrl = adminTicketUrl($row['kode_tiket']);

$arwahRows = getArwahForReg($pdo, $id);
$maxSlots  = min($jumlah, 5);
$canAdd    = count($arwahRows) < $maxSlots;
$hubOpts   = hubunganOptions();

$pageTitle  = 'Edit Registrasi';
$activeMenu = 'dashboard';
$mainClass  = 'adm-main-full';
require __DIR__ . '/partials/header.php';
?>

        <a href="index.php" class="adm-btn-ghost" style="display:inline-flex;margin-bottom:1rem;">← Kembali ke Dashboard</a>

        <h2 style="margin-bottom:0.2rem;"><?= htmlspecialchars($row['nama']) ?></h2>

        <!-- Kontak: tampilan default, form terbuka saat klik Edit -->
        <div id="contactView" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.7rem;">
            <p class="m-contact" style="margin:0;"><?= htmlspecialchars(phoneDisplay($row['no_hp'])) ?> &middot; <?= htmlspecialchars($row['email']) ?></p>
            <button type="button" class="m-copy-btn" style="width:auto;padding:.15rem .55rem;font-size:.78rem;" onclick="toggleContact(true)">✎ Edit</button>
        </div>
        <div id="contactEdit" style="display:none;margin:.1rem 0 .7rem;">
            <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
                <div style="flex:1.2 1 200px;min-width:180px;max-width:300px;">
                    <label class="hc-label" style="margin-top:0;">Nama</label>
                    <input type="text" id="cNama" class="m-link-input" style="width:100%;" value="<?= htmlspecialchars($row['nama']) ?>">
                </div>
                <div style="flex:1 1 170px;min-width:160px;max-width:260px;">
                    <label class="hc-label" style="margin-top:0;">No. WhatsApp</label>
                    <input type="text" id="cPhone" class="m-link-input" style="width:100%;" value="<?= htmlspecialchars(phoneDisplay($row['no_hp'])) ?>">
                </div>
                <div style="flex:1.4 1 220px;min-width:200px;max-width:340px;">
                    <label class="hc-label" style="margin-top:0;">Email</label>
                    <input type="email" id="cEmail" class="m-link-input" style="width:100%;" value="<?= htmlspecialchars($row['email']) ?>">
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;flex:0 0 auto;">
                    <button type="button" class="m-view-btn" onclick="saveContact()">💾 Simpan</button>
                    <button type="button" class="m-cancel-btn" onclick="toggleContact(false)">Batal</button>
                    <span id="cStatus" style="font-size:.8rem;font-weight:600;"></span>
                </div>
            </div>
        </div>

        <!-- Link tiket publik -->
        <div class="m-linkrow" style="max-width:560px;">
            <input class="m-link-input" value="<?= htmlspecialchars($ticketUrl) ?>" readonly onclick="this.select()">
            <button type="button" class="m-copy-btn" onclick="copyUrl(this)" title="Salin link tiket">📋</button>
        </div>

        <div class="m-cols<?= $arwahRows || $canAdd ? ' two' : '' ?>">

            <!-- Kolom kiri: aktivitas tiket -->
            <div class="m-col-left">
                <h4 class="m-sub">Tiket Terbentuk (<?= $aktif ?> aktif / <?= $jumlah ?>)</h4>
                <div class="m-tickets">
                    <?php foreach ($codes as $i => $c):
                        $isCancel = in_array($c, $cancelled, true);
                        $isIn     = in_array($c, $checked, true);
                    ?>
                    <div class="m-ticket<?= $isCancel ? ' cancelled' : '' ?>">
                        <div class="m-ticket-head"><span class="tt-n"><?= $i + 1 ?></span><code><?= htmlspecialchars($c) ?></code></div>
                        <div class="m-ticket-foot">
                            <?php if ($isCancel): ?>
                                <span class="badge-no">Dibatalkan</span>
                            <?php elseif ($isIn): ?>
                                <span class="badge-ok">✓ Check-in</span>
                            <?php else: ?>
                                <span class="m-badge-active">Aktif</span>
                                <button type="button" class="m-cancel-btn" onclick="cancelTicket('<?= htmlspecialchars($c, ENT_QUOTES) ?>')">Batalkan</button>
                            <?php endif; ?>
                        </div>
                        <div class="m-ticket-actions">
                            <button type="button" class="m-view-btn" onclick="viewTicket('<?= htmlspecialchars($c, ENT_QUOTES) ?>')">👁 Lihat Tiket</button>
                            <button type="button" class="m-wa-btn" onclick="shareTicketWA('<?= htmlspecialchars($c, ENT_QUOTES) ?>')">↗ Share WA</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Kolom kanan: data arwah -->
            <?php if ($arwahRows || $canAdd): ?>
            <div class="m-col-right">
                <h4 class="m-sub">🕊️ Data Arwah (<?= count($arwahRows) ?> dari maks. <?= $maxSlots ?>)</h4>
                <div class="m-arwah-list">
                <?php foreach ($arwahRows as $i => $a): $uid = (int)$a['id'];
                    $fotoUrl = $a['foto_arwah'] ? adminUploadUrl($a['foto_arwah']) : ''; ?>
                    <div class="m-arwah-unit">
                        <div class="m-arwah-no" style="font-weight:600;color:#8a6d1a;margin:.4rem 0 .2rem;">Arwah #<?= $i + 1 ?></div>

                        <!-- Tampilan -->
                        <div id="aView-<?= $uid ?>">
                            <?php if ($fotoUrl): ?>
                                <div style="text-align:center;margin-bottom:.5rem;"><img class="m-arwah-foto" src="<?= htmlspecialchars($fotoUrl) ?>" alt=""></div>
                            <?php endif; ?>
                            <div class="m-section">
                                <div class="m-row"><span>Nama Arwah</span><strong><?= htmlspecialchars($a['nama_arwah'] ?: '—') ?></strong></div>
                                <div class="m-row"><span>Tahun Lahir</span><strong><?= htmlspecialchars($a['tahun_lahir'] ?? '') ?: '—' ?></strong></div>
                                <div class="m-row"><span>Tahun Wafat</span><strong><?= htmlspecialchars($a['tahun_wafat'] ?? '') ?: '—' ?></strong></div>
                                <div class="m-row"><span>Hubungan</span><strong><?= htmlspecialchars(hubunganLabel($a['hubungan_arwah'])) ?></strong></div>
                            </div>
                            <div style="display:flex;gap:.4rem;margin-top:.4rem;">
                                <button type="button" class="m-copy-btn" style="width:auto;padding:.15rem .55rem;font-size:.78rem;" onclick="toggleArwah(<?= $uid ?>, true)">✎ Edit</button>
                                <button type="button" class="m-cancel-btn" style="padding:.15rem .55rem;font-size:.78rem;" onclick="deleteArwah(<?= $uid ?>, '<?= htmlspecialchars($a['nama_arwah'], ENT_QUOTES) ?>')">🗑 Hapus</button>
                            </div>
                        </div>

                        <!-- Form edit (tertutup sampai klik Edit) -->
                        <div id="aEdit-<?= $uid ?>" style="display:none;">
                            <?php if ($fotoUrl): ?>
                                <div style="text-align:center;margin-bottom:.4rem;"><img class="m-arwah-foto" src="<?= htmlspecialchars($fotoUrl) ?>" alt=""></div>
                            <?php endif; ?>
                            <label class="hc-label"><?= $fotoUrl ? 'Upload Ulang Foto' : 'Upload Foto' ?> (JPG/PNG, maks 2MB)</label>
                            <input type="file" id="aFoto-<?= $uid ?>" accept="image/jpeg,image/png" style="width:100%;font-size:.8rem;">
                            <label class="hc-label">Nama Arwah</label>
                            <input type="text" id="aNama-<?= $uid ?>" class="m-link-input" style="width:100%;" value="<?= htmlspecialchars($a['nama_arwah']) ?>">
                            <div style="display:flex;gap:.5rem;">
                                <div style="flex:1;">
                                    <label class="hc-label">Tahun Lahir</label>
                                    <input type="number" id="aLahir-<?= $uid ?>" class="m-link-input" style="width:100%;" value="<?= htmlspecialchars($a['tahun_lahir'] ?? '') ?>">
                                </div>
                                <div style="flex:1;">
                                    <label class="hc-label">Tahun Wafat</label>
                                    <input type="number" id="aWafat-<?= $uid ?>" class="m-link-input" style="width:100%;" value="<?= htmlspecialchars($a['tahun_wafat'] ?? '') ?>">
                                </div>
                            </div>
                            <label class="hc-label">Hubungan</label>
                            <select id="aHub-<?= $uid ?>" class="m-link-input" style="width:100%;">
                                <option value="">— Pilih Hubungan —</option>
                                <?php foreach ($hubOpts as $k => $label): ?>
                                    <option value="<?= $k ?>" <?= ($a['hubungan_arwah'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div style="display:flex;align-items:center;gap:.5rem;margin-top:.6rem;flex-wrap:wrap;">
                                <button type="button" class="m-view-btn" onclick="saveArwah(<?= $uid ?>)">💾 Simpan</button>
                                <button type="button" class="m-cancel-btn" onclick="toggleArwah(<?= $uid ?>, false)">Batal</button>
                                <span id="aStatus-<?= $uid ?>" style="font-size:.8rem;font-weight:600;"></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <?php if ($canAdd): ?>
                <!-- Tambah arwah (form tertutup sampai tombol diklik) -->
                <button type="button" class="adm-btn-secondary" id="btnShowAdd" style="margin-top:.6rem;" onclick="toggleAdd(true)">➕ Tambah Arwah (<?= $maxSlots - count($arwahRows) ?> slot tersisa)</button>
                <div id="addArwahForm" style="display:none;border:1px solid var(--gold);border-radius:10px;padding:0.9rem 1rem;margin-top:.6rem;">
                    <div style="font-weight:600;color:#8a6d1a;margin-bottom:.3rem;">Tambah Arwah #<?= count($arwahRows) + 1 ?></div>
                    <label class="hc-label">Foto (JPG/PNG, maks 2MB — opsional)</label>
                    <input type="file" id="nFoto" accept="image/jpeg,image/png" style="width:100%;font-size:.8rem;">
                    <label class="hc-label">Nama Arwah</label>
                    <input type="text" id="nNama" class="m-link-input" style="width:100%;">
                    <div style="display:flex;gap:.5rem;">
                        <div style="flex:1;">
                            <label class="hc-label">Tahun Lahir</label>
                            <input type="number" id="nLahir" class="m-link-input" style="width:100%;">
                        </div>
                        <div style="flex:1;">
                            <label class="hc-label">Tahun Wafat</label>
                            <input type="number" id="nWafat" class="m-link-input" style="width:100%;">
                        </div>
                    </div>
                    <label class="hc-label">Hubungan</label>
                    <select id="nHub" class="m-link-input" style="width:100%;">
                        <option value="">— Pilih Hubungan —</option>
                        <?php foreach ($hubOpts as $k => $label): ?>
                            <option value="<?= $k ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:flex;align-items:center;gap:.5rem;margin-top:.7rem;flex-wrap:wrap;">
                        <button type="button" class="m-view-btn" onclick="addArwah()">💾 Simpan Arwah</button>
                        <button type="button" class="m-cancel-btn" onclick="toggleAdd(false)">Batal</button>
                        <span id="nStatus" style="font-size:.8rem;font-weight:600;"></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    <!-- Lightbox gambar tiket -->
    <div id="imgLightbox" class="img-lightbox" onclick="if(event.target===this)closeLightbox()">
        <div class="img-lightbox-inner">
            <button class="modal-close" onclick="closeLightbox()">✕</button>
            <div id="lightboxBody"></div>
            <button type="button" class="adm-btn-wa" style="margin-top:0.9rem;" onclick="shareTicketWA(lbCode)">↗ Share ke WhatsApp</button>
        </div>
    </div>

        <script>
        var REG_ID = <?= $id ?>;

        function setStatus(el, ok, text) {
            el.style.color = ok ? '#1a7a40' : '#c0392b';
            el.textContent = text;
        }

        /* ---------- Kontak ---------- */
        function toggleContact(edit) {
            document.getElementById('contactView').style.display = edit ? 'none' : 'flex';
            document.getElementById('contactEdit').style.display = edit ? 'block' : 'none';
            var st = document.getElementById('cStatus'); if (st) st.textContent = '';
        }
        function saveContact() {
            var st = document.getElementById('cStatus');
            var fd = new FormData();
            fd.append('id', REG_ID);
            fd.append('nama',  document.getElementById('cNama').value.trim());
            fd.append('no_hp', document.getElementById('cPhone').value.trim());
            fd.append('email', document.getElementById('cEmail').value.trim());
            st.style.color = '#666'; st.textContent = 'Menyimpan...';
            fetch('update_contact.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { setStatus(st, false, res.error || 'Gagal menyimpan'); return; }
                    setStatus(st, true, '✓ Tersimpan');
                    setTimeout(function () { location.reload(); }, 600);
                })
                .catch(function () { setStatus(st, false, 'Koneksi gagal'); });
        }

        function copyUrl(btn) {
            var input = btn.parentElement.querySelector('.m-link-input');
            var done = function () {
                var o = btn.textContent;
                btn.textContent = '✓';
                setTimeout(function () { btn.textContent = o; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(input.value).then(done).catch(function () { input.select(); document.execCommand('copy'); done(); });
            } else {
                input.select(); document.execCommand('copy'); done();
            }
        }

        /* ---------- Tiket: batalkan, lihat, share ---------- */
        function cancelTicket(code) {
            if (!confirm('Batalkan tiket ' + code + '?\nTiket tidak dihapus, hanya mengurangi total tiket aktif.')) return;
            var fd = new FormData();
            fd.append('code', code);
            fetch('cancel_ticket.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.ok) { location.reload(); }
                    else { alert(res.error || 'Gagal membatalkan tiket.'); }
                })
                .catch(function () { alert('Koneksi gagal.'); });
        }

        var lbCode = null;
        function viewTicket(code) {
            lbCode = code;
            document.getElementById('lightboxBody').innerHTML =
                '<p class="lb-loading">Memuat gambar tiket…</p>' +
                '<img class="lb-img" src="ticket_image.php?code=' + encodeURIComponent(code) + '&t=' + Date.now() + '" ' +
                     'onload="this.previousElementSibling.style.display=\'none\';" ' +
                     'onerror="this.style.display=\'none\';this.previousElementSibling.innerHTML=\'Gambar tiket belum tersedia.<br><small>Tiket tersimpan otomatis saat peserta membuka link tiketnya.</small>\';">';
            document.getElementById('imgLightbox').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeLightbox() {
            document.getElementById('imgLightbox').classList.remove('open');
            document.body.style.overflow = '';
        }
        async function shareTicketWA(code) {
            if (!code) return;
            try {
                var resp = await fetch('ticket_image.php?code=' + encodeURIComponent(code));
                if (!resp.ok) throw new Error('no-image');
                var blob = await resp.blob();
                var file = new File([blob], code + '.jpg', { type: 'image/jpeg' });

                if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({ files: [file], title: 'Tiket FOAS 14' });
                } else {
                    // Fallback (desktop): buka gambar di tab baru untuk dibagikan manual
                    var url = URL.createObjectURL(blob);
                    window.open(url, '_blank');
                    setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
                }
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                alert('Gambar tiket belum tersedia untuk dibagikan.');
            }
        }

        /* ---------- Edit arwah ---------- */
        function toggleArwah(id, edit) {
            document.getElementById('aView-' + id).style.display = edit ? 'none' : 'block';
            document.getElementById('aEdit-' + id).style.display = edit ? 'block' : 'none';
            var st = document.getElementById('aStatus-' + id); if (st) st.textContent = '';
        }
        function saveArwah(id) {
            var st = document.getElementById('aStatus-' + id);
            var fd = new FormData();
            fd.append('id', id);
            fd.append('nama_arwah',     document.getElementById('aNama-'  + id).value.trim());
            fd.append('tahun_lahir',    document.getElementById('aLahir-' + id).value.trim());
            fd.append('tahun_wafat',    document.getElementById('aWafat-' + id).value.trim());
            fd.append('hubungan_arwah', document.getElementById('aHub-'   + id).value);
            var f = document.getElementById('aFoto-' + id).files[0];
            if (f) fd.append('foto', f);
            st.style.color = '#666'; st.textContent = 'Menyimpan...';
            fetch('arwah_update.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { setStatus(st, false, res.error || 'Gagal menyimpan'); return; }
                    setStatus(st, true, '✓ Tersimpan');
                    setTimeout(function () { location.reload(); }, 600);
                })
                .catch(function () { setStatus(st, false, 'Koneksi gagal'); });
        }

        function deleteArwah(id, nama) {
            if (!confirm('Hapus data arwah "' + nama + '"?\nFoto yang diupload juga akan dihapus dan slide PPT-nya hilang.')) return;
            var fd = new FormData();
            fd.append('id', id);
            fetch('arwah_delete.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { alert(res.error || 'Gagal menghapus.'); return; }
                    location.reload();
                })
                .catch(function () { alert('Koneksi gagal.'); });
        }

        /* ---------- Tambah arwah ---------- */
        function toggleAdd(show) {
            document.getElementById('addArwahForm').style.display = show ? 'block' : 'none';
            document.getElementById('btnShowAdd').style.display   = show ? 'none' : 'inline-flex';
            var st = document.getElementById('nStatus'); if (st) st.textContent = '';
        }
        function addArwah() {
            var st = document.getElementById('nStatus');
            var fd = new FormData();
            fd.append('registration_id', REG_ID);
            fd.append('nama_arwah',     document.getElementById('nNama').value.trim());
            fd.append('tahun_lahir',    document.getElementById('nLahir').value.trim());
            fd.append('tahun_wafat',    document.getElementById('nWafat').value.trim());
            fd.append('hubungan_arwah', document.getElementById('nHub').value);
            var f = document.getElementById('nFoto').files[0];
            if (f) fd.append('foto', f);
            st.style.color = '#666'; st.textContent = 'Menyimpan...';
            fetch('arwah_add.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { setStatus(st, false, res.error || 'Gagal menyimpan'); return; }
                    setStatus(st, true, '✓ Arwah ditambahkan');
                    setTimeout(function () { location.reload(); }, 600);
                })
                .catch(function () { setStatus(st, false, 'Koneksi gagal'); });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById('imgLightbox').classList.contains('open')) closeLightbox();
        });
        </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
