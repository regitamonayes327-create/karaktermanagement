-- ============================================================
-- MIGRATION: Kategori Prestasi Siswa
-- SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
-- ============================================================

SET NAMES utf8mb4;

-- Tabel Kategori Prestasi
CREATE TABLE IF NOT EXISTS `achievement_categories` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(150) NOT NULL,
    `level` ENUM('sekolah','desa','kecamatan','kabupaten','nasional','internasional') NOT NULL,
    `points` INT(5) NOT NULL DEFAULT 0 COMMENT 'Poin prestasi',
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default achievement categories
INSERT INTO `achievement_categories` (`category_name`, `level`, `points`, `description`) VALUES
-- Tingkat Sekolah
('Juara Kelas', 'sekolah', 10, 'Peringkat 1-3 di kelas'),
('Siswa Teladan', 'sekolah', 15, 'Terpilih sebagai siswa teladan tingkat sekolah'),
('Juara Lomba Internal', 'sekolah', 10, 'Juara lomba yang diadakan internal sekolah'),
('Penampilan Terbaik', 'sekolah', 5, 'Penampilan terbaik dalam acara sekolah'),
('Hafalan Terbaik', 'sekolah', 10, 'Pencapaian hafalan Al-Quran/doa terbaik'),

-- Tingkat Desa/Kelurahan
('Juara Lomba Tingkat Desa', 'desa', 20, 'Juara lomba di tingkat desa/kelurahan'),
('Perwakilan Desa', 'desa', 15, 'Mewakili desa dalam kegiatan/lomba'),
('Prestasi Keagamaan Desa', 'desa', 20, 'Juara MTQ/lomba keagamaan tingkat desa'),

-- Tingkat Kecamatan
('Juara Lomba Tingkat Kecamatan', 'kecamatan', 30, 'Juara lomba di tingkat kecamatan'),
('Juara Olahraga Kecamatan', 'kecamatan', 30, 'Juara kompetisi olahraga tingkat kecamatan'),
('Juara Seni Kecamatan', 'kecamatan', 30, 'Juara lomba seni/budaya tingkat kecamatan'),
('Perwakilan Kecamatan', 'kecamatan', 25, 'Mewakili kecamatan ke tingkat kabupaten'),

-- Tingkat Kabupaten
('Juara Lomba Tingkat Kabupaten', 'kabupaten', 50, 'Juara lomba di tingkat kabupaten/kota'),
('Juara O2SN Kabupaten', 'kabupaten', 50, 'Juara Olimpiade Olahraga Siswa Nasional tingkat kabupaten'),
('Juara FLS2N Kabupaten', 'kabupaten', 50, 'Juara Festival Lomba Seni Siswa Nasional tingkat kabupaten'),
('Juara OSN Kabupaten', 'kabupaten', 50, 'Juara Olimpiade Sains Nasional tingkat kabupaten'),
('Perwakilan Kabupaten', 'kabupaten', 40, 'Mewakili kabupaten ke tingkat provinsi/nasional'),

-- Tingkat Nasional
('Juara Lomba Tingkat Nasional', 'nasional', 100, 'Juara lomba di tingkat nasional'),
('Juara O2SN Nasional', 'nasional', 100, 'Juara Olimpiade Olahraga Siswa Nasional'),
('Juara FLS2N Nasional', 'nasional', 100, 'Juara Festival Lomba Seni Siswa Nasional'),
('Juara OSN Nasional', 'nasional', 100, 'Juara Olimpiade Sains Nasional'),
('Peserta Tingkat Nasional', 'nasional', 70, 'Peserta/finalis lomba tingkat nasional'),

-- Tingkat Internasional
('Juara Lomba Internasional', 'internasional', 150, 'Juara lomba di tingkat internasional'),
('Peserta Tingkat Internasional', 'internasional', 100, 'Peserta/perwakilan di ajang internasional');

-- Add index
ALTER TABLE `achievement_categories` ADD INDEX `idx_achcat_level` (`level`);
ALTER TABLE `achievement_categories` ADD INDEX `idx_achcat_active` (`is_active`);

-- Add category_id column to achievements table (optional relation)
ALTER TABLE `achievements` ADD COLUMN `achievement_category_id` INT(11) UNSIGNED DEFAULT NULL AFTER `student_id`;
ALTER TABLE `achievements` ADD FOREIGN KEY (`achievement_category_id`) REFERENCES `achievement_categories`(`id`) ON DELETE SET NULL;
