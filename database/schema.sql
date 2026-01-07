-- ============================================
-- STEP 3: SQL CREATE TABLE
-- Database: WiFi Password Generator
-- ============================================

-- Buat tabel wifi_passwords
CREATE TABLE IF NOT EXISTS `wifi_passwords` (
    -- Primary Key: ID unik untuk setiap record
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Nama user yang request password
    `user_name` VARCHAR(100) NOT NULL,
    
    -- Password WiFi yang digenerate (8-10 karakter)
    `password` VARCHAR(20) NOT NULL,
    
    -- Waktu pembuatan password
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Waktu kedaluwarsa password (default: 7 hari dari created_at)
    `expired_at` DATETIME NOT NULL,
    
    -- Status aktif: 1 = aktif, 0 = expired/nonaktif
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    
    -- Index untuk pencarian cepat
    INDEX `idx_user_name` (`user_name`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_expired_at` (`expired_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PENJELASAN FIELD:
-- ============================================
-- id          : Identifier unik, auto increment
-- user_name   : Nama lengkap user (wajib diisi)
-- password    : Password random yang digenerate sistem
-- created_at  : Timestamp saat password dibuat
-- expired_at  : Timestamp kapan password kadaluarsa
-- is_active   : Flag untuk menandai password masih aktif/tidak
-- ============================================
