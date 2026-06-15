<?php
session_start();
require_once __DIR__ . '/../config/admin.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Sudah login → ke dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === $ADMIN_USER && password_verify($p, $ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $u;
        header('Location: index.php');
        exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin — FOAS 13</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body class="admin-login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-logo">FOAS 13</div>
            <p>Vita Voxa Choir &middot; Panel Admin</p>
        </div>
        <?php if ($error): ?>
            <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <label class="adm-label">Username</label>
            <input type="text" name="username" class="adm-input" autocomplete="username" required autofocus>
            <label class="adm-label">Password</label>
            <input type="password" name="password" class="adm-input" autocomplete="current-password" required>
            <button type="submit" class="adm-btn-primary" style="width:100%;margin-top:1rem;">Masuk</button>
        </form>
    </div>
</body>
</html>
