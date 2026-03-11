-- Add revoke functionality fields to voucher_logs table
ALTER TABLE voucher_logs 
ADD COLUMN revoked_at TIMESTAMP NULL,
ADD COLUMN revoked_by INT NULL,
ADD COLUMN revoke_reason TEXT,
ADD COLUMN is_active BOOLEAN DEFAULT 1,
ADD FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL;
