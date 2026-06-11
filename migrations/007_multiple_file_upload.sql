-- Migration: Support Multiple File Upload for Assignments
-- Purpose: Allow students to upload more than one file per assignment

CREATE TABLE IF NOT EXISTS `pengumpulan_files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pengumpulan_id` INT NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`pengumpulan_id`) REFERENCES `pengumpulan_tugas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration: Add note about existing files
-- We keep file_upload in pengumpulan_tugas for backward compatibility 
-- but will prioritize pengumpulan_files in the new logic.
