<?php
/**
 * Activity Logger Extension - Page Load & Request Tracking
 * 
 * Tracks page views, API calls, and performance metrics for analytics.
 * Complements ActivityLogger with request-level tracking.
 */

class RequestLogger {

    private static $requestStartTime = null;
    private static $enabled = true;

    /**
     * Initialize request tracking
     * 
     * @return void
     */
    public static function init() {
        self::$requestStartTime = microtime(true);
    }

    /**
     * Log page view
     * 
     * @param int $userId User ID (null for anonymous)
     * @param string $page Page name/path
     * @param array $context Additional context (query params, POST data, etc.)
     * @return bool Success status
     */
    public static function logPageView($userId, $page, $context = []) {
        global $conn;
        
        if (!self::$enabled || !$conn) {
            return false;
        }

        $executionTime = self::getExecutionTime();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = self::getClientIpAddress();

        $contextJson = json_encode($context);

        $stmt = $conn->prepare("
            INSERT INTO activity_logs 
            (user_id, action, entity_type, details, ip_address)
            VALUES (?, ?, 'page_view', ?, ?)
        ");

        if (!$stmt) {
            error_log("RequestLogger::logPageView - Prepare error: " . $conn->error);
            return false;
        }

        $action = 'page_view_' . str_replace('/', '_', trim($page, '/'));
        $contextJson = json_encode([
            'page' => $page,
            'method' => $method,
            'execution_time' => $executionTime,
            'referer' => $referer,
            'user_agent' => substr($userAgent, 0, 255),
            'context' => $context
        ]);

        $stmt->bind_param("iss", $userId, $action, $contextJson);
        if (!$stmt->execute()) {
            error_log("RequestLogger::logPageView - Execute error: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Log API call
     * 
     * @param int $userId User ID
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param int $statusCode HTTP response code
     * @param array $data Request/response data (optional)
     * @return bool Success status
     */
    public static function logApiCall($userId, $endpoint, $method, $statusCode, $data = []) {
        global $conn;
        
        if (!self::$enabled || !$conn) {
            return false;
        }

        $executionTime = self::getExecutionTime();
        $ipAddress = self::getClientIpAddress();

        $stmt = $conn->prepare("
            INSERT INTO activity_logs 
            (user_id, action, entity_type, details, ip_address)
            VALUES (?, ?, 'api_call', ?, ?)
        ");

        if (!$stmt) {
            return false;
        }

        $action = strtolower($method) . '_' . str_replace('/', '_', trim($endpoint, '/'));
        $details = json_encode([
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'execution_time' => $executionTime,
            'data' => $data
        ]);

        $stmt->bind_param("iss", $userId, $action, $details);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Log database query
     * 
     * @param string $operation Operation name
     * @param int $status Status code (200 for success, error code otherwise)
     * @param float $executionTime Query execution time
     * @param string $query SQL query (sanitized, passwords removed)
     * @return bool Success status
     */
    public static function logDatabaseQuery($operation, $status, $executionTime, $query = '') {
        global $conn;
        
        if (!self::$enabled || !$conn) {
            return false;
        }

        // Don't log query text for security
        $stmt = $conn->prepare("
            INSERT INTO activity_logs 
            (user_id, action, entity_type, details)
            VALUES (?, ?, 'database_query', ?)
        ");

        if (!$stmt) {
            return false;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $details = json_encode([
            'operation' => $operation,
            'status' => $status,
            'execution_time' => $executionTime
        ]);

        $stmt->bind_param("iss", $userId, $operation, $details);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Get page view statistics
     * 
     * @param string $page Page name
     * @param string $period Period: today, week, month, all
     * @return array Statistics
     */
    public static function getPageViewStats($page, $period = 'today') {
        global $conn;
        
        if (!$conn) {
            return [];
        }

        $dateFilter = self::getDateFilter($period);
        $action = 'page_view_' . str_replace('/', '_', trim($page, '/'));

        $result = $conn->query("
            SELECT 
                COUNT(*) as views,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM activity_logs
            WHERE action = '$action'
            AND created_at >= $dateFilter
        ");

        if (!$result) {
            return [];
        }

        return $result->fetch_assoc() ?? [];
    }

    /**
     * Get most viewed pages
     * 
     * @param int $limit Limit results
     * @param string $period Period: today, week, month, all
     * @return array Page views
     */
    public static function getMostViewedPages($limit = 10, $period = 'today') {
        global $conn;
        
        if (!$conn) {
            return [];
        }

        $dateFilter = self::getDateFilter($period);

        $result = $conn->query("
            SELECT 
                action,
                COUNT(*) as views,
                COUNT(DISTINCT user_id) as unique_users
            FROM activity_logs
            WHERE entity_type = 'page_view'
            AND created_at >= $dateFilter
            GROUP BY action
            ORDER BY views DESC
            LIMIT $limit
        ");

        if (!$result) {
            return [];
        }

        $pages = [];
        while ($row = $result->fetch_assoc()) {
            $pages[] = $row;
        }

        return $pages;
    }

    /**
     * Get user activity summary
     * 
     * @param int $userId User ID
     * @param string $period Period: today, week, month, all
     * @return array Activity summary
     */
    public static function getUserActivitySummary($userId, $period = 'today') {
        global $conn;
        
        if (!$conn) {
            return [];
        }

        $dateFilter = self::getDateFilter($period);

        $result = $conn->query("
            SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT entity_type) as action_types,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                MAX(created_at) as last_activity
            FROM activity_logs
            WHERE user_id = $userId
            AND created_at >= $dateFilter
        ");

        if (!$result) {
            return [];
        }

        return $result->fetch_assoc() ?? [];
    }

    /**
     * Get execution time (milliseconds)
     * 
     * @return float Execution time in ms
     */
    private static function getExecutionTime() {
        if (self::$requestStartTime === null) {
            return 0;
        }

        $endTime = microtime(true);
        return round(($endTime - self::$requestStartTime) * 1000, 2);
    }

    /**
     * Get client IP address
     * 
     * @return string Client IP
     */
    private static function getClientIpAddress() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP']; // CloudFlare
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return 'UNKNOWN';
    }

    /**
     * Get date filter SQL for period
     * 
     * @param string $period Period: today, week, month, all
     * @return string SQL date filter
     */
    private static function getDateFilter($period) {
        switch ($period) {
            case 'today':
                return "DATE(NOW())";
            case 'week':
                return "DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'all':
            default:
                return "'1970-01-01'";
        }
    }

    /**
     * Enable/disable logging
     * 
     * @param bool $enabled Enable flag
     * @return void
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }

}

?>
