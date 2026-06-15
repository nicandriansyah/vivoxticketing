<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/checkin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        ensureTicketTables($pdo);
        $quota = max(0, (int)($_POST['quota'] ?? 0));
        setSetting($pdo, 'ticket_quota', (string)$quota);
        header('Location: index.php?msg=quota');
        exit;
    } catch (Exception $e) {
        header('Location: index.php?msg=err');
        exit;
    }
}
header('Location: index.php');
exit;
