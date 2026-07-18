<?php
/* Helper bersama untuk halaman admin */
require_once __DIR__ . '/../config/app.php';

/** Direktori root app publik untuk fallback (parent dari folder /admin). */
function adminRootDir(): string {
    $adminDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/'); // .../admin
    return rtrim(str_replace('\\', '/', dirname($adminDir)), '/');                 // root app
}

/** Bangun URL tiket publik (pakai PUBLIC_BASE_URL bila diset). */
function adminTicketUrl(string $kode): string {
    return publicTicketUrl($kode, adminRootDir());
}

/** URL foto upload via endpoint admin (dibaca filesystem; aman lintas subdomain). */
function adminUploadUrl(string $file): string {
    return 'upload_image.php?file=' . rawurlencode($file);
}

/** Normalisasi nomor HP Indonesia ke format internasional tanpa '+' (mis. 628xxx). */
function waNumber(string $no): string {
    $n = preg_replace('/\D/', '', $no);          // ambil digit saja
    if ($n === '') return '';
    if (substr($n, 0, 2) === '62') return $n;    // sudah 62...
    if ($n[0] === '0')  return '62' . substr($n, 1); // 0812.. -> 62812..
    return '62' . $n;                            // 812.. -> 62812..
}

/** Nomor HP untuk ditampilkan: +62xxxxx (atau '-' jika kosong). */
function phoneDisplay(string $no): string {
    $n = waNumber($no);
    return $n === '' ? '-' : '+' . $n;
}

/** Bangun link wa.me dengan pesan otomatis berisi link tiket */
function waLink(string $noHp, string $ticketUrl): string {
    $num  = waNumber($noHp);
    $text = 'Hi vovoxers!, berikut adalah link dari tiketmu ya ' . $ticketUrl;
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}

/**
 * Bangun klausa WHERE pencarian (tanpa kata WHERE) + parameternya.
 * Mendukung pencarian via kode tiket apa pun (mis. UGBQ001, UGBQ003,
 * FOAS14-UGBQ002) dengan mencocokkan batch tiketnya.
 * Return: [string $clause, array $params]  — clause '' jika query kosong.
 */
function buildSearchClause(string $q): array {
    $q = trim($q);
    if ($q === '') return ['', []];

    $like    = "%$q%";
    $clauses = ['nama LIKE ?', 'email LIKE ?', 'no_hp LIKE ?', 'kode_tiket LIKE ?'];
    $params  = [$like, $like, $like, $like];

    // Jika query menyerupai kode tiket → cocokkan batch-nya (4 char) ke kode_tiket
    $qb = preg_replace('/^FOAS14-?/i', '', strtoupper($q));
    if (preg_match('/^([A-Z0-9]{4})[0-9]{0,3}$/', $qb, $m)) {
        $clauses[] = 'kode_tiket LIKE ?';
        $params[]  = '%' . $m[1] . '%';
    }
    return ['(' . implode(' OR ', $clauses) . ')', $params];
}
