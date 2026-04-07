-- Migration: Create notifications system tables (SAFE/IDEMPOTENT version)
-- Date: 2025-02-05 (revised M2-T5)
-- Description: Creates notifications and user_preferences tables.
--              NON-DESTRUCTIVE: does NOT drop the notifications table.
--              Additive columns (category, related_id, read_at) are handled
--              by migration 2026_07_20_100000_add_notification_extras.sql.
--
-- NOTE: This file is excluded from the automatic migration runner (see
--       migration_manager.php $EXCLUDED_MIGRATIONS). Run manually only
--       if setting up a fresh environment without db/schema.sql.

-- Ensure notifications table exists with the canonical live schema.
-- If it already exists, CREATE TABLE IF NOT EXISTS is a no-op.
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    category VARCHAR(50) NULL,
    related_id INT NULL,
    read_status BOOLEAN NOT NULL DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient_unread (recipient_id, read_status),
    INDEX idx_created (created_at),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_preferences table for notification settings
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

-- Initialize default preferences for existing users
INSERT INTO user_preferences (user_id, notify_device_requests, notify_device_status, notify_vouchers, notify_new_students, email_notifications)
SELECT id, 1, 1, 1, 1, 0
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences)
ON DUPLICATE KEY UPDATE user_id = user_id;
