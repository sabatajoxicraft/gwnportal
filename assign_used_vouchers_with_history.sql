-- Assign used/inuse vouchers with device history to students
SET @month_iso = '2026-02';

-- Clear existing Feb vouchers
DELETE FROM voucher_logs WHERE voucher_month = @month_iso;

-- Assign vouchers to students (these vouchers have historical device usage)
INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at, created_at, gwn_group_id, is_active) VALUES 
(10, '12593701812', '2026-02', 'SMS', 'sent', NOW(), NOW(), 245934, 1),
(11, '15439946061', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(12, '12469646059', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(13, '12469346057', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(14, '14489846054', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(15, '14419546051', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(16, '11459946049', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(17, '12449646047', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(18, '18449746041', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(19, '10429246040', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(20, '16499546036', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1),
(21, '18469746035', '2026-02', 'SMS', 'sent', NOW(), NOW(), 241669, 1);

-- Verify
SELECT 
    u.id,
    CONCAT(u.first_name, ' ', u.last_name) AS student,
    vl.voucher_code,
    vl.gwn_group_id
FROM voucher_logs vl
JOIN users u ON u.id = vl.user_id
WHERE vl.voucher_month = @month_iso
ORDER BY u.id;

SELECT CONCAT('✓ Assigned ', COUNT(*), ' vouchers with device history to students') AS summary
FROM voucher_logs WHERE voucher_month = @month_iso;
