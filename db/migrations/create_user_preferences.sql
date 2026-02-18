CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT PRIMARY KEY,
    notify_device_requests BOOLEAN DEFAULT 1,
    notify_device_status BOOLEAN DEFAULT 1,
    notify_vouchers BOOLEAN DEFAULT 1,
    notify_new_students BOOLEAN DEFAULT 1,
    email_notifications BOOLEAN DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
