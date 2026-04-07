CREATE DATABASE IF NOT EXISTS gwn_wifi_system;

USE gwn_wifi_system;

-- First disable foreign key checks to allow dropping tables with constraints
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in correct order (most dependent first)
DROP TABLE IF EXISTS voucher_logs;
DROP TABLE IF EXISTS onboarding_codes;
DROP TABLE IF EXISTS user_devices;
DROP TABLE IF EXISTS user_accommodation;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS accommodations;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create unified users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    id_number VARCHAR(13) UNIQUE,  -- South African ID number (13 digits)
    phone_number VARCHAR(20),
    whatsapp_number VARCHAR(20),
    preferred_communication ENUM('SMS', 'WhatsApp') DEFAULT 'SMS',
    profile_photo VARCHAR(255) DEFAULT NULL,
    role_id INT NOT NULL,
    status ENUM('active', 'pending', 'inactive') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    password_reset_required BOOLEAN DEFAULT 0,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Accommodations table
CREATE TABLE IF NOT EXISTS accommodations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address_line1 VARCHAR(255) NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    map_url VARCHAR(500) NULL,
    max_students INT UNSIGNED NULL,
    contact_phone VARCHAR(20) NULL,
    contact_email VARCHAR(100) NULL,
    notes TEXT NULL,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User-Accommodation relationship table (for managers)
CREATE TABLE IF NOT EXISTS user_accommodation (
    user_id INT NOT NULL,
    accommodation_id INT NOT NULL,
    PRIMARY KEY (user_id, accommodation_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
);

-- User Devices table (for students)
CREATE TABLE IF NOT EXISTS user_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_type VARCHAR(50) NOT NULL,
    mac_address VARCHAR(17) NOT NULL,
    linked_via ENUM('manual','auto','request') DEFAULT 'manual',
    device_name VARCHAR(100),
    last_seen DATETIME,
    is_blocked TINYINT(1) DEFAULT 0,
    blocked_at DATETIME,
    blocked_by INT,
    blocked_reason VARCHAR(255),
    unblocked_at DATETIME,
    unblocked_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX uq_user_devices_mac_address (mac_address),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Onboarding codes table
CREATE TABLE IF NOT EXISTS onboarding_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_by INT NOT NULL,
    accommodation_id INT NOT NULL,
    used_by INT NULL,
    status ENUM('unused', 'used', 'expired') NOT NULL DEFAULT 'unused',
    role_id INT NOT NULL, -- Target role (student or manager)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    used_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Voucher logs table
CREATE TABLE IF NOT EXISTS voucher_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    voucher_code VARCHAR(50) NOT NULL,
    gwn_voucher_id INT NULL,
    gwn_group_id INT NULL,
    voucher_month VARCHAR(20) NOT NULL,
    sent_via ENUM('SMS', 'WhatsApp') NOT NULL,
    status ENUM('sent', 'failed', 'pending') NOT NULL DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    first_used_at DATETIME NULL,
    first_used_mac VARCHAR(17) NULL,
    revoked_at TIMESTAMP NULL,
    revoked_by INT NULL,
    revoke_reason TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_voucher_first_used_at (first_used_at)
);

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (timestamp)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    category VARCHAR(50) NULL,
    related_id INT NULL,
    read_status BOOLEAN NOT NULL DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient_unread (recipient_id, read_status),
    INDEX idx_created (created_at),
    INDEX idx_category (category)
);

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    accommodation_id INT NOT NULL,
    room_number VARCHAR(20),
    status ENUM('active', 'pending', 'inactive') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
);

-- Insert default roles (required for fresh schema)
INSERT INTO roles (id, name, description) 
VALUES 
(1, 'admin', 'System administrator with full access'),
(2, 'owner', 'Accommodation owner who can manage multiple accommodations'),
(3, 'manager', 'Accommodation manager who manages a specific accommodation'),
(4, 'student', 'Student who uses WiFi services')
ON DUPLICATE KEY UPDATE name=name;

-- TEST DATA REMOVED: Use db/fixtures/test-data.sql for development/testing
-- Usage: mysql gwn_wifi_system < db/fixtures/test-data.sql

-- User notification preferences table
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    notify_device_requests BOOLEAN DEFAULT 1,
    notify_device_status BOOLEAN DEFAULT 1,
    notify_vouchers BOOLEAN DEFAULT 1,
    notify_new_students BOOLEAN DEFAULT 1,
    email_notifications BOOLEAN DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
