<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
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
        $message = $_SESSION['flash']['message'];
        $type = $_SESSION['flash']['type'];
        echo "<div class='alert alert-$type'>$message</div>";
        unset($_SESSION['flash']);
    }
}

// Load accommodation handler for managers (must be after session start and DB connection)
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    require_once __DIR__ . '/accommodation-handler.php';
}
?>
