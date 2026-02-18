<?php
/**
 * Generate Fresh Query String with Current Signature
 */

require_once 'includes/config.php';
require_once 'includes/python_interface.php';

$token = gwnGetToken();
if (!$token) {
    echo "ERROR: Could not obtain GWN token\n";
    exit(1);
}

// Build request body for charmain
$bodyData = [
    'clientId' => 'BC:F1:05:00:1D:83',
    'name' => 'charmain-test-' . date('His')
];

$sig = gwnBuildSignature($token, $bodyData);

// Generate the fresh query string in the exact format requested
$queryString = "?appID=" . GWN_APP_ID . "&timestamp=" . $sig['timestamp'] . "&signature=" . $sig['signature'];

echo "\n========================================\n";
echo "FRESH QUERY STRING\n";
echo "========================================\n\n";

echo "Query String:\n";
echo $queryString . "\n\n";

echo "Full URL:\n";
echo "https://www.gwn.cloud/oapi/v1.0.0/client/edit" . $queryString . "\n\n";

echo "Request Body:\n";
echo $sig['bodyJson'] . "\n\n";

echo "Complete HTTP/1.1 Request:\n";
echo "========================================\n";
echo "POST /oapi/v1.0.0/client/edit" . $queryString . " HTTP/1.1\n";
echo "Host: www.gwn.cloud\n";
echo "Content-Type: application/json\n";
echo "Content-Length: " . strlen($sig['bodyJson']) . "\n";
echo "\n";
echo $sig['bodyJson'] . "\n";
echo "========================================\n\n";

echo "Curl Command:\n";
echo "========================================\n";
echo "curl -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit" . $queryString . "' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '" . $sig['bodyJson'] . "'\n";
echo "========================================\n";
?>
