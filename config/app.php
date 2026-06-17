<?php
/* ============================================================
   Konfigurasi aplikasi umum.
   $PUBLIC_BASE_URL = base URL situs publik tiket (untuk link di
   email & dashboard admin). Penting saat admin diakses dari
   domain berbeda (mis. dashboard.vitavoxa.my.id) — link tiket
   harus tetap mengarah ke situs publik (ticket.vitavoxa.my.id).

   Kosongkan untuk auto-deteksi dari host saat ini (cocok untuk
   localhost di mana admin & publik satu domain).
   Override nilai sebenarnya di config/app.local.php (gitignored).
   ============================================================ */

$PUBLIC_BASE_URL = '';

$localCfg = __DIR__ . '/app.local.php';
if (file_exists($localCfg)) require_once $localCfg;

/** Base URL publik yang dikonfigurasi (tanpa trailing slash), atau '' jika auto. */
function configuredBaseUrl(): string {
    global $PUBLIC_BASE_URL;
    return $PUBLIC_BASE_URL ? rtrim($PUBLIC_BASE_URL, '/') : '';
}

/**
 * Bangun URL tiket publik.
 * @param string $kode       kode tiket (token)
 * @param string $deriveDir  path direktori app (untuk fallback auto-deteksi)
 */
function publicTicketUrl(string $kode, string $deriveDir = ''): string {
    $base = configuredBaseUrl();
    if ($base !== '') {
        return $base . '/ticket.php?token=' . urlencode($kode);
    }
    // Fallback: deteksi dari request (localhost / satu domain)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . rtrim($deriveDir, '/') . '/ticket.php?token=' . urlencode($kode);
}
