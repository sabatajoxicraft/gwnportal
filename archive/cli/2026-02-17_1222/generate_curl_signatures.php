<?php
/**
 * Generate valid GWN API signatures for curl testing
 */

require_once 'includes/config.php';
require_once 'includes/python_interface.php';

echo "=== GWN API Signature Generator for Curl Testing ===\n\n";

// Get a valid token first
$token = gwnGetToken();
if (!$token) {
    echo "ERROR: Could not obtain GWN token\n";
    exit(1);
}

echo "Token obtained: " . substr($token, 0, 20) . "...\n\n";

// Test Case 1: Valid MAC with name change
echo "TEST 1: Valid MAC with name change\n";
$bodyData1 = [
    'clientId' => '20:EE:28:86:C2:ED',
    'name' => 'TestClient123'
];
$sig1 = gwnBuildSignature($token, $bodyData1);
echo "URL: https://www.gwn.cloud/oapi/v1.0.0/client/edit";
echo "?appID=" . GWN_APP_ID;
echo "&timestamp=" . $sig1['timestamp'];
echo "&signature=" . $sig1['signature'] . "\n";
echo "Body: " . $sig1['bodyJson'] . "\n";
echo "Curl command:\n";
echo "curl -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=" . GWN_APP_ID . "&timestamp=" . $sig1['timestamp'] . "&signature=" . $sig1['signature'] . "' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '" . $sig1['bodyJson'] . "'\n\n";

// Test Case 2: Invalid MAC
echo "TEST 2: Invalid MAC (too short)\n";
$bodyData2 = [
    'clientId' => '11:22:33:44',
    'name' => 'TestClient'
];
$sig2 = gwnBuildSignature($token, $bodyData2);
echo "Curl command:\n";
echo "curl -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=" . GWN_APP_ID . "&timestamp=" . $sig2['timestamp'] . "&signature=" . $sig2['signature'] . "' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '" . $sig2['bodyJson'] . "'\n\n";

// Test Case 3: Name exceeding 64 chars
echo "TEST 3: Name exceeding 64 characters\n";
$longName = str_repeat('A', 100);
$bodyData3 = [
    'clientId' => '20:EE:28:86:C2:ED',
    'name' => $longName
];
$sig3 = gwnBuildSignature($token, $bodyData3);
echo "Curl command:\n";
echo "curl -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=" . GWN_APP_ID . "&timestamp=" . $sig3['timestamp'] . "&signature=" . $sig3['signature'] . "' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '" . $sig3['bodyJson'] . "'\n\n";

// Test Case 4: Non-existent MAC
echo "TEST 4: Non-existent MAC\n";
$bodyData4 = [
    'clientId' => 'FF:FF:FF:FF:FF:FF',
    'name' => 'NonExistentClient'
];
$sig4 = gwnBuildSignature($token, $bodyData4);
echo "Curl command:\n";
echo "curl -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=" . GWN_APP_ID . "&timestamp=" . $sig4['timestamp'] . "&signature=" . $sig4['signature'] . "' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '" . $sig4['bodyJson'] . "'\n\n";
?>
