<?php
require_once __DIR__ . '/auth.php';
requireAdminRole();   // khusus role admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $path = dirname(__DIR__) . '/uploads/program-book/book.pdf';
    if (is_file($path)) @unlink($path);
    if ($pdo) {
        try {
            ensureTicketTables($pdo);
            setSetting($pdo, 'program_book_filename', '');
            setSetting($pdo, 'program_book_uploaded_at', '');
        } catch (Exception $e) {}
    }
    header('Location: program_book.php?msg=deleted');
    exit;
}
header('Location: program_book.php');
exit;
