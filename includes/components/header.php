<?php
/**
 * Smart Header Router
 * 
 * Automatically detects context and includes appropriate header:
 * - header-public.php: Landing pages, marketing content, public navigation
 * - header-auth.php: Login, registration, password reset (minimal, no nav)
 * - header-app.php: Dashboard, authenticated app pages with full navigation (DEFAULT)
 * 
 * Override auto-detection by setting $headerType before including:
 * $headerType = 'public'; // or 'auth' or 'app'
 * require_once '../includes/components/header.php';
 * 
 * @param string $pageTitle Page title (defaults to APP_NAME)
 * @param string $bodyClass Additional body CSS classes
 * @param string $extraCss Additional CSS to include
 * @param string $headerType Override auto-detection ('public', 'auth', 'app')
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allow explicit override
if (!isset($headerType)) {
    // Auto-detect based on current script
    $currentScript = basename($_SERVER['PHP_SELF']);
    
    // Define context patterns
    $authPages = [
        'login.php',
        'reset_password.php',
        'forgot_password.php',
        'register.php',
        'verify_email.php'
    ];
    
    $publicPages = [
        'index.php'  // Landing page only
    ];
    
    // Determine header type
    if (in_array($currentScript, $publicPages)) {
        $headerType = 'public';
    } elseif (in_array($currentScript, $authPages)) {
        $headerType = 'auth';
    } else {
        // Default to app header for all other pages
        // This ensures backward compatibility for existing pages
        $headerType = 'app';
    }
}

// Validate and sanitize header type
$validTypes = ['public', 'auth', 'app'];
if (!in_array($headerType, $validTypes)) {
    error_log("Invalid header type '$headerType' requested. Defaulting to 'app'.");
    $headerType = 'app';
}

// Include the appropriate header component
$headerComponentPath = __DIR__ . "/header-{$headerType}.php";

if (!file_exists($headerComponentPath)) {
    error_log("Header component not found: {$headerComponentPath}");
    // Fallback to a basic header to prevent blank pages
    $pageTitle = $pageTitle ?? APP_NAME;
    $bodyClass = $bodyClass ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $pageTitle ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
    <?php
} else {
    require_once $headerComponentPath;
}
