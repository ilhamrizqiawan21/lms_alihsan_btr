-- Migration: Add dashboard_widgets table for user widget preferences
-- Created: 2025-05-31

CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    widget_key VARCHAR(100) NOT NULL,
    is_visible BOOLEAN DEFAULT 1,
    widget_order INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_widget (user_id, widget_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prebuilt widget config untuk admin dashboard
-- Widgets: stat_cards, login_history, pengumuman, attendance_chart, activity_log
INSERT IGNORE INTO dashboard_widgets (user_id, widget_key, is_visible, widget_order, is_pinned) 
SELECT id, 'stat_cards', 1, 1, 1 FROM users WHERE role_id = 1;

INSERT IGNORE INTO dashboard_widgets (user_id, widget_key, is_visible, widget_order, is_pinned) 
SELECT id, 'login_history', 1, 2, 0 FROM users WHERE role_id = 1;

INSERT IGNORE INTO dashboard_widgets (user_id, widget_key, is_visible, widget_order, is_pinned) 
SELECT id, 'pengumuman', 1, 3, 0 FROM users WHERE role_id = 1;

INSERT IGNORE INTO dashboard_widgets (user_id, widget_key, is_visible, widget_order, is_pinned) 
SELECT id, 'attendance_chart', 1, 4, 0 FROM users WHERE role_id = 1;
