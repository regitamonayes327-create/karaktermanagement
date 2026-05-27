-- ============================================================
-- MIGRATION: Sistem Kenaikan Kelas & Kelulusan
-- SIKAPDIK
-- ============================================================

SET NAMES utf8mb4;

-- Tabel Riwayat Kenaikan Kelas
CREATE TABLE IF NOT EXISTS `class_promotions` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `from_class_id` INT(11) UNSIGNED DEFAULT NULL,
    `to_class_id` INT(11) UNSIGNED DEFAULT NULL,
    `academic_year` VARCHAR(20) NOT NULL COMMENT 'Tahun ajaran saat proses',
    `promotion_type` ENUM('naik_kelas','tinggal_kelas','lulus','pindah_sekolah','dikeluarkan') NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `promoted_by` INT(11) UNSIGNED NOT NULL,
    `promoted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`to_class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`promoted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_promotion_student` (`student_id`),
    INDEX `idx_promotion_year` (`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add graduated status to students
ALTER TABLE `students` ADD COLUMN `status` ENUM('aktif','lulus','pindah','dikeluarkan') DEFAULT 'aktif' AFTER `is_active`;
