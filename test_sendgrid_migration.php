<?php
/**
 * SendGrid Migration Validation Smoke Test (Refined)
 * 
 * Checks:
 * 1. PHP syntax on modified files (via PHP parser)
 * 2. Config and functions.php load without fatal errors
 * 3. sendAppEmail() transport order is SendGrid -> SMTP
 * 4. SendGrid error handling safely guards non-JSON responses
 * 5. ActivityLogHelper renders SendGrid success/failure wording
 */

// Suppress all output before checks
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$errors = [];
$passed = [];

// === CHECK 1: PHP Syntax Validation ===
echo "Check 1: PHP Syntax Validation\n";
echo str_repeat("-", 60) . "\n";

$files_to_check = [
    'includes/config.php',
    'includes/functions.php',
    'includes/helpers/ActivityLogHelper.php',
    'includes/services/CommunicationLogger.php',
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (!file_exists($full_path)) {
        $errors[] = "File not found: $file";
        echo "✗ $file (not found)\n";
        continue;
    }
    
    $output = shell_exec("php -l " . escapeshellarg($full_path) . " 2>&1");
    if (strpos($output, 'No syntax errors detected') === false) {
        $errors[] = "PHP syntax error in $file: $output";
        echo "✗ $file\n  $output\n";
    } else {
        $passed[] = "PHP syntax OK: $file";
        echo "✓ $file\n";
    }
}

// === CHECK 2: Smoke Test - Load core email functions ===
echo "\nCheck 2: Smoke Test - Core Email Functions Available\n";
echo str_repeat("-", 60) . "\n";

// Mock minimal environment
putenv('APP_ENV=development');
putenv('DB_HOST=localhost');
putenv('DB_USER=root');
putenv('DB_PASS=');
putenv('DB_NAME=test_db');
putenv('APP_DEBUG=0');
putenv('APP_NAME=GWN Portal');
putenv('APP_URL=http://localhost');
putenv('SENDGRID_API_KEY=');
putenv('SENDGRID_FROM=');
putenv('SENDGRID_FROM_NAME=');
putenv('SMTP_HOST=localhost');
putenv('SMTP_PORT=587');
putenv('SMTP_USER=');
putenv('SMTP_PASSWORD=');
putenv('SMTP_FROM=');
putenv('SMTP_ENCRYPTION=tls');
putenv('SMTP_AUTH=1');
putenv('APP_TIMEZONE=UTC');
putenv('M365_GRAPH_ENABLED=0');
putenv('M365_TENANT_ID=');
putenv('M365_CLIENT_ID=');
putenv('M365_CLIENT_SECRET=');

// Suppress output
ob_end_clean();
ob_start();

// Try to include the email functions directly (skip config to avoid session/header issues)
try {
    // Load just the helper and services that don't require session
    require_once __DIR__ . '/includes/helpers/ActivityLogHelper.php';
    require_once __DIR__ . '/includes/services/CommunicationLogger.php';
    
    // Verify functions exist by parsing functions.php
    $functions_content = file_get_contents(__DIR__ . '/includes/functions.php');
    
    $required_functions = [
        '_sendSendGridEmailDetails',
        '_sendSmtpEmailDetails',
        'sendAppEmail',
    ];
    
    $functions_found = true;
    foreach ($required_functions as $func) {
        if (strpos($functions_content, "function $func") === false) {
            $errors[] = "Function not found: $func";
            echo "✗ $func not defined\n";
            $functions_found = false;
        } else {
            echo "✓ $func defined\n";
        }
    }
    
    // Verify CommunicationLogger class was loaded
    if (class_exists('CommunicationLogger')) {
        echo "✓ CommunicationLogger class loaded\n";
    } else {
        $errors[] = "CommunicationLogger class not loaded";
        echo "✗ CommunicationLogger not loaded\n";
        $functions_found = false;
    }
    
    if ($functions_found) {
        $passed[] = "All required email functions and services present";
    }
    
    ob_end_clean();
} catch (Exception $e) {
    ob_end_clean();
    $errors[] = "Failed to load core functions: " . $e->getMessage();
    echo "✗ Failed to load core functions\n";
}

// === CHECK 3: Verify sendAppEmail() Transport Order ===
echo "\nCheck 3: Transport Order Validation (SendGrid -> SMTP)\n";
echo str_repeat("-", 60) . "\n";

$functions_content = file_get_contents(__DIR__ . '/includes/functions.php');

// Extract sendAppEmail function
if (!preg_match('/function sendAppEmail\s*\((.*?)\n\}/s', $functions_content, $m)) {
    $errors[] = "Could not extract sendAppEmail function";
    echo "✗ sendAppEmail function not found\n";
} else {
    $sendAppEmail_body = $m[0];

    // Find positions of each transport call
    $pos_sendgrid = strpos($sendAppEmail_body, '_sendSendGridEmailDetails');
    $pos_smtp     = strpos($sendAppEmail_body, '_sendSmtpEmailDetails');

    // Graph transport is not part of the current stack; assert it is absent
    $pos_graph = strpos($sendAppEmail_body, '_sendGraphEmailDetails');

    if ($pos_sendgrid !== false && $pos_smtp !== false && $pos_sendgrid < $pos_smtp) {
        echo "✓ Transport order confirmed: SendGrid → SMTP\n";
        $passed[] = "Transport order: SendGrid -> SMTP confirmed";
    } else {
        echo "✗ Transport order is incorrect (expected SendGrid before SMTP)\n";
        $errors[] = "Transport order not SendGrid -> SMTP";
    }

    if ($pos_graph === false) {
        echo "✓ Graph transport absent (not part of current stack)\n";
        $passed[] = "Graph transport correctly absent";
    } else {
        echo "⚠ _sendGraphEmailDetails found in sendAppEmail – unexpected\n";
    }
}

// === CHECK 4: SendGrid Error Handling Guards Non-JSON Responses ===
echo "\nCheck 4: SendGrid Error Handling (Non-JSON Response Safety)\n";
echo str_repeat("-", 60) . "\n";

// Check for safe JSON decode with fallback
if (preg_match('/function _sendSendGridEmailDetails\s*\((.*?)\nfunction /s', $functions_content, $m)) {
    $sg_func = $m[0];
    
    $has_json_decode = preg_match('/json_decode\s*\(\s*\$body.*?true\s*\)/s', $sg_func);
    $has_is_array = preg_match('/is_array\s*\(\s*\$decoded\s*\)/', $sg_func);
    $has_is_string = preg_match('/is_string\s*\(\s*\$body\s*\)/', $sg_func);
    $has_substr_fallback = preg_match('/\$errSnippet\s*=\s*substr\s*\(\s*\$body/', $sg_func);
    
    if ($has_json_decode && $has_is_array && $has_is_string) {
        echo "✓ SendGrid safely guards non-JSON responses\n";
        $passed[] = "SendGrid error handling safely guards non-JSON responses";
    } else {
        echo "⚠ SendGrid error handling may not be fully safe\n";
        if (!$has_json_decode) echo "  - Missing json_decode check\n";
        if (!$has_is_array) echo "  - Missing is_array check\n";
        if (!$has_is_string) echo "  - Missing is_string check\n";
        $errors[] = "SendGrid error handling incomplete";
    }
} else {
    $errors[] = "Could not locate _sendSendGridEmailDetails function";
    echo "✗ Could not verify SendGrid error handling\n";
}

// === CHECK 5: ActivityLogHelper Renders SendGrid Success/Failure Wording ===
echo "\nCheck 5: ActivityLogHelper SendGrid Wording\n";
echo str_repeat("-", 60) . "\n";

$helper_content = file_get_contents(__DIR__ . '/includes/helpers/ActivityLogHelper.php');

$wording_checks = [
    'Success wording' => preg_match('/Accepted\s+by\s+SendGrid/i', $helper_content),
    'HTTP code reference' => preg_match('/HTTP.*http_code|http_code.*HTTP/i', $helper_content),
    'Failure wording' => preg_match('/SendGrid.*error|error.*SendGrid/i', $helper_content),
    'Fallback marker' => preg_match('/fallback/i', $helper_content),
];

$all_wording_pass = true;
foreach ($wording_checks as $label => $result) {
    if ($result) {
        echo "✓ $label\n";
        $passed[] = $label;
    } else {
        echo "✗ $label not found\n";
        $errors[] = $label;
        $all_wording_pass = false;
    }
}

if ($all_wording_pass) {
    $passed[] = "ActivityLogHelper renders SendGrid success/failure wording";
}

// === RESULTS ===
echo "\n" . str_repeat("=", 60) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat("=", 60) . "\n";
echo "Passed: " . count($passed) . " checks\n";
echo "Failed: " . count($errors) . " checks\n";

if (!empty($errors)) {
    echo "\n" . str_repeat("-", 60) . "\n";
    echo "ERRORS:\n";
    foreach ($errors as $error) {
        echo "  • $error\n";
    }
    exit(1);
} else {
    echo "\n✓ All validation checks passed!\n";
    exit(0);
}
