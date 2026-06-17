<?php
/* Menyajikan foto dari folder /uploads (dibaca via filesystem, bukan URL),
   sehingga tetap jalan walau admin di subdomain berbeda.
   Mendukung subfolder kategori (mis. "anak/24LH001 - Anak - ....jpg"). */
require_once __DIR__ . '/auth.php';

$rel = (string)($_GET['file'] ?? '');
$rel = str_replace('\\', '/', $rel);

// Tolak path traversal & karakter aneh; izinkan huruf, angka, spasi, . _ - / [ ]
if ($rel === '' || strpos($rel, '..') !== false ||
    !preg_match('#^[A-Za-z0-9 ._/\[\]\-]+\.(jpe?g|png|gif|webp)$#i', $rel)) {
    http_response_code(400);
    exit('bad file');
}

$baseDir = realpath(__DIR__ . '/../uploads');
$path    = realpath(__DIR__ . '/../uploads/' . $rel);

// Pastikan file benar-benar di dalam folder uploads
if ($baseDir === false || $path === false || strpos($path, $baseDir) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('not found');
}

$mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
