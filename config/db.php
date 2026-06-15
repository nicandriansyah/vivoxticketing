<?php
// Local dev defaults — override via config/db.local.php (gitignored)
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'ticket_foas';

$localCfg = __DIR__ . '/db.local.php';
if (file_exists($localCfg)) require_once $localCfg;

$dbError = null;
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    $pdo     = null;
    $dbError = $e->getMessage();   // simpan pesan error untuk debug
}
