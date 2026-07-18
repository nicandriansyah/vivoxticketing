-- FOAS 14 — Vita Voxa Choir
-- Jalankan script ini satu kali di phpMyAdmin atau MySQL CLI

CREATE DATABASE IF NOT EXISTS ticket_foas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ticket_foas;

CREATE TABLE IF NOT EXISTS registrations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    kode_tiket      VARCHAR(20)  UNIQUE NOT NULL,
    nama            VARCHAR(255) NOT NULL,
    no_hp           VARCHAR(20)  NOT NULL,
    email           VARCHAR(255) NOT NULL,
    jumlah_tiket    TINYINT      NOT NULL DEFAULT 1,
    upload_arwah    TINYINT(1)   NOT NULL DEFAULT 0,
    foto_arwah      VARCHAR(255) NULL,
    nama_arwah      VARCHAR(255) NULL,
    tahun_lahir     SMALLINT     NULL,
    tahun_wafat     SMALLINT     NULL,
    hubungan_arwah  ENUM('orang_tua','anak','saudara') NULL,
    sumbangan_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    email_sent      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
