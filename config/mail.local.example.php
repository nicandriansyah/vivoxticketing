<?php
/* ============================================================
   CONTOH konfigurasi email — SALIN file ini menjadi:
       config/mail.local.php
   lalu isi password email yang sebenarnya.
   File mail.local.php TIDAK ikut ke Git (gitignored).
   ============================================================ */

$MAIL_HOST       = 'smtp.gmail.com';
$MAIL_PORT       = 587;                       // 465 = SSL (implicit TLS)
$MAIL_SECURE     = 'ssl';                     // 'ssl' for 465, 'tls' for 587
$MAIL_USER       = 'sandbox@parokigrogolkaj.or.id';
$MAIL_PASS       = 'anqm qyst ewke hecs';                        // set in mail.local.php
$MAIL_FROM       = 'sandbox@parokigrogolkaj.or.id';
$MAIL_FROM_NAME  = 'no Reply - Email Service';