-- Migration: Create voucher_notification_locks
-- Description: Concurrency-safe duplicate-send guard for voucher notifications.
-- Prevents the SAME voucher code from being delivered to the SAME recipient
-- (user_id) more than once within a rolling window (enforced in application
-- code, see VoucherNotificationGuard). A unique (user_id, voucher_code) row
-- is reserved and locked with SELECT ... FOR UPDATE so concurrent requests
-- (duplicate clicks, concurrent routes, retried webhooks) serialize on the
-- same row and cannot both send the same voucher code. Failed sends are
-- deleted so retries are never blocked; only confirmed successful sends are
-- recorded as 'sent'.

CREATE TABLE IF NOT EXISTS voucher_notification_locks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    voucher_code VARCHAR(50) NOT NULL,
    status ENUM('pending', 'sent') NOT NULL DEFAULT 'pending',
    reserved_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_voucher (user_id, voucher_code),
    INDEX idx_reserved_at (reserved_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
