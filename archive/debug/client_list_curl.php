<?php
require_once 'includes/config.php';
require_once 'includes/python_interface.php';

$token = gwnGetToken();
if (!$token) {
    echo "ERROR: Could not obtain GWN token\n";
    exit(1);
}

$bodyData = [
    'networkId' => (int)GWN_NETWORK_ID,
    'pageNum' => 1,
    'pageSize' => 20,
    'untilNow' => 0,
];

$sig = gwnBuildSignature($token, $bodyData);
$url = GWN_API_URL . '/oapi/v1.0.0/client/list?access_token=' . $token . '&appID=' . GWN_APP_ID . '&timestamp=' . $sig['timestamp'] . '&signature=' . $sig['signature'];

echo "CURL:\n";
echo "curl.exe -X POST '" . $url . "' -H 'Content-Type: application/json' --data-raw '" . $sig['bodyJson'] . "'\n\n";

$result = gwnApiCall('/oapi/v1.0.0/client/list', $bodyData);
echo "RESPONSE:\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
