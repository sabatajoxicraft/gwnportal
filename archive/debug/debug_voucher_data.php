<?php
require_once 'includes/config.php';
require_once 'includes/python_interface/gwn_cloud.php';

echo "=== Debug Voucher Data from GWN API ===\n\n";

// Test one of our assigned voucher groups
$groupId = 241639; // Hyesco Arcade Feb 2026
$testVoucher = '10409944983'; // Assigned to user_id 10

echo "Querying group $groupId for voucher $testVoucher...\n\n";

$pageNum = 1;
$found = false;

$data = gwnGetVouchersInGroup($groupId, $pageNum, 200);

if ($data === false) {
    echo "ERROR: Failed to fetch vouchers from GWN\n";
    exit(1);
}

$rows = gwnCollectRows($data);
echo "Found " . count($rows) . " vouchers in group $groupId\n\n";

foreach ($rows as $row) {
    $voucherCode = gwnExtractVoucherCode($row);
    if ($voucherCode === $testVoucher) {
        $found = true;
        echo "✓ Found our test voucher: $testVoucher\n\n";
        echo "=== VOUCHER DATA ===\n";
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n\n";
        
        $mac = gwnExtractMacFromVoucherRow($row);
        $isUsed = gwnVoucherRowLooksUsed($row);
        
        echo "=== EXTRACTED DATA ===\n";
        echo "MAC address: " . ($mac !== '' ? $mac : 'NONE') . "\n";
        echo "Looks used: " . ($isUsed ? 'YES' : 'NO') . "\n";
        break;
    }
}

if (!$found) {
    echo "❌ Test voucher $testVoucher not found in group $groupId\n";
    echo "\nShowing first 3 vouchers for comparison:\n";
    foreach (array_slice($rows, 0, 3) as $row) {
        $code = gwnExtractVoucherCode($row);
        $mac = gwnExtractMacFromVoucherRow($row);
        $isUsed = gwnVoucherRowLooksUsed($row);
        echo "  - Code: $code, MAC: " . ($mac ?: 'NONE') . ", Used: " . ($isUsed ? 'YES' : 'NO') . "\n";
    }
}
