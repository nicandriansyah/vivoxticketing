<?php
session_start();
// Clear session saat kembali ke halaman utama — fresh start
$_SESSION = [];
session_destroy();

// No-cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

// Status penjualan tiket
require_once 'config/db.php';
require_once 'config/checkin.php';
$salesOpen = true;
if ($pdo) {
    try {
        ensureTicketTables($pdo);
        $manual = ((int)getSetting($pdo, 'sales_open', '1') === 1);
        $quota  = (int)getSetting($pdo, 'ticket_quota', '0');
        $sold   = getTotalSold($pdo);
        $avail  = ($quota <= 0) || ($sold < $quota);
        $salesOpen = $manual && $avail;
    } catch (Exception $e) { $salesOpen = true; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FOAS 14 — Vita Voxa Choir</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="assets/css/style.css?v=5" rel="stylesheet">
</head>
<body class="welcome-page">

    <div class="welcome-container">
        <div class="choir-logo">
            <img src="logo.png" alt="Vita Voxa Choir" class="choir-logo-img">
        </div>

        <p class="choir-name">Vita Voxa Choir</p>
        <p class="presents-text">Presents</p>

        <h1 class="event-title">FOAS 14</h1>
        <p class="event-tagline">"mensana on corpore sano"</p>

        <div class="event-details">
            <p class="event-date">Sabtu, 7 November</p>
            <p class="event-time">19.00 WIB</p>
        </div>

        <?php if ($salesOpen): ?>
            <a href="form.php" class="btn-reserve">Reservasi Tiket</a>
        <?php else: ?>
            <span class="btn-reserve btn-coming-soon">Coming Soon</span>
        <?php endif; ?>
        <br>
        <br>
    </div>


<script>
// Prevent double-tap zoom on iOS Safari
var _lt = 0;
document.addEventListener('touchend', function(e) {
    var now = Date.now();
    if (now - _lt < 300) { e.preventDefault(); }
    _lt = now;
}, { passive: false });
</script>
</body>
</html>
