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

// Initialize error handling in development mode
ErrorHandler::init(false);  // Set to false for development, true for production

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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: https://api.qrserver.com;");

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

// Load environment variables from .env file
$env_file = realpath(__DIR__ . '/../.env');
$env_vars = [];

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and invalid lines
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // Remove quotes if present
        if (preg_match('/^"(.+)"$/', $value, $matches)) {
            $value = $matches[1];
        } else if (preg_match("/^'(.+)'$/", $value, $matches)) {
            $value = $matches[1];
        }
        
        // Handle variable interpolation
        $value = preg_replace_callback('/\${([a-zA-Z0-9_]+)}/', function ($matches) use ($env_vars) {
            return isset($env_vars[$matches[1]]) ? $env_vars[$matches[1]] : '';
        }, $value);
        
        $env_vars[$name] = $value;
        putenv("$name=$value");
        $_ENV[$name] = $value;
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
define('APP_NAME', $_ENV['APP_NAME'] ?? 'WiFi Management System');
// Normalize BASE_URL so asset links resolve correctly when served from container root
$appUrl = $_ENV['APP_URL'] ?? '';
$appUrl = rtrim($appUrl, '/');
define('BASE_URL', $appUrl === '' ? '' : $appUrl);

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
define('TWILIO_SMS_VOUCHER_TEMPLATE_SID', getenv('TWILIO_SMS_VOUCHER_TEMPLATE_SID') ?: '');
define('TWILIO_SMS_LOGIN_TEMPLATE_SID', getenv('TWILIO_SMS_LOGIN_TEMPLATE_SID') ?: '');

// GWN Cloud API Configuration
define('GWN_API_URL', getenv('GWN_API_URL') ?: '');
define('GWN_APP_ID', getenv('GWN_APP_ID') ?: '');
define('GWN_SECRET_KEY', getenv('GWN_SECRET_KEY') ?: '');
define('GWN_NETWORK_ID', getenv('GWN_NETWORK_ID') ?: '');
define('GWN_ALLOWED_DEVICES', getenv('GWN_ALLOWED_DEVICES') ?: '2');

// Python integration
define('PYTHON_SCRIPT_PATH', getenv('PYTHON_SCRIPT_PATH') ?: (getenv('PYTHON_SCRIPT') ?: ''));

// Path settings
define('PUBLIC_PATH', __DIR__ . '/../public');
define('INCLUDES_PATH', __DIR__);

// Enable error reporting for development (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
?>
