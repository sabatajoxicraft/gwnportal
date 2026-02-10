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
 * Send SMS message (placeholder implementation)
 */
function sendSMS($number, $message) {
    // Placeholder function - implement actual SMS sending logic here
    error_log("SMS would be sent to $number: $message");
    return true;
}

/**
 * Send WhatsApp message (placeholder implementation)
 */
function sendWhatsApp($number, $message) {
    // Placeholder function - implement actual WhatsApp sending logic here
    error_log("WhatsApp message would be sent to $number: $message");
    return true;
}

/**
 * Safely prepares an SQL statement with improved error handling
 */
function safeQueryPrepare($conn, $sql, $debug = true) {
    // Ensure connection is valid
    if ($conn === null) {
        $conn = getDbConnection();
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        // Log the error regardless
        error_log("Database error in SQL: $sql - " . $conn->error);
        
        if ($debug && ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1')) {
            // Only show detailed errors in local development
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            $file = $trace[0]['file'] ?? 'unknown';
            $line = $trace[0]['line'] ?? 'unknown';
            
            echo "
                <div style='background:#f8d7da; color:#721c24; padding:10px; margin:10px 0; border-radius:5px;'>
                    <h3>Database Error</h3>
                    <p><strong>Prepare statement failed:</strong> (" . $conn->errno . ") " . $conn->error . "</p>
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
