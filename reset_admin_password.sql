-- ============================================================================
-- ADMIN PASSWORD RESET SQL SCRIPT
-- ============================================================================
-- Run this in phpMyAdmin to reset the admin password
-- 
-- Instructions:
--   1. Login to cPanel: https://joxicraft.co.za:2083
--   2. Open phpMyAdmin
--   3. Select database: joxicaxs_wifi
--   4. Click on "SQL" tab at the top
--   5. Copy and paste ONE of the options below
--   6. Click "Go" to execute
-- ============================================================================

-- ----------------------------------------------------------------------------
-- OPTION 1: Reset password to "password" (change it immediately after login!)
-- ----------------------------------------------------------------------------
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE username = 'admin' 
LIMIT 1;

-- After running, login with:
-- Username: admin
-- Password: password
-- THEN CHANGE IT IMMEDIATELY in the portal!


-- ----------------------------------------------------------------------------
-- OPTION 2: Check if admin user exists first
-- ----------------------------------------------------------------------------
-- Run this to see the admin user details:
SELECT id, username, email, status FROM users WHERE username = 'admin';

-- If the username is different, update the WHERE clause above


-- ----------------------------------------------------------------------------
-- OPTION 3: Reset password AND log the action (recommended)
-- ----------------------------------------------------------------------------
-- Step 1: Reset the password
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE username = 'admin' 
LIMIT 1;

-- Step 2: Log the reset (optional - requires activity_log table)
INSERT INTO activity_log (user_id, action, details, created_at)
SELECT id, 'password_reset', 'Password reset via phpMyAdmin emergency script', NOW()
FROM users 
WHERE username = 'admin' 
LIMIT 1;


-- ============================================================================
-- ALTERNATIVE: Create a brand new admin user (if admin account is locked)
-- ============================================================================
-- Run this ONLY if you need to create a new admin account:
/*
INSERT INTO users (username, password, email, first_name, last_name, role_id, status, created_at, updated_at)
VALUES (
    'newadmin',                                                                      -- Change this username
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',                -- Password: "password"
    'admin@joxicraft.co.za',                                                         -- Change this email
    'Super',                                                                          -- First name
    'Admin',                                                                          -- Last name
    (SELECT id FROM roles WHERE name = 'admin' LIMIT 1),                             -- Admin role
    'active',                                                                         -- Status
    NOW(),                                                                            -- Created at
    NOW()                                                                             -- Updated at
);
*/


-- ============================================================================
-- TROUBLESHOOTING
-- ============================================================================

-- Check what roles exist in the database:
-- SELECT * FROM roles;

-- Check all admin users:
-- SELECT id, username, email, status FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'admin');

-- Check if password reset worked:
-- SELECT username, email, updated_at FROM users WHERE username = 'admin';


-- ============================================================================
-- PASSWORD HASH REFERENCE
-- ============================================================================
-- The hash '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
-- is a bcrypt hash of the password "password"
-- 
-- You can generate custom hashes in PHP:
-- <?php echo password_hash('your-new-password', PASSWORD_DEFAULT); ?>
-- ============================================================================
