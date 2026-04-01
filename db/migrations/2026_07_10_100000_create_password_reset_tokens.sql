-- ============================================================================
-- Password Reset Tokens Migration
-- Creates table for self-service email-based password reset token storage.
-- Tokens are stored as SHA-256 hashes; plaintext is only ever held in memory
-- and emailed to the user.  One-time use; outstanding tokens are revoked when
-- a new request arrives for the same user.
-- ============================================================================

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    token_hash  VARCHAR(64)  NOT NULL        COMMENT 'SHA-256 hex digest of the plaintext token',
    status      ENUM('pending','used','revoked') NOT NULL DEFAULT 'pending',
    request_ip  VARCHAR(45)  NULL            COMMENT 'IPv4 or IPv6 address of the requester',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    UNIQUE  KEY uq_token_hash  (token_hash),
    INDEX       idx_user_status (user_id, status),
    INDEX       idx_expires     (expires_at),
    INDEX       idx_request_ip  (request_ip),
    CONSTRAINT  fk_prt_user  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Migration Complete
-- ============================================================================
