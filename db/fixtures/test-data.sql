-- GWN Portal - Test Data for Development & Testing
-- Use: mysql gwn_wifi_system < db/fixtures/test-data.sql
-- NOTE: This is ONLY for development/testing. Never run in production.

USE gwn_wifi_system;

-- Insert test admin and owner users (without pre-hashed passwords)
INSERT INTO users (id, username, password, email, first_name, last_name, phone_number, role_id, status, password_reset_required)
VALUES 
(1, 'admin', '', 'admin@kimwifi.co.za', 'Lethabo', 'Sithole', '+27718234567', 1, 'active', 1),
(2, 'nokuthula', '', 'nokuthula@kimwifi.co.za', 'Nokuthula', 'Mkhize', '+27829876543', 2, 'active', 1),
(3, 'sipho', '', 'sipho@kimwifi.co.za', 'Sipho', 'Dlamini', '+27731234567', 3, 'active', 0),
(4, 'amanda', '', 'amanda.shaw@kimwifi.co.za', 'Amanda', 'Shaw', '+27764321098', 3, 'active', 0),
(5, 'james_m', '', 'james.mthembu@kimwifi.co.za', 'James', 'Mthembu', '+27658765432', 3, 'active', 0)
ON DUPLICATE KEY UPDATE email=email;

-- Insert test accommodations (Kimberley venues)
INSERT INTO accommodations (id, name, owner_id)
VALUES 
(1, 'De Beers Diamond Lodge', 2),
(2, 'Kimberley Student Residences', 2),
(3, 'The Bungalow Guesthouse', 2),
(4, 'Northern Cape University Housing', 2)
ON DUPLICATE KEY UPDATE name=name;

-- Link test managers to accommodations
INSERT INTO user_accommodation (user_id, accommodation_id)
VALUES 
(3, 1),
(3, 2),
(4, 3),
(5, 4)
ON DUPLICATE KEY UPDATE user_id=user_id;

-- Insert test students with realistic South African names
INSERT INTO users (id, username, password, email, first_name, last_name, id_number, phone_number, whatsapp_number, preferred_communication, role_id, status, password_reset_required)
VALUES 
(10, 'thandi_nkomo', '', 'thandi.nkomo@student.ac.za', 'Thandi', 'Nkomo', '9809015234891', '+27701234567', '+27701234567', 'WhatsApp', 4, 'active', 0),
(11, 'lerato_molefe', '', 'lerato.molefe@student.ac.za', 'Lerato', 'Molefe', '9907234567891', '+27721567890', '+27721567890', 'WhatsApp', 4, 'active', 0),
(12, 'bongiwe_tshuma', '', 'bongiwe.tshuma@student.ac.za', 'Bongiwe', 'Tshuma', '0012312345678', '+27768901234', '+27768901234', 'SMS', 4, 'active', 0),
(13, 'mandla_zuma', '', 'mandla.zuma@student.ac.za', 'Mandla', 'Zuma', '9704123456789', '+27741234567', '+27741234567', 'WhatsApp', 4, 'pending', 0),
(14, 'nomsa_khumalo', '', 'nomsa.khumalo@student.ac.za', 'Nomsa', 'Khumalo', '9608234567890', '+27765432109', '+27765432109', 'SMS', 4, 'active', 0),
(15, 'aiden_smith', '', 'aiden.smith@student.ac.za', 'Aiden', 'Smith', '9905312345678', '+27751111111', '+27751111111', 'SMS', 4, 'active', 0),
(16, 'lindiwe_nkosi', '', 'lindiwe.nkosi@student.ac.za', 'Lindiwe', 'Nkosi', '9803214567890', '+27734567890', '+27734567890', 'WhatsApp', 4, 'active', 0),
(17, 'thabo_makela', '', 'thabo.makela@student.ac.za', 'Thabo', 'Makela', '9710123456789', '+27778901234', '+27778901234', 'SMS', 4, 'pending', 0),
(18, 'amelia_ross', '', 'amelia.ross@student.ac.za', 'Amelia', 'Ross', '9912234567890', '+27759876543', '+27759876543', 'WhatsApp', 4, 'active', 0),
(19, 'david_mngomezulu', '', 'david.mngomezulu@student.ac.za', 'David', 'Mngomezulu', '9611345678901', '+27715432109', '+27715432109', 'SMS', 4, 'active', 0)
ON DUPLICATE KEY UPDATE email=email;

-- Link test students to accommodations
INSERT INTO students (user_id, accommodation_id, room_number, status)
VALUES 
(10, 1, '101', 'active'),
(11, 1, '102', 'active'),
(12, 2, 'A201', 'active'),
(13, 2, 'A202', 'pending'),
(14, 3, '05', 'active'),
(15, 3, '06', 'active'),
(16, 4, 'G101', 'active'),
(17, 4, 'G102', 'pending'),
(18, 1, '201', 'active'),
(19, 2, 'B103', 'active')
ON DUPLICATE KEY UPDATE room_number=room_number;

-- Insert test onboarding codes
INSERT INTO onboarding_codes (code, created_by, accommodation_id, used_by, status, role_id, expires_at)
VALUES 
('KB2D-A8F2-H5K1', 3, 1, NULL, 'unused', 4, DATE_ADD(NOW(), INTERVAL 7 DAY)),
('KC3E-B9G3-I6L2', 3, 1, NULL, 'unused', 4, DATE_ADD(NOW(), INTERVAL 7 DAY)),
('KD4F-C0H4-J7M3', 4, 3, NULL, 'unused', 4, DATE_ADD(NOW(), INTERVAL 5 DAY)),
('KE5G-D1I5-K8N4', 5, 4, 17, 'used', 4, DATE_ADD(NOW(), INTERVAL 7 DAY))
ON DUPLICATE KEY UPDATE code=code;

-- Insert sample test voucher logs (sent WiFi vouchers)
INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at)
VALUES 
(10, 'KB-2024-0001', 'January 2024', 'WhatsApp', 'sent', NOW()),
(11, 'KB-2024-0002', 'January 2024', 'WhatsApp', 'sent', NOW()),
(12, 'KB-2024-0003', 'January 2024', 'SMS', 'sent', NOW()),
(14, 'KB-2024-0005', 'January 2024', 'SMS', 'sent', NOW()),
(15, 'KB-2024-0006', 'January 2024', 'SMS', 'sent', NOW()),
(16, 'KB-2024-0007', 'January 2024', 'WhatsApp', 'sent', NOW()),
(18, 'KB-2024-0009', 'January 2024', 'WhatsApp', 'sent', NOW()),
(19, 'KB-2024-0010', 'January 2024', 'SMS', 'sent', NOW())
ON DUPLICATE KEY UPDATE sent_at=NOW();

-- Insert sample test activity log
INSERT INTO activity_log (user_id, action, details, timestamp)
VALUES 
(1, 'System Setup', 'Database initialized and seed data loaded', NOW()),
(2, 'Accommodation Created', 'De Beers Diamond Lodge created', NOW()),
(3, 'Manager Assignment', 'Assigned as manager to De Beers Diamond Lodge', NOW()),
(10, 'Student Registration', 'Account created using onboarding code', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(11, 'Voucher Received', 'Monthly WiFi voucher sent via WhatsApp', DATE_SUB(NOW(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE timestamp=NOW();
