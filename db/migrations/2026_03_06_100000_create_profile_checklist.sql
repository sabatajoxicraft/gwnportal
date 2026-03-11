-- ============================================================================
-- Profile Completion Checklist Migration
-- Creates table to track role-based onboarding checklist completion
-- ============================================================================

CREATE TABLE IF NOT EXISTS profile_checklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    checklist_key VARCHAR(50) NOT NULL,
    completed BOOLEAN DEFAULT FALSE,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_checklist (user_id, checklist_key),
    INDEX idx_user_completed (user_id, completed),
    INDEX idx_user_key (user_id, checklist_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add checklist widget dismissed flag to user_preferences
-- Note: If column already exists, this will error. Run only once.
ALTER TABLE user_preferences 
ADD COLUMN checklist_widget_dismissed BOOLEAN DEFAULT FALSE;

-- ============================================================================
-- Initialize checklist items for existing users
-- ============================================================================

-- Admin Role (role_id = 1) - 4 checklist items
-- These tasks are typically done during initial setup
INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT id, 'admin.create_super_admin', TRUE, NOW()
FROM users WHERE role_id = 1;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT id, 'admin.configure_system', TRUE, NOW()
FROM users WHERE role_id = 1;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT id, 'admin.review_security', FALSE, NULL
FROM users WHERE role_id = 1;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT id, 'admin.test_notifications', FALSE, NULL
FROM users WHERE role_id = 1;

-- Owner Role (role_id = 2) - 5 checklist items
INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'owner.complete_profile', 
    CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN TRUE ELSE FALSE END,
    CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 2;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'owner.create_accommodation',
    CASE WHEN EXISTS(SELECT 1 FROM accommodations WHERE owner_id = u.id) THEN TRUE ELSE FALSE END,
    CASE WHEN EXISTS(SELECT 1 FROM accommodations WHERE owner_id = u.id) THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 2;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'owner.assign_manager',
    CASE WHEN EXISTS(SELECT 1 FROM user_accommodation ua WHERE ua.user_id = u.id) THEN TRUE ELSE FALSE END,
    CASE WHEN EXISTS(SELECT 1 FROM user_accommodation ua WHERE ua.user_id = u.id) THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 2;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'owner.upload_profile_photo', FALSE, NULL
FROM users u WHERE u.role_id = 2;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'owner.configure_notifications', FALSE, NULL
FROM users u WHERE u.role_id = 2;

-- Manager Role (role_id = 3) - 6 checklist items
INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'manager.complete_profile', 
    CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN TRUE ELSE FALSE END,
    CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 3;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'manager.view_accommodation',
    CASE WHEN EXISTS(SELECT 1 FROM user_accommodation WHERE user_id = u.id) THEN TRUE ELSE FALSE END,
    CASE WHEN EXISTS(SELECT 1 FROM user_accommodation WHERE user_id = u.id) THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 3;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'manager.generate_student_code',
    CASE WHEN EXISTS(SELECT 1 FROM onboarding_codes WHERE created_by = u.id) THEN TRUE ELSE FALSE END,
    CASE WHEN EXISTS(SELECT 1 FROM onboarding_codes WHERE created_by = u.id) THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 3;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'manager.send_first_voucher',
    CASE WHEN EXISTS(SELECT 1 FROM voucher_logs WHERE created_by_user_id = u.id) THEN TRUE ELSE FALSE END,
    CASE WHEN EXISTS(SELECT 1 FROM voucher_logs WHERE created_by_user_id = u.id) THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 3;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'manager.upload_profile_photo', FALSE, NULL
FROM users u WHERE u.role_id = 3;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'manager.configure_notifications', FALSE, NULL
FROM users u WHERE u.role_id = 3;

-- Student Role (role_id = 4) - 7 checklist items
INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'student.complete_onboarding',
    CASE WHEN EXISTS(SELECT 1 FROM students WHERE user_id = u.id) THEN TRUE ELSE FALSE END,
    CASE WHEN EXISTS(SELECT 1 FROM students WHERE user_id = u.id) THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 4;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'student.complete_profile', 
    CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN TRUE ELSE FALSE END,
    CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 4;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'student.request_voucher',
    CASE WHEN EXISTS(SELECT 1 FROM voucher_logs WHERE user_id = u.id) THEN TRUE ELSE FALSE END,
    CASE WHEN EXISTS(SELECT 1 FROM voucher_logs WHERE user_id = u.id) THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 4;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'student.connect_device',
    CASE WHEN EXISTS(SELECT 1 FROM user_devices WHERE user_id = u.id) THEN TRUE ELSE FALSE END,
    CASE WHEN EXISTS(SELECT 1 FROM user_devices WHERE user_id = u.id) THEN NOW() ELSE NULL END
FROM users u WHERE u.role_id = 4;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'student.upload_profile_photo', FALSE, NULL
FROM users u WHERE u.role_id = 4;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'student.configure_notifications', FALSE, NULL
FROM users u WHERE u.role_id = 4;

INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
SELECT u.id, 'student.read_help_docs', FALSE, NULL
FROM users u WHERE u.role_id = 4;

-- ============================================================================
-- Migration Complete
-- ============================================================================
