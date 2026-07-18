<?php
session_start();
// No-cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reservasi Tiket — FOAS 14</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css?v=7" rel="stylesheet">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body class="form-page">
<script>
    // Clear localStorage & sessionStorage saat halaman form dibuka
    try { localStorage.clear(); sessionStorage.clear(); } catch(e) {}
</script>

<div class="form-wrapper">

    <?php
    $err = $_GET['error'] ?? '';
    $stockErrs = ['habis', 'sisa', 'closed'];
    $arwahErrs = ['arwah_nama', 'arwah_tahun', 'arwah_tahun_urut', 'arwah_hubungan', 'foto_format', 'foto_besar', 'foto_gagal'];
    if (in_array($err, $stockErrs, true)):
    ?>
    <div class="form-soldout">
        <?php if ($err === 'habis'): ?>
            <strong>Maaf, tiket sudah habis.</strong><br>Kuota tiket FOAS 14 telah terpenuhi.
        <?php elseif ($err === 'closed'): ?>
            <strong>Penjualan tiket belum dibuka.</strong><br>Nantikan info selanjutnya — Coming Soon.
        <?php else: $n = max(0, (int)($_GET['n'] ?? 0)); ?>
            <strong>Sisa tiket tinggal <?= $n ?>.</strong><br>Silakan kurangi jumlah tiket Anda.
        <?php endif; ?>
    </div>
    <?php elseif (in_array($err, $arwahErrs, true)): ?>
    <div class="form-soldout">
        <?php if ($err === 'arwah_nama'): ?>
            <strong>Nama arwah wajib diisi.</strong><br>Anda mencentang "upload arwah" tetapi nama arwah masih kosong.
        <?php elseif ($err === 'arwah_tahun'): ?>
            <strong>Tahun lahir & wafat wajib diisi.</strong><br>Isi dengan tahun yang valid (4 digit, tidak melebihi tahun ini).
        <?php elseif ($err === 'arwah_tahun_urut'): ?>
            <strong>Tahun wafat tidak boleh sebelum tahun lahir.</strong><br>Periksa kembali tahun lahir dan tahun wafat.
        <?php elseif ($err === 'arwah_hubungan'): ?>
            <strong>Hubungan wajib dipilih.</strong><br>Pilih hubungan Anda dengan arwah yang didoakan.
        <?php elseif ($err === 'foto_format'): ?>
            <strong>Format foto tidak didukung.</strong><br>Foto arwah harus berupa file JPG atau PNG.
        <?php elseif ($err === 'foto_besar'): ?>
            <strong>Ukuran foto terlalu besar.</strong><br>Maksimal 2 MB. Silakan perkecil ukuran file.
        <?php else: ?>
            <strong>Gagal mengunggah foto arwah.</strong><br>Silakan coba lagi.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Step Indicators -->
    <div class="progress-container">
        <div class="step-indicators">
            <div class="step-item active" id="si-1">
                <div class="step-circle">1</div>
                <span>Data Diri</span>
            </div>
            <div class="step-line" id="line-1"></div>
            <div class="step-item" id="si-2">
                <div class="step-circle">2</div>
                <span>Persembahan</span>
            </div>
            <div class="step-line" id="line-2"></div>
            <div class="step-item" id="si-3">
                <div class="step-circle">3</div>
                <span>Review</span>
            </div>
        </div>
    </div>

    <form id="registrasiForm" method="POST" action="process.php" enctype="multipart/form-data" autocomplete="off" novalidate>

        <!-- ================================================
             STEP 1: Data Diri
             ================================================ -->
        <div class="form-step" id="step-1">
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-step-tag">Langkah 1 dari 3</span>
                    <h2>Data Peserta</h2>
                    <p>Isi informasi Anda untuk reservasi tiket FOAS 14</p>
                </div>

                <div class="form-card-body">

                    <div class="form-group mb-4">
                        <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama" class="custom-input" style="width:100%; border-radius:8px; padding:.8rem 1rem; border:1px solid #2e2e2e; background:#222; color:#f0f0f0; font-size:1rem;" placeholder="Nama lengkap Anda" autocomplete="off">
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label">Nomor WhatsApp <span class="required">*</span></label>
                        <div style="display:flex;">
                            <span class="input-group-text country-code" style="border-radius:8px 0 0 8px;">+62</span>
                            <input type="tel" name="no_hp" inputmode="numeric" maxlength="15" class="custom-input" style="border-radius:0 8px 8px 0; width:100%; padding:.8rem 1rem; border:1px solid #2e2e2e; border-left:none; background:#222; color:#f0f0f0; font-size:1rem;" placeholder="812-3456-7890" autocomplete="off" oninput="var v=this.value.replace(/\D/g,'').replace(/^0+/,''); if(v.indexOf('62')===0)v=v.slice(2); this.value=v;">
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label">Email Aktif <span class="required">*</span></label>
                        <input type="email" name="email" class="custom-input" style="width:100%; border-radius:8px; padding:.8rem 1rem; border:1px solid #2e2e2e; background:#222; color:#f0f0f0; font-size:1rem;" placeholder="email@anda.com" autocomplete="off">
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label">Jumlah Tiket <span class="required">*</span></label>
                        <div class="ticket-counter">
                            <button type="button" class="counter-btn" id="decreaseBtn">−</button>
                            <input type="number" name="jumlah_tiket" id="ticketCount" class="counter-input" value="1" min="1" max="5" readonly>
                            <button type="button" class="counter-btn" id="increaseBtn">+</button>
                        </div>
                        <small class="text-muted-gold">Maksimal 5 tiket per transaksi</small>
                    </div>

                    <!-- Arwah Checkbox -->
                    <div class="arwah-toggle mb-3">
                        <label class="custom-checkbox-label">
                            <input type="checkbox" id="uploadArwah" name="upload_arwah" value="1" class="custom-checkbox">
                            <span class="checkmark"></span>
                            <div class="checkbox-text">
                                <strong>Upload Nama Arwah</strong>
                                <small>Dedikasikan tiket ini untuk mendoakan orang terkasih yang telah berpulang</small>
                            </div>
                        </label>
                    </div>

                    <!-- Arwah Form (Hidden) — mendukung sampai 5 arwah per tiket -->
                    <div class="arwah-form" id="arwahForm" style="display:none;">
                        <div id="arwahEntries">
                            <div class="arwah-card arwah-entry">
                                <div class="arwah-entry-head" style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                                    <h5 class="arwah-title">✦ Data Arwah <span class="arwah-num">1</span></h5>
                                    <button type="button" class="arwah-remove" title="Hapus arwah ini" style="display:none;background:none;border:none;color:#c0392b;font-size:1.1rem;cursor:pointer;line-height:1;">✕</button>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Foto Arwah <small style="color:#666;">(opsional)</small></label>
                                    <div class="upload-warning">⚠ Hanya file <strong>JPG</strong> atau <strong>PNG</strong>, maksimal <strong>2 MB</strong>.</div>
                                    <div class="upload-area arwah-uploadarea">
                                        <input type="file" name="foto_arwah[]" class="arwah-foto" accept="image/jpeg,image/png,.jpg,.jpeg,.png" hidden>
                                        <div class="upload-placeholder arwah-ph">
                                            <div class="upload-icon" style="font-size:2rem; margin-bottom:.5rem;">📷</div>
                                            <p>Drag &amp; drop foto di sini</p>
                                            <p class="upload-or">atau</p>
                                            <button type="button" class="btn-browse">Browse File</button>
                                            <small class="upload-hint">Format JPG / PNG &middot; Maksimal 2 MB</small>
                                        </div>
                                        <div class="upload-preview arwah-pv" style="display:none;">
                                            <img class="arwah-previmg" src="" alt="Preview">
                                            <button type="button" class="btn-remove-img arwah-rmimg">✕</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Nama Arwah <span class="required">*</span></label>
                                    <input type="text" name="nama_arwah[]" class="custom-input" style="width:100%; border-radius:8px; padding:.8rem 1rem; border:1px solid #2e2e2e; background:#181818; color:#f0f0f0; font-size:1rem;" placeholder="Nama lengkap almarhum/almarhumah" autocomplete="off">
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Tahun Lahir <span class="required">*</span></label>
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="tahun_lahir[]" class="custom-input" style="width:100%; border-radius:8px; padding:.8rem 1rem; border:1px solid #2e2e2e; background:#181818; color:#f0f0f0; font-size:1rem;" placeholder="1950" maxlength="4" autocomplete="off">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Tahun Wafat <span class="required">*</span></label>
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="tahun_wafat[]" class="custom-input" style="width:100%; border-radius:8px; padding:.8rem 1rem; border:1px solid #2e2e2e; background:#181818; color:#f0f0f0; font-size:1rem;" placeholder="2023" maxlength="4" autocomplete="off">
                                </div>

                                <div class="form-group mb-1">
                                    <label class="form-label">Hubungan dengan Anda <span class="required">*</span></label>
                                    <select name="hubungan_arwah[]" class="custom-input" style="width:100%; border-radius:8px; padding:.8rem 1rem; border:1px solid #2e2e2e; background:#181818; color:#f0f0f0; font-size:1rem; appearance:auto;">
                                        <option value="" disabled selected>Pilih hubungan...</option>
                                        <option value="orang_tua_ayah">Orang Tua - Ayah</option>
                                        <option value="orang_tua_ibu">Orang Tua - Ibu</option>
                                        <option value="pasangan">Pasangan</option>
                                        <option value="anak">Anak</option>
                                        <option value="saudara">Saudara/Kerabat/Teman</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-browse arwah-add" id="arwahAddBtn" style="margin-top:.9rem;">＋ Tambah Arwah (maks 5)</button>
                    </div>

                </div>

                <div class="form-card-footer">
                    <a href="index.php" class="btn-secondary-custom">← Kembali</a>
                    <button type="button" class="btn-primary-custom" onclick="nextStep(1)">Lanjutkan →</button>
                </div>
            </div>
        </div>

        <!-- ================================================
             STEP 2: Persembahan
             ================================================ -->
        <div class="form-step" id="step-2" style="display:none;">
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-step-tag">Langkah 2 dari 3</span>
                    <h2>Persembahan</h2>
                    <p>Dukungan Anda sangat berarti untuk kelangsungan FOAS 14</p>
                </div>

                <div class="form-card-body">

                    <div class="offering-info">
                        <div class="offering-icon">🎵</div>
                        <h5>Sumbangan Sukarela</h5>
                        <p>Persembahan ini bersifat <strong>tidak wajib</strong>. Jika ingin memberikan dukungan, silakan transfer ke rekening berikut:</p>
                    </div>

                    <div class="bank-card">
                        <div class="bank-logo">BCA</div>
                        <div class="bank-details">
                            <p class="bank-name">PT Bank Central Asia Tbk</p>
                            <p class="bank-account">1234 5678</p>
                            <p class="bank-holder">Vita Voxa Choir</p>
                        </div>
                        <button type="button" class="btn-copy btn-copy-full" onclick="copyRekening()">Salin Nomor Rekening</button>
                    </div>

                    <div class="offering-note">
                        <div class="note-icon">💡</div>
                        <p>Tambahkan <strong>001</strong> di akhir nominal transfer Anda sebagai penanda sumbangan.<br>
                        <em>Contoh: Rp 100.001 atau Rp 50.001</em></p>
                    </div>

                    <div class="form-group mt-4">
                        <label class="form-label">Nominal Sumbangan <small style="color:#666;">(opsional)</small></label>
                        <div style="display:flex;">
                            <span class="input-group-text country-code" style="border-radius:8px 0 0 8px;">Rp</span>
                            <input type="text" inputmode="numeric" name="sumbangan_amount" class="custom-input" style="border-radius:0 8px 8px 0; width:100%; padding:.8rem 1rem; border:1px solid #2e2e2e; border-left:none; background:#222; color:#f0f0f0; font-size:1rem;" placeholder="0" autocomplete="off" oninput="formatSumbangan(this)">
                        </div>
                        <small class="text-muted-gold">Kosongkan jika tidak ingin memberikan sumbangan</small>
                    </div>

                </div>

                <div class="form-card-footer">
                    <button type="button" class="btn-secondary-custom" onclick="prevStep(2)">← Kembali</button>
                    <button type="button" class="btn-primary-custom" onclick="nextStep(2)">Lanjutkan →</button>
                </div>
            </div>
        </div>

        <!-- ================================================
             STEP 3: Review
             ================================================ -->
        <div class="form-step" id="step-3" style="display:none;">
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-step-tag">Langkah 3 dari 3</span>
                    <h2>Periksa Data</h2>
                    <p>Pastikan semua informasi sudah benar sebelum mengirimkan</p>
                </div>

                <div class="form-card-body">
                    <div class="review-section" id="reviewContent">
                        <!-- Diisi oleh JavaScript -->
                    </div>
                </div>

                <div class="form-card-footer">
                    <button type="button" class="btn-secondary-custom" onclick="prevStep(3)">← Kembali</button>
                    <button type="submit" class="btn-submit-custom">Kirim Reservasi ✓</button>
                </div>
            </div>
        </div>

    </form>
</div>

<!-- Photo Preview Modal -->
<div id="photoModal" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:9999;align-items:center;justify-content:center;padding:1.5rem;">
    <img id="photoModalImg" src="" style="max-width:90vw;max-height:88vh;border-radius:12px;object-fit:contain;box-shadow:0 8px 32px rgba(0,0,0,0.5);">
    <div style="position:absolute;top:1rem;right:1.2rem;color:#fff;font-size:1.8rem;cursor:pointer;line-height:1;" onclick="document.getElementById('photoModal').style.display='none'">✕</div>
</div>

<script src="assets/js/form.js?v=5"></script>
<script>
var _lt = 0;
document.addEventListener('touchend', function(e) {
    var now = Date.now();
    if (now - _lt < 300) { e.preventDefault(); }
    _lt = now;
}, { passive: false });
</script>
</body>
</html>
