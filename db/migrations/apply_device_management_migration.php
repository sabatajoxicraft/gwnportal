<?php
/**
 * Database Migration Script
 * Run this script to apply device management fields to the database
 * 
 * Usage: php apply_device_management_migration.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

echo "=== GWN Portal Database Migration ===\n";
echo "Adding device management, blocking, and voucher first-use tracking...\n\n";

$conn = getDbConnection();

// Read migration SQL file
$migration_file = __DIR__ . '/add_device_management.sql';
if (!file_exists($migration_file)) {
    echo "❌ Migration file not found: $migration_file\n";
    exit(1);
}

$migration_sql = file_get_contents($migration_file);
if ($migration_sql === false) {
    echo "❌ Failed to read migration file\n";
    exit(1);
}

try {
    echo "Running migration...\n";
    
    if ($conn->multi_query($migration_sql)) {
        do {
            if ($result = $conn->store_result()) {
                while ($row = $result->fetch_assoc()) {
                    if (isset($row['message'])) {
                        echo "  " . $row['message'] . "\n";
                    }
                }
                $result->free();
            }
            if ($conn->errno) {
                throw new Exception($conn->error);
            }
        } while ($conn->next_result());
    }
    
    if ($conn->errno) {
        throw new Exception($conn->error);
    }
    
    echo "\n✓ Migration completed successfully!\n";
    echo "✓ Added device management columns to user_devices\n";
    echo "✓ Added first-use tracking columns to voucher_logs\n";
    echo "✓ Created device_block_log table\n";
    echo "✓ Added unique index on mac_address\n\n";
    
    // Verify
    $verify_sql = "SHOW COLUMNS FROM user_devices";
    $verify_result = $conn->query($verify_sql);
    
    echo "Current user_devices table structure:\n";
    echo "------------------------------------\n";
    while ($col = $verify_result->fetch_assoc()) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n✓ device_block_log table created\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
echo "\n✓ Migration script completed.\n";
