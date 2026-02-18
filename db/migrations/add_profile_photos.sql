-- Migration: Add profile photo support
-- Created: 2026-02-17
-- Purpose: Add profile_photo fields for visual identification during onboarding

-- Add profile photo to users table
ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER whatsapp_number;

-- Add profile photo to onboarding_codes table (captured when manager creates code)
ALTER TABLE onboarding_codes ADD COLUMN profile_photo VARCHAR(255) NULL AFTER expires_at;

-- Add student name fields to onboarding codes for pre-identification
ALTER TABLE onboarding_codes ADD COLUMN student_first_name VARCHAR(50) NULL AFTER profile_photo;
ALTER TABLE onboarding_codes ADD COLUMN student_last_name VARCHAR(50) NULL AFTER student_first_name;

-- Add indexes for faster queries
CREATE INDEX idx_users_profile_photo ON users(profile_photo);
CREATE INDEX idx_codes_profile_photo ON onboarding_codes(profile_photo);
