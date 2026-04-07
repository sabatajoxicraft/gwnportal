-- Migration: Add notification extras for M2-T5
-- Date: 2026-07-20
-- Description: Adds category, related_id, read_at columns to notifications and
--              ensures user_preferences table exists. Safe for existing DBs.
--              Requires MySQL 8.0.16+ or MariaDB 10.0.2+ for IF NOT EXISTS syntax.

-- Add optional routing/categorisation columns to existing notifications table.
ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS category VARCHAR(50) NULL AFTER type,
    ADD COLUMN IF NOT EXISTS related_id INT NULL AFTER category,
    ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL AFTER read_status;

-- Add indexes only if they do not already exist.
-- Using CREATE INDEX ... IF NOT EXISTS (MySQL 8.0.12+, MariaDB 10.1.4+).
CREATE INDEX IF NOT EXISTS idx_recipient_unread ON notifications (recipient_id, read_status);
CREATE INDEX IF NOT EXISTS idx_created ON notifications (created_at);
CREATE INDEX IF NOT EXISTS idx_category ON notifications (category);

-- Ensure user_preferences exists (idempotent).
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    notify_device_requests BOOLEAN DEFAULT 1,
    notify_device_status BOOLEAN DEFAULT 1,
    notify_vouchers BOOLEAN DEFAULT 1,
    notify_new_students BOOLEAN DEFAULT 1,
    email_notifications BOOLEAN DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Back-fill missing preference rows for any existing users.
INSERT INTO user_preferences (user_id, notify_device_requests, notify_device_status, notify_vouchers, notify_new_students, email_notifications)
SELECT id, 1, 1, 1, 1, 0
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences)
ON DUPLICATE KEY UPDATE user_id = user_id;
