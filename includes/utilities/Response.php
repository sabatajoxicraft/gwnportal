<?php
/**
 * Response Utility Class - Standardized HTTP Response Handling
 * 
 * Provides consistent JSON response formatting for API endpoints and AJAX requests.
 * All API endpoints should use this class for responses.
 * 
 * Usage: 
 * - Response::json(['data' => $value], 200);
 * - Response::error('User not found', 404);
 * - Response::success('Operation completed');
 */

class Response {

    // HTTP status codes
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_ACCEPTED = 202;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_CONFLICT = 409;
    const HTTP_INTERNAL_ERROR = 500;
    const HTTP_SERVICE_UNAVAILABLE = 503;

    /**
     * Send JSON response
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code (default: 200)
     * @param array $headers Additional headers
     * @return void (kills execution after sending response)
     */
    public static function json($data = [], $statusCode = 200, $headers = []) {
        self::setStatusCode($statusCode);
        self::setContentType('application/json');
        
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send success JSON response
     * 
     * @param string|array $message Success message or data
     * @param array $data Additional data to return
     * @param int $statusCode HTTP status code (default: 200)
     * @return void
     */
    public static function success($message = 'Success', $data = [], $statusCode = 200) {
        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send error JSON response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default: 400)
     * @param array $errors Additional error details (optional)
     * @return void
     */
    public static function error($message = 'Error', $statusCode = 400, $errors = []) {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send validation error response
     * 
     * @param array $errors Field validation errors (field => message)
     * @param string $message Optional custom message
     * @return void
     */
    public static function validationError($errors = [], $message = 'Validation failed') {
        $response = [
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ];

        self::json($response, self::HTTP_BAD_REQUEST);
    }

    /**
     * Send unauthorized response
     * 
     * @param string $message Error message
     * @return void
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, self::HTTP_UNAUTHORIZED);
    }

    /**
     * Send forbidden response
     * 
     * @param string $message Error message
     * @return void
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, self::HTTP_FORBIDDEN);
    }

    /**
     * Send not found response
     * 
     * @param string $message Error message
     * @return void
     */
    public static function notFound($message = 'Not found') {
        self::error($message, self::HTTP_NOT_FOUND);
    }

    /**
     * Send conflict response (duplicate resource, etc.)
     * 
     * @param string $message Error message
     * @param array $data Additional data
     * @return void
     */
    public static function conflict($message = 'Resource already exists', $data = []) {
        self::error($message, self::HTTP_CONFLICT, $data);
    }

    /**
     * Send server error response
     * 
     * @param string $message Error message
     * @return void
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, self::HTTP_INTERNAL_ERROR);
    }

    /**
     * Send paginated response
     * 
     * @param array $data Data items
     * @param int $total Total items count
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @param string $message Optional message
     * @return void
     */
    public static function paginated($data = [], $total = 0, $page = 1, $perPage = 20) {
        $lastPage = ceil($total / $perPage);
        
        $response = [
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total)
            ]
        ];

        self::json($response);
    }

    /**
     * Download file response
     * 
     * @param string $filePath Path to file
     * @param string $filename Filename to send to client
     * @return void
     */
    public static function download($filePath, $filename = null) {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
        }

        if ($filename === null) {
            $filename = basename($filePath);
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        
        readfile($filePath);
        exit;
    }

    /**
     * Redirect to URL with optional message
     * 
     * @param string $url Target URL
     * @param string $message Optional success/error message
     * @param string $messageType Message type: success, warning, danger, info
     * @return void
     */
    public static function redirect($url, $message = '', $messageType = 'info') {
        if (!empty($message)) {
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = $messageType;
        }

        header('Location: ' . $url);
        exit;
    }

    /**
     * Set HTTP status code
     * 
     * @param int $code HTTP status code
     * @return void
     */
    private static function setStatusCode($code) {
        http_response_code($code);
    }

    /**
     * Set content type header
     * 
     * @param string $type Content type (e.g., 'application/json')
     * @return void
     */
    private static function setContentType($type) {
        header('Content-Type: ' . $type . '; charset=utf-8');
    }

}

?>
