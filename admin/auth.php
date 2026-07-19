<?php
/* Guard halaman admin — sertakan di awal setiap halaman yang butuh login. */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

/** Role user yang sedang login: 'admin' | 'ticketing'. Sesi lama dianggap admin. */
function adminRole(): string {
    return ($_SESSION['admin_role'] ?? 'admin') === 'ticketing' ? 'ticketing' : 'admin';
}

/** Guard halaman khusus admin — role lain dipulangkan ke dashboard. */
function requireAdminRole(): void {
    if (adminRole() !== 'admin') { header('Location: index.php'); exit; }
}
