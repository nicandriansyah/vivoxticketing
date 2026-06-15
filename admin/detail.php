<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Registrasi — Admin FOAS 13</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body class="admin-page">

    <header class="adm-topbar">
        <div class="adm-brand">FOAS 13 <span>Admin</span></div>
        <nav class="adm-nav">
            <span class="adm-user">👤 <?= htmlspecialchars($_SESSION['admin_user'] ?? 'admin') ?></span>
            <a href="logout.php" class="adm-logout">Keluar</a>
        </nav>
    </header>

    <main class="adm-main">
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
                <form method="POST" action="resend.php" style="margin-top:1rem;">
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
    </main>
</body>
</html>
