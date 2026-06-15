<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

$dbReady = (bool)$pdo;

$stats = ['reg' => 0, 'tiket' => 0, 'sumbangan' => 0, 'email' => 0];
$rows  = [];
$total = 0;

// Pencarian + pagination
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

if ($dbReady) {
    try {
        // Statistik
        $s = $pdo->query("SELECT
                COUNT(*)                       AS reg,
                COALESCE(SUM(jumlah_tiket),0)  AS tiket,
                COALESCE(SUM(sumbangan_amount),0) AS sumbangan,
                COALESCE(SUM(email_sent),0)    AS email
            FROM registrations")->fetch(PDO::FETCH_ASSOC);
        $stats = [
            'reg'       => (int)$s['reg'],
            'tiket'     => (int)$s['tiket'],
            'sumbangan' => (float)$s['sumbangan'],
            'email'     => (int)$s['email'],
        ];

        // Filter
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = "WHERE nama LIKE ? OR email LIKE ? OR kode_tiket LIKE ? OR no_hp LIKE ?";
            $like  = "%$q%";
            $params = [$like, $like, $like, $like];
        }

        $cstmt = $pdo->prepare("SELECT COUNT(*) FROM registrations $where");
        $cstmt->execute($params);
        $total = (int)$cstmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM registrations $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $dbReady = false;
        $dbErrMsg = $e->getMessage();
    }
}

$totalPages = max(1, (int)ceil($total / $perPage));
function rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Admin FOAS 13</title>
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

        <?php
        $msg = $_GET['msg'] ?? '';
        if ($msg === 'sent'): ?>
            <div class="adm-success">✓ Email berhasil dikirim ulang.</div>
        <?php elseif ($msg === 'fail'): ?>
            <div class="adm-alert">Gagal mengirim email: <?= htmlspecialchars($_SESSION['resend_error'] ?? 'unknown') ?><?php unset($_SESSION['resend_error']); ?></div>
        <?php elseif ($msg === 'err'): ?>
            <div class="adm-alert">Terjadi kesalahan saat mengirim email.</div>
        <?php endif; ?>

        <?php if (!$dbReady): ?>
            <div class="adm-alert">
                Koneksi database gagal<?= isset($dbErrMsg) ? ': ' . htmlspecialchars($dbErrMsg) : '' ?>.
                Pastikan <code>config/db.local.php</code> sudah benar.
            </div>
        <?php endif; ?>

        <!-- Stat cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label">Total Registrasi</div>
                <div class="stat-value"><?= number_format($stats['reg'], 0, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Tiket</div>
                <div class="stat-value"><?= number_format($stats['tiket'], 0, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Sumbangan</div>
                <div class="stat-value"><?= rp($stats['sumbangan']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Email Terkirim</div>
                <div class="stat-value"><?= number_format($stats['email'], 0, ',', '.') ?> <small>/ <?= number_format($stats['reg'], 0, ',', '.') ?></small></div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="adm-toolbar">
            <form method="GET" class="adm-search">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama, email, no HP, atau kode tiket..." class="adm-input">
                <button type="submit" class="adm-btn-primary">Cari</button>
                <?php if ($q !== ''): ?><a href="index.php" class="adm-btn-ghost">Reset</a><?php endif; ?>
            </form>
            <a href="export.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>" class="adm-btn-secondary">⬇ Export CSV</a>
        </div>

        <!-- Table -->
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Nama</th>
                        <th>No. WhatsApp</th>
                        <th>Email</th>
                        <th>Tiket</th>
                        <th>Sumbangan</th>
                        <th>Arwah</th>
                        <th>Email</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="10" class="adm-empty"><?= $q !== '' ? 'Tidak ada hasil untuk "' . htmlspecialchars($q) . '"' : 'Belum ada registrasi.' ?></td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                            <td class="adm-strong"><?= htmlspecialchars($r['nama']) ?></td>
                            <td><?= htmlspecialchars($r['no_hp']) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td style="text-align:center;"><?= (int)$r['jumlah_tiket'] ?></td>
                            <td><?= (float)$r['sumbangan_amount'] > 0 ? rp($r['sumbangan_amount']) : '—' ?></td>
                            <td style="text-align:center;"><?= $r['upload_arwah'] ? '🕊️' : '—' ?></td>
                            <td style="text-align:center;"><?= $r['email_sent'] ? '<span class="badge-ok">✓</span>' : '<span class="badge-no">✗</span>' ?></td>
                            <td style="white-space:nowrap;">
                                <a href="detail.php?id=<?= (int)$r['id'] ?>" class="adm-link">Detail</a>
                                <form method="POST" action="resend.php" style="display:inline; margin-left:0.6rem;">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="return" value="index.php?page=<?= $page ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">
                                    <button type="submit" class="adm-link-btn" title="Kirim ulang email">✉ Resend</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="adm-pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"
                   class="adm-page <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <p class="adm-foot">Menampilkan <?= count($rows) ?> dari <?= number_format($total, 0, ',', '.') ?> registrasi</p>

    </main>
</body>
</html>
