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
    <link href="assets/css/style.css?v=14" rel="stylesheet">
</head>
<body class="welcome-page">

    <div class="welcome-container">
        <div class="choir-logo">
            <img src="logo.png" alt="Vita Voxa Choir" class="choir-logo-img" draggable="false" oncontextmenu="return false">
        </div>

        <p class="choir-name">Vita Voxa Choir</p>
        <p class="presents-text">Presents</p>

        <h1 class="event-title"><img src="foas-logo.png" alt="FOAS 14" class="event-title-img" draggable="false" oncontextmenu="return false"></h1>
        <p class="event-tagline">"I Know My Redeemer Lives"</p>

        <div class="event-details">
            <p class="event-date">Sabtu, 7 November</p>
            <p class="event-time">19.30 WIB</p>
            <p class="event-location">Gereja St. Polikarpus,<br>Grogol, Jakarta Barat</p>
        </div>

        <div class="social-links">
            <a href="https://maps.app.goo.gl/m36MQcxCPtTfAKax7" target="_blank" rel="noopener" class="social-icon" aria-label="Lokasi di Google Maps">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C7.86 2 4.5 5.36 4.5 9.5c0 5.25 6.5 11.5 7.5 12.5 1-1 7.5-7.25 7.5-12.5C19.5 5.36 16.14 2 12 2zm0 10.25a2.75 2.75 0 110-5.5 2.75 2.75 0 010 5.5z"/></svg>
            </a>
            <a href="https://www.instagram.com/vitavoxa.choir/" target="_blank" rel="noopener" class="social-icon" aria-label="Instagram Vita Voxa Choir">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zm0 10.162a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
            </a>
        </div>

        <?php if ($salesOpen): ?>
            <a href="form.php" class="btn-reserve">Reservasi Tiket</a>
        <?php else: ?>
            <span class="btn-reserve btn-coming-soon">Coming Soon</span>
        <?php endif; ?>
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
