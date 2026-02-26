<?php
/**
 * Smart Footer Router
 * 
 * Automatically detects context and includes appropriate footer:
 * - footer-public.php: Landing pages, marketing content
 * - footer-auth.php: Login, registration, password reset
 * - footer-app.php: Dashboard, authenticated app pages (DEFAULT)
 * 
 * Override auto-detection by setting $footerType before including:
 * $footerType = 'public'; // or 'auth' or 'app'
 * require_once '../includes/components/footer.php';
 * 
 * @param array $extraScripts Additional scripts to include (passed to footer variants)
 * @param string $footerType Override auto-detection ('public', 'auth', 'app')
 */

// Allow explicit override
if (!isset($footerType)) {
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
    
    // Determine footer type
    if (in_array($currentScript, $publicPages)) {
        $footerType = 'public';
    } elseif (in_array($currentScript, $authPages)) {
        $footerType = 'auth';
    } else {
        // Default to app footer for all other pages
        // This ensures backward compatibility for existing pages
        $footerType = 'app';
    }
}

// Validate and sanitize footer type
$validTypes = ['public', 'auth', 'app'];
if (!in_array($footerType, $validTypes)) {
    error_log("Invalid footer type '$footerType' requested. Defaulting to 'app'.");
    $footerType = 'app';
}

// Include the appropriate footer component
$footerComponentPath = __DIR__ . "/footer-{$footerType}.php";

if (!file_exists($footerComponentPath)) {
    error_log("Footer component not found: {$footerComponentPath}");
    // Fallback to a basic footer to prevent blank pages
    ?>
    <footer class="mt-auto py-3 bg-light border-top">
        <div class="container">
            <p class="text-center text-muted mb-0">
                &copy; <?= date('Y') ?> <?= APP_NAME ?>
            </p>
        </div>
    </footer>
    </body>
    </html>
    <?php
} else {
    require_once $footerComponentPath;
}
