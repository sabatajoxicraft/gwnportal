<?php
/**
 * List available clients on GWN network - DEBUG VERSION
 */

require_once 'includes/config.php';
require_once 'includes/python_interface.php';

echo "\n========================================\n";
echo "GWN CLIENT LISTING - DEBUG\n";
echo "========================================\n\n";

$token = gwnGetToken();
if (!$token) {
    echo "ERROR: Could not obtain GWN token\n";
    exit(1);
}

echo "✓ Authentication successful\n";
echo "  Token: " . substr($token, 0, 20) . "...\n";
echo "  Network ID: " . GWN_NETWORK_ID . "\n";
echo "  App ID: " . GWN_APP_ID . "\n\n";

// Get raw API response
echo "Making API call to /oapi/v1.0.0/client/list...\n";

$bodyData = [
    'networkId' => (int)GWN_NETWORK_ID,
    'pageNum' => 1,
    'pageSize' => 50
];

$sig = gwnBuildSignature($token, $bodyData);
$queryParams = http_build_query([
    'access_token' => $token,
    'appID'        => GWN_APP_ID,
    'timestamp'    => $sig['timestamp'],
    'signature'    => $sig['signature'],
]);

$url = GWN_API_URL . '/oapi/v1.0.0/client/list' . '?' . $queryParams;

echo "URL: " . str_replace($token, "[TOKEN]", $url) . "\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $sig['bodyJson'],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n\n";

echo "Raw Response:\n";
echo $response . "\n\n";

$decoded = json_decode($response, true);
echo "Decoded JSON:\n";
echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

if (isset($decoded['data'])) {
    echo "Data field structure:\n";
    if (is_array($decoded['data'])) {
        echo "Type: Array with " . count($decoded['data']) . " items\n\n";
        if (!empty($decoded['data'])) {
            echo "First item:\n";
            echo json_encode($decoded['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }
    } else {
        echo "Type: " . gettype($decoded['data']) . "\n";
        echo json_encode($decoded['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

echo "\n========================================\n";
?>
