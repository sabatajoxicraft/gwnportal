<?php
/**
 * ActivityLogger Service - Centralized Activity & Audit Logging
 * 
 * All user actions, device changes, voucher operations, etc. are logged here.
 * This provides a complete audit trail for compliance and security.
 * 
 * Usage: ActivityLogger::logAction($userId, 'device_blocked', ['device_id' => 123]);
 */

class ActivityLogger {

    /**
     * Get database connection
     * @return mysqli Database connection
     */
    private static function getConn() {
        global $conn;
        if (!isset($conn)) {
            $conn = getDbConnection();
        }
        return $conn;
    }

    /**
     * Log a generic user action
     * 
     * @param int $userId User ID performing the action
     * @param string $action Action name (e.g., 'user_created', 'device_blocked')
     * @param array $details Additional details as JSON (optional)
     * @param string|null $ipAddress IP address (auto-detected if null)
     * @return bool Success
     */
    public static function logAction($userId, $action, $details = [], $ipAddress = null) {
        if ($ipAddress === null) {
            $ipAddress = self::getClientIp();
        }

        $detailsJson = !empty($details) ? json_encode($details) : null;
        $timestamp = date('Y-m-d H:i:s');

        $conn = self::getConn();
        $stmt = $conn->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, timestamp)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("ActivityLogger::logAction - Prepare error: " . $conn->error);
            return false;
        }

        $stmt->bind_param("issss", $userId, $action, $detailsJson, $ipAddress, $timestamp);
        
        if (!$stmt->execute()) {
            error_log("ActivityLogger::logAction - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Log page/section visit
     * 
     * @param int $userId User ID
     * @param string $page Page name (e.g., 'dashboard', 'settings')
     * @param array $details Additional context (optional)
     * @return bool Success
     */
    public static function logPageVisit($userId, $page, $details = []) {
        $details['page_visited'] = $page;
        return self::logAction($userId, 'page_visit', $details);
    }

    /**
     * Log device-related actions
     * 
     * @param int $userId User ID performing action
     * @param string $action Device action: 'register', 'block', 'unblock', 'unlink', 'update'
     * @param int $deviceId Device ID
     * @param array $details Additional details
     * @return bool Success
     */
    public static function logDeviceAction($userId, $action, $deviceId, $details = []) {
        $details['device_id'] = $deviceId;
        return self::logAction($userId, 'device_' . $action, $details);
    }

    /**
     * Log voucher-related actions
     * 
     * @param int $userId User ID performing action
     * @param string $action Voucher action: 'issued', 'used', 'revoked', 'sent'
     * @param int $voucherId Voucher ID
     * @param array $details Additional details
     * @return bool Success
     */
    public static function logVoucherAction($userId, $action, $voucherId, $details = []) {
        $details['voucher_id'] = $voucherId;
        return self::logAction($userId, 'voucher_' . $action, $details);
    }

    /**
     * Log student-related actions
     * 
     * @param int $userId User ID performing action (usually manager or admin)
     * @param int $studentId Student user ID being acted upon
     * @param string $action Student action: 'created', 'updated', 'disabled', 'assigned', 'removed'
     * @param array $details Additional details
     * @return bool Success
     */
    public static function logStudentAction($userId, $studentId, $action, $details = []) {
        $details['student_id'] = $studentId;
        return self::logAction($userId, 'student_' . $action, $details);
    }

    /**
     * Log accommodation-related actions
     * 
     * @param int $userId User ID performing action
     * @param int $accommodationId Accommodation ID
     * @param string $action Accommodation action: 'created', 'updated', 'deleted'
     * @param array $details Additional details
     * @return bool Success
     */
    public static function logAccommodationAction($userId, $accommodationId, $action, $details = []) {
        $details['accommodation_id'] = $accommodationId;
        return self::logAction($userId, 'accommodation_' . $action, $details);
    }

    /**
     * Log permission-related changes
     * 
     * @param int $userId User ID performing action
     * @param int $targetUserId User ID being affected
     * @param string $action Permission action: 'assigned', 'removed', 'role_changed'
     * @param array $details Additional details (especially new/old values)
     * @return bool Success
     */
    public static function logPermissionChange($userId, $targetUserId, $action, $details = []) {
        $details['target_user_id'] = $targetUserId;
        return self::logAction($userId, 'permission_' . $action, $details);
    }

    /**
     * Log authentication-related events
     * 
     * @param int|null $userId User ID (null for failed logins)
     * @param string $action Auth action: 'login_success', 'login_failed', 'logout', 'password_changed'
     * @param array $details Additional details
     * @return bool Success
     */
    public static function logAuthEvent($userId, $action, $details = []) {
        return self::logAction($userId, 'auth_' . $action, $details);
    }

    /**
     * Get activity log for a specific user
     * 
     * @param int $userId User ID
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Activity log records
     */
    public static function getActivityLog($userId, $limit = 50, $offset = 0) {
        $conn = self::getConn();
        
        $stmt = $conn->prepare("
            SELECT 
                id,
                user_id,
                action,
                details,
                ip_address,
                timestamp
            FROM activity_log
            WHERE user_id = ?
            ORDER BY timestamp DESC
            LIMIT ? OFFSET ?
        ");

        if (!$stmt) {
            error_log("ActivityLogger::getActivityLog - Prepare error: " . $conn->error);
            return [];
        }

        $stmt->bind_param("iii", $userId, $limit, $offset);
        
        if (!$stmt->execute()) {
            error_log("ActivityLogger::getActivityLog - Execute error: " . $stmt->error);
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $logs;
    }

    /**
     * Get activity log for a specific accommodation (all users)
     * 
     * @param int $accommodationId Accommodation ID
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Activity log records
     */
    public static function getAccommodationActivityLog($accommodationId, $limit = 50, $offset = 0) {
        $conn = self::getConn();
        
        $stmt = $conn->prepare("
            SELECT 
                al.id,
                al.user_id,
                u.username,
                u.first_name,
                u.last_name,
                al.action,
                al.details,
                al.timestamp
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.details LIKE ?
            ORDER BY al.timestamp DESC
            LIMIT ? OFFSET ?
        ");

        if (!$stmt) {
            error_log("ActivityLogger::getAccommodationActivityLog - Prepare error: " . $conn->error);
            return [];
        }

        $searchTerm = '%"accommodation_id":' . $accommodationId . '%';
        $stmt->bind_param("sii", $searchTerm, $limit, $offset);
        
        if (!$stmt->execute()) {
            error_log("ActivityLogger::getAccommodationActivityLog - Execute error: " . $stmt->error);
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $logs;
    }

    /**
     * Get all activity logs with filtering
     * 
     * @param array $filter Filters: ['user_id' => 123, 'action' => 'login_success', 'days' => 7]
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Activity log records
     */
    public static function getAllActivityLogs($filter = [], $limit = 100, $offset = 0) {
        $conn = self::getConn();
        
        $query = "
            SELECT 
                al.id,
                al.user_id,
                u.username,
                u.first_name,
                u.last_name,
                al.action,
                al.ip_address,
                al.timestamp
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1
        ";

        $params = [];
        $types = "";

        if (!empty($filter['user_id'])) {
            $query .= " AND al.user_id = ?";
            $params[] = $filter['user_id'];
            $types .= "i";
        }

        if (!empty($filter['action'])) {
            $query .= " AND al.action = ?";
            $params[] = $filter['action'];
            $types .= "s";
        }

        if (!empty($filter['days'])) {
            $query .= " AND al.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $params[] = $filter['days'];
            $types .= "i";
        }

        $query .= " ORDER BY al.timestamp DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("ActivityLogger::getAllActivityLogs - Prepare error: " . $conn->error);
            return [];
        }

        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            error_log("ActivityLogger::getAllActivityLogs - Execute error: " . $stmt->error);
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $logs;
    }

    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private static function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'INVALID';
    }

    /**
     * Clear old activity logs (for cleanup)
     * 
     * @param int $daysToKeep Keep logs from the last N days (default: 90)
     * @return int Number of records deleted
     */
    public static function clearOldLogs($daysToKeep = 90) {
        $conn = self::getConn();
        
        $stmt = $conn->prepare("
            DELETE FROM activity_log
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");

        if (!$stmt) {
            error_log("ActivityLogger::clearOldLogs - Prepare error: " . $conn->error);
            return 0;
        }

        $stmt->bind_param("i", $daysToKeep);
        
        if (!$stmt->execute()) {
            error_log("ActivityLogger::clearOldLogs - Execute error: " . $stmt->error);
            $stmt->close();
            return 0;
        }

        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        return $affectedRows;
    }

}

?>
