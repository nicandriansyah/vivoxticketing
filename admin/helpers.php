<?php
/* Helper bersama untuk halaman admin */
require_once __DIR__ . '/../config/app.php';

/** Bangun URL tiket publik (pakai PUBLIC_BASE_URL bila diset; ticket.php ada di parent folder /admin). */
function adminTicketUrl(string $kode): string {
    // Fallback dir (localhost): parent dari folder /admin
    $adminDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/'); // .../admin
    $rootDir  = rtrim(str_replace('\\', '/', dirname($adminDir)), '/');            // root app
    return publicTicketUrl($kode, $rootDir);
}

/** Bangun link wa.me dengan pesan otomatis berisi link tiket */
function waLink(string $noHp, string $ticketUrl): string {
    $num  = preg_replace('/[^0-9]/', '', $noHp); // +62812.. -> 62812..
    $text = 'Hi vovoxers!, berikut adalah link dari tiketmu ya ' . $ticketUrl;
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}

/**
 * Bangun klausa WHERE pencarian (tanpa kata WHERE) + parameternya.
 * Mendukung pencarian via kode tiket apa pun (mis. UGBQ001, UGBQ003,
 * FOAS13-UGBQ002) dengan mencocokkan batch tiketnya.
 * Return: [string $clause, array $params]  — clause '' jika query kosong.
 */
function buildSearchClause(string $q): array {
    $q = trim($q);
    if ($q === '') return ['', []];

    $like    = "%$q%";
    $clauses = ['nama LIKE ?', 'email LIKE ?', 'no_hp LIKE ?', 'kode_tiket LIKE ?'];
    $params  = [$like, $like, $like, $like];

    // Jika query menyerupai kode tiket → cocokkan batch-nya (4 char) ke kode_tiket
    $qb = preg_replace('/^FOAS13-?/i', '', strtoupper($q));
    if (preg_match('/^([A-Z0-9]{4})[0-9]{0,3}$/', $qb, $m)) {
        $clauses[] = 'kode_tiket LIKE ?';
        $params[]  = '%' . $m[1] . '%';
    }
    return ['(' . implode(' OR ', $clauses) . ')', $params];
}
