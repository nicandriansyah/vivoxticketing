<?php
require_once __DIR__ . '/auth.php';
requireAdminRole();   // khusus role admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
require_once __DIR__ . '/../config/app.php';
if ($pdo) { try { ensureTicketTables($pdo); } catch (Exception $e) {} }

$pageTitle  = 'Buku Program';
$activeMenu = 'program_book';
require __DIR__ . '/partials/header.php';

$bookFile = $pdo ? (string)getSetting($pdo, 'program_book_filename', '') : '';
$bookAt   = $pdo ? (string)getSetting($pdo, 'program_book_uploaded_at', '') : '';
$bookPath = dirname(__DIR__) . '/uploads/program-book/book.pdf';
$hasBook  = $bookFile !== '' && is_file($bookPath);
$bookSize = $hasBook ? filesize($bookPath) : 0;

$basePath   = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$publicLink = publicUrl('buku-program.php', $basePath);

function formatBytes(int $b): string {
    if ($b >= 1024 * 1024) return number_format($b / (1024 * 1024), 1, ',', '.') . ' MB';
    if ($b >= 1024)        return number_format($b / 1024, 0, ',', '.') . ' KB';
    return $b . ' B';
}

$err = $_GET['error'] ?? '';
$errMsgs = [
    'format' => 'File harus berformat PDF.',
    'size'   => 'Ukuran file terlalu besar (maks 50MB).',
    'save'   => 'Gagal menyimpan file ke server.',
];
?>

        <div class="checkin-card" style="max-width:560px;margin:0 auto;">
            <h3 class="ci-title">📖 Buku Program</h3>
            <p class="ci-sub">Upload PDF buku acara — penonton bisa buka &amp; baca di HP masing-masing lewat link di bawah, dengan tampilan buka-buku yang bisa di-swipe.</p>

            <?php if ($err && isset($errMsgs[$err])): ?>
                <div class="ci-error" style="display:block;"><?= htmlspecialchars($errMsgs[$err]) ?></div>
            <?php endif; ?>
            <?php if (($_GET['msg'] ?? '') === 'ok'): ?>
                <div class="adm-success" style="margin-bottom:1rem;">✓ Buku program berhasil diupload.</div>
            <?php elseif (($_GET['msg'] ?? '') === 'deleted'): ?>
                <div class="adm-success" style="margin-bottom:1rem;">✓ Buku program dihapus.</div>
            <?php endif; ?>

            <?php if ($hasBook): ?>
            <div class="m-section" style="margin-bottom:1.2rem;">
                <div class="m-row"><span>File</span><strong><?= htmlspecialchars($bookFile) ?></strong></div>
                <div class="m-row"><span>Ukuran</span><strong><?= formatBytes($bookSize) ?></strong></div>
                <?php if ($bookAt): ?><div class="m-row"><span>Diupload</span><strong><?= htmlspecialchars($bookAt) ?></strong></div><?php endif; ?>
            </div>

            <label class="hc-label">Link buat penonton</label>
            <div style="display:flex;gap:.4rem;margin-bottom:1rem;">
                <input type="text" id="bookLink" class="hc-input" readonly style="flex:1;" value="<?= htmlspecialchars($publicLink) ?>">
                <button type="button" class="adm-btn-ghost" style="white-space:nowrap;" onclick="copyBookLink()">📋 Salin</button>
                <a href="<?= htmlspecialchars($publicLink) ?>" target="_blank" class="adm-btn-secondary" style="white-space:nowrap;">👁 Lihat</a>
            </div>
            <span id="bookLinkStatus" style="font-size:.8rem;font-weight:600;color:#1a7a40;"></span>
            <?php endif; ?>

            <form method="POST" action="program_book_process.php" enctype="multipart/form-data" style="margin-top:1rem;">
                <label class="hc-label"><?= $hasBook ? 'Ganti dengan PDF lain' : 'Pilih file PDF' ?></label>
                <input type="file" name="book" accept="application/pdf,.pdf" class="hc-input" required>
                <button type="submit" class="adm-btn-primary" style="width:100%;margin-top:1rem;">⬆ Upload</button>
            </form>

            <?php if ($hasBook): ?>
            <form method="POST" action="program_book_delete.php" style="margin-top:.6rem;" onsubmit="return confirm('Hapus buku program? Link publik akan berhenti menampilkan apa-apa sampai ada upload baru.');">
                <button type="submit" class="adm-btn-danger" style="width:100%;">🗑 Hapus Buku Program</button>
            </form>
            <?php endif; ?>
        </div>

    <script>
    function copyBookLink() {
        var el = document.getElementById('bookLink');
        el.select(); el.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(el.value).catch(function () { document.execCommand('copy'); });
        var st = document.getElementById('bookLinkStatus');
        st.textContent = '✓ Link disalin';
        setTimeout(function () { st.textContent = ''; }, 1500);
    }
    </script>

<?php require __DIR__ . '/partials/footer.php'; ?>
