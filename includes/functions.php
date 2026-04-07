<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/TwilioService.php';

/**
 * Parse latitude/longitude from a Google Maps URL.
 *
 * Priority:
 *  1. !3d<lat>!4d<lng>  – pinned-place coordinates (most accurate for Street View)
 *  2. @<lat>,<lng>       – viewport centre
 *  3. ?q=               – query parameter fallback
 *
 * Shortened goo.gl / maps.app.goo.gl URLs are expanded via a HEAD request first.
 *
 * @param  string $map_url  The raw URL stored for the accommodation.
 * @param  string $map_host Lower-cased host extracted from $map_url.
 * @return array{lat: string|null, lng: string|null, map_query: string, parse_url: string}
 */
function parseGoogleMapsCoords(string $map_url, string $map_host): array
{
    $parse_url = $map_url;

    // Expand shortened URLs (maps.app.goo.gl / goo.gl) via a HEAD request.
    if (($map_host === 'maps.app.goo.gl' || $map_host === 'goo.gl') && function_exists('curl_init')) {
        $ch = curl_init($map_url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_exec($ch);
        $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        if (!empty($effective) && $effective !== $map_url) {
            $parse_url = $effective;
        }
    }

    $lat       = null;
    $lng       = null;
    $map_query = $parse_url;

    // 1. Prefer place/pin coordinates embedded as !3d<lat>!4d<lng> – these point
    //    to the actual location, not the viewport centre.
    if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $parse_url, $m)) {
        $lat       = $m[1];
        $lng       = $m[2];
        $map_query = $lat . ',' . $lng;
    // 2. Fall back to viewport centre (@lat,lng) only when place coords are absent.
    } elseif (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $parse_url, $m)) {
        $lat       = $m[1];
        $lng       = $m[2];
        $map_query = $lat . ',' . $lng;
    // 3. Last resort: ?q= query parameter.
    } else {
        $parts = parse_url($parse_url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            if (!empty($queryParams['q'])) {
                $map_query = (string)$queryParams['q'];
                if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $map_query, $cm)) {
                    $lat = $cm[1];
                    $lng = $cm[2];
                }
            }
        }
    }

    return ['lat' => $lat, 'lng' => $lng, 'map_query' => $map_query, 'parse_url' => $parse_url];
}

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
function sendSMS($number, $message, $auditCategory = null) {
    $result = TwilioService::sendSMS($number, $message);
    if ($auditCategory !== null) {
        CommunicationLogger::logSms((string)$number, (string)$auditCategory, (bool)$result);
    }
    return $result;
}

/**
 * Send WhatsApp message via Twilio REST API
 */
function sendWhatsApp($number, $message, $auditCategory = null) {
    $result = TwilioService::sendWhatsApp($number, $message);
    if ($auditCategory !== null) {
        CommunicationLogger::logWhatsApp((string)$number, (string)$auditCategory, (bool)$result);
    }
    return $result;
}

/**
 * Route message to SMS or WhatsApp based on preferred method
 */
function sendMessage($number, $message, $method = 'SMS', $auditCategory = null) {
    $result = TwilioService::sendMessage($number, $message, $method);
    if ($auditCategory !== null) {
        if (strtoupper((string)$method) === 'WHATSAPP') {
            CommunicationLogger::logWhatsApp((string)$number, (string)$auditCategory, (bool)$result);
        } else {
            CommunicationLogger::logSms((string)$number, (string)$auditCategory, (bool)$result);
        }
    }
    return $result;
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
    $result = TwilioService::sendVoucherMessage($number, $studentName, $month, $voucherCode, $method);
    if (strtoupper((string)$method) === 'WHATSAPP') {
        CommunicationLogger::logWhatsApp((string)$number, 'voucher', (bool)$result);
    } else {
        CommunicationLogger::logSms((string)$number, 'voucher', (bool)$result);
    }
    return $result;
}

/**
 * Send login credentials using Twilio Content Template (SMS only)
 * Template variables: {{1}} = first name, {{2}} = username, {{3}} = temp password
 */
function sendCredentialsMessage($number, $firstName, $username, $tempPassword) {
    $result = TwilioService::sendCredentialsMessage($number, $firstName, $username, $tempPassword);
    CommunicationLogger::logSms((string)$number, 'credentials', (bool)$result);
    return $result;
}

/**
 * Send invitation code using Twilio Content Template (SMS)
 * Template variables: {{1}} first name, {{2}} invitation code, {{3}} register URL, {{4}} expiry date
 * Falls back to plain SMS if TWILIO_SMS_INVITATION_TEMPLATE_SID is not set.
 */
function sendInvitationCodeMessage($number, $firstName, $invitationCode, $registerUrl, $expiryDate) {
    $result = TwilioService::sendInvitationCodeMessage($number, $firstName, $invitationCode, $registerUrl, $expiryDate);
    CommunicationLogger::logSms((string)$number, 'invitation_code', (bool)$result);
    return $result;
}

/**
 * Send invitation code via WhatsApp using Twilio Content Template only (no freeform).
 * Template variables: {{1}} first name, {{2}} invitation code, {{3}} register URL, {{4}} expiry date.
 * Uses TWILIO_WA_INVITATION_TEMPLATE_SID; falls back to TWILIO_SMS_INVITATION_TEMPLATE_SID.
 */
function sendInvitationCodeWhatsAppMessage($number, $firstName, $invitationCode, $registerUrl, $expiryDate) {
    $result = TwilioService::sendInvitationCodeWhatsAppMessage($number, $firstName, $invitationCode, $registerUrl, $expiryDate);
    CommunicationLogger::logWhatsApp((string)$number, 'invitation_code', (bool)$result);
    return $result;
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
        $stmt = new DummyStatement();
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
        
        // Return the DummyStatement so ->bind_param() calls won't fatal
        return $stmt;
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
    
    $tz = defined('ACTIVITY_LOG_STORAGE_TIMEZONE') ? ACTIVITY_LOG_STORAGE_TIMEZONE : 'UTC';
    $timestamp = (new DateTimeImmutable('now', new DateTimeZone($tz)))->format('Y-m-d H:i:s'); // always store UTC
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
    
    $stmt = safeQueryPrepare($conn, $sql);
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
 * @param string $type Type: info, success, warning, danger, device_request, device_approval, device_rejection, voucher, new_student
 * @param int|null $sender_id Sender user ID (defaults to current user or system)
 * @param string|null $category Routing category (maps to type if omitted)
 * @param int|null $related_id Optional related entity ID for click-through routing
 * @return bool True on success, false on failure
 */
function createNotification($recipient_id, $message, $type = 'info', $sender_id = null, $category = null, $related_id = null) {
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

    // Normalise category: use type as fallback so click-through routing always has a value
    if ($category === null) {
        $category = $type;
    }

    // Attempt INSERT with optional columns first; fall back to basic schema if columns absent
    $success = false;
    if ($related_id !== null) {
        $stmt = safeQueryPrepare($conn,
            "INSERT INTO notifications (recipient_id, sender_id, message, type, category, related_id, read_status)
             VALUES (?, ?, ?, ?, ?, ?, 0)", false);
        if ($stmt && !($stmt instanceof DummyStatement)) {
            $stmt->bind_param("iisssi", $recipient_id, $sender_id, $message, $type, $category, $related_id);
            $success = $stmt->execute();
        }
    } else {
        $stmt = safeQueryPrepare($conn,
            "INSERT INTO notifications (recipient_id, sender_id, message, type, category, read_status)
             VALUES (?, ?, ?, ?, ?, 0)", false);
        if ($stmt && !($stmt instanceof DummyStatement)) {
            $stmt->bind_param("iisss", $recipient_id, $sender_id, $message, $type, $category);
            $success = $stmt->execute();
        }
    }

    // Fallback to basic schema (no category/related_id columns yet)
    if (!$success) {
        $stmt = safeQueryPrepare($conn,
            "INSERT INTO notifications (recipient_id, sender_id, message, type, read_status)
             VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("iiss", $recipient_id, $sender_id, $message, $type);
        $success = $stmt->execute();
    }

    CommunicationLogger::logInApp((int)$recipient_id, (string)$type, (string)$message, $success, (int)$sender_id ?: null);
    return $success;
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

    // Try with read_at first; fall back silently if column absent
    $stmt = safeQueryPrepare($conn,
        "UPDATE notifications
         SET read_status = 1, read_at = IFNULL(read_at, NOW())
         WHERE id = ? AND recipient_id = ? AND read_status = 0", false);

    if ($stmt && !($stmt instanceof DummyStatement)) {
        $stmt->bind_param("ii", $notification_id, $user_id);
        return $stmt->execute();
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

    // Try with read_at; fall back if column absent
    $stmt = safeQueryPrepare($conn,
        "UPDATE notifications
         SET read_status = 1, read_at = IFNULL(read_at, NOW())
         WHERE recipient_id = ? AND read_status = 0", false);

    if ($stmt && !($stmt instanceof DummyStatement)) {
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }

    $stmt = safeQueryPrepare($conn, 
        "UPDATE notifications 
         SET read_status = 1 
         WHERE recipient_id = ? AND read_status = 0");
    $stmt->bind_param("i", $user_id);
    
    return $stmt->execute();
}

/**
 * Return IDs of all users who should receive notifications for an accommodation:
 * the accommodation's managers, its owner, and all active admins.
 *
 * @param int $accommodationId
 * @param int $excludeUserId  Optional: exclude this user (e.g. the acting student)
 * @return int[]
 */
function getAccommodationNotifyRecipients($accommodationId, $excludeUserId = 0) {
    $conn = getDbConnection();
    if (!$conn) {
        return [];
    }

    $ids = [];

    // Managers linked to this accommodation
    $stmt = safeQueryPrepare($conn,
        "SELECT ua.user_id FROM user_accommodation ua WHERE ua.accommodation_id = ?");
    $stmt->bind_param("i", $accommodationId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['user_id'];
    }
    $stmt->close();

    // Accommodation owner
    $stmt = safeQueryPrepare($conn, "SELECT owner_id FROM accommodations WHERE id = ?");
    $stmt->bind_param("i", $accommodationId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($owner = $res->fetch_assoc()) {
        $ids[] = (int)$owner['owner_id'];
    }
    $stmt->close();

    // All active admins
    $stmt = safeQueryPrepare($conn,
        "SELECT u.id FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.name = 'admin' AND u.status = 'active'");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }
    $stmt->close();

    $ids = array_unique(array_filter($ids));
    if ($excludeUserId > 0) {
        $ids = array_values(array_filter($ids, fn($id) => $id !== (int)$excludeUserId));
    }
    return $ids;
}

/**
 * Optionally send an email copy for a notification event.
 * Only sends when the recipient has explicitly opted-in (email_notifications = 1).
 * Does NOT bypass or replace sendAppEmail() for critical account emails.
 *
 * @param int $recipientId
 * @param string $subject
 * @param string $message Plain-text message body
 * @return bool
 */
function sendNotificationEmail($recipientId, $subject, $message) {
    $conn = getDbConnection();
    if (!$conn) {
        return false;
    }

    $stmt = safeQueryPrepare($conn,
        "SELECT email_notifications FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $recipientId);
    $stmt->execute();
    $res = $stmt->get_result();
    $pref = $res->fetch_assoc();
    $stmt->close();

    // Default is off; only send if explicitly opted in
    if (!$pref || empty($pref['email_notifications'])) {
        return false;
    }

    $stmt = safeQueryPrepare($conn,
        "SELECT email FROM users WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param("i", $recipientId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['email'])) {
        return false;
    }

    return sendAppEmail($user['email'], $subject, $message, false, 'notification');
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

/**
 * Resolve the sender address for SMTP fallback delivery.
 * Returns SMTP_FROM if valid, otherwise derives noreply@<host>.
 */
function resolveSenderEmail(): string {
    $smtpFrom = getenv('SMTP_FROM');
    if ($smtpFrom !== false && !empty($smtpFrom) && filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)) {
        return $smtpFrom;
    }

    $host = 'localhost';
    if (defined('ABSOLUTE_APP_URL') && !empty(ABSOLUTE_APP_URL)) {
        $parsedHost = parse_url(ABSOLUTE_APP_URL, PHP_URL_HOST);
        if ($parsedHost) {
            $host = $parsedHost;
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'];
        }
    } elseif (!empty($_SERVER['SERVER_NAME'])) {
        $host = $_SERVER['SERVER_NAME'];
    }

    $host = preg_replace('/:\d+$/', '', $host);
    $host = preg_replace('/^www\./i', '', $host);

    return 'noreply@' . $host;
}

function resolveAppEmailUrl($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    if ($url[0] === '/') {
        if (defined('ABSOLUTE_APP_URL') && !empty(ABSOLUTE_APP_URL)) {
            return rtrim((string) ABSOLUTE_APP_URL, '/') . $url;
        }
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        if (!empty($host)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $scheme . '://' . $host . $url;
        }
        return $url;
    }

    if (!preg_match('/^[a-z]+:\/\//i', $url) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}/i', $url)) {
        return 'https://' . $url;
    }

    return $url;
}

function formatPlainAppEmailBody($message) {
    $lines = preg_split('/\r\n|\r|\n/', (string) $message);
    $paragraphLines = [];
    $credentialRows = [];
    $actionUrl = '';

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);

        if ($trimmed === '') {
            $paragraphLines[] = '';
            continue;
        }

        if (preg_match('/^(Username|Password|Temporary Password|Role)\s*:\s*(.+)$/i', $trimmed, $matches)) {
            $credentialRows[] = [
                'label' => $matches[1],
                'value' => $matches[2]
            ];
            continue;
        }

        if (preg_match('/^Your invitation code is:\s*(.+)$/i', $trimmed, $matches)) {
            $credentialRows[] = [
                'label' => 'Invitation Code',
                'value' => $matches[1]
            ];
            continue;
        }

        if (preg_match('/^You can login at\s+(.+)$/i', $trimmed, $matches)) {
            $actionUrl = resolveAppEmailUrl($matches[1]);
            continue;
        }

        if ($actionUrl === '' && preg_match('/^Please visit\s+(.+?)\s+to\s+/i', $trimmed, $matches)) {
            $actionUrl = resolveAppEmailUrl($matches[1]);
        }

        $paragraphLines[] = $trimmed;
    }

    $bodyHtml = '';
    $currentParagraph = [];

    foreach ($paragraphLines as $line) {
        if ($line === '') {
            if (!empty($currentParagraph)) {
                $bodyHtml .= '<p style="margin:0 0 14px 0;">' . htmlspecialchars(implode(' ', $currentParagraph), ENT_QUOTES, 'UTF-8') . '</p>';
                $currentParagraph = [];
            }
            continue;
        }

        $currentParagraph[] = $line;
    }

    if (!empty($currentParagraph)) {
        $bodyHtml .= '<p style="margin:0 0 14px 0;">' . htmlspecialchars(implode(' ', $currentParagraph), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    if (!empty($credentialRows)) {
        $bodyHtml .= '<div style="margin:18px 0;padding:14px;border:1px solid #dbe4ff;background:#f8faff;border-radius:10px;">';
        $bodyHtml .= '<div style="font-weight:700;color:#1e3a8a;margin-bottom:10px;">Account Details</div>';
        $bodyHtml .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">';
        foreach ($credentialRows as $row) {
            $label = htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars((string) $row['value'], ENT_QUOTES, 'UTF-8');
            $bodyHtml .= '<tr>'
                . '<td style="padding:6px 10px 6px 0;color:#4b5563;width:150px;vertical-align:top;">' . $label . '</td>'
                . '<td style="padding:6px 0;color:#111827;"><strong>' . $value . '</strong></td>'
                . '</tr>';
        }
        $bodyHtml .= '</table></div>';
    }

    if ($actionUrl !== '') {
        $safeActionUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $bodyHtml .= '<div style="margin:18px 0 16px 0;">'
            . '<a href="' . $safeActionUrl . '" style="display:inline-block;background:#0d6efd;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">Open Portal</a>'
            . '</div>'
            . '<p style="margin:0 0 14px 0;font-size:12px;color:#6b7280;">If the button does not work, copy and paste this link into your browser:<br>' . $safeActionUrl . '</p>';
    }

    return $bodyHtml;
}

function wrapAppEmailContent($subject, $bodyHtml) {
    $appName = defined('APP_NAME') ? (string) APP_NAME : 'System';
    $safeAppName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars((string) $subject, ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    return '<!doctype html><html><body style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;">'
        . '<div style="max-width:640px;margin:0 auto;">'
        . '<div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">'
        . '<div style="padding:16px 20px;background:#0d6efd;color:#ffffff;font-size:16px;font-weight:700;">' . $safeAppName . '</div>'
        . '<div style="padding:20px;">'
        . '<h2 style="margin:0 0 16px 0;font-size:20px;color:#111827;">' . $safeSubject . '</h2>'
        . '<div style="font-size:14px;line-height:1.6;color:#1f2937;">' . $bodyHtml . '</div>'
        . '</div>'
        . '<div style="padding:14px 20px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">&copy; ' . $year . ' ' . $safeAppName . '</div>'
        . '</div>'
        . '</div>'
        . '</body></html>';
}

/**
 * Send an HTML email via SendGrid Web API v3.
 * No Composer required – uses direct cURL.
 * Returns structured transport details for audit logging.
 *
 * @param  string      $to        Recipient address
 * @param  string      $subject   Email subject
 * @param  string      $htmlBody  Full HTML body
 * @param  string|null $sender    From address (defaults to SENDGRID_FROM constant)
 * @param  string|null $fromName  Sender display name (defaults to SENDGRID_FROM_NAME constant)
 * @return array{success: bool, transport: string, http_code: int, sender: string, error: string, message_id: string}
 */
function _sendSendGridEmailDetails(
    string  $to,
    string  $subject,
    string  $htmlBody,
    ?string $sender   = null,
    ?string $fromName = null
): array {
    $apiKey    = defined('SENDGRID_API_KEY')   ? SENDGRID_API_KEY   : '';
    $fromEmail = $sender   ?? (defined('SENDGRID_FROM')      ? SENDGRID_FROM      : '');
    $fromName  = $fromName ?? (defined('SENDGRID_FROM_NAME') ? SENDGRID_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'System'));

    $result = [
        'success'    => false,
        'transport'  => 'sendgrid',
        'http_code'  => 0,
        'sender'     => $fromEmail,
        'error'      => '',
        'message_id' => '',
    ];

    if (empty($apiKey)) {
        $result['error'] = 'SendGrid API key not configured';
        error_log('SendGrid email failed: API key not configured');
        return $result;
    }

    if (empty($fromEmail) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $result['error'] = 'Invalid or missing SendGrid From address';
        error_log("SendGrid email failed: invalid SENDGRID_FROM ({$fromEmail})");
        return $result;
    }

    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result['error'] = 'Invalid or missing recipient address';
        error_log("SendGrid email failed: invalid recipient ({$to})");
        return $result;
    }

    $payload = [
        'personalizations' => [
            ['to' => [['email' => $to]]],
        ],
        'from'    => ['email' => $fromEmail, 'name' => $fromName],
        'subject' => $subject,
        'content' => [
            ['type' => 'text/plain', 'value' => strip_tags($htmlBody)],
            ['type' => 'text/html',  'value' => $htmlBody],
        ],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // capture headers to extract X-Message-Id
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $result['http_code'] = $httpCode;

    // SendGrid returns 202 Accepted with an empty body on success
    if ($httpCode === 202) {
        $result['success'] = true;
        // Best-effort: extract X-Message-Id from response headers
        if ($rawResponse !== false && $headerSize > 0) {
            $headers = substr($rawResponse, 0, $headerSize);
            if (preg_match('/^X-Message-Id:\s*(\S+)/im', $headers, $m)) {
                $result['message_id'] = trim($m[1]);
            }
        }
        return $result;
    }

    // Extract a human-readable error from the JSON body (SendGrid error format)
    $body       = ($rawResponse !== false && $headerSize > 0) ? substr($rawResponse, $headerSize) : (string)$rawResponse;
    $errSnippet = '';
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['errors'][0]['message'])) {
            $errSnippet = substr((string)$decoded['errors'][0]['message'], 0, 120);
        } elseif (is_array($decoded) && isset($decoded['message'])) {
            $errSnippet = substr((string)$decoded['message'], 0, 120);
        } elseif (is_string($body)) {
            $errSnippet = substr($body, 0, 80);
        }
    }
    $result['error'] = $errSnippet ?: "HTTP {$httpCode}";
    error_log("SendGrid email failed [to={$to}]: HTTP {$httpCode}" . ($errSnippet ? " – {$errSnippet}" : ''));
    return $result;
}

/**
 * Send an email via PHPMailer over authenticated SMTP.
 * Returns structured transport details for audit logging.
 *
 * @param  string $to        Recipient address
 * @param  string $subject   Email subject
 * @param  string $htmlBody  Full HTML body
 * @param  string $sender    From/Reply-To address
 * @return array{success: bool, transport: string, sender: string, error: string}
 */
function _sendSmtpEmailDetails(string $to, string $subject, string $htmlBody, string $sender): array {
    $result = [
        'success'    => false,
        'transport'  => 'smtp',
        'sender'     => $sender,
        'error'      => '',
        'message_id' => '',
    ];

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true); // true = throw exceptions
        $mail->isSMTP();
        $mail->Host     = SMTP_HOST;
        $mail->Port     = SMTP_PORT;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASSWORD;

        if (SMTP_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $fromName = defined('APP_NAME') ? APP_NAME : 'System';
        $mail->setFrom($sender, $fromName);
        $mail->addReplyTo($sender, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags((string) $htmlBody);
        $mail->CharSet = 'UTF-8';
        $mail->XMailer = ' '; // omit X-Mailer header

        $mail->send();
        $result['success'] = true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $errMsg = substr($e->getMessage(), 0, 200);
        $result['error'] = $errMsg;
        error_log("SMTP email failed [to={$to}]: {$errMsg}");
    } catch (\Exception $e) {
        $errMsg = substr($e->getMessage(), 0, 200);
        $result['error'] = $errMsg;
        error_log("SMTP email exception [to={$to}]: {$errMsg}");
    }

    return $result;
}

function sendAppEmail($to, $subject, $message, $isHtml = false, $auditCategory = 'general') {
    $category = ($auditCategory !== null && $auditCategory !== '') ? (string)$auditCategory : 'general';

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        CommunicationLogger::logEmail((string)$to, (string)$subject, $category, false, null, [
            'transport' => 'none',
            'error'     => 'Invalid email address',
        ]);
        return false;
    }

    $bodyContent = $isHtml ? (string) $message : formatPlainAppEmailBody($message);
    $htmlMessage = wrapAppEmailContent($subject, $bodyContent);

    $result           = false;
    $transportMeta    = [];
    $priorAttemptMade = false;

    // ── Primary: SendGrid Web API v3 ──────────────────────────────────────
    $sendgridReady = defined('SENDGRID_API_KEY') && SENDGRID_API_KEY !== ''
        && defined('SENDGRID_FROM') && SENDGRID_FROM !== ''
        && filter_var(SENDGRID_FROM, FILTER_VALIDATE_EMAIL);

    if ($sendgridReady) {
        $sgResult         = _sendSendGridEmailDetails((string)$to, (string)$subject, $htmlMessage);
        $transportMeta    = $sgResult;
        $result           = $sgResult['success'];
        $priorAttemptMade = true;
    }

    // ── Fallback: PHPMailer SMTP ──────────────────────────────────────────
    if (!$result) {
        $smtpReady = class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')
            && defined('SMTP_HOST') && SMTP_HOST !== ''
            && defined('SMTP_USER') && SMTP_USER !== ''
            && defined('SMTP_PASSWORD') && SMTP_PASSWORD !== '';

        if ($smtpReady) {
            $smtpSender    = (defined('SMTP_FROM') && filter_var(SMTP_FROM, FILTER_VALIDATE_EMAIL))
                ? SMTP_FROM
                : resolveSenderEmail();
            $smtpResult    = _sendSmtpEmailDetails((string)$to, (string)$subject, $htmlMessage, $smtpSender);
            $smtpResult['fallback_used'] = $priorAttemptMade;
            $transportMeta    = $smtpResult;
            $result           = $smtpResult['success'];
            $priorAttemptMade = true;
        }
    }

    if (empty($transportMeta)) {
        $transportMeta = [
            'transport' => 'none',
            'success'   => false,
            'error'     => 'No configured transport available (check SENDGRID_API_KEY/SENDGRID_FROM or SMTP_HOST/SMTP_USER/SMTP_PASSWORD)',
        ];
    }

    CommunicationLogger::logEmail((string)$to, (string)$subject, $category, $result, null, $transportMeta);

    return $result;
}

/**
 * Dummy Statement class
 */
class DummyStatement {
    public $error = 'Dummy statement execution';
    public $errno = -1;
    public $insert_id = 0;
    public $affected_rows = 0;
    
    public function bind_param(...$args) { return true; }
    public function execute() { return false; }
    public function get_result() { return new DummyResult(); }
    public function fetch() { return false; }
    public function close() { return true; }
}

class DummyResult {
    public $num_rows = 0;
    public function fetch_assoc() { return null; }
    public function fetch_all() { return []; }
    public function fetch_object() { return null; }
    public function free() { return true; }
}


