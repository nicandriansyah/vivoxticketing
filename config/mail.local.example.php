<?php
/* ============================================================
   CONTOH konfigurasi email — SALIN file ini menjadi:
       config/mail.local.php
   lalu isi password email yang sebenarnya.
   File mail.local.php TIDAK ikut ke Git (gitignored).
   ============================================================ */

$MAIL_HOST       = 'mail.vitavoxa.my.id';
$MAIL_PORT       = 465;                       // 465 = SSL
$MAIL_SECURE     = 'ssl';
$MAIL_USER       = 'ticketing@vitavoxa.my.id';
$MAIL_PASS       = 'ISI_PASSWORD_EMAIL_DI_SINI';
$MAIL_FROM       = 'ticketing@vitavoxa.my.id';
$MAIL_FROM_NAME  = 'Vita Voxa Choir';
