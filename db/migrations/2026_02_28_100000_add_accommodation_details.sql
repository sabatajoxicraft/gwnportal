USE gwn_wifi_system;

ALTER TABLE accommodations ADD COLUMN address_line1 VARCHAR(255) NULL AFTER NAME;
ALTER TABLE accommodations ADD COLUMN address_line2 VARCHAR(255) NULL AFTER address_line1;
ALTER TABLE accommodations ADD COLUMN city VARCHAR(100) NULL AFTER address_line2;
ALTER TABLE accommodations ADD COLUMN province VARCHAR(100) NULL AFTER city;
ALTER TABLE accommodations ADD COLUMN postal_code VARCHAR(20) NULL AFTER province;
ALTER TABLE accommodations ADD COLUMN map_url VARCHAR(500) NULL AFTER postal_code;
ALTER TABLE accommodations ADD COLUMN max_students INT UNSIGNED NULL AFTER map_url;
ALTER TABLE accommodations ADD COLUMN contact_phone VARCHAR(20) NULL AFTER max_students;
ALTER TABLE accommodations ADD COLUMN contact_email VARCHAR(100) NULL AFTER contact_phone;
ALTER TABLE accommodations ADD COLUMN notes TEXT NULL AFTER contact_email;

-- Ensure capacity is not capped by a legacy tinyint column definition
ALTER TABLE accommodations MODIFY COLUMN max_students INT UNSIGNED NULL;
