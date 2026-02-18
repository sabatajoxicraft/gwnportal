<?php
/**
 * Generate working PowerShell curl command
 */

require_once 'includes/config.php';
require_once 'includes/python_interface.php';

$token = gwnGetToken();
if (!$token) {
    echo "ERROR: Could not obtain GWN token\n";
    exit(1);
}

// Build request body
$bodyData = [
    'clientId' => 'BC:F1:05:00:1D:83',
    'name' => 'charmain-test-' . date('His')
];

$sig = gwnBuildSignature($token, $bodyData);

// Build URL with access_token (was missing!)
$url = 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?access_token=' . $token . '&appID=' . GWN_APP_ID . '&timestamp=' . $sig['timestamp'] . '&signature=' . $sig['signature'];

$body = $sig['bodyJson'];

echo "\n========================================\n";
echo "POWERSHELL CURL COMMAND\n";
echo "========================================\n\n";

echo "ONE-LINE FORMAT:\n";
echo "curl -X POST '$url' -H 'Content-Type: application/json' -d '$body'\n\n";

echo "MULTI-LINE FORMAT (copy-paste friendly):\n";
echo '@"\n';
echo "curl -X POST '" . $url . "' `\n";
echo "  -H 'Content-Type: application/json' `\n";
echo "  -d '" . $body . "'\n";
echo '"@\n\n';

echo "For PowerShell, use backtick (`) for line continuation, NOT backslash\n\n";

echo "Or use PowerShell native Invoke-WebRequest:\n";
echo '@"\n';
echo '$response = Invoke-WebRequest -Uri "' . $url . '" `\n';
echo "  -Method POST `\n";
echo "  -Headers @{'Content-Type'='application/json'} `\n";
echo "  -Body '" . addslashes($body) . "'\n";
echo '$response.Content\n';
echo '"@\n\n';

echo "========================================\n";
echo "QUERY STRING COMPONENTS:\n";
echo "========================================\n";
echo "Access Token: " . substr($token, 0, 20) . "...\n";
echo "App ID: " . GWN_APP_ID . "\n";
echo "Timestamp: " . $sig['timestamp'] . "\n";
echo "Signature: " . substr($sig['signature'], 0, 40) . "...\n\n";

echo "Request Body:\n";
echo $body . "\n\n";

echo "========================================\n";
?>
