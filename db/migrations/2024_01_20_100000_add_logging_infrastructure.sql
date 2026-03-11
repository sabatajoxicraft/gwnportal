-- Migration: Add Logging Infrastructure Tables
-- Description: Adds database error logging, activity tracking, and migration tracking
-- Date: 2024

SET FOREIGN_KEY_CHECKS = 0;

-- Rename activity_log to activity_logs for consistency
ALTER TABLE IF EXISTS activity_log RENAME TO activity_logs;

-- Ensure activity_logs has the correct structure
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) DEFAULT 'general',
    entity_id INT,
    description TEXT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_entity_type (entity_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create error_logs table for database error tracking
CREATE TABLE IF NOT EXISTS error_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    error_type VARCHAR(100) NOT NULL,
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'error',
    message TEXT NOT NULL,
    context JSON,
    stack_trace LONGTEXT,
    file_path VARCHAR(255),
    line_number INT,
    user_id INT,
    ip_address VARCHAR(45),
    url VARCHAR(512),
    method VARCHAR(10),
    request_data JSON,
    response_code INT,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    resolved_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_error_type (error_type),
    INDEX idx_severity (severity),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_resolved (resolved),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create migrations tracking table
CREATE TABLE IF NOT EXISTS _migrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    batch INT NOT NULL,
    status ENUM('success', 'failed', 'pending', 'skipped') DEFAULT 'success',
    notes TEXT,
    INDEX idx_batch (batch),
    INDEX idx_status (status)
);

-- Add accommodation_managers table alias for clarity
CREATE TABLE IF NOT EXISTS accommodation_managers (
    manager_id INT NOT NULL,
    accommodation_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (manager_id, accommodation_id),
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
);

-- If user_accommodation already exists and has data, migrate to accommodation_managers
INSERT IGNORE INTO accommodation_managers (manager_id, accommodation_id, assigned_at)
SELECT user_id, accommodation_id, NOW() FROM user_accommodation 
WHERE NOT EXISTS (SELECT 1 FROM accommodation_managers 
                   WHERE manager_id = user_id 
                   AND accommodation_id = accommodation_id);

SET FOREIGN_KEY_CHECKS = 1;

-- Migration complete
