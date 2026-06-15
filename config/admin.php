<?php
/* ============================================================
   Kredensial admin panel.
   Default di bawah untuk dev lokal (user: admin / pass: admin123).
   Di production, override lewat config/admin.local.php (gitignored):
       <?php
       $ADMIN_USER      = 'namauser';
       $ADMIN_PASS_HASH = '...';   // hasil password_hash('passwordbaru', PASSWORD_DEFAULT)
   ============================================================ */

$ADMIN_USER      = 'admin';
// hash dari 'admin123'
$ADMIN_PASS_HASH = '$2y$10$sp1MCFrcRB3RxhPiMumdXOeT/pZDeLuuLL5rfWRK/ulPZeEBnFSxq';

$localCfg = __DIR__ . '/admin.local.php';
if (file_exists($localCfg)) require_once $localCfg;
