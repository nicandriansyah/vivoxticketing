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

// Default produksi (situs publik tiket). Untuk localhost, override jadi ''
// di config/app.local.php agar link auto-deteksi dari host.
$PUBLIC_BASE_URL = 'https://ticket.vitavoxa.my.id';

$localCfg = __DIR__ . '/app.local.php';
if (file_exists($localCfg)) require_once $localCfg;

/** Base URL publik yang dikonfigurasi (tanpa trailing slash), atau '' jika auto. */
function configuredBaseUrl(): string {
    global $PUBLIC_BASE_URL;
    return $PUBLIC_BASE_URL ? rtrim($PUBLIC_BASE_URL, '/') : '';
}

/**
 * Bangun URL absolut ke aset di situs publik (mis. "uploads/foto.jpg",
 * "ticket.php?token=..."). Pakai PUBLIC_BASE_URL bila diset.
 * @param string $path       path relatif terhadap root app publik
 * @param string $deriveDir  path direktori app (fallback auto-deteksi)
 */
function publicUrl(string $path, string $deriveDir = ''): string {
    $path = ltrim($path, '/');
    $base = configuredBaseUrl();
    if ($base !== '') {
        return $base . '/' . $path;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . rtrim($deriveDir, '/') . '/' . $path;
}

/** Bangun URL tiket publik. */
function publicTicketUrl(string $kode, string $deriveDir = ''): string {
    return publicUrl('ticket.php?token=' . urlencode($kode), $deriveDir);
}
