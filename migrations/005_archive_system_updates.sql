-- Migration: Add tinggal_kelas column to siswa table
-- Purpose: Support students who stay in the same class during academic year rollover
-- Run this in phpMyAdmin or MySQL client

ALTER TABLE `siswa` 
ADD COLUMN `tinggal_kelas` TINYINT(1) DEFAULT 0 
AFTER `status`;

-- Add index for performance
ALTER TABLE `siswa` ADD INDEX `idx_tinggal_kelas` (`tinggal_kelas`);
