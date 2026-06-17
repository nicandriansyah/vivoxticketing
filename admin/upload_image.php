<?php
/* Menyajikan foto dari folder /uploads (dibaca via filesystem, bukan URL),
   sehingga tetap jalan walau admin di subdomain berbeda. */
require_once __DIR__ . '/auth.php';

$file = basename($_GET['file'] ?? '');   // cegah path traversal
if (!preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|gif|webp)$/i', $file)) {
    http_response_code(400);
    exit('bad file');
}

$path = __DIR__ . '/../uploads/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit('not found');
}

$mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
