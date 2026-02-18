<?php
require_once 'includes/config.php';
require_once 'includes/python_interface/gwn_cloud.php';

echo "=== Debug GWN Client API Response ===\n\n";

$body = array(
    'pageNum' => 1,
    'pageSize' => 50,
    'untilNow' => 0,
    'networkId' => GWN_NETWORK_ID
);

$response = gwnApiCall('/oapi/v1.0.0/client/list', $body, 'POST');

echo "Response type: " . gettype($response) . "\n";
echo "Response successful: " . (gwnResponseSuccessful($response) ? 'YES' : 'NO') . "\n\n";

if ($response) {
    echo "=== RAW RESPONSE ===\n";
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
    
    $normalized = gwnNormalizeListResponse($response, 1, 50);
    echo "=== NORMALIZED DATA ===\n";
    echo json_encode($normalized, JSON_PRETTY_PRINT) . "\n";
}
