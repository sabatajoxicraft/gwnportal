<?php
/**
 * Migration Service - Track and Manage Database Schema Migrations
 * 
 * Provides utilities for tracking applied migrations and managing schema versions.
 * All migrations should register themselves with this service.
 * 
 * Usage:
 * - MigrationService::init($conn) - Initialize migration tracking
 * - MigrationService::recordMigration('2024_01_15_create_users_table')
 * - MigrationService::getMigrationStatus()
 */

class MigrationService {

    private static $conn = null;
    private static $initialized = false;

    /**
     * Initialize migration tracking - creates migrations table if not exists
     * 
     * @param mysqli $connection Database connection
     * @return bool Success status
     */
    public static function init($connection) {
        self::$conn = $connection;

        // Create migrations tracking table if not exists
        $sql = "CREATE TABLE IF NOT EXISTS _migrations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            batch INT NOT NULL,
            status ENUM('success', 'failed', 'pending') DEFAULT 'success'
        )";

        if (!self::$conn->query($sql)) {
            error_log("MigrationService::init - Error creating migrations table: " . self::$conn->error);
            return false;
        }

        self::$initialized = true;
        return true;
    }

    /**
     * Record a migration as applied
     * 
     * @param string $migrationName Migration name/filename
     * @param int $batch Batch number (optional)
     * @return bool Success status
     */
    public static function recordMigration($migrationName, $batch = null) {
        if (!self::$initialized || !self::$conn) {
            return false;
        }

        // Get current batch number if not specified
        if ($batch === null) {
            $batch = self::getCurrentBatch() + 1;
        }

        $stmt = self::$conn->prepare("
            INSERT INTO _migrations (migration_name, batch, status) 
            VALUES (?, ?, 'success')
            ON DUPLICATE KEY UPDATE 
            status = 'success'
        ");

        if (!$stmt) {
            error_log("MigrationService::recordMigration - Prepare error: " . self::$conn->error);
            return false;
        }

        $stmt->bind_param("si", $migrationName, $batch);
        if (!$stmt->execute()) {
            error_log("MigrationService::recordMigration - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Check if migration has been applied
     * 
     * @param string $migrationName Migration name to check
     * @return bool Whether migration was applied
     */
    public static function isMigrationApplied($migrationName) {
        if (!self::$initialized || !self::$conn) {
            return false;
        }

        $stmt = self::$conn->prepare("
            SELECT id FROM _migrations 
            WHERE migration_name = ? AND status = 'success'
            LIMIT 1
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("s", $migrationName);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Mark migration as failed
     * 
     * @param string $migrationName Migration name
     * @param string $errorMessage Error details (optional)
     * @return bool Success status
     */
    public static function markMigrationFailed($migrationName, $errorMessage = '') {
        if (!self::$initialized || !self::$conn) {
            return false;
        }

        $status = 'failed';
        $stmt = self::$conn->prepare("
            INSERT INTO _migrations (migration_name, batch, status) 
            VALUES (?, 0, ?)
            ON DUPLICATE KEY UPDATE 
            status = ?
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sss", $migrationName, $status, $status);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $stmt->close();

        // Log error if provided
        if (!empty($errorMessage)) {
            error_log("Migration failed: $migrationName - $errorMessage");
        }

        return true;
    }

    /**
     * Get all applied migrations
     * 
     * @return array Array of migration records
     */
    public static function getAppliedMigrations() {
        if (!self::$initialized || !self::$conn) {
            return [];
        }

        $result = self::$conn->query("
            SELECT migration_name, applied_at, batch, status 
            FROM _migrations 
            ORDER BY batch ASC, applied_at ASC
        ");

        if (!$result) {
            return [];
        }

        $migrations = [];
        while ($row = $result->fetch_assoc()) {
            $migrations[] = $row;
        }

        return $migrations;
    }

    /**
     * Get migration status summary
     * 
     * @return array Status information
     */
    public static function getMigrationStatus() {
        if (!self::$initialized || !self::$conn) {
            return [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'pending' => 0,
                'current_batch' => 0
            ];
        }

        $result = self::$conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                MAX(batch) as current_batch
            FROM _migrations
        ");

        if (!$result) {
            return [];
        }

        $status = $result->fetch_assoc();
        return [
            'total' => (int)$status['total'],
            'successful' => (int)$status['successful'] ?? 0,
            'failed' => (int)$status['failed'] ?? 0,
            'pending' => (int)$status['pending'] ?? 0,
            'current_batch' => (int)$status['current_batch'] ?? 0
        ];
    }

    /**
     * Get current batch number
     * 
     * @return int Current batch number or 0
     */
    public static function getCurrentBatch() {
        if (!self::$initialized || !self::$conn) {
            return 0;
        }

        $result = self::$conn->query("SELECT MAX(batch) as current_batch FROM _migrations");
        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)$row['current_batch'] ?? 0;
    }

    /**
     * Get last batch migrations
     * 
     * @return array Migrations in last batch
     */
    public static function getLastBatchMigrations() {
        if (!self::$initialized || !self::$conn) {
            return [];
        }

        $currentBatch = self::getCurrentBatch();
        if ($currentBatch === 0) {
            return [];
        }

        $result = self::$conn->query("
            SELECT migration_name, applied_at, status 
            FROM _migrations 
            WHERE batch = $currentBatch
            ORDER BY applied_at ASC
        ");

        if (!$result) {
            return [];
        }

        $migrations = [];
        while ($row = $result->fetch_assoc()) {
            $migrations[] = $row;
        }

        return $migrations;
    }

    /**
     * Rollback last batch of migrations
     * 
     * @return bool Success status
     */
    public static function rollbackLastBatch() {
        if (!self::$initialized || !self::$conn) {
            return false;
        }

        $currentBatch = self::getCurrentBatch();
        if ($currentBatch === 0) {
            return false;
        }

        // Delete migrations from last batch
        $stmt = self::$conn->prepare("DELETE FROM _migrations WHERE batch = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $currentBatch);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Get migrations by status
     * 
     * @param string $status Status to filter: success, failed, pending
     * @return array Migrations matching status
     */
    public static function getMigrationsByStatus($status) {
        if (!self::$initialized || !self::$conn) {
            return [];
        }

        $stmt = self::$conn->prepare("
            SELECT migration_name, applied_at, batch, status 
            FROM _migrations 
            WHERE status = ?
            ORDER BY batch ASC, applied_at ASC
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();

        $migrations = [];
        while ($row = $result->fetch_assoc()) {
            $migrations[] = $row;
        }

        $stmt->close();
        return $migrations;
    }

}

?>
