<?php
/**
 * test_sendgrid_live.php — Real SendGrid API live test (no Composer, no mocks)
 *
 * CLI usage:
 *   php test_sendgrid_live.php you@example.com
 *   php test_sendgrid_live.php you@example.com "Custom subject" "Custom body"
 *
 * Browser usage:
 *   http://localhost/test_sendgrid_live.php?to=you@example.com
 *   http://localhost/test_sendgrid_live.php?to=you@example.com&subject=Test&body=Hello
 *
 * Required env vars (set in .env or shell):
 *   SENDGRID_API_KEY   — your SendGrid Web API v3 key
 *   SENDGRID_FROM      — verified sender email address
 * Optional:
 *   SENDGRID_FROM_NAME — display name (defaults to APP_NAME or "System")
 */

declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

// ---------------------------------------------------------------------------
// Bootstrap — load config constants only; no DB, no session side-effects
// ---------------------------------------------------------------------------
$configPath = __DIR__ . '/includes/config.php';
if (!file_exists($configPath)) {
    echo "[FATAL] includes/config.php not found at: $configPath\n";
    exit(1);
}

// Suppress any output the config file might emit during bootstrap
ob_start();
require_once $configPath;
ob_end_clean();

// ---------------------------------------------------------------------------
// Gather inputs
// ---------------------------------------------------------------------------
if ($isCli) {
    $recipient = $argv[1] ?? '';
    $subject   = $argv[2] ?? '';
    $body      = $argv[3] ?? '';
} else {
    $recipient = trim($_GET['to']      ?? '');
    $subject   = trim($_GET['subject'] ?? '');
    $body      = trim($_GET['body']    ?? '');
}

// ---------------------------------------------------------------------------
// Validate configuration
// ---------------------------------------------------------------------------
$apiKey   = defined('SENDGRID_API_KEY')   ? SENDGRID_API_KEY   : '';
$fromEmail = defined('SENDGRID_FROM')     ? SENDGRID_FROM      : '';
$fromName  = defined('SENDGRID_FROM_NAME') ? SENDGRID_FROM_NAME : 'System';

$errors = [];
if ($apiKey === '') {
    $errors[] = 'SENDGRID_API_KEY is not set (or is empty).';
}
if ($fromEmail === '') {
    $errors[] = 'SENDGRID_FROM is not set (or is empty).';
}
if ($recipient === '') {
    if ($isCli) {
        $errors[] = 'Recipient email required as first argument: php test_sendgrid_live.php you@example.com';
    } else {
        $errors[] = 'Recipient email required as ?to= query param.';
    }
} elseif (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Recipient '$recipient' is not a valid email address.";
}

if ($errors) {
    echo "=== SendGrid Live Test — Configuration Errors ===\n\n";
    foreach ($errors as $e) {
        echo "  [MISSING] $e\n";
    }
    echo "\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Defaults
// ---------------------------------------------------------------------------
$appName = defined('APP_NAME') ? APP_NAME : 'App';
if ($subject === '') {
    $subject = "[{$appName}] SendGrid Live Test — " . date('Y-m-d H:i:s');
}
if ($body === '') {
    $body = "<p>This is a <strong>live SendGrid API test</strong> sent from "
          . htmlspecialchars($appName) . " at " . date('Y-m-d H:i:s T') . ".</p>"
          . "<p>If you received this, the SendGrid integration is working correctly.</p>";
}

// ---------------------------------------------------------------------------
// Report what we are about to send
// ---------------------------------------------------------------------------
echo "=== SendGrid Live Test ===\n\n";
echo "  Sender     : {$fromName} <{$fromEmail}>\n";
echo "  Recipient  : {$recipient}\n";
echo "  Subject    : {$subject}\n";
echo "  API key    : " . substr($apiKey, 0, 8) . str_repeat('*', max(0, strlen($apiKey) - 8)) . "\n";
echo "\n";
echo "Sending via SendGrid Web API v3...\n\n";

// ---------------------------------------------------------------------------
// Build payload (mirrors _sendSendGridEmailDetails in includes/functions.php)
// ---------------------------------------------------------------------------
$payload = [
    'personalizations' => [
        ['to' => [['email' => $recipient]]],
    ],
    'from'    => ['email' => $fromEmail, 'name' => $fromName],
    'subject' => $subject,
    'content' => [
        ['type' => 'text/plain', 'value' => strip_tags($body)],
        ['type' => 'text/html',  'value' => $body],
    ],
];

// ---------------------------------------------------------------------------
// Execute cURL request
// ---------------------------------------------------------------------------
if (!function_exists('curl_init')) {
    echo "[FATAL] PHP cURL extension is not available.\n";
    exit(1);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,            'https://api.sendgrid.com/v3/mail/send');
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER,         true);  // capture response headers
curl_setopt($ch, CURLOPT_TIMEOUT,        15);
curl_setopt($ch, CURLOPT_HTTPHEADER,     [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
]);

$rawResponse = curl_exec($ch);
$curlError   = curl_error($ch);
$httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize  = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

// ---------------------------------------------------------------------------
// Parse response
// ---------------------------------------------------------------------------
$success   = false;
$messageId = null;
$errDetail = null;

if ($rawResponse === false || $curlError !== '') {
    $errDetail = 'cURL transport error: ' . ($curlError ?: 'unknown');
} else {
    $responseHeaders = substr($rawResponse, 0, $headerSize);
    $responseBody    = substr($rawResponse, $headerSize);

    // Extract X-Message-Id from response headers
    if (preg_match('/^X-Message-Id:\s*(.+)$/im', $responseHeaders, $m)) {
        $messageId = trim($m[1]);
    }

    if ($httpCode === 202) {
        $success = true;
    } else {
        // Attempt to extract a human-readable error from the JSON body
        $decoded = json_decode($responseBody, true);
        if (is_array($decoded) && isset($decoded['errors'][0]['message'])) {
            $errDetail = substr((string)$decoded['errors'][0]['message'], 0, 200);
        } elseif (is_array($decoded) && isset($decoded['message'])) {
            $errDetail = substr((string)$decoded['message'], 0, 200);
        } elseif (is_string($responseBody) && $responseBody !== '') {
            $errDetail = substr($responseBody, 0, 200);
        } else {
            $errDetail = 'No error detail returned.';
        }
    }
}

// ---------------------------------------------------------------------------
// Output result
// ---------------------------------------------------------------------------
echo "  HTTP Status : {$httpCode}\n";

if ($success) {
    echo "  Result      : SUCCESS — SendGrid accepted the message (HTTP 202)\n";
    if ($messageId !== null) {
        echo "  Message-Id  : {$messageId}\n";
    }
    echo "\n[PASS] Email delivered to SendGrid for relay to {$recipient}\n";
    exit(0);
} else {
    echo "  Result      : FAILURE\n";
    if ($errDetail !== null) {
        echo "  Error       : {$errDetail}\n";
    }
    echo "\n[FAIL] SendGrid did not accept the message.\n";
    exit(1);
}
