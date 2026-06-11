-- Migration: Fix Foreign Key Integrity for User Deletion (Robust Version)
-- Purpose: Ensure orphan data is cleaned up automatically when a user is deleted.
-- Note: If you get an error about "Foreign key constraint is incorrectly formed", 
-- it usually means there is orphan data that must be cleaned first.

-- 1. Clean up orphan data (data pointing to non-existent users)
DELETE FROM `siswa` WHERE `user_id` NOT IN (SELECT id FROM `users`);
DELETE FROM `guru_mapel` WHERE `guru_id` NOT IN (SELECT id FROM `users`);
DELETE FROM `log_login` WHERE `user_id` NOT IN (SELECT id FROM `users`);
DELETE FROM `notifikasi` WHERE `user_id` NOT IN (SELECT id FROM `users`);
DELETE FROM `dashboard_widgets` WHERE `user_id` NOT IN (SELECT id FROM `users`);

-- 2. Add Constraints (using unique names to avoid conflicts)
-- Table: siswa
ALTER TABLE `siswa` ADD CONSTRAINT `fk_siswa_user_integrity` 
FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Table: guru_mapel
ALTER TABLE `guru_mapel` ADD CONSTRAINT `fk_gurumapel_guru_integrity` 
FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Table: log_login
ALTER TABLE `log_login` ADD CONSTRAINT `fk_loglogin_user_integrity` 
FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Table: notifikasi
ALTER TABLE `notifikasi` ADD CONSTRAINT `fk_notifikasi_user_integrity` 
FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Table: dashboard_widgets
ALTER TABLE `dashboard_widgets` ADD CONSTRAINT `fk_widgets_user_integrity` 
FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
