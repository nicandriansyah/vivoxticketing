<?php
require_once __DIR__ . '/auth.php';
requireAdminRole();   // khusus role admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['book'])) {
    header('Location: program_book.php');
    exit;
}

$file = $_FILES['book'];

if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    header('Location: program_book.php?error=size');
    exit;
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    header('Location: program_book.php?error=save');
    exit;
}
if ($file['size'] > 50 * 1024 * 1024) {
    header('Location: program_book.php?error=size');
    exit;
}

// Pastikan benar-benar PDF (jangan percaya ekstensi/MIME kiriman browser begitu saja)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if ($mime !== 'application/pdf') {
    header('Location: program_book.php?error=format');
    exit;
}

$dir = dirname(__DIR__) . '/uploads/program-book/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

$dest = $dir . 'book.pdf';
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    header('Location: program_book.php?error=save');
    exit;
}

if ($pdo) {
    try {
        ensureTicketTables($pdo);
        $origName = preg_replace('/[\\/:*?"<>|]+/', '', $file['name']);
        setSetting($pdo, 'program_book_filename', $origName !== '' ? $origName : 'book.pdf');
        setSetting($pdo, 'program_book_uploaded_at', date('d-m-Y H:i'));
    } catch (Exception $e) { /* file sudah tersimpan, abaikan kalau gagal catat metadata */ }
}

header('Location: program_book.php?msg=ok');
exit;
