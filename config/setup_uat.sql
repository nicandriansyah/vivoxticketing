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
    hubungan_arwah   VARCHAR(50)   NULL,
    sumbangan_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    email_sent       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel check-in tiket (1 baris = 1 tiket ter-scan).
-- Aplikasi juga membuat tabel ini otomatis bila belum ada.
CREATE TABLE IF NOT EXISTS checkins (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    registration_id  INT          NOT NULL,
    kode_tiket       VARCHAR(30)  NOT NULL UNIQUE,
    checked_in_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reg (registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tiket yang dibatalkan (data tidak dihapus, hanya mengurangi tiket aktif).
CREATE TABLE IF NOT EXISTS cancelled_tickets (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    registration_id  INT          NOT NULL,
    kode_tiket       VARCHAR(30)  NOT NULL UNIQUE,
    cancelled_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reg (registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pengaturan key-value (mis. kuota tiket).
CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(50)  PRIMARY KEY,
    sval VARCHAR(255) NULL
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
