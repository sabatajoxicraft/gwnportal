-- Add phone number and send method to onboarding codes table
-- This allows pre-filling the student registration form with captured data

ALTER TABLE onboarding_codes 
ADD COLUMN phone_number VARCHAR(20) AFTER student_last_name,
ADD COLUMN send_method VARCHAR(20) DEFAULT 'none' AFTER phone_number;

CREATE INDEX idx_phone_number ON onboarding_codes(phone_number);
