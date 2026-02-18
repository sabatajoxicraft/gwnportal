<?php
/**
 * UserService - Centralized User Management Operations
 * 
 * Handles all user-related business logic:
 * - User creation, update, deletion
 * - Password management
 * - Role-based access control
 * - User authentication
 * 
 * Usage: UserService::createUser($conn, $userData);
 */

class UserService {

    /**
     * Create a new user account
     * 
     * @param mysqli $conn Database connection
     * @param array $userData User data with keys: username, password, email, first_name, last_name, role_id, status (optional)
     * @return array|false Array with ['success' => true, 'user_id' => X] or false on failure
     */
    public static function createUser($conn, $userData) {
        // Validate required fields
        $requiredFields = ['username', 'password', 'email', 'first_name', 'last_name', 'role_id'];
        foreach ($requiredFields as $field) {
            if (empty($userData[$field])) {
                error_log("UserService::createUser - Missing required field: $field");
                return false;
            }
        }

        // Hash password (use bcrypt or password_hash)
        $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
        
        $status = $userData['status'] ?? 'pending';
        $idNumber = $userData['id_number'] ?? null;
        $phoneNumber = $userData['phone_number'] ?? null;
        $whatsappNumber = $userData['whatsapp_number'] ?? null;
        $preferredCommunication = $userData['preferred_communication'] ?? 'SMS';

        $stmt = $conn->prepare("
            INSERT INTO users (
                username, password, email, first_name, last_name,
                id_number, phone_number, whatsapp_number, preferred_communication,
                role_id, status, password_reset_required
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("UserService::createUser - Prepare error: " . $conn->error);
            return false;
        }

        $passwordReset = $userData['password_reset_required'] ?? true ? 1 : 0;

        $stmt->bind_param(
            "ssssssssssii",
            $userData['username'],
            $hashedPassword,
            $userData['email'],
            $userData['first_name'],
            $userData['last_name'],
            $idNumber,
            $phoneNumber,
            $whatsappNumber,
            $preferredCommunication,
            $userData['role_id'],
            $status,
            $passwordReset
        );

        if (!$stmt->execute()) {
            error_log("UserService::createUser - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $userId = $stmt->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'user_id' => $userId,
            'username' => $userData['username'],
            'email' => $userData['email']
        ];
    }

    /**
     * Update user profile information
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID to update
     * @param array $updateData Fields to update (username, email, first_name, last_name, phone_number, etc.)
     * @return bool Success
     */
    public static function updateUser($conn, $userId, $updateData) {
        if (empty($userId) || empty($updateData)) {
            return false;
        }

        // Build dynamic UPDATE query
        $allowedFields = ['username', 'email', 'first_name', 'last_name', 'phone_number', 'whatsapp_number', 'preferred_communication', 'status'];
        $updateFields = [];
        $params = [];
        $types = "";

        foreach ($updateData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = ?";
                $params[] = $value;
                $types .= "s";
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $query = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $params[] = $userId;
        $types .= "i";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("UserService::updateUser - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            error_log("UserService::updateUser - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Change user password
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @param string $newPassword New password (plain text - will be hashed)
     * @param bool $requireReset Set password_reset_required flag
     * @return bool Success
     */
    public static function changePassword($conn, $userId, $newPassword, $requireReset = false) {
        if (empty($userId) || empty($newPassword)) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $resetFlag = $requireReset ? 0 : 0; // After change, reset not required

        $stmt = $conn->prepare("
            UPDATE users
            SET password = ?, password_reset_required = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            error_log("UserService::changePassword - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("sii", $hashedPassword, $resetFlag, $userId);
        
        if (!$stmt->execute()) {
            error_log("UserService::changePassword - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Verify user password (for login)
     * 
     * @param string $plainPassword Plain text password from login form
     * @param string $hashedPassword Hashed password from database
     * @return bool True if password matches, false otherwise
     */
    public static function verifyPassword($plainPassword, $hashedPassword) {
        if (empty($plainPassword) || empty($hashedPassword)) {
            return false;
        }

        return password_verify($plainPassword, $hashedPassword);
    }

    /**
     * Authenticate user (login)
     * 
     * @param mysqli $conn Database connection
     * @param string $username Username or email
     * @param string $password Password (plain text)
     * @return array|false User data if successful, false otherwise
     */
    public static function authenticate($conn, $username, $password) {
        if (empty($username) || empty($password)) {
            return false;
        }

        // Query user by username OR email
        $stmt = $conn->prepare("
            SELECT 
                u.id,
                u.username,
                u.password,
                u.email,
                u.first_name,
                u.last_name,
                u.role_id,
                r.name AS role_name,
                u.status,
                u.password_reset_required
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.username = ? OR u.email = ?
        ");

        if (!$stmt) {
            error_log("UserService::authenticate - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return false;
        }

        // Verify password
        if (!self::verifyPassword($password, $user['password'])) {
            return false;
        }

        // Remove sensitive field
        unset($user['password']);

        return $user;
    }

    /**
     * Get user by ID with all details
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @return array|null User data or null if not found
     */
    public static function getUser($conn, $userId) {
        return QueryService::getUserWithRole($conn, $userId);
    }

    /**
     * Delete user account
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID to delete
     * @return bool Success
     */
    public static function deleteUser($conn, $userId) {
        if (empty($userId)) {
            return false;
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");

        if (!$stmt) {
            error_log("UserService::deleteUser - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            error_log("UserService::deleteUser - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Check if username exists
     * 
     * @param mysqli $conn Database connection
     * @param string $username Username to check
     * @param int $excludeUserId Optional: exclude specific user ID (for updates)
     * @return bool True if exists, false otherwise
     */
    public static function usernameExists($conn, $username, $excludeUserId = null) {
        $query = "SELECT id FROM users WHERE username = ?";
        $params = [$username];
        $types = "s";

        if ($excludeUserId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeUserId;
            $types .= "i";
        }

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Check if email exists
     * 
     * @param mysqli $conn Database connection
     * @param string $email Email to check
     * @param int $excludeUserId Optional: exclude specific user ID (for updates)
     * @return bool True if exists, false otherwise
     */
    public static function emailExists($conn, $email, $excludeUserId = null) {
        $query = "SELECT id FROM users WHERE email = ?";
        $params = [$email];
        $types = "s";

        if ($excludeUserId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeUserId;
            $types .= "i";
        }

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Update user status
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @param string $status New status: active, pending, inactive
     * @return bool Success
     */
    public static function setStatus($conn, $userId, $status) {
        $validStatuses = ['active', 'pending', 'inactive'];
        
        if (empty($userId) || !in_array($status, $validStatuses)) {
            return false;
        }

        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");

        if (!$stmt) {
            error_log("UserService::setStatus - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("si", $status, $userId);
        
        if (!$stmt->execute()) {
            error_log("UserService::setStatus - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Set password reset required flag
     * 
     * @param mysqli $conn Database connection
     * @param int $userId User ID
     * @param bool $required True to require reset, false otherwise
     * @return bool Success
     */
    public static function requirePasswordReset($conn, $userId, $required = true) {
        if (empty($userId)) {
            return false;
        }

        $flag = $required ? 1 : 0;

        $stmt = $conn->prepare("UPDATE users SET password_reset_required = ? WHERE id = ?");

        if (!$stmt) {
            error_log("UserService::requirePasswordReset - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("ii", $flag, $userId);
        
        if (!$stmt->execute()) {
            error_log("UserService::requirePasswordReset - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

}

?>
