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

/**
 * Versi aplikasi berdasarkan subject commit terakhir (mis. "v.1.032").
 * Diambil dari git bila tersedia; fallback ke konstanta untuk lingkungan
 * produksi yang tidak menyertakan git.
 */
function appVersion(): string {
    static $ver = null;
    if ($ver !== null) return $ver;

    $fallback = 'v.1.046';   // dibump saat rilis bila git tak tersedia
    $root = dirname(__DIR__);
    if (function_exists('shell_exec') && is_dir($root . '/.git')) {
        $out = @shell_exec('git -C ' . escapeshellarg($root) . ' log -1 --pretty=%s 2>&1');
        if (is_string($out) && preg_match('/v\.?\d+\.\d+/i', $out, $m)) {
            return $ver = $m[0];
        }
    }
    return $ver = $fallback;
}
