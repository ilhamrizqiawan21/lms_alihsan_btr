-- Migration: Alter calendar_events to support holiday and school-wide events
-- Created: 2026-05-31

ALTER TABLE calendar_events 
  MODIFY COLUMN user_id INT NULL,
  ADD COLUMN is_holiday TINYINT(1) NOT NULL DEFAULT 0 AFTER event_date,
  ADD COLUMN scope VARCHAR(20) NOT NULL DEFAULT 'user' AFTER is_holiday,
  ADD INDEX idx_event_date (event_date);
