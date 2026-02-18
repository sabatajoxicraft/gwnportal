<?php
/**
 * Database Migration Script
 * Run this script to apply voucher revoke fields to the database
 * 
 * Usage: php apply_voucher_migration.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

echo "=== GWN Portal Database Migration ===\n";
echo "Adding voucher revoke fields to voucher_logs table...\n\n";

$conn = getDbConnection();

// Check if columns already exist
$check_sql = "SHOW COLUMNS FROM voucher_logs LIKE 'revoked_at'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "⚠️  Migration already applied. Revoke fields already exist.\n";
    exit(0);
}

// Apply migration
$migration_sql = "
ALTER TABLE voucher_logs 
ADD COLUMN revoked_at TIMESTAMP NULL,
ADD COLUMN revoked_by INT NULL,
ADD COLUMN revoke_reason TEXT,
ADD COLUMN is_active BOOLEAN DEFAULT 1;
";

try {
    if ($conn->multi_query($migration_sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
    }
    
    // Add foreign key constraint separately
    $fk_sql = "ALTER TABLE voucher_logs ADD FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL";
    $conn->query($fk_sql);
    
    echo "✓ Migration completed successfully!\n";
    echo "✓ Added columns: revoked_at, revoked_by, revoke_reason, is_active\n";
    echo "✓ Added foreign key constraint on revoked_by\n\n";
    
    // Verify
    $verify_sql = "SHOW COLUMNS FROM voucher_logs";
    $verify_result = $conn->query($verify_sql);
    
    echo "Current voucher_logs table structure:\n";
    echo "------------------------------------\n";
    while ($col = $verify_result->fetch_assoc()) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
echo "\n✓ Migration script completed.\n";
