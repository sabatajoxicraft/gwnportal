<?php
/**
 * Error Handler Class - Centralized Error Handling & Logging
 * 
 * Standardizes error handling throughout the application. All errors, warnings,
 * and exceptions should be processed through this class.
 * 
 * Usage:
 * - ErrorHandler::handle(new Exception('Error message'));
 * - ErrorHandler::logError('Database error', $conn->error);
 * - ErrorHandler::getLastError();
 */

class ErrorHandler {

    // Error log storage
    private static $errors = [];
    
    // Production vs development mode
    private static $productionMode = true;
    
    // Error log file
    private static $logFile = '';

    /**
     * Initialize error handler
     * 
     * @param bool $production Production mode flag
     * @param string $logPath Path to error log file
     * @return void
     */
    public static function init($production = true, $logPath = '') {
        self::$productionMode = $production;
        
        if (!empty($logPath)) {
            self::$logFile = $logPath;
        }

        // Set custom error handler
        set_error_handler([self::class, 'handlePhpError']);
        
        // Set custom exception handler
        set_exception_handler([self::class, 'handleException']);
    }

    /**
     * Handle PHP errors
     * 
     * @param int $level Error level
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number
     * @return void
     */
    public static function handlePhpError($level, $message, $file, $line) {
        $error = [
            'type' => 'PHP Error',
            'level' => $level,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'trace' => debug_backtrace()
        ];

        self::log($error);

        // In development, display error; in production, log silently
        if (!self::$productionMode) {
            echo '<pre>';
            echo 'PHP Error: ' . $message . PHP_EOL;
            echo 'File: ' . $file . ':' . $line . PHP_EOL;
            echo '</pre>';
        }
    }

    /**
     * Handle uncaught exceptions
     * 
     * @param Throwable $exception Exception object
     * @return void
     */
    public static function handleException($exception) {
        $error = [
            'type' => 'Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'timestamp' => date('Y-m-d H:i:s'),
            'trace' => $exception->getTrace()
        ];

        self::log($error);

        // In development, display exception; in production, show generic error
        if (!self::$productionMode) {
            echo '<pre>';
            echo 'Exception: ' . $exception->getMessage() . PHP_EOL;
            echo 'File: ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL;
            echo 'Trace: ' . PHP_EOL . $exception->getTraceAsString();
            echo '</pre>';
            exit;
        } else {
            // Return 500 error page
            http_response_code(500);
            echo 'An error occurred. Please try again later.';
            exit;
        }
    }

    /**
     * Log database error
     * 
     * @param string $operation Operation name (e.g., "UserService::create")
     * @param string $errorMessage Database error message
     * @param string $query SQL query (optional)
     * @return void
     */
    public static function logDatabaseError($operation, $errorMessage, $query = '') {
        $error = [
            'type' => 'Database Error',
            'operation' => $operation,
            'error' => $errorMessage,
            'query' => $query,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        self::log($error);
    }

    /**
     * Log validation error
     * 
     * @param string $context Where validation occurred (e.g., "UserService::create")
     * @param array $errors Field => message array
     * @return void
     */
    public static function logValidationError($context, $errors) {
        $error = [
            'type' => 'Validation Error',
            'context' => $context,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        self::log($error);
    }

    /**
     * Log custom application error
     * 
     * @param string $type Error type
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public static function logError($type, $message, $context = []) {
        $error = [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ];

        self::log($error);
    }

    /**
     * Log application event
     * 
     * @param string $event Event name
     * @param array $details Event details
     * @return void
     */
    public static function logEvent($event, $details = []) {
        $entry = [
            'type' => 'Event',
            'event' => $event,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null
        ];

        self::log($entry);
    }

    /**
     * Store error in memory and write to log file
     * 
     * @param array $error Error data
     * @return void
     */
    private static function log($error) {
        // Store in memory
        self::$errors[] = $error;

        // Write to file
        self::writeToFile($error);
    }

    /**
     * Write error to log file
     * 
     * @param array $error Error data
     * @return void
     */
    private static function writeToFile($error) {
        if (empty(self::$logFile)) {
            error_log(self::formatError($error));
            return;
        }

        $logDir = dirname(self::$logFile);
        
        // Create logs directory if needed
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logMessage = self::formatError($error) . PHP_EOL . str_repeat('=', 80) . PHP_EOL;

        if (!is_dir($logDir) || !is_writable($logDir)) {
            error_log($logMessage);
            return;
        }

        if (file_exists(self::$logFile) && !is_writable(self::$logFile)) {
            error_log($logMessage);
            return;
        }

        error_log($logMessage, 3, self::$logFile);
    }

    /**
     * Format error for logging
     * 
     * @param array $error Error data
     * @return string Formatted error string
     */
    private static function formatError($error) {
        $message = '';
        $message .= '[' . ($error['timestamp'] ?? date('Y-m-d H:i:s')) . '] ';
        $message .= ($error['type'] ?? 'Unknown') . ': ';
        $message .= ($error['message'] ?? '') . PHP_EOL;

        if (!empty($error['file'])) {
            $message .= 'File: ' . $error['file'] . ':' . ($error['line'] ?? '?') . PHP_EOL;
        }

        if (!empty($error['user_id'])) {
            $message .= 'User: ' . $error['user_id'] . PHP_EOL;
        }

        if (!empty($error['url'])) {
            $message .= 'URL: ' . $error['url'] . PHP_EOL;
        }

        if (!empty($error['query'])) {
            $message .= 'Query: ' . $error['query'] . PHP_EOL;
        }

        if (!empty($error['errors']) || !empty($error['context'])) {
            $message .= 'Details: ' . json_encode($error['errors'] ?? $error['context']) . PHP_EOL;
        }

        return $message;
    }

    /**
     * Get all logged errors
     * 
     * @return array Array of errors
     */
    public static function getErrors() {
        return self::$errors;
    }

    /**
     * Get last logged error
     * 
     * @return array Last error or empty array
     */
    public static function getLastError() {
        return !empty(self::$errors) ? end(self::$errors) : [];
    }

    /**
     * Get errors by type
     * 
     * @param string $type Error type to filter
     * @return array Filtered errors
     */
    public static function getErrorsByType($type) {
        return array_filter(self::$errors, function($error) use ($type) {
            return ($error['type'] ?? '') === $type;
        });
    }

    /**
     * Clear error log
     * 
     * @return void
     */
    public static function clear() {
        self::$errors = [];
    }

}

?>
