<?php
/**
 * List available clients on GWN network for testing
 */

require_once 'includes/config.php';
require_once 'includes/python_interface.php';

echo "\n========================================\n";
echo "GWN CLIENT LISTING FOR EDIT API TESTING\n";
echo "========================================\n\n";

// Get token using same auth as voucher creation
$token = gwnGetToken();
if (!$token) {
    echo "ERROR: Could not obtain GWN token\n";
    echo "Check your GWN_APP_ID and GWN_SECRET_KEY credentials\n";
    exit(1);
}

echo "✓ Authentication successful\n";
echo "  Token: " . substr($token, 0, 20) . "...\n";
echo "  Network ID: " . GWN_NETWORK_ID . "\n";
echo "  App ID: " . GWN_APP_ID . "\n\n";

// List clients - same pattern as voucher creation
echo "Fetching client list from GWN Cloud...\n\n";

$clients = gwnListClients(1, 50);

if (!$clients) {
    echo "ERROR: Could not fetch clients from GWN\n";
    echo "This may be because:\n";
    echo "  1. No clients are connected to your network\n";
    echo "  2. API permissions issue\n";
    echo "  3. Network ID mismatch\n";
    exit(1);
}

if (empty($clients)) {
    echo "No clients found on the network\n";
    exit(1);
}

echo "Found " . count($clients) . " clients:\n\n";

$header = sprintf("%-20s | %-40s | %-20s", "MAC Address", "Hostname", "IP Address");
echo $header . "\n";
echo str_repeat("-", strlen($header)) . "\n";

foreach ($clients as $client) {
    $mac = isset($client['clientId']) ? $client['clientId'] : 'N/A';
    $name = isset($client['hostname']) ? substr($client['hostname'], 0, 40) : 'N/A';
    $ip = isset($client['ip']) ? $client['ip'] : 'N/A';
    
    printf("%-20s | %-40s | %-20s\n", $mac, $name, $ip);
}

echo "\n";
echo str_repeat("-", strlen($header)) . "\n";

// Show curl test commands for the first client
if (!empty($clients)) {
    $firstClient = $clients[0];
    $mac = $firstClient['clientId'] ?? null;
    
    if ($mac) {
        echo "\n✓ RECOMMENDED TEST CLIENT:\n";
        echo "  MAC: " . $mac . "\n";
        echo "  Hostname: " . ($firstClient['hostname'] ?? 'N/A') . "\n";
        echo "  IP: " . ($firstClient['ip'] ?? 'N/A') . "\n";
        
        // Generate a valid signature for testing
        $testName = "Updated-Client-" . date('HisY');
        $bodyData = [
            'clientId' => $mac,
            'name' => $testName
        ];
        
        $sig = gwnBuildSignature($token, $bodyData);
        
        echo "\n✓ CURL TEST COMMAND:\n\n";
        echo "curl -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?access_token=" . $token . "&appID=" . GWN_APP_ID . "&timestamp=" . $sig['timestamp'] . "&signature=" . $sig['signature'] . "' \\\n";
        echo "  -H 'Content-Type: application/json' \\\n";
        echo "  -d '" . $sig['bodyJson'] . "'\n\n";
    }
}

echo "========================================\n";
?>
