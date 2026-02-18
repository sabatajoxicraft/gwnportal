-- Simulate voucher first-use with real client MACs for autolink testing
-- This manually links vouchers to MAC addresses that are currently connected

SET @month_iso = '2026-02';

-- Update first 5 students with real active client MACs
UPDATE voucher_logs 
SET first_used_mac = 'A4:93:D9:AB:B7:05',
    first_used_at = NOW()
WHERE user_id = 10 AND voucher_month = @month_iso;

UPDATE voucher_logs 
SET first_used_mac = '06:AB:2A:C2:C9:60',
    first_used_at = NOW()
WHERE user_id = 11 AND voucher_month = @month_iso;

UPDATE voucher_logs 
SET first_used_mac = '08:60:33:88:E0:02',
    first_used_at = NOW()
WHERE user_id = 12 AND voucher_month = @month_iso;

UPDATE voucher_logs 
SET first_used_mac = 'A4:6B:3D:19:D7:F5',
    first_used_at = NOW()
WHERE user_id = 13 AND voucher_month = @month_iso;

UPDATE voucher_logs 
SET first_used_mac = 'CA:8E:58:B6:A6:CB',
    first_used_at = NOW()
WHERE user_id = 14 AND voucher_month = @month_iso;

-- Verify simulation
SELECT 
    u.id AS user_id,
    CONCAT(u.first_name, ' ', u.last_name) AS student_name,
    vl.voucher_code,
    vl.first_used_mac,
    vl.first_used_at
FROM voucher_logs vl
JOIN users u ON u.id = vl.user_id
WHERE vl.voucher_month = @month_iso
  AND vl.first_used_mac IS NOT NULL
ORDER BY u.id;

SELECT CONCAT('✓ Simulated ', COUNT(*), ' voucher first-uses with MAC addresses') AS summary
FROM voucher_logs
WHERE voucher_month = @month_iso AND first_used_mac IS NOT NULL;
