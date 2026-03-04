-- Fix activity_log timestamp to have proper default
-- Issue: timestamp DATETIME NOT NULL with no default caused 1970 dates

ALTER TABLE activity_log MODIFY COLUMN timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Also ensure existing NULL/0 values are corrected to current time
UPDATE activity_log SET timestamp = CURRENT_TIMESTAMP WHERE timestamp IS NULL OR timestamp = '0000-00-00 00:00:00';
