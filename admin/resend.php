<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$return = $_POST['return'] ?? 'index.php';

// Batasi redirect ke halaman internal saja
if (!preg_match('/^(index|detail)\.php(\?.*)?$/', $return)) {
    $return = 'index.php';
}

if (!$pdo || !$id) {
    header('Location: ' . $return . (strpos($return, '?') === false ? '?' : '&') . 'msg=err');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header('Location: ' . $return . (strpos($return, '?') === false ? '?' : '&') . 'msg=err');
    exit;
}

// Bangun URL tiket (pakai PUBLIC_BASE_URL bila diset; admin ada di /admin, ticket.php di parent)
require_once __DIR__ . '/../config/app.php';
$adminDir  = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/'); // .../admin
$rootDir   = rtrim(str_replace('\\', '/', dirname($adminDir)), '/');            // ...
$ticketUrl = publicTicketUrl($row['kode_tiket'], $rootDir);

$result = sendTicketEmailForRow($row, $ticketUrl);

if ($result === true) {
    try {
        $pdo->prepare("UPDATE registrations SET email_sent = 1 WHERE id = ?")->execute([$id]);
    } catch (Exception $e) { /* abaikan */ }
    $msg = 'sent';
} else {
    $msg = 'fail';
    $_SESSION['resend_error'] = is_string($result) ? $result : 'Gagal mengirim';
}

header('Location: ' . $return . (strpos($return, '?') === false ? '?' : '&') . 'msg=' . $msg);
exit;
