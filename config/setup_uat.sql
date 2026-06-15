-- ============================================================
-- FOAS 13 — Vita Voxa Choir
-- Setup script untuk UAT server
-- Jalankan sebagai root / super admin MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS vitavoxa_ticket_foas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vitavoxa_ticket_foas;

CREATE TABLE IF NOT EXISTS registrations (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    kode_tiket       VARCHAR(20)   NOT NULL UNIQUE,
    nama             VARCHAR(255)  NOT NULL,
    no_hp            VARCHAR(20)   NOT NULL,
    email            VARCHAR(255)  NOT NULL,
    jumlah_tiket     TINYINT       NOT NULL DEFAULT 1,
    upload_arwah     TINYINT(1)    NOT NULL DEFAULT 0,
    foto_arwah       VARCHAR(255)  NULL,
    nama_arwah       VARCHAR(255)  NULL,
    tahun_lahir      SMALLINT      NULL,
    tahun_wafat      SMALLINT      NULL,
    hubungan_arwah   ENUM('orang_tua','anak','saudara') NULL,
    sumbangan_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    email_sent       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Grant akses untuk user vitavoxa_admin
-- Ganti '202.165.34.82' dengan IP server / '%' untuk semua IP
-- ============================================================

GRANT ALL PRIVILEGES ON vitavoxa_ticket_foas.*
    TO 'vitavoxa_admin'@'%'
    IDENTIFIED BY 'P@ssw0rd1234';

FLUSH PRIVILEGES;

-- ============================================================
-- Verifikasi
-- ============================================================

SHOW TABLES;
DESCRIBE registrations;
