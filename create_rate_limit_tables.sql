-- Migration: create rate limiting tables
-- Run this once: mysql -u root -p lms_alihsan_btr < create_rate_limit_tables.sql

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  username VARCHAR(100) DEFAULT NULL,
  attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) DEFAULT 0,
  INDEX idx_ip_username_time (ip_address, username, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocked_ips (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL UNIQUE,
  blocked_until DATETIME DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
