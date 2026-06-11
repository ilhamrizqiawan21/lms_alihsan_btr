-- Migration: Add status column to siswa table
-- Purpose: Track student status (aktif, lulus, keluar) for academic year rollover
-- Run this in phpMyAdmin or MySQL client

-- Check if column doesn't exist before adding
ALTER TABLE `siswa` 
ADD COLUMN `status` ENUM('aktif', 'lulus', 'keluar') 
COLLATE utf8mb4_general_ci 
DEFAULT 'aktif' 
AFTER `angkatan`;

-- Update existing students to 'aktif' status
UPDATE `siswa` SET `status` = 'aktif' WHERE `status` IS NULL OR `status` = '';

-- Add index for faster queries
ALTER TABLE `siswa` ADD INDEX `idx_status` (`status`);
