<?php
/**
 * Execute curl tests for GWN Client Edit API
 */

require_once 'includes/config.php';
require_once 'includes/python_interface.php';

echo "\n========================================\n";
echo "GWN CLIENT EDIT API - CURL TESTS\n";
echo "========================================\n\n";

// Get fresh token
$token = gwnGetToken();
if (!$token) {
    echo "ERROR: Could not obtain GWN token\n";
    exit(1);
}

echo "Using credentials:\n";
echo "  Domain: https://www.gwn.cloud\n";
echo "  App ID: " . GWN_APP_ID . "\n";
echo "  Token: " . substr($token, 0, 20) . "...\n\n";

$tests = [
    [
        'name' => 'TEST 1: Valid MAC with name change',
        'clientId' => '20:EE:28:86:C2:ED',
        'name' => 'TestClient123',
        'expectedError' => '50004 (Service error - subscription needed)',
    ],
    [
        'name' => 'TEST 2: Invalid MAC (too short)',
        'clientId' => '11:22:33:44',
        'name' => 'TestClient',
        'expectedError' => '50007 (invalid mac)',
    ],
    [
        'name' => 'TEST 3: Name exceeding 64 characters',
        'clientId' => '20:EE:28:86:C2:ED',
        'name' => str_repeat('A', 100),
        'expectedError' => '40004 (client length error)',
    ],
    [
        'name' => 'TEST 4: Non-existent MAC',
        'clientId' => 'FF:FF:FF:FF:FF:FF',
        'name' => 'NonExistentClient',
        'expectedError' => '50004 (Service error)',
    ],
];

foreach ($tests as $index => $test) {
    echo str_repeat("=", 50) . "\n";
    echo $test['name'] . "\n";
    echo str_repeat("=", 50) . "\n";
    
    $bodyData = [
        'clientId' => $test['clientId'],
        'name' => $test['name']
    ];
    
    $sig = gwnBuildSignature($token, $bodyData);
    
    echo "Endpoint: POST https://www.gwn.cloud/oapi/v1.0.0/client/edit\n";
    echo "Parameters:\n";
    echo "  clientId: " . $test['clientId'] . "\n";
    echo "  name: " . substr($test['name'], 0, 50) . (strlen($test['name']) > 50 ? "..." : "") . "\n";
    echo "\nExpected Error: " . $test['expectedError'] . "\n";
    echo "\nCurl Command:\n";
    echo "curl -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=" . GWN_APP_ID . "&timestamp=" . $sig['timestamp'] . "&signature=" . $sig['signature'] . "' \\\n";
    echo "  -H 'Content-Type: application/json' \\\n";
    echo "  -d '" . addslashes($sig['bodyJson']) . "'\n";
    
    echo "\nResponse:\n";
    
    // Execute the curl command
    $url = 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=' . GWN_APP_ID . '&timestamp=' . $sig['timestamp'] . '&signature=' . $sig['signature'];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $sig['bodyJson'],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            echo $response . "\n";
        }
    } else {
        echo "CURL Error\n";
    }
    
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "\n";
}

echo "========================================\n";
echo "All Tests Complete\n";
echo "========================================\n";
?>
