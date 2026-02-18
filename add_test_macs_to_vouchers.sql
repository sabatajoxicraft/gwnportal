-- Complete autolink test by adding real client MAC addresses to vouchers
-- These are actual MACs from currently connected clients on the network

SET @month_iso = '2026-02';

-- Update vouchers with real client MACs (simulating what GWN API would return if sessions were active)
UPDATE voucher_logs SET first_used_mac = 'A4:93:D9:AB:B7:05' WHERE user_id = 10 AND voucher_month = @month_iso;
UPDATE voucher_logs SET first_used_mac = '06:AB:2A:C2:C9:60' WHERE user_id = 11 AND voucher_month = @month_iso;
UPDATE voucher_logs SET first_used_mac = '08:60:33:88:E0:02' WHERE user_id = 12 AND voucher_month = @month_iso;
UPDATE voucher_logs SET first_used_mac = 'A4:6B:3D:19:D7:F5' WHERE user_id = 13 AND voucher_month = @month_iso;
UPDATE voucher_logs SET first_used_mac = 'CA:8E:58:B6:A6:CB' WHERE user_id = 14 AND voucher_month = @month_iso;

-- Verify the update
SELECT 
    u.id,
    CONCAT(u.first_name, ' ', u.last_name) AS student,
    vl.voucher_code,
    vl.first_used_mac,
    CASE WHEN vl.first_used_mac IS NOT NULL THEN 'Ready' ELSE 'Pending' END AS status
FROM voucher_logs vl
JOIN users u ON u.id = vl.user_id
WHERE vl.voucher_month = @month_iso
ORDER BY u.id;

SELECT '✓ Test data ready - 5 vouchers with MAC addresses' AS summary;
