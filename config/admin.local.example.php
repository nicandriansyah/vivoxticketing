<?php
/* ============================================================
   CONTOH kredensial admin — SALIN file ini menjadi:
       config/admin.local.php
   lalu ganti user & hash password.
   Buat hash baru di terminal:
       php -r "echo password_hash('PASSWORD_BARU', PASSWORD_DEFAULT);"
   ============================================================ */

$ADMIN_USER      = 'admin';
$ADMIN_PASS_HASH = 'GANTI_DENGAN_HASIL_password_hash';
