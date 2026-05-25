-- ============================================================
-- MIGRATION: Add follow_up_status to behavior_records
-- SIKAPDIK - Integrasi Catatan Perilaku dengan Tindak Lanjut
-- ============================================================

-- Add follow_up_status column to behavior_records table
ALTER TABLE `behavior_records` 
ADD COLUMN `follow_up_status` ENUM('','ditindaklanjuti','tidak_perlu') DEFAULT '' 
AFTER `show_to_parent`;

-- Add index for faster filtering
ALTER TABLE `behavior_records` ADD INDEX `idx_followup_status` (`follow_up_status`);
