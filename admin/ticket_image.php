<?php
/* Menyajikan gambar JPG tiket dari folder /tickets berdasarkan kode. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/checkin.php';

$code = trim($_GET['code'] ?? '');
$p = parseTicketCode($code);
if (!$p) { http_response_code(400); exit('bad code'); }

// Cari file tickets/<kode>-*.jpg
$dir     = __DIR__ . '/../tickets/';
$matches = glob($dir . $p['full'] . '-*.jpg');

if (!$matches) { http_response_code(404); exit('not found'); }

$file = $matches[0];
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=300');
readfile($file);
exit;
