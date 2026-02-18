<?php
require_once __DIR__ . '/config.php';

/**
 * Generate a random onboarding code
 */
function generateOnboardingCode($length = CODE_LENGTH) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    
    // Fix: Change the condition to avoid infinite loop
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

/**
 * Generate a secure random password
 */
function generateRandomPassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

/**
 * Format phone number to international format
 */
function formatPhoneNumber($number) {
    // Remove any spaces, dashes, or other characters
    $number = preg_replace('/[^0-9]/', '', $number);
    
    // Check if it's already in international format
    if (substr($number, 0, 2) == '27') {
        return '+' . $number;
    }
    
    // If it starts with 0, replace with +27 (South Africa)
    if (substr($number, 0, 1) == '0') {
        return '+27' . substr($number, 1);
    }
    
    // If it's a 9-digit number, assume it's South African
    if (strlen($number) == 9) {
        return '+27' . $number;
    }
    
    // Return with + prefix if none of the above
    return '+' . $number;
}

/**
 * Format MAC address to standard format (XX:XX:XX:XX:XX:XX)
 */
function formatMacAddress($mac) {
    // Remove any non-alphanumeric characters
    $mac = preg_replace('/[^a-fA-F0-9]/', '', $mac);
    
    if (strlen($mac) != 12) {
        return null; // Invalid MAC address length
    }
    
    // Format with colons
    return strtoupper(
        substr($mac, 0, 2) . ':' . 
        substr($mac, 2, 2) . ':' . 
        substr($mac, 4, 2) . ':' . 
        substr($mac, 6, 2) . ':' . 
        substr($mac, 8, 2) . ':' . 
        substr($mac, 10, 2)
    );
}

/**
 * Validate if a user is logged in as a manager
 */
function isManagerLoggedIn() {
    return isset($_SESSION['manager_id']);
}

/**
 * Require manager login or redirect
 */
function requireManagerLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php', 'Please login to access this page', 'warning');
    }
    
    if ($_SESSION['user_role'] !== 'manager') {
        redirect(BASE_URL . '/dashboard.php', 'You do not have permission to access this page', 'danger');
    }
}

/**
 * Validate if a user is logged in as an owner
 */
function isOwnerLoggedIn() {
    return isset($_SESSION['owner_id']);
}

/**
 * Require owner login or redirect
 */
function requireOwnerLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php', 'Please login to access this page', 'warning');
    }
    
    if ($_SESSION['user_role'] !== 'owner') {
        redirect(BASE_URL . '/dashboard.php', 'You do not have permission to access this page', 'danger');
    }
}

/**
 * Generate a random manager onboarding code
 */
function generateManagerCode($length = CODE_LENGTH) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = 'MGR';
    
    for ($i = 0; $i < $length - 3; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $code;
}

/**
 * Check if onboarding code is valid
 */
function validateOnboardingCode($code) {
    $conn = getDbConnection();
    
    $sql = "SELECT oc.*, a.name as accommodation_name 
            FROM onboarding_codes oc
            JOIN accommodations a ON oc.accommodation_id = a.id 
            WHERE oc.code = ? AND oc.status = 'unused' AND oc.expires_at > NOW()";
    
    $stmt = safeQueryPrepare($conn, $sql);
    if ($stmt === false) {
        return false;
    }
    
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return false;
}

/**
 * Send SMS message via Twilio REST API
 * Uses Messaging Service SID if available, falls back to phone number
 */
function sendSMS($number, $message) {
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
        error_log("Twilio SMS not configured. Message to $number: $message");
        return false;
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

    $data = [
        'To' => $number,
        'Body' => $message
    ];

    // For SMS, use From phone number directly (Messaging Service may not have SMS sender)
    if (!empty(TWILIO_PHONE_NUMBER)) {
        $data['From'] = TWILIO_PHONE_NUMBER;
    } elseif (!empty(TWILIO_MESSAGING_SERVICE_SID)) {
        $data['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
    } else {
        error_log("Twilio SMS: No From number or MessagingServiceSid configured");
        return false;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Twilio SMS cURL error: $error");
        return false;
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("SMS sent to $number. SID: " . ($result['sid'] ?? 'unknown'));
        return true;
    }

    error_log("Twilio SMS failed ($httpCode): " . ($result['message'] ?? $response));
    return false;
}

/**
 * Send WhatsApp message via Twilio REST API
 */
function sendWhatsApp($number, $message) {
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN) || empty(TWILIO_WHATSAPP_NO)) {
        error_log("Twilio WhatsApp not configured. Message to $number: $message");
        return false;
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

    $whatsappTo = (strpos($number, 'whatsapp:') === 0) ? $number : "whatsapp:$number";

    $data = [
        'To' => $whatsappTo,
        'From' => TWILIO_WHATSAPP_NO,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Twilio WhatsApp cURL error: $error");
        return false;
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("WhatsApp sent to $number. SID: " . ($result['sid'] ?? 'unknown'));
        return true;
    }

    error_log("Twilio WhatsApp failed ($httpCode): " . ($result['message'] ?? $response));
    return false;
}

/**
 * Route message to SMS or WhatsApp based on preferred method
 */
function sendMessage($number, $message, $method = 'SMS') {
    if ($method === 'WhatsApp') {
        return sendWhatsApp($number, $message);
    }
    return sendSMS($number, $message);
}

/**
 * Send WiFi voucher using Twilio Content Templates
 * Template variables: {{1}} = student name, {{2}} = month, {{3}} = voucher code
 * 
 * @param string $number Phone number
 * @param string $studentName Student full name
 * @param string $month Voucher month (e.g. "February")
 * @param string $voucherCode Voucher code
 * @param string $method 'SMS' or 'WhatsApp'
 * @return bool
 */
function sendVoucherMessage($number, $studentName, $month, $voucherCode, $method = 'SMS') {
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
        error_log("Twilio not configured for voucher send to $number");
        return false;
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

    // Pick template SID based on method
    $templateSid = ($method === 'WhatsApp') ? TWILIO_WA_VOUCHER_TEMPLATE_SID : TWILIO_SMS_VOUCHER_TEMPLATE_SID;

    if (empty($templateSid)) {
        // Fallback to plain text if no template configured
        $message = "Hi $studentName,\nBelow is your monthly WiFi Voucher code valid until {$month}'s month end, this code only grants you a max of 2 devices per month to be connected to the wifi.\n\nYour Voucher: $voucherCode";
        return sendMessage($number, $message, $method);
    }

    $data = [
        'ContentSid' => $templateSid,
        'ContentVariables' => json_encode([
            '1' => $studentName,
            '2' => $month,
            '3' => $voucherCode
        ])
    ];

    // Set To and From based on method
    if ($method === 'WhatsApp') {
        $data['To'] = (strpos($number, 'whatsapp:') === 0) ? $number : "whatsapp:$number";
        $data['From'] = TWILIO_WHATSAPP_NO;
    } else {
        $data['To'] = $number;
        // SMS: use From number directly (Messaging Service may not have SMS sender)
        $data['From'] = TWILIO_PHONE_NUMBER;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Twilio voucher cURL error: $error");
        return false;
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("Voucher sent to $number via $method. SID: " . ($result['sid'] ?? 'unknown'));
        return true;
    }

    error_log("Twilio voucher failed ($httpCode): " . ($result['message'] ?? $response));
    return false;
}

/**
 * Send login credentials using Twilio Content Template (SMS only)
 * Template variables: {{1}} = first name, {{2}} = username, {{3}} = temp password
 */
function sendCredentialsMessage($number, $firstName, $username, $tempPassword) {
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
        error_log("Twilio not configured for credentials send to $number");
        return false;
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";
    $templateSid = TWILIO_SMS_LOGIN_TEMPLATE_SID;

    if (empty($templateSid)) {
        $message = "Hello $firstName,\n\nHere are your login details for the WiFi Portal:\n\nUsername: $username\nTemporary Password: $tempPassword\n\nPlease login and change your password immediately.\n\n- WiFi Management Team";
        return sendSMS($number, $message);
    }

    $data = [
        'ContentSid' => $templateSid,
        'ContentVariables' => json_encode([
            '1' => $firstName,
            '2' => $username,
            '3' => $tempPassword
        ]),
        'To' => $number,
        'From' => TWILIO_PHONE_NUMBER
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Twilio credentials cURL error: $error");
        return false;
    }

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("Credentials sent to $number via SMS. SID: " . ($result['sid'] ?? 'unknown'));
        return true;
    }

    error_log("Twilio credentials failed ($httpCode): " . ($result['message'] ?? $response));
    return false;
}

/**
 * Safely prepares an SQL statement with improved error handling
 */
function safeQueryPrepare($conn, $sql, $debug = true) {
    // Ensure connection is valid
    if ($conn === null) {
        $conn = getDbConnection();
    }
    
    $stmt = false;
    $prepareError = '';
    try {
        $stmt = $conn->prepare($sql);
    } catch (mysqli_sql_exception $e) {
        $prepareError = "(" . $e->getCode() . ") " . $e->getMessage();
        error_log("Database exception in SQL: $sql - " . $prepareError);
    }
    
    if ($stmt === false) {
        // Log the error regardless
        $dbError = $prepareError !== '' ? $prepareError : "(" . $conn->errno . ") " . $conn->error;
        error_log("Database error in SQL: $sql - " . $dbError);
        
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($debug && ($remoteAddr === '127.0.0.1' || $remoteAddr === '::1')) {
            // Only show detailed errors in local development
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            $file = $trace[0]['file'] ?? 'unknown';
            $line = $trace[0]['line'] ?? 'unknown';
            
            echo "
                <div style='background:#f8d7da; color:#721c24; padding:10px; margin:10px 0; border-radius:5px;'>
                    <h3>Database Error</h3>
                    <p><strong>Prepare statement failed:</strong> " . htmlspecialchars($dbError) . "</p>
                    <p><strong>SQL:</strong> " . htmlspecialchars($sql) . "</p>
                    <p><strong>File:</strong> " . $file . " on line " . $line . "</p>
                </div>
            ";
        }
        
        // Return false instead of showing an error message
        return false;
    }
    
    return $stmt;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user's role
 */
function getUserRole() {
    if (!isLoggedIn()) return null;
    return $_SESSION['user_role'] ?? null;
}

/**
 * Check if user has a specific role
 */
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles) {
    if (!isLoggedIn()) return false;
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Require login with specific role(s)
 */
function requireRole($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php', 'Please login to access this page', 'warning');
        exit;
    }
    
    if (!hasAnyRole($roles)) {
        redirect(BASE_URL . '/index.php', 'You do not have permission to access this page', 'danger');
        exit;
    }
}

/**
 * Get user details
 */
function getUserDetails($userId = null) {
    $conn = getDbConnection();
    $id = $userId ?? $_SESSION['user_id'];
    
    $stmt = safeQueryPrepare($conn, "SELECT u.*, r.name as role_name FROM users u 
                             JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Generate appropriate dashboard URL based on user role
 */
function getDashboardUrl() {
    if (!isLoggedIn()) return BASE_URL . '/login.php';
    
    // Return role-specific dashboard URLs
    switch ($_SESSION['user_role']) {
        case 'admin':
            return BASE_URL . '/admin/dashboard.php';
        default:
            return BASE_URL . '/dashboard.php';
    }
}

/**
 * Generate a unique code with built-in validation
 * Reused from admin/create-code.php for consistency
 */
if (!function_exists('generateUniqueCode')) {
    function generateUniqueCode($length = 8) {
        // Exclude potentially confusing characters (0, O, 1, I)
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }
}

/**
 * Require login or redirect
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php', 'Please login to access this page', 'warning');
    }
}

/**
 * Require admin login or redirect
 */
function requireAdminLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php', 'Please login to access this page', 'warning');
    }
    
    if ($_SESSION['user_role'] !== 'admin') {
        redirect(BASE_URL . '/dashboard.php', 'You do not have permission to access this page', 'danger');
    }
}

/**
 * Require student login or redirect
 */
function requireStudentLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php', 'Please login to access this page', 'warning');
    }
    
    if ($_SESSION['user_role'] !== 'student') {
        redirect(BASE_URL . '/dashboard.php', 'You do not have permission to access this page', 'danger');
    }
}

/**
 * Log user activity
 */
function logActivity($conn, $userId, $action, $details, $ipAddress = '') {
    // Ensure connection is valid
    if ($conn === null) {
        $conn = getDbConnection();
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ipAddress = $ipAddress ?: $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = safeQueryPrepare($conn, "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, timestamp) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("isssss", $userId, $action, $details, $ipAddress, $userAgent, $timestamp);
        return $stmt->execute();
    }
    
    return false;
}

/**
 * Check if user has too many failed login attempts
 */
function checkLoginThrottle($username) {
    // Initialize attempts tracking if it doesn't exist
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // Clean up old attempts (older than 15 minutes)
    $now = time();
    foreach ($_SESSION['login_attempts'] as $user => $attempts) {
        foreach ($attempts as $index => $timestamp) {
            if ($now - $timestamp > 900) { // 15 minutes = 900 seconds
                unset($_SESSION['login_attempts'][$user][$index]);
            }
        }
        
        // Remove empty arrays
        if (empty($_SESSION['login_attempts'][$user])) {
            unset($_SESSION['login_attempts'][$user]);
        }
    }
    
    // Check if this username has too many attempts
    if (isset($_SESSION['login_attempts'][$username]) && count($_SESSION['login_attempts'][$username]) >= 5) {
        return true; // Too many attempts
    }
    
    return false;
}

/**
 * Record a failed login attempt
 */
function recordFailedLogin($username) {
    // Initialize attempts tracking if it doesn't exist
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // Initialize array for this username if it doesn't exist
    if (!isset($_SESSION['login_attempts'][$username])) {
        $_SESSION['login_attempts'][$username] = [];
    }
    
    // Add current timestamp
    $_SESSION['login_attempts'][$username][] = time();
}

/**
 * Reset login attempts for a username after successful login
 */
function resetLoginAttempts($username) {
    if (isset($_SESSION['login_attempts'][$username])) {
        unset($_SESSION['login_attempts'][$username]);
    }
}

/**
 * Regenerate session ID on login or privilege change
 * Prevents session fixation attacks
 * 
 * @return void
 */
function regenerateSessionOnLogin() {
    // Regenerate session ID and delete old session
    session_regenerate_id(true);
    
    // Reset session timing markers
    $_SESSION['CREATED'] = time();
    $_SESSION['LAST_ACTIVITY'] = time();
}

/**
 * Regenerate session on privilege/role change
 * Should be called when user role is elevated or changed
 * 
 * @return void
 */
function regenerateSessionOnPrivilegeChange() {
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}

/**
 * Get the appropriate Bootstrap badge class for a user role
 * @param string $role The user role name
 * @return string The CSS class for the badge
 */
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin': return 'bg-danger';
        case 'owner': return 'bg-info';
        case 'manager': return 'bg-primary';
        case 'student': return 'bg-success';
        default: return 'bg-secondary';
    }
}

/**
 * Creates a standardized password hash using PHP's recommended algorithm
 * 
 * @param string $password The password to hash
 * @return string The password hash
 */
function createPasswordHash($password) {
    // Use PASSWORD_DEFAULT to ensure the strongest algorithm is used
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifies a password against a hash
 * 
 * @param string $password The password to verify
 * @param string $hash The hash to verify against
 * @return bool True if the password matches the hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Checks if a password hash needs to be updated
 * 
 * @param string $hash The current hash
 * @return bool True if the hash needs to be updated
 */
function passwordNeedsRehash($hash) {
    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

// ============================================================================
// INPUT VALIDATION HELPERS (M1-T5)
// ============================================================================

/**
 * Validate email address
 * 
 * @param string $email The email to validate
 * @return bool True if valid email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (South African format)
 * Accepts: 0821234567, +27821234567, 27821234567
 * 
 * @param string $phone The phone number to validate
 * @return bool True if valid phone
 */
function validatePhone($phone) {
    // Remove all non-numeric except + at start
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    
    // Check for valid SA formats
    if (preg_match('/^0[678]\d{8}$/', $cleaned)) {
        return true; // 0XX XXX XXXX
    }
    if (preg_match('/^\+?27[678]\d{8}$/', $cleaned)) {
        return true; // +27 or 27 format
    }
    
    return false;
}

/**
 * Sanitize input based on type
 * 
 * @param mixed $input The input to sanitize
 * @param string $type Type: 'string', 'int', 'email', 'phone', 'alphanumeric', 'html'
 * @param int|null $maxLength Maximum length for strings (null for no limit)
 * @return mixed Sanitized value
 */
function sanitizeInput($input, $type = 'string', $maxLength = null) {
    if ($input === null) {
        return '';
    }
    
    switch ($type) {
        case 'int':
            return (int)$input;
            
        case 'float':
            return (float)$input;
            
        case 'email':
            $input = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            break;
            
        case 'phone':
            // Keep only digits and + sign
            $input = preg_replace('/[^0-9+]/', '', $input);
            break;
            
        case 'alphanumeric':
            $input = preg_replace('/[^a-zA-Z0-9]/', '', $input);
            break;
            
        case 'html':
            // Strip all HTML tags
            $input = strip_tags(trim($input));
            break;
            
        case 'string':
        default:
            $input = trim($input);
            break;
    }
    
    // Apply max length if specified
    if ($maxLength !== null && is_string($input)) {
        $input = mb_substr($input, 0, $maxLength);
    }
    
    return $input;
}

/**
 * Validate that a string doesn't exceed max length
 * 
 * @param string $value The value to check
 * @param int $maxLength Maximum allowed length
 * @return bool True if within limit
 */
function validateMaxLength($value, $maxLength) {
    return mb_strlen($value) <= $maxLength;
}

/**
 * Validate required fields are not empty
 * 
 * @param array $fields Associative array of field_name => value
 * @return array Array of missing field names
 */
function validateRequired($fields) {
    $missing = [];
    foreach ($fields as $name => $value) {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            $missing[] = $name;
        }
    }
    return $missing;
}

// ============================================================================
// OUTPUT ESCAPING HELPERS (M1-T6)
// ============================================================================

/**
 * Escape string for HTML output (XSS prevention)
 * Shorthand alias: e()
 * 
 * @param string|null $string The string to escape
 * @return string Escaped string safe for HTML output
 */
function htmlEscape($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Shorthand alias for htmlEscape()
 * 
 * @param string|null $string The string to escape
 * @return string Escaped string safe for HTML output
 */
function e($string) {
    return htmlEscape($string);
}

/**
 * Escape for JavaScript string context
 * 
 * @param string $string The string to escape
 * @return string Escaped string safe for JS
 */
function jsEscape($string) {
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

/**
 * Escape for URL parameter
 *
 * @param string $string The string to escape
 * @return string URL-encoded string
 */
function urlEscape($string) {
    return urlencode($string);
}

// ============================================================================
// VOUCHER MANAGEMENT FUNCTIONS (M2-T3)
// ============================================================================

/**
 * Revoke a voucher
 * 
 * @param int $voucher_id The voucher ID to revoke
 * @param string $reason Reason for revoking
 * @param int $revoked_by_user_id User ID who is revoking
 * @return bool True if revoked successfully, false otherwise
 */
function revokeVoucher($voucher_id, $reason, $revoked_by_user_id) {
    $conn = getDbConnection();
    
    $sql = "UPDATE voucher_logs 
            SET is_active = 0, 
                revoked_at = NOW(), 
                revoked_by = ?, 
                revoke_reason = ?
            WHERE id = ? AND is_active = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $revoked_by_user_id, $reason, $voucher_id);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

/**
 * Notification System Functions for M2-T4
 */

/**
 * Create a notification for a user
 * 
 * @param int $recipient_id User ID to notify
 * @param string $message Notification message
 * @param string $type Type: info, success, warning, danger
 * @param int|null $sender_id Sender user ID (defaults to current user or system)
 * @return bool True on success, false on failure
 */
function createNotification($recipient_id, $message, $type = 'info', $sender_id = null) {
    $conn = getDbConnection();
    
    if (!$conn) {
        return false;
    }

    // Check user preferences
    $pref_column = null;
    switch ($type) {
        case 'device_request':
            $pref_column = 'notify_device_requests';
            break;
        case 'device_status':
        case 'device_approval':
        case 'device_rejection':
            $pref_column = 'notify_device_status';
            break;
        case 'voucher':
            $pref_column = 'notify_vouchers';
            break;
        case 'new_student':
            $pref_column = 'notify_new_students';
            break;
    }
    
    if ($pref_column) {
        $stmt_pref = safeQueryPrepare($conn, "SELECT $pref_column FROM user_preferences WHERE user_id = ?");
        $stmt_pref->bind_param("i", $recipient_id);
        $stmt_pref->execute();
        $res_pref = $stmt_pref->get_result();
        
        if ($res_pref->num_rows > 0) {
            $pref = $res_pref->fetch_assoc();
            // If preference is 0 (false), do not create notification
            if (isset($pref[$pref_column]) && $pref[$pref_column] == 0) {
                return true; // Successfully decided not to notify
            }
        }
        // If no row exists, defaults are TRUE, so we proceed
    }
    
    // Default sender to current user or system user (ID 1)
    if ($sender_id === null) {
        $sender_id = $_SESSION['user_id'] ?? 1;
    }
    
    // Insert notification - schema has: recipient_id, sender_id, message, type, read_status
    $stmt = safeQueryPrepare($conn, 
        "INSERT INTO notifications (recipient_id, sender_id, message, type, read_status) 
         VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("iiss", $recipient_id, $sender_id, $message, $type);
    
    return $stmt->execute();
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id User ID
 * @return int Unread count
 */
function getUnreadNotificationCount($user_id) {
    $conn = getDbConnection();
    
    if (!$conn) {
        return 0;
    }
    
    $stmt = safeQueryPrepare($conn, "SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND read_status = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return (int)($result['count'] ?? 0);
}

/**
 * Get recent notifications for a user
 * 
 * @param int $user_id User ID
 * @param int $limit Maximum number of notifications
 * @return array Array of notifications with normalized field names
 */
function getRecentNotifications($user_id, $limit = 10) {
    $conn = getDbConnection();
    
    if (!$conn) {
        return [];
    }
    
    $stmt = safeQueryPrepare($conn, 
        "SELECT n.*, n.read_status as is_read, u.first_name, u.last_name 
         FROM notifications n
         LEFT JOIN users u ON n.sender_id = u.id
         WHERE n.recipient_id = ? 
         ORDER BY n.created_at DESC 
         LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Mark a notification as read
 * 
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security check)
 * @return bool True on success
 */
function markNotificationAsRead($notification_id, $user_id) {
    $conn = getDbConnection();
    
    if (!$conn) {
        return false;
    }
    
    $stmt = safeQueryPrepare($conn, 
        "UPDATE notifications 
         SET read_status = 1 
         WHERE id = ? AND recipient_id = ? AND read_status = 0");
    $stmt->bind_param("ii", $notification_id, $user_id);
    
    return $stmt->execute();
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id User ID
 * @return bool True on success
 */
function markAllNotificationsAsRead($user_id) {
    $conn = getDbConnection();
    
    if (!$conn) {
        return false;
    }
    
    $stmt = safeQueryPrepare($conn, 
        "UPDATE notifications 
         SET read_status = 1 
         WHERE recipient_id = ? AND read_status = 0");
    $stmt->bind_param("i", $user_id);
    
    return $stmt->execute();
}

/**
 * Get time ago string (e.g., "5 minutes ago", "2 hours ago")
 * 
 * @param string $datetime DateTime string
 * @return string Human-readable time ago
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'just now';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 2592000) {
        $weeks = floor($difference / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}
