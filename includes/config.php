<?php
/**
 * GWN Portal Configuration
 * 
 * Session security configured in session-config.php
 */

// Apply session security configuration BEFORE starting session
require_once __DIR__ . '/session-config.php';

// Load role constants (used throughout application)
require_once __DIR__ . '/constants/roles.php';

// Load message constants (error/success/warning messages)
require_once __DIR__ . '/constants/messages.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// ENVIRONMENT LOADING (must be early – other code depends on APP_ENV)
// ============================================================================
// CRA-style priority: .env.{APP_ENV}.local → .env.local → .env.{APP_ENV} → .env
require_once __DIR__ . '/env-loader.php';
$env_vars = loadEnvironment();

define('APP_ENV', $env_vars['APP_ENV'] ?? 'development');
define('IS_PRODUCTION', APP_ENV === 'production');
define('APP_DEBUG', (bool)(getenv('APP_DEBUG') ?: (IS_PRODUCTION ? '0' : '1')));

// PSR-4 autoloader for services and utilities
spl_autoload_register(static function ($className) {
    // Try services directory first
    $path = __DIR__ . '/services/' . $className . '.php';
    if (is_file($path)) {
        require_once $path;
        return;
    }
    
    // Try utilities directory
    $path = __DIR__ . '/utilities/' . $className . '.php';
    if (is_file($path)) {
        require_once $path;
        return;
    }
});

// Load utility classes directly (used throughout app)
require_once __DIR__ . '/utilities/Response.php';
require_once __DIR__ . '/utilities/FormHelper.php';
require_once __DIR__ . '/utilities/ErrorHandler.php';
require_once __DIR__ . '/utilities/PermissionHelper.php';
require_once __DIR__ . '/utilities/ActivityDashboardWidget.php';

// Initialize error handling based on environment
ErrorHandler::init(IS_PRODUCTION);

// ============================================================================
// SECURITY HEADERS (M1-T7)
// ============================================================================
// Prevent clickjacking attacks
header('X-Frame-Options: DENY');
// Prevent MIME-type sniffing
header('X-Content-Type-Options: nosniff');
// Enable XSS filter in older browsers
header('X-XSS-Protection: 1; mode=block');
// Referrer policy - don't leak URLs to external sites
header('Referrer-Policy: strict-origin-when-cross-origin');
// Basic Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://maps.googleapis.com https://maps.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; connect-src 'self' https://cdn.jsdelivr.net https://maps.googleapis.com https://maps.gstatic.com; img-src 'self' data: https://api.qrserver.com https://maps.googleapis.com https://maps.gstatic.com; frame-src 'self' https://www.google.com https://maps.google.com; child-src 'self' https://www.google.com https://maps.google.com;");

// Session timeout check - only for authenticated users
if (isset($_SESSION['user_id']) && isset($_SESSION['LAST_ACTIVITY'])) {
    if ((time() - $_SESSION['LAST_ACTIVITY']) > SESSION_TIMEOUT) {
        // Last request was more than SESSION_TIMEOUT seconds ago
        session_unset();
        session_destroy();
        
        // Start a new session for the redirect flash message
        session_start();
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Your session has expired due to inactivity. Please login again.'
        ];
        
        header("Location: " . (defined('BASE_URL') ? BASE_URL : '') . "/login.php");
        exit();
    }
}

// Update last activity timestamp for logged-in users
if (isset($_SESSION['user_id'])) {
    $_SESSION['LAST_ACTIVITY'] = time();
}

// Session hijacking prevention - regenerate session ID periodically
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } else if (time() - $_SESSION['CREATED'] > SESSION_REGENERATE_INTERVAL) {
        // Regenerate session ID periodically (every hour by default)
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

// Database Configuration
// Check for Docker environment variables first, then fall back to defaults
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');     // Database host
define('DB_USER', getenv('DB_USER') ?: 'root');          // Database username
define('DB_PASS', getenv('DB_PASS') ?: '');              // Database password
define('DB_NAME', getenv('DB_NAME') ?: 'gwn_wifi_system'); // Database name

// Include database connection (after loading env variables)
require_once __DIR__ . '/db.php';

// Application settings from env file
define('APP_NAME', $_ENV['APP_NAME'] ?? 'JoxiSphere');

// Timezone constants.
// APP_TIMEZONE      – local timezone for all display/UI output.
// ACTIVITY_LOG_STORAGE_TIMEZONE – timezone used when writing activity_log timestamps.
//   Keep this UTC so that all stored timestamps are timezone-agnostic.
//   Do NOT call date_default_timezone_set() globally; use these constants explicitly.
define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'Africa/Johannesburg');
define('ACTIVITY_LOG_STORAGE_TIMEZONE', 'UTC');

// ABSOLUTE_APP_URL: the full configured origin (scheme + host + port) from APP_URL.
// Use this wherever an absolute URL is required, e.g. outbound email/SMS login links.
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
define('ABSOLUTE_APP_URL', $appUrl);

// BASE_URL: path-only prefix so browser-facing assets and redirects are host-agnostic.
// Serving at the root (the common case) gives an empty string, making all links
// root-relative (/login.php etc.) and reachable via any loopback alias or hostname.
$_appUrlPath = parse_url($appUrl, PHP_URL_PATH) ?? '';
define('BASE_URL', $_appUrlPath !== '' ? '/' . trim($_appUrlPath, '/') : '');
unset($appUrl, $_appUrlPath);

// Onboarding settings
define('CODE_EXPIRY_DAYS', $_ENV['CODE_EXPIRY_DAYS'] ?? 7);
define('CODE_LENGTH', $_ENV['CODE_LENGTH'] ?? 8);

// Twilio Configuration
define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: '');
define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: '');
define('TWILIO_PHONE_NUMBER', getenv('TWILIO_PHONE_NUMBER') ?: '');
define('TWILIO_WHATSAPP_NO', getenv('TWILIO_WHATSAPP_NO') ?: '');
define('TWILIO_MESSAGING_SERVICE_SID', getenv('TWILIO_MESSAGING_SERVICE_SID') ?: '');
define('TWILIO_WA_VOUCHER_TEMPLATE_SID', getenv('TWILIO_WA_VOUCHER_TEMPLATE_SID') ?: '');
define('TWILIO_WA_FREEFORM_TEMPLATE_SID', getenv('TWILIO_WA_FREEFORM_TEMPLATE_SID') ?: '');
define('TWILIO_SMS_VOUCHER_TEMPLATE_SID', getenv('TWILIO_SMS_VOUCHER_TEMPLATE_SID') ?: '');
define('TWILIO_SMS_LOGIN_TEMPLATE_SID', getenv('TWILIO_SMS_LOGIN_TEMPLATE_SID') ?: '');
define('TWILIO_SMS_INVITATION_TEMPLATE_SID', getenv('TWILIO_SMS_INVITATION_TEMPLATE_SID') ?: '');
define('TWILIO_WA_INVITATION_TEMPLATE_SID', getenv('TWILIO_WA_INVITATION_TEMPLATE_SID') ?: '');

// GWN Cloud API Configuration
define('GWN_API_URL', getenv('GWN_API_URL') ?: '');
define('GWN_APP_ID', getenv('GWN_APP_ID') ?: '');
define('GWN_SECRET_KEY', getenv('GWN_SECRET_KEY') ?: '');
define('GWN_NETWORK_ID', getenv('GWN_NETWORK_ID') ?: '');
define('GWN_ALLOWED_DEVICES', getenv('GWN_ALLOWED_DEVICES') ?: '2');

// Python integration
define('PYTHON_SCRIPT_PATH', getenv('PYTHON_SCRIPT_PATH') ?: (getenv('PYTHON_SCRIPT') ?: ''));

// Google Maps API
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');

// ============================================================================
// SendGrid Configuration (primary outbound email transport – Web API v3)
// No Composer required; uses direct cURL in _sendSendGridEmailDetails().
// Set SENDGRID_API_KEY + SENDGRID_FROM to activate. Leave empty to skip.
// ============================================================================
define('SENDGRID_API_KEY',   getenv('SENDGRID_API_KEY')   ?: '');
define('SENDGRID_FROM',      getenv('SENDGRID_FROM')      ?: '');
define('SENDGRID_FROM_NAME', getenv('SENDGRID_FROM_NAME') ?: (defined('APP_NAME') ? APP_NAME : 'System'));

// ============================================================================
// SMTP Configuration (PHPMailer – fallback email transport)
// Defaults match docker-entrypoint.sh; override via .env or server environment.
// ============================================================================
define('SMTP_HOST',       getenv('SMTP_HOST')       ?: 'student.joxicraft.co.za');
define('SMTP_PORT',       (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_USER',       getenv('SMTP_USER')       ?: 'donotreply@student.joxicraft.co.za');
define('SMTP_PASSWORD',   getenv('SMTP_PASSWORD')   ?: '');
define('SMTP_FROM',       getenv('SMTP_FROM')       ?: 'donotreply@student.joxicraft.co.za');
// 'ssl' for implicit TLS on port 465 (SMTPS); 'tls' for STARTTLS on port 587; '' = none
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: (((int)(getenv('SMTP_PORT') ?: 465)) === 587 ? 'tls' : 'ssl'));
// Set to '0' to disable SMTP AUTH (not recommended)
define('SMTP_AUTH',       (getenv('SMTP_AUTH') !== false ? (bool)(int)getenv('SMTP_AUTH') : true));

// Path settings
define('PUBLIC_PATH', __DIR__ . '/../public');
define('INCLUDES_PATH', __DIR__);

// Error reporting – verbose in development, silent in production
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('log_errors', 1);
}

// Load vendored third-party libraries (PHPMailer, etc.)
// Committed to repo so production/shared-hosting works without running Composer.
$_vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($_vendorAutoload)) {
    require_once $_vendorAutoload;
}
unset($_vendorAutoload);

// Function to redirect with a message
function redirect($location, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type
        ];
    }
    header("Location: $location");
    exit;
}

// Function to display flash messages
function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $message = htmlspecialchars($_SESSION['flash']['message'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $type = htmlspecialchars($_SESSION['flash']['type'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        echo "<div class='alert alert-$type'>$message</div>";
        unset($_SESSION['flash']);
    }
}

// Load CSRF protection (must be after session start)
require_once __DIR__ . '/csrf.php';

// Load accommodation handler for managers (must be after session start and DB connection)
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    require_once __DIR__ . '/accommodation-handler.php';
}
