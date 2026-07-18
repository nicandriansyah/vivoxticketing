<?php
/* Layout atas admin — set $pageTitle & $activeMenu sebelum include.
   Wajib di-include SETELAH auth.php (agar redirect login bekerja). */
$pageTitle  = $pageTitle  ?? 'Admin';
$activeMenu = $activeMenu ?? '';
$adminUser  = $_SESSION['admin_user'] ?? 'admin';
require_once __DIR__ . '/../../config/app.php';   // appVersion() untuk badge versi sidebar
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Admin Ticketing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css?v=41" rel="stylesheet">
</head>
<body class="admin-page">
<div class="adm-layout">

    <aside class="adm-sidebar" id="admSidebar">
        <button type="button" class="adm-collapse-btn" id="admCollapseBtn" onclick="toggleCollapse()" title="Kecilkan / besarkan sidebar">«</button>
        <div class="adm-side-brand"><span class="side-txt">Ticketing </span><span class="brand-gold">Admin</span>
            <div class="adm-side-version side-txt"><?= htmlspecialchars(function_exists('appVersion') ? appVersion() : '') ?></div>
            <div class="adm-side-user side-txt">
                <span class="adm-side-welcome">Selamat Datang,</span>
                👤 <?= htmlspecialchars($adminUser) ?>
            </div>
        </div>
        <nav class="adm-side-nav">
            <a href="index.php"   class="<?= $activeMenu === 'dashboard' ? 'active' : '' ?>"><span class="ico">📊</span> <span class="side-txt">Dashboard</span></a>
            <a href="checkin.php" class="<?= $activeMenu === 'checkin'   ? 'active' : '' ?>"><span class="ico">🎫</span> <span class="side-txt">Check-in Tiket</span></a>
            <a href="ppt.php"     class="<?= $activeMenu === 'ppt'       ? 'active' : '' ?>" onclick="return checkPptAccess()"><span class="ico">📑</span> <span class="side-txt">PPT Generator</span></a>
        </nav>
        <a href="logout.php" class="adm-side-logout">🚪 <span class="side-txt">Keluar</span></a>
        <div class="adm-side-footer">
            ©2026 Powered by KonserKoe<br>
            Pejuang Mencari Pundi | <a href="https://nirdev.web.id" target="_blank" rel="noopener">Website</a>
        </div>
    </aside>
    <script>
    // Terapkan status collapse tersimpan sebelum halaman selesai render (hindari kedip)
    if (localStorage.getItem('admSideCollapsed') === '1') {
        document.getElementById('admSidebar').classList.add('collapsed');
        document.getElementById('admCollapseBtn').textContent = '»';
    }
    </script>
    <div class="adm-overlay" id="admOverlay" onclick="toggleSidebar()"></div>

    <!-- Modal warning PPT Generator (akses dari layar kecil) -->
    <div id="pptWarnModal" class="modal-overlay" style="align-items:center;" onclick="if(event.target===this)closePptWarn()">
        <div class="modal-box" style="max-width:340px;text-align:center;padding:2rem 1.5rem;">
            <div style="font-size:3rem;line-height:1;margin-bottom:0.75rem;">💻</div>
            <h3 style="font-size:1.1rem;margin-bottom:0.5rem;">PPT Generator</h3>
            <p style="color:#666;font-size:0.9rem;line-height:1.6;margin-bottom:1.25rem;">
                Fitur ini hanya bisa diakses melalui <strong>laptop, komputer, atau tablet</strong>
                agar slide dapat diedit dengan nyaman.
            </p>
            <button type="button" class="m-view-btn" style="width:100%;" onclick="closePptWarn()">Mengerti</button>
        </div>
    </div>

    <div class="adm-content">
        <button class="adm-hamburger" onclick="toggleSidebar()" aria-label="Menu">☰</button>
        <main class="adm-main <?= htmlspecialchars($mainClass ?? '') ?>">
        <script>
        function toggleSidebar() {
            document.getElementById('admSidebar').classList.toggle('open');
            document.getElementById('admOverlay').classList.toggle('open');
        }
        /* Collapse sidebar (khusus desktop) — icon-only, tersimpan di localStorage */
        function toggleCollapse() {
            var sb  = document.getElementById('admSidebar');
            var btn = document.getElementById('admCollapseBtn');
            var on  = sb.classList.toggle('collapsed');
            btn.textContent = on ? '»' : '«';
            localStorage.setItem('admSideCollapsed', on ? '1' : '0');
        }
        /* PPT Generator hanya untuk layar laptop/komputer/tablet */
        function checkPptAccess() {
            if (window.innerWidth <= 720) {
                document.getElementById('admSidebar').classList.remove('open');
                document.getElementById('admOverlay').classList.remove('open');
                document.getElementById('pptWarnModal').classList.add('open');
                return false;
            }
            return true;
        }
        function closePptWarn() {
            document.getElementById('pptWarnModal').classList.remove('open');
        }
        </script>
