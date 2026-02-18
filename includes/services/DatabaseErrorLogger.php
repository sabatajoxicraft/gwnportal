<?php
/**
 * Database Error Logger - Log Errors to Database for Querying & Analysis
 * 
 * Extends error logging capabilities to include database storage for better
 * error analysis, pattern detection, and historical tracking.
 */

class DatabaseErrorLogger {

    private static $conn = null;
    private static $enabled = true;
    
    // Error severity levels
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Initialize database error logging
     * 
     * @param mysqli $connection Database connection
     * @return bool Success status
     */
    public static function init($connection) {
        self::$conn = $connection;
        return self::createErrorTable();
    }

    /**
     * Create error log table if not exists
     * 
     * @return bool Success status
     */
    private static function createErrorTable() {
        if (!self::$conn) {
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS error_logs (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            error_type VARCHAR(100) NOT NULL,
            severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'error',
            message TEXT NOT NULL,
            context JSON,
            stack_trace LONGTEXT,
            file_path VARCHAR(255),
            line_number INT,
            user_id INT,
            ip_address VARCHAR(45),
            url VARCHAR(512),
            method VARCHAR(10),
            request_data JSON,
            response_code INT,
            resolved BOOLEAN DEFAULT FALSE,
            resolved_at TIMESTAMP NULL,
            resolved_by INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_error_type (error_type),
            INDEX idx_severity (severity),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_resolved (resolved)
        )";

        if (!self::$conn->query($sql)) {
            error_log("DatabaseErrorLogger::init - Error creating table: " . self::$conn->error);
            return false;
        }

        return true;
    }

    /**
     * Log error to database
     * 
     * @param string $errorType Error type (Database, API, Validation, etc.)
     * @param string $message Error message
     * @param string $severity Error severity (info, warning, error, critical)
     * @param array $context Additional context
     * @return int Error log ID or 0 on failure
     */
    public static function log($errorType, $message, $severity = self::SEVERITY_ERROR, $context = []) {
        if (!self::$enabled || !self::$conn) {
            return 0;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $ipAddress = self::getClientIpAddress();
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $contextJson = json_encode($context);
        $responseCode = http_response_code() ?: 200;

        // Get stack trace
        $stackTrace = '';
        if ($severity === self::SEVERITY_CRITICAL || $severity === self::SEVERITY_ERROR) {
            $stackTrace = self::getStackTrace();
        }

        $stmt = self::$conn->prepare("
            INSERT INTO error_logs 
            (error_type, severity, message, context, stack_trace, user_id, ip_address, url, method, response_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("DatabaseErrorLogger::log - Prepare error: " . self::$conn->error);
            return 0;
        }

        $stmt->bind_param(
            "ssssssssi",
            $errorType,
            $severity,
            $message,
            $contextJson,
            $stackTrace,
            $userId,
            $ipAddress,
            $url,
            $method,
            $responseCode
        );

        if (!$stmt->execute()) {
            error_log("DatabaseErrorLogger::log - Execute error: " . $stmt->error);
            $stmt->close();
            return 0;
        }

        $errorId = $stmt->insert_id;
        $stmt->close();

        // Log to file as well
        ErrorHandler::logError($errorType, $message, array_merge($context, ['error_log_id' => $errorId]));

        return $errorId;
    }

    /**
     * Log HTTP error response
     * 
     * @param int $statusCode HTTP status code
     * @param string $message Error message
     * @param array $data Response data (optional)
     * @return int Error log ID
     */
    public static function logHttpError($statusCode, $message, $data = []) {
        $severity = self::SEVERITY_ERROR;

        if ($statusCode >= 500) {
            $severity = self::SEVERITY_CRITICAL;
        } elseif ($statusCode >= 400) {
            $severity = self::SEVERITY_WARNING;
        }

        return self::log("HTTP Error ($statusCode)", $message, $severity, $data);
    }

    /**
     * Get error by ID
     * 
     * @param int $errorId Error ID
     * @return array Error data or empty array
     */
    public static function getError($errorId) {
        if (!self::$conn) {
            return [];
        }

        $stmt = self::$conn->prepare("SELECT * FROM error_logs WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $errorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $error = $result->fetch_assoc() ?? [];
        $stmt->close();

        return $error;
    }

    /**
     * Get recent errors
     * 
     * @param int $limit Number of errors
     * @param string $severity Filter by severity (optional)
     * @return array Error records
     */
    public static function getRecentErrors($limit = 30, $severity = null) {
        if (!self::$conn) {
            return [];
        }

        $severityFilter = $severity ? "AND severity = '$severity'" : '';

        $result = self::$conn->query("
            SELECT * FROM error_logs
            WHERE resolved = FALSE $severityFilter
            ORDER BY created_at DESC
            LIMIT $limit
        ");

        if (!$result) {
            return [];
        }

        $errors = [];
        while ($row = $result->fetch_assoc()) {
            $errors[] = $row;
        }

        return $errors;
    }

    /**
     * Get error statistics
     * 
     * @param string $period Period: today, week, month, all
     * @return array Statistics
     */
    public static function getErrorStats($period = 'today') {
        if (!self::$conn) {
            return [];
        }

        $dateFilter = self::getDateFilter($period);

        $result = self::$conn->query("
            SELECT 
                COUNT(*) as total_errors,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning_count,
                SUM(CASE WHEN resolved = FALSE THEN 1 ELSE 0 END) as unresolved_count,
                COUNT(DISTINCT error_type) as error_types
            FROM error_logs
            WHERE created_at >= $dateFilter
        ");

        if (!$result) {
            return [];
        }

        return $result->fetch_assoc() ?? [];
    }

    /**
     * Get errors by type
     * 
     * @param string $errorType Error type
     * @param int $limit Number of errors
     * @return array Error records
     */
    public static function getErrorsByType($errorType, $limit = 20) {
        if (!self::$conn) {
            return [];
        }

        $stmt = self::$conn->prepare("
            SELECT * FROM error_logs
            WHERE error_type = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("si", $errorType, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $errors = [];
        while ($row = $result->fetch_assoc()) {
            $errors[] = $row;
        }

        $stmt->close();
        return $errors;
    }

    /**
     * Mark error as resolved
     * 
     * @param int $errorId Error ID
     * @param int $resolvedBy Admin user ID
     * @param string $notes Resolution notes (optional)
     * @return bool Success status
     */
    public static function markResolved($errorId, $resolvedBy, $notes = '') {
        if (!self::$conn) {
            return false;
        }

        $stmt = self::$conn->prepare("
            UPDATE error_logs
            SET resolved = TRUE, resolved_at = NOW(), resolved_by = ?, notes = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("isi", $resolvedBy, $notes, $errorId);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $stmt->close();
        return true;
    }

    /**
     * Get unresolved errors count
     * 
     * @return int Count of unresolved errors
     */
    public static function getUnresolvedErrorsCount() {
        if (!self::$conn) {
            return 0;
        }

        $result = self::$conn->query("SELECT COUNT(*) as count FROM error_logs WHERE resolved = FALSE");
        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)$row['count'];
    }

    /**
     * Clean up old resolved errors
     * 
     * @param int $daysOld Delete errors older than this many days
     * @return int Number of deleted records
     */
    public static function cleanupOldErrors($daysOld = 90) {
        if (!self::$conn) {
            return 0;
        }

        $result = self::$conn->query("
            DELETE FROM error_logs 
            WHERE resolved = TRUE 
            AND created_at < DATE_SUB(NOW(), INTERVAL $daysOld DAY)
        ");

        if (!$result) {
            return 0;
        }

        return self::$conn->affected_rows;
    }

    /**
     * Get stack trace
     * 
     * @return string Stack trace string
     */
    private static function getStackTrace() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        
        $traceString = '';
        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? '?';
            $function = $frame['function'] ?? 'unknown';
            $class = $frame['class'] ?? '';
            
            $traceString .= "#$index $file($line): ";
            if ($class) {
                $traceString .= $class . '->' . $function . '()';
            } else {
                $traceString .= $function . '()';
            }
            $traceString .= "\n";
        }

        return $traceString;
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
     * Enable/disable database logging
     * 
     * @param bool $enabled Enable flag
     * @return void
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }

}

?>
