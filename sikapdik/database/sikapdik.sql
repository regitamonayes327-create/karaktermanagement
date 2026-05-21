-- ============================================================
-- SIKAPDIK - Sistem Informasi Pemantauan Perilaku Siswa
-- SD Negeri 04 Jatigunung
-- Database Schema
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+07:00";
SET NAMES utf8mb4;

-- ============================================================
-- 1. TABEL PENGATURAN SISTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('school_name', 'SD Negeri 04 Jatigunung', 'general', 'Nama sekolah'),
('school_address', '', 'general', 'Alamat sekolah'),
('school_logo', '', 'general', 'Path logo sekolah'),
('school_phone', '', 'general', 'Nomor telepon sekolah'),
('school_email', '', 'general', 'Email sekolah'),
('active_academic_year', '2024/2025', 'academic', 'Tahun ajaran aktif'),
('active_semester', '1', 'academic', 'Semester aktif'),
('late_threshold_time', '07:00:00', 'attendance', 'Batas jam keterlambatan'),
('school_start_time', '06:45:00', 'attendance', 'Jam mulai sekolah'),
('late_threshold_global', '1', 'attendance', '1=global, 0=per kelas'),
('validation_mode', '0', 'behavior', '1=aktif validasi guru mapel, 0=langsung masuk'),
('parent_view_mode', 'selected', 'parent', 'all=semua, selected=pilihan guru'),
('app_version', '1.0.0', 'system', 'Versi aplikasi');

-- ============================================================
-- 2. TABEL TAHUN AJARAN
-- ============================================================

CREATE TABLE IF NOT EXISTS `academic_years` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `year_name` VARCHAR(20) NOT NULL,
    `semester` ENUM('1','2') NOT NULL DEFAULT '1',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `is_active` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `academic_years` (`year_name`, `semester`, `start_date`, `end_date`, `is_active`) VALUES
('2024/2025', '1', '2024-07-15', '2024-12-20', 0),
('2024/2025', '2', '2025-01-06', '2025-06-20', 1);

-- ============================================================
-- 3. TABEL PENGGUNA (USERS)
-- ============================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin','kepala_sekolah','wali_kelas','guru_mapel','orang_tua') NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `login_attempts` INT(3) DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `password_changed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin account (password: Admin@2024)
-- Note: Password hash will be generated during installation via install.php
-- Placeholder hash below - use install.php to set up properly
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`) VALUES
('admin', 'admin@sdn4jatigunung.sch.id', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- ============================================================
-- 4. TABEL GURU
-- ============================================================

CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `nip` VARCHAR(30) DEFAULT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `gender` ENUM('L','P') NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `position` VARCHAR(100) DEFAULT NULL,
    `subject` VARCHAR(100) DEFAULT NULL COMMENT 'Mata pelajaran yang diajar',
    `is_homeroom` TINYINT(1) DEFAULT 0 COMMENT '1=wali kelas',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. TABEL KELAS
-- ============================================================

CREATE TABLE IF NOT EXISTS `classes` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(20) NOT NULL,
    `grade_level` TINYINT(1) NOT NULL COMMENT '1-6',
    `academic_year_id` INT(11) UNSIGNED DEFAULT NULL,
    `homeroom_teacher_id` INT(11) UNSIGNED DEFAULT NULL,
    `student_count` INT(5) DEFAULT 0,
    `late_threshold_time` TIME DEFAULT NULL COMMENT 'Batas terlambat per kelas (jika tidak global)',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. TABEL PENUGASAN GURU MAPEL KE KELAS
-- ============================================================

CREATE TABLE IF NOT EXISTS `teacher_class_assignments` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT(11) UNSIGNED NOT NULL,
    `class_id` INT(11) UNSIGNED NOT NULL,
    `subject` VARCHAR(100) DEFAULT NULL,
    `academic_year_id` INT(11) UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_assignment` (`teacher_id`, `class_id`, `academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. TABEL SISWA
-- ============================================================

CREATE TABLE IF NOT EXISTS `students` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nis` VARCHAR(20) NOT NULL UNIQUE,
    `nisn` VARCHAR(20) DEFAULT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `gender` ENUM('L','P') NOT NULL,
    `birth_place` VARCHAR(50) DEFAULT NULL,
    `birth_date` DATE DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `class_id` INT(11) UNSIGNED DEFAULT NULL,
    `qr_token` VARCHAR(64) DEFAULT NULL UNIQUE COMMENT 'Token unik untuk QR Code',
    `qr_generated_at` DATETIME DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `enrolled_at` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. TABEL ORANG TUA / WALI
-- ============================================================

CREATE TABLE IF NOT EXISTS `parents` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `occupation` VARCHAR(100) DEFAULT NULL,
    `relationship` ENUM('ayah','ibu','wali') DEFAULT 'ayah',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. TABEL RELASI ORANG TUA - SISWA
-- ============================================================

CREATE TABLE IF NOT EXISTS `parent_student` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT(11) UNSIGNED NOT NULL,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_parent_student` (`parent_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. TABEL PRESENSI
-- ============================================================

CREATE TABLE IF NOT EXISTS `attendances` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `class_id` INT(11) UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `scan_time` TIME DEFAULT NULL,
    `status` ENUM('hadir','terlambat','sakit','izin','alpa') NOT NULL DEFAULT 'hadir',
    `method` ENUM('qr_scan','manual') NOT NULL DEFAULT 'qr_scan',
    `notes` TEXT DEFAULT NULL,
    `recorded_by` INT(11) UNSIGNED DEFAULT NULL COMMENT 'user_id guru pencatat',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_attendance` (`student_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. TABEL KATEGORI PERILAKU
-- ============================================================

CREATE TABLE IF NOT EXISTS `behavior_categories` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL,
    `type` ENUM('keteladanan','pelanggaran') NOT NULL,
    `points` INT(5) NOT NULL DEFAULT 0 COMMENT 'Poin positif atau negatif',
    `severity` ENUM('ringan','sedang','berat') DEFAULT 'ringan',
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default behavior categories
INSERT INTO `behavior_categories` (`category_name`, `type`, `points`, `severity`, `description`) VALUES
-- Keteladanan
('Membantu teman', 'keteladanan', 5, 'ringan', 'Siswa membantu teman yang membutuhkan'),
('Rajin piket', 'keteladanan', 3, 'ringan', 'Siswa melaksanakan piket dengan baik'),
('Tertib antre', 'keteladanan', 3, 'ringan', 'Siswa tertib saat antre'),
('Berani maju', 'keteladanan', 5, 'ringan', 'Siswa berani tampil di depan kelas'),
('Sopan santun', 'keteladanan', 5, 'sedang', 'Siswa menunjukkan sopan santun'),
('Menjaga kebersihan', 'keteladanan', 3, 'ringan', 'Siswa menjaga kebersihan lingkungan'),
('Jujur', 'keteladanan', 10, 'sedang', 'Siswa menunjukkan kejujuran'),
('Disiplin luar biasa', 'keteladanan', 10, 'sedang', 'Siswa sangat disiplin dalam kegiatan'),
('Kepemimpinan', 'keteladanan', 10, 'sedang', 'Siswa menunjukkan jiwa kepemimpinan'),
('Peduli sesama', 'keteladanan', 5, 'ringan', 'Siswa peduli terhadap sesama'),
-- Pelanggaran
('Terlambat masuk kelas', 'pelanggaran', -3, 'ringan', 'Siswa terlambat masuk kelas setelah istirahat'),
('Tidak membawa alat tulis', 'pelanggaran', -2, 'ringan', 'Siswa tidak membawa alat tulis'),
('Mengganggu teman', 'pelanggaran', -5, 'sedang', 'Siswa mengganggu teman saat pembelajaran'),
('Mengejek teman', 'pelanggaran', -5, 'sedang', 'Siswa mengejek atau mengolok teman'),
('Tidak mengerjakan tugas', 'pelanggaran', -3, 'ringan', 'Siswa tidak mengerjakan tugas/PR'),
('Melanggar aturan kelas', 'pelanggaran', -5, 'sedang', 'Siswa melanggar aturan kelas'),
('Berkelahi', 'pelanggaran', -15, 'berat', 'Siswa terlibat perkelahian'),
('Merusak fasilitas', 'pelanggaran', -10, 'berat', 'Siswa merusak fasilitas sekolah'),
('Berkata kasar', 'pelanggaran', -5, 'sedang', 'Siswa berkata kasar kepada teman atau guru'),
('Membolos', 'pelanggaran', -10, 'berat', 'Siswa membolos/keluar tanpa izin');

-- ============================================================
-- 12. TABEL CATATAN PERILAKU / POIN
-- ============================================================

CREATE TABLE IF NOT EXISTS `behavior_records` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `category_id` INT(11) UNSIGNED NOT NULL,
    `type` ENUM('keteladanan','pelanggaran') NOT NULL,
    `points` INT(5) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `incident_date` DATE NOT NULL,
    `recorded_by` INT(11) UNSIGNED NOT NULL COMMENT 'user_id guru pencatat',
    `recorder_role` ENUM('wali_kelas','guru_mapel','admin') NOT NULL,
    `validation_status` ENUM('pending','approved','rejected') DEFAULT 'approved',
    `validated_by` INT(11) UNSIGNED DEFAULT NULL,
    `validation_notes` TEXT DEFAULT NULL,
    `validated_at` DATETIME DEFAULT NULL,
    `show_to_parent` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `behavior_categories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`validated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. TABEL TINDAK LANJUT PEMBINAAN
-- ============================================================

CREATE TABLE IF NOT EXISTS `follow_ups` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `behavior_record_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Relasi ke catatan perilaku',
    `follow_up_type` ENUM('teguran_lisan','nasihat','mediasi','komunikasi_ortu','pembinaan_kepsek','skorsing','lainnya') NOT NULL,
    `description` TEXT NOT NULL,
    `result` TEXT DEFAULT NULL COMMENT 'Hasil pembinaan',
    `follow_up_date` DATE NOT NULL,
    `status` ENUM('belum_diproses','dalam_pemantauan','selesai') DEFAULT 'belum_diproses',
    `parent_involved` TINYINT(1) DEFAULT 0,
    `show_to_parent` TINYINT(1) DEFAULT 0,
    `created_by` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`behavior_record_id`) REFERENCES `behavior_records`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. TABEL PRESTASI SISWA
-- ============================================================

CREATE TABLE IF NOT EXISTS `achievements` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `category` ENUM('akademik','non_akademik','seni','olahraga','keagamaan','lainnya') NOT NULL,
    `level` ENUM('kelas','sekolah','kecamatan','kabupaten','provinsi','nasional','internasional') NOT NULL,
    `achievement_date` DATE NOT NULL,
    `description` TEXT DEFAULT NULL,
    `certificate_file` VARCHAR(255) DEFAULT NULL,
    `points` INT(5) DEFAULT 0,
    `show_to_parent` TINYINT(1) DEFAULT 1,
    `recorded_by` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. TABEL POTENSI SISWA
-- ============================================================

CREATE TABLE IF NOT EXISTS `potentials` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `field` ENUM('akademik','seni','olahraga','literasi','keagamaan','kepemimpinan','sosial','teknologi','lainnya') NOT NULL,
    `description` TEXT NOT NULL,
    `evidence` TEXT DEFAULT NULL COMMENT 'Bukti pengamatan',
    `recommendation` TEXT DEFAULT NULL COMMENT 'Rekomendasi pengembangan',
    `recorded_by` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. TABEL REWARD / APRESIASI
-- ============================================================

CREATE TABLE IF NOT EXISTS `rewards` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `reward_type` ENUM('siswa_teladan','piagam','sertifikat','hadiah','pengumuman','lainnya') NOT NULL,
    `reward_date` DATE NOT NULL,
    `given_by` INT(11) UNSIGNED NOT NULL,
    `show_to_parent` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`given_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. TABEL NOTIFIKASI
-- ============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info','warning','success','danger') DEFAULT 'info',
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. TABEL AUDIT LOG
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `old_data` JSON DEFAULT NULL,
    `new_data` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_module` (`module`),
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. INDEXES FOR PERFORMANCE
-- ============================================================

ALTER TABLE `attendances` ADD INDEX `idx_attendance_date` (`date`);
ALTER TABLE `attendances` ADD INDEX `idx_attendance_student_date` (`student_id`, `date`);
ALTER TABLE `attendances` ADD INDEX `idx_attendance_class_date` (`class_id`, `date`);
ALTER TABLE `behavior_records` ADD INDEX `idx_behavior_student` (`student_id`);
ALTER TABLE `behavior_records` ADD INDEX `idx_behavior_date` (`incident_date`);
ALTER TABLE `behavior_records` ADD INDEX `idx_behavior_type` (`type`);
ALTER TABLE `behavior_records` ADD INDEX `idx_behavior_validation` (`validation_status`);
ALTER TABLE `follow_ups` ADD INDEX `idx_followup_student` (`student_id`);
ALTER TABLE `follow_ups` ADD INDEX `idx_followup_status` (`status`);
ALTER TABLE `students` ADD INDEX `idx_student_class` (`class_id`);
ALTER TABLE `students` ADD INDEX `idx_student_qr` (`qr_token`);
ALTER TABLE `notifications` ADD INDEX `idx_notif_user_read` (`user_id`, `is_read`);

COMMIT;
