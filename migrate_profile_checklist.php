<?php
/**
 * Profile Completion Checklist Migration
 * 
 * This migration creates the profile_checklist system and initializes data for existing users.
 * Safe to run multiple times - uses IF NOT EXISTS and checks for existing columns.
 * 
 * Usage:
 *   Via browser: https://student.joxicraft.co.za/migrate_profile_checklist.php
 *   Via CLI: php migrate_profile_checklist.php
 *   Via Docker: docker exec gwn-portal-app php /var/www/html/migrate_profile_checklist.php
 */

// Allow both CLI and web execution
if (php_sapi_name() !== 'cli') {
    // Web execution - require admin authentication
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/functions.php';
    
    if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        die('Access denied. Admin authentication required.');
    }
    
    // Set headers for web output
    header('Content-Type: text/plain; charset=utf-8');
} else {
    // CLI execution
    require_once __DIR__ . '/includes/config.php';
}

require_once __DIR__ . '/includes/db.php';

echo "==============================================\n";
echo "Profile Checklist Migration\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

$conn = getDbConnection();
if (!$conn) {
    die("ERROR: Could not connect to database\n");
}

// Keep migration non-destructive and resilient across slightly different schemas.
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$errors = [];
$warnings = [];
$success = [];

/**
 * Execute SQL without aborting the whole migration on one failure.
 */
function runQuery($conn, $sql, &$errors, $errorPrefix)
{
    try {
        $result = $conn->query($sql);
        if ($result === false) {
            $errors[] = $errorPrefix . ': ' . $conn->error;
            return false;
        }
        return true;
    } catch (Throwable $e) {
        $errors[] = $errorPrefix . ': ' . $e->getMessage();
        return false;
    }
}

/**
 * Return true if a column exists on a table.
 */
function columnExists($conn, $table, $column)
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

// =============================================================================
// Step 1: Create profile_checklist table
// =============================================================================
echo "[1/6] Creating profile_checklist table...\n";

$sql = "CREATE TABLE IF NOT EXISTS profile_checklist (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (runQuery($conn, $sql, $errors, 'Failed to create profile_checklist table')) {
    // Check if table already existed
    $result = $conn->query("SELECT COUNT(*) as count FROM profile_checklist");
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        $warnings[] = "Table 'profile_checklist' already exists with {$row['count']} records";
    } else {
        $success[] = "Table 'profile_checklist' created successfully";
    }
} else {
    // Error already collected by runQuery().
}

// =============================================================================
// Step 2: Add checklist_widget_dismissed column to user_preferences
// =============================================================================
echo "[2/6] Adding checklist_widget_dismissed column...\n";

// Check if column exists
$columnExists = false;
$result = $conn->query("SHOW COLUMNS FROM user_preferences LIKE 'checklist_widget_dismissed'");
if ($result && $result->num_rows > 0) {
    $columnExists = true;
    $warnings[] = "Column 'checklist_widget_dismissed' already exists in user_preferences";
}

if (!$columnExists) {
    $sql = "ALTER TABLE user_preferences ADD COLUMN checklist_widget_dismissed BOOLEAN DEFAULT FALSE";
    if (runQuery($conn, $sql, $errors, 'Failed to add checklist_widget_dismissed column')) {
        $success[] = "Column 'checklist_widget_dismissed' added to user_preferences";
    }
}

// =============================================================================
// Step 3: Initialize Admin checklist items
// =============================================================================
echo "[3/6] Initializing Admin checklist items...\n";

$adminItems = [
    ['admin.create_super_admin', 'TRUE', 'NOW()'],
    ['admin.configure_system', 'TRUE', 'NOW()'],
    ['admin.review_security', 'FALSE', 'NULL'],
    ['admin.test_notifications', 'FALSE', 'NULL'],
];

$adminInserted = 0;
foreach ($adminItems as $item) {
    $sql = "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
            SELECT id, '{$item[0]}', {$item[1]}, {$item[2]}
            FROM users WHERE role_id = 1";
    if (runQuery($conn, $sql, $errors, 'Failed to initialize admin checklist item ' . $item[0])) {
        $adminInserted += $conn->affected_rows;
    }
}
$success[] = "Admin checklist: {$adminInserted} items initialized";

// =============================================================================
// Step 4: Initialize Owner checklist items
// =============================================================================
echo "[4/6] Initializing Owner checklist items...\n";

$ownerQueries = [
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'owner.complete_profile', 
         CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN TRUE ELSE FALSE END,
         CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 2",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'owner.create_accommodation',
         CASE WHEN EXISTS(SELECT 1 FROM accommodations WHERE owner_id = u.id) THEN TRUE ELSE FALSE END,
         CASE WHEN EXISTS(SELECT 1 FROM accommodations WHERE owner_id = u.id) THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 2",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'owner.assign_manager',
         CASE WHEN EXISTS(SELECT 1 FROM user_accommodation ua JOIN accommodations a ON ua.accommodation_id = a.id WHERE a.owner_id = u.id) THEN TRUE ELSE FALSE END,
         CASE WHEN EXISTS(SELECT 1 FROM user_accommodation ua JOIN accommodations a ON ua.accommodation_id = a.id WHERE a.owner_id = u.id) THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 2",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'owner.upload_profile_photo', FALSE, NULL
     FROM users u WHERE u.role_id = 2",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'owner.configure_notifications', FALSE, NULL
     FROM users u WHERE u.role_id = 2",
];

$ownerInserted = 0;
foreach ($ownerQueries as $sql) {
    if (runQuery($conn, $sql, $errors, 'Failed to initialize owner checklist items')) {
        $ownerInserted += $conn->affected_rows;
    }
}
$success[] = "Owner checklist: {$ownerInserted} items initialized";

// =============================================================================
// Step 5: Initialize Manager checklist items
// =============================================================================
echo "[5/6] Initializing Manager checklist items...\n";

$voucherLogCreatorColumn = null;
if (columnExists($conn, 'voucher_logs', 'created_by_user_id')) {
    $voucherLogCreatorColumn = 'created_by_user_id';
} elseif (columnExists($conn, 'voucher_logs', 'created_by')) {
    $voucherLogCreatorColumn = 'created_by';
}

if ($voucherLogCreatorColumn === null) {
    $warnings[] = "Could not find voucher_logs creator column (created_by_user_id/created_by). manager.send_first_voucher defaults to incomplete.";
}

$managerSendFirstVoucherQuery = "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'manager.send_first_voucher', FALSE, NULL
     FROM users u WHERE u.role_id = 3";

if ($voucherLogCreatorColumn !== null) {
    $managerSendFirstVoucherQuery = "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'manager.send_first_voucher',
         CASE WHEN EXISTS(SELECT 1 FROM voucher_logs WHERE {$voucherLogCreatorColumn} = u.id) THEN TRUE ELSE FALSE END,
         CASE WHEN EXISTS(SELECT 1 FROM voucher_logs WHERE {$voucherLogCreatorColumn} = u.id) THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 3";
}

$managerQueries = [
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'manager.complete_profile', 
         CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN TRUE ELSE FALSE END,
         CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 3",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'manager.view_accommodation',
         CASE WHEN EXISTS(SELECT 1 FROM user_accommodation WHERE user_id = u.id) THEN TRUE ELSE FALSE END,
         CASE WHEN EXISTS(SELECT 1 FROM user_accommodation WHERE user_id = u.id) THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 3",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'manager.generate_student_code',
         CASE WHEN EXISTS(SELECT 1 FROM onboarding_codes WHERE created_by = u.id) THEN TRUE ELSE FALSE END,
         CASE WHEN EXISTS(SELECT 1 FROM onboarding_codes WHERE created_by = u.id) THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 3",
    
    $managerSendFirstVoucherQuery,
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'manager.upload_profile_photo', FALSE, NULL
     FROM users u WHERE u.role_id = 3",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'manager.configure_notifications', FALSE, NULL
     FROM users u WHERE u.role_id = 3",
];

$managerInserted = 0;
foreach ($managerQueries as $sql) {
    if (runQuery($conn, $sql, $errors, 'Failed to initialize manager checklist items')) {
        $managerInserted += $conn->affected_rows;
    }
}
$success[] = "Manager checklist: {$managerInserted} items initialized";

// =============================================================================
// Step 6: Initialize Student checklist items
// =============================================================================
echo "[6/6] Initializing Student checklist items...\n";

$studentQueries = [
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'student.complete_onboarding',
         CASE WHEN EXISTS(SELECT 1 FROM students WHERE user_id = u.id) THEN TRUE ELSE FALSE END,
         CASE WHEN EXISTS(SELECT 1 FROM students WHERE user_id = u.id) THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 4",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'student.complete_profile', 
         CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN TRUE ELSE FALSE END,
         CASE WHEN u.first_name IS NOT NULL AND u.last_name IS NOT NULL AND u.email IS NOT NULL THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 4",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'student.request_voucher',
         CASE WHEN EXISTS(SELECT 1 FROM voucher_logs WHERE user_id = u.id) THEN TRUE ELSE FALSE END,
         CASE WHEN EXISTS(SELECT 1 FROM voucher_logs WHERE user_id = u.id) THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 4",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'student.connect_device',
         CASE WHEN EXISTS(SELECT 1 FROM user_devices WHERE user_id = u.id) THEN TRUE ELSE FALSE END,
         CASE WHEN EXISTS(SELECT 1 FROM user_devices WHERE user_id = u.id) THEN NOW() ELSE NULL END
     FROM users u WHERE u.role_id = 4",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'student.upload_profile_photo', FALSE, NULL
     FROM users u WHERE u.role_id = 4",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'student.configure_notifications', FALSE, NULL
     FROM users u WHERE u.role_id = 4",
    
    "INSERT IGNORE INTO profile_checklist (user_id, checklist_key, completed, completed_at)
     SELECT u.id, 'student.read_help_docs', FALSE, NULL
     FROM users u WHERE u.role_id = 4",
];

$studentInserted = 0;
foreach ($studentQueries as $sql) {
    if (runQuery($conn, $sql, $errors, 'Failed to initialize student checklist items')) {
        $studentInserted += $conn->affected_rows;
    }
}
$success[] = "Student checklist: {$studentInserted} items initialized";

// =============================================================================
// Summary Report
// =============================================================================
echo "\n==============================================\n";
echo "Migration Summary\n";
echo "==============================================\n\n";

if (!empty($success)) {
    echo "SUCCESS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
}

// Final statistics
$result = $conn->query("SELECT COUNT(*) as count FROM profile_checklist");
$row = $result->fetch_assoc();
$totalItems = $row['count'];

$result = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM profile_checklist");
$row = $result->fetch_assoc();
$totalUsers = $row['count'];

echo "Final Statistics:\n";
echo "  - Total checklist items: $totalItems\n";
echo "  - Users with checklists: $totalUsers\n";

// Role breakdown
$roleStats = $conn->query("
    SELECT r.name as role, COUNT(DISTINCT pc.user_id) as users, COUNT(pc.id) as items, SUM(pc.completed) as completed
    FROM profile_checklist pc
    JOIN users u ON pc.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    GROUP BY r.name
    ORDER BY r.id
");

echo "\nRole Breakdown:\n";
while ($row = $roleStats->fetch_assoc()) {
    echo "  - {$row['role']}: {$row['users']} users, {$row['items']} items ({$row['completed']} completed)\n";
}

echo "\n==============================================\n";
echo "Migration completed: " . date('Y-m-d H:i:s') . "\n";
echo "Status: " . (empty($errors) ? "SUCCESS" : "COMPLETED WITH ERRORS") . "\n";
echo "==============================================\n";

$conn->close();

// Return exit code
exit(empty($errors) ? 0 : 1);
