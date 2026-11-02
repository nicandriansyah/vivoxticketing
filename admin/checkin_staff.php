<?php
/* Akses petugas check-in TANPA login admin — cukup PIN pendek yang di-set
   admin lewat dashboard (card "PIN Check-in"). Sesi PIN terpisah dari sesi
   admin biasa ($_SESSION['checkin_pin_ok']), jadi tidak bisa buka halaman
   admin lain (Dashboard, PPT Generator, dst). */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';
if ($pdo) { try { ensureTicketTables($pdo); } catch (Exception $e) {} }

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (isset($_GET['logout'])) {
    unset($_SESSION['checkin_pin_ok']);
    header('Location: checkin_staff.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin          = trim($_POST['pin'] ?? '');
    $configured   = $pdo ? (string)getSetting($pdo, 'checkin_pin', '') : '';
    if ($configured === '') {
        $error = 'PIN check-in belum diaktifkan admin. Hubungi panitia.';
    } elseif ($pin !== '' && hash_equals($configured, $pin)) {
        $_SESSION['checkin_pin_ok'] = true;
    } else {
        $error = 'PIN salah, coba lagi.';
    }
}

$unlocked = !empty($_SESSION['checkin_pin_ok']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in Tiket</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css?v=55" rel="stylesheet">
</head>
<body class="<?= $unlocked ? 'admin-page' : 'admin-login-page' ?>">
<?php if (!$unlocked): ?>
    <div class="login-card">
        <div class="login-brand">
            <img src="../tiker.png" alt="ticker" class="login-logo-img" draggable="false" oncontextmenu="return false">
        </div>
        <p style="text-align:center;color:var(--muted);font-size:0.85rem;margin:-0.6rem 0 1.2rem;">Akses petugas check-in — masukkan PIN dari panitia.</p>
        <?php if ($error): ?>
            <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <label class="adm-label">PIN</label>
            <input type="password" inputmode="numeric" name="pin" class="adm-input" style="text-align:center;letter-spacing:6px;font-size:1.3rem;" autofocus required>
            <button type="submit" class="adm-btn-primary" style="width:100%;margin-top:1rem;">Masuk</button>
        </form>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);text-align:center;">
            <a href="login.php" class="adm-btn-ghost" style="display:block;">← Kembali ke Login Admin</a>
        </div>
    </div>
<?php else: ?>
    <div class="adm-main" style="max-width:480px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <h2 style="margin:0;font-size:1.2rem;">🎫 Check-in Tiket</h2>
            <a href="?logout=1" class="adm-btn-ghost" style="padding:0.4rem 0.8rem;font-size:0.85rem;">Keluar</a>
        </div>
        <?php require __DIR__ . '/partials/checkin_body.php'; ?>
    </div>
<?php endif; ?>
</body>
</html>
