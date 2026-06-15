<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

$id  = (int)($_GET['id'] ?? 0);
$row = null;

if ($pdo && $id) {
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$row) {
    http_response_code(404);
    $notFound = true;
}

// Derive daftar kode tiket dari batch
$ticketCodes = [];
if ($row) {
    $jt    = (int)$row['jumlah_tiket'];
    $batch = substr($row['kode_tiket'], 7, 4);
    for ($i = 0; $i < $jt; $i++) {
        $ticketCodes[] = 'FOAS13-' . $batch . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
    }
}

$hubunganMap = ['orang_tua' => 'Orang Tua', 'anak' => 'Anak', 'saudara' => 'Saudara'];
function rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

$pageTitle  = 'Detail Registrasi';
$activeMenu = 'dashboard';
require __DIR__ . '/partials/header.php';
?>
        <a href="index.php" class="adm-back">← Kembali ke Dashboard</a>

        <?php
        $msg = $_GET['msg'] ?? '';
        if ($msg === 'sent'): ?>
            <div class="adm-success">✓ Email berhasil dikirim ulang.</div>
        <?php elseif ($msg === 'fail'): ?>
            <div class="adm-alert">Gagal mengirim email: <?= htmlspecialchars($_SESSION['resend_error'] ?? 'unknown') ?><?php unset($_SESSION['resend_error']); ?></div>
        <?php elseif ($msg === 'err'): ?>
            <div class="adm-alert">Terjadi kesalahan saat mengirim email.</div>
        <?php endif; ?>

        <?php if (!empty($notFound)): ?>
            <div class="adm-alert">Registrasi tidak ditemukan.</div>
        <?php else: ?>

        <div class="detail-grid">
            <!-- Data peserta -->
            <div class="detail-card">
                <h3>Data Peserta</h3>
                <div class="detail-row"><span>Nama Lengkap</span><strong><?= htmlspecialchars($row['nama']) ?></strong></div>
                <div class="detail-row"><span>No. WhatsApp</span><strong><?= htmlspecialchars($row['no_hp']) ?></strong></div>
                <div class="detail-row"><span>Email</span><strong><?= htmlspecialchars($row['email']) ?></strong></div>
                <div class="detail-row"><span>Jumlah Tiket</span><strong><?= (int)$row['jumlah_tiket'] ?> tiket</strong></div>
                <div class="detail-row"><span>Sumbangan</span><strong><?= (float)$row['sumbangan_amount'] > 0 ? rp($row['sumbangan_amount']) : '—' ?></strong></div>
                <div class="detail-row"><span>Status Email</span><strong><?= $row['email_sent'] ? '<span class="badge-ok">Terkirim</span>' : '<span class="badge-no">Belum</span>' ?></strong></div>
                <div class="detail-row"><span>Tanggal Daftar</span><strong><?= date('d M Y H:i', strtotime($row['created_at'])) ?></strong></div>
                <?php $waUrl = waLink($row['no_hp'], adminTicketUrl($row['kode_tiket'])); ?>
                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener" class="adm-btn-wa" style="margin-top:1rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.867-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.345.223-.643.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    Kirim Link via WhatsApp
                </a>
                <form method="POST" action="resend.php" style="margin-top:0.6rem;">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="return" value="detail.php?id=<?= (int)$row['id'] ?>">
                    <button type="submit" class="adm-btn-secondary" style="width:100%;">
                        ✉ <?= $row['email_sent'] ? 'Kirim Ulang Email' : 'Kirim Email Tiket' ?>
                    </button>
                </form>
            </div>

            <!-- Kode tiket -->
            <div class="detail-card">
                <h3>Kode Tiket (<?= count($ticketCodes) ?>)</h3>
                <?php foreach ($ticketCodes as $i => $code): ?>
                    <div class="ticket-code-row">
                        <span class="tc-num">Tiket <?= $i + 1 ?></span>
                        <code><?= htmlspecialchars($code) ?></code>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Data arwah -->
            <?php if ($row['upload_arwah']): ?>
            <div class="detail-card">
                <h3>🕊️ Data Arwah yang Didoakan</h3>
                <?php if (!empty($row['foto_arwah'])): ?>
                    <div style="text-align:center;margin-bottom:1rem;">
                        <img src="../uploads/<?= htmlspecialchars($row['foto_arwah']) ?>" alt="Foto Arwah"
                             style="max-width:160px;max-height:160px;border-radius:10px;object-fit:cover;">
                    </div>
                <?php endif; ?>
                <div class="detail-row"><span>Nama Arwah</span><strong><?= htmlspecialchars($row['nama_arwah'] ?? '—') ?></strong></div>
                <div class="detail-row"><span>Tahun Lahir</span><strong><?= htmlspecialchars($row['tahun_lahir'] ?? '—') ?></strong></div>
                <div class="detail-row"><span>Tahun Wafat</span><strong><?= htmlspecialchars($row['tahun_wafat'] ?? '—') ?></strong></div>
                <div class="detail-row"><span>Hubungan</span><strong><?= htmlspecialchars($hubunganMap[$row['hubungan_arwah']] ?? '—') ?></strong></div>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
