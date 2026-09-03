-- Migration: Add authoritative voucher expiry to voucher_logs
-- Date: 2026-09-03
-- Description: Persist the intended "valid through" timestamp for every voucher so
--              displays and expiry checks no longer re-derive the calendar-month
--              boundary from voucher_month at read time. The stored value is the
--              final second of the voucher's calendar month in the business
--              timezone (Africa/Johannesburg, a fixed UTC+2 zone with no DST), so a
--              plain DATETIME wall-clock value is unambiguous. LAST_DAY() yields the
--              correct final day for 28/29/30/31-day months.
--
--              Idempotent and safe to re-run. The column is added via an
--              information_schema guard (MySQL 8.0 does not support
--              ALTER TABLE ... ADD COLUMN IF NOT EXISTS), and the back-fill only
--              touches rows whose expiry is still NULL. Application code tolerates
--              the column being absent (pre-migration) via a legacy fallback, so
--              this migration can be applied at any time.

-- 1. Add the column only if it does not already exist.
SET @add_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'voucher_logs'
      AND COLUMN_NAME = 'voucher_expires_at'
);
SET @ddl := IF(@add_col = 0,
    'ALTER TABLE voucher_logs ADD COLUMN voucher_expires_at DATETIME NULL AFTER voucher_month',
    'DO 0');
PREPARE add_col_stmt FROM @ddl;
EXECUTE add_col_stmt;
DEALLOCATE PREPARE add_col_stmt;

-- 2. Back-fill existing rows from voucher_month. Supports both the 'F Y'
--    ("February 2026") and 'Y-m' ("2026-02") stored formats. Inputs are matched
--    with REGEXP and always given an explicit day before STR_TO_DATE, so no
--    zero-date is produced and strict SQL mode never raises on a mismatch. Rows
--    whose voucher_month matches neither format are left NULL and continue to be
--    handled by the application's legacy fallback.
UPDATE voucher_logs
SET voucher_expires_at = CONCAT(
        DATE_FORMAT(
            LAST_DAY(
                CASE
                    WHEN voucher_month REGEXP '^[0-9]{4}-[0-9]{2}$'
                        THEN STR_TO_DATE(CONCAT(voucher_month, '-01'), '%Y-%m-%d')
                    WHEN voucher_month REGEXP '^[A-Za-z]+ [0-9]{4}$'
                        THEN STR_TO_DATE(CONCAT('01 ', voucher_month), '%d %M %Y')
                    ELSE NULL
                END
            ),
            '%Y-%m-%d'
        ),
        ' 23:59:59'
    )
WHERE voucher_expires_at IS NULL
  AND (
        voucher_month REGEXP '^[0-9]{4}-[0-9]{2}$'
     OR voucher_month REGEXP '^[A-Za-z]+ [0-9]{4}$'
  );
