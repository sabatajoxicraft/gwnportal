-- Migration: Create notifications system tables
-- Date: 2025-02-05
-- Description: Creates notifications and user_preferences tables for M2-T4

-- Drop existing notifications table if exists (old schema)
DROP TABLE IF EXISTS notifications;

-- Create notifications table with comprehensive schema
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    category VARCHAR(50),
    related_id INT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
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

-- REMOVED: These columns do not match the schema in db/schema.sql
-- The user_devices table already has the correct structure:
-- - user_id (NOT student_id)
-- - device_type (NOT device_name)
-- - created_at (NOT added_at/updated_at)
-- - No status column in schema

-- Initialize default preferences for existing users
INSERT INTO user_preferences (user_id, notify_device_requests, notify_device_status, notify_vouchers, notify_new_students, email_notifications)
SELECT id, 1, 1, 1, 1, 0 
FROM users 
WHERE id NOT IN (SELECT user_id FROM user_preferences)
ON DUPLICATE KEY UPDATE user_id = user_id;
