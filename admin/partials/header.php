<?php
/* Layout atas admin — set $pageTitle & $activeMenu sebelum include.
   Wajib di-include SETELAH auth.php (agar redirect login bekerja). */
$pageTitle  = $pageTitle  ?? 'Admin';
$activeMenu = $activeMenu ?? '';
$adminUser  = $_SESSION['admin_user'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Admin Ticketing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css?v=27" rel="stylesheet">
</head>
<body class="admin-page">
<div class="adm-layout">

    <aside class="adm-sidebar" id="admSidebar">
        <div class="adm-side-brand">Ticketing <span>Admin</span>
            <div class="adm-side-version"><?= htmlspecialchars(function_exists('appVersion') ? appVersion() : '') ?></div>
        </div>
        <nav class="adm-side-nav">
            <a href="index.php"   class="<?= $activeMenu === 'dashboard' ? 'active' : '' ?>"><span class="ico">📊</span> Dashboard</a>
            <a href="checkin.php" class="<?= $activeMenu === 'checkin'   ? 'active' : '' ?>"><span class="ico">🎫</span> Check-in Tiket</a>
            <a href="ppt.php"     class="<?= $activeMenu === 'ppt'       ? 'active' : '' ?>"><span class="ico">📑</span> PPT Generator</a>
        </nav>
        <a href="logout.php" class="adm-side-logout">⏻ Keluar</a>
        <div class="adm-side-footer">
            ©2026 Powered by KonserKoe<br>
            Pejuang Mencari Pundi | Website
        </div>
    </aside>
    <div class="adm-overlay" id="admOverlay" onclick="toggleSidebar()"></div>

    <div class="adm-content">
        <header class="adm-topbar">
            <div class="adm-topbar-left">
                <button class="adm-hamburger" onclick="toggleSidebar()" aria-label="Menu">☰</button>
                <div class="adm-page-title"><?= htmlspecialchars($pageTitle) ?></div>
            </div>
            <span class="adm-user">👤 <?= htmlspecialchars($adminUser) ?></span>
        </header>
        <main class="adm-main <?= htmlspecialchars($mainClass ?? '') ?>">
        <script>
        function toggleSidebar() {
            document.getElementById('admSidebar').classList.toggle('open');
            document.getElementById('admOverlay').classList.toggle('open');
        }
        </script>
