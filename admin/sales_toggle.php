<?php
require_once __DIR__ . '/auth.php';
requireAdminRole();   // khusus role admin
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        ensureTicketTables($pdo);
        $open = ($_POST['open'] ?? '1') === '1' ? '1' : '0';
        setSetting($pdo, 'sales_open', $open);
        header('Location: index.php?msg=' . ($open === '1' ? 'opened' : 'closed'));
        exit;
    } catch (Exception $e) {
        header('Location: index.php?msg=err');
        exit;
    }
}
header('Location: index.php');
exit;
