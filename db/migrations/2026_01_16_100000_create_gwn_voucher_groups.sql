-- Track voucher groups created on GWN Cloud
CREATE TABLE IF NOT EXISTS gwn_voucher_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gwn_group_id INT NOT NULL,
    group_name VARCHAR(255) NOT NULL,
    accommodation_id INT NOT NULL,
    voucher_month VARCHAR(50) NOT NULL,
    network_id VARCHAR(50) NOT NULL,
    voucher_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (accommodation_id) REFERENCES accommodations(id),
    INDEX idx_accomm_month (accommodation_id, voucher_month)
);

-- Add GWN voucher ID to voucher_logs for API deletion (safe for re-runs)
SET @col_exists_vid = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'voucher_logs' AND COLUMN_NAME = 'gwn_voucher_id');
SET @sql_vid = IF(@col_exists_vid = 0,
    'ALTER TABLE voucher_logs ADD COLUMN gwn_voucher_id INT NULL AFTER voucher_code',
    'SELECT 1');
PREPARE stmt_vid FROM @sql_vid;
EXECUTE stmt_vid;
DEALLOCATE PREPARE stmt_vid;

SET @col_exists_gid = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'voucher_logs' AND COLUMN_NAME = 'gwn_group_id');
SET @sql_gid = IF(@col_exists_gid = 0,
    'ALTER TABLE voucher_logs ADD COLUMN gwn_group_id INT NULL AFTER gwn_voucher_id',
    'SELECT 1');
PREPARE stmt_gid FROM @sql_gid;
EXECUTE stmt_gid;
DEALLOCATE PREPARE stmt_gid;
