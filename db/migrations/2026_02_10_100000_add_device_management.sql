-- Migration: Add device management and blocking functionality
-- Date: 2025-02-05
-- Description: Adds device management columns and device_block_log table

-- Add columns to user_devices table if they don't exist
SET @db_name = DATABASE();

-- Check and add linked_via column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'linked_via';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN linked_via ENUM(''manual'',''auto'',''request'') DEFAULT ''manual'' AFTER mac_address',
  'SELECT ''Column linked_via already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add device_name column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'device_name';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN device_name VARCHAR(100) AFTER linked_via',
  'SELECT ''Column device_name already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add last_seen column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'last_seen';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN last_seen DATETIME AFTER device_name',
  'SELECT ''Column last_seen already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add is_blocked column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'is_blocked';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN is_blocked TINYINT(1) DEFAULT 0 AFTER last_seen',
  'SELECT ''Column is_blocked already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add blocked_at column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'blocked_at';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN blocked_at DATETIME AFTER is_blocked',
  'SELECT ''Column blocked_at already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add blocked_by column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'blocked_by';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN blocked_by INT AFTER blocked_at',
  'SELECT ''Column blocked_by already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add blocked_reason column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'blocked_reason';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN blocked_reason VARCHAR(255) AFTER blocked_by',
  'SELECT ''Column blocked_reason already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add unblocked_at column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'unblocked_at';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN unblocked_at DATETIME AFTER blocked_reason',
  'SELECT ''Column unblocked_at already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add unblocked_by column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND COLUMN_NAME = 'unblocked_by';

SET @sql = IF(@col_exists = 0, 
  'ALTER TABLE user_devices ADD COLUMN unblocked_by INT AFTER unblocked_at',
  'SELECT ''Column unblocked_by already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add first_used_at column to voucher_logs
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'voucher_logs'
  AND COLUMN_NAME = 'first_used_at';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE voucher_logs ADD COLUMN first_used_at DATETIME NULL',
  'SELECT ''Column first_used_at already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add first_used_mac column to voucher_logs
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'voucher_logs'
  AND COLUMN_NAME = 'first_used_mac';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE voucher_logs ADD COLUMN first_used_mac VARCHAR(17) NULL',
  'SELECT ''Column first_used_mac already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create device_block_log table if not exists
CREATE TABLE IF NOT EXISTS device_block_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    user_id INT NOT NULL,
    mac_address VARCHAR(17) NOT NULL,
    action ENUM('block','unblock') NOT NULL,
    reason VARCHAR(255),
    performed_by INT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES user_devices(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_device_id (device_id),
    INDEX idx_user_id (user_id),
    INDEX idx_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add unique index on mac_address if not exists
SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = @db_name 
  AND TABLE_NAME = 'user_devices' 
  AND INDEX_NAME = 'uq_user_devices_mac_address';

SET @sql = IF(@idx_exists = 0, 
  'ALTER TABLE user_devices ADD UNIQUE INDEX uq_user_devices_mac_address (mac_address)',
  'SELECT ''Index uq_user_devices_mac_address already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add first-use index if not exists
SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'voucher_logs'
  AND INDEX_NAME = 'idx_voucher_first_used_at';

SET @sql = IF(@idx_exists = 0,
  'CREATE INDEX idx_voucher_first_used_at ON voucher_logs(first_used_at)',
  'SELECT ''Index idx_voucher_first_used_at already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
