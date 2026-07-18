<?php
/* ============================================================
   CONTOH konfigurasi email — SALIN file ini menjadi:
       config/mail.local.php
   lalu isi kredensial yang sebenarnya.
   File mail.local.php TIDAK ikut ke Git (gitignored).
   JANGAN menaruh password asli di file contoh ini.
   ============================================================

   Gmail / Google Workspace (pakai App Password, tanpa spasi):
       HOST = smtp.gmail.com, PORT = 587, SECURE = 'tls'
       USER = FROM = alamat akun Google yang login
   ============================================================ */

$MAIL_HOST       = 'smtp.gmail.com';
$MAIL_PORT       = 587;                       // 465 = SSL implicit, 587 = STARTTLS
$MAIL_SECURE     = 'tls';                     // 'ssl' untuk 465, 'tls' untuk 587
$MAIL_USER       = 'alamat@email.anda';
$MAIL_PASS       = 'app-password-anda';
$MAIL_FROM       = 'alamat@email.anda';
$MAIL_FROM_NAME  = 'Email Broadcaster';

