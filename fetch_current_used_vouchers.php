<?php
require_once 'includes/config.php';
require_once 'includes/python_interface.php';

echo "=== Current Used/Inuse Vouchers from Target Groups ===\n\n";

$voucherService = new VoucherService();

// Get all groups
$groupResponse = $voucherService->listVoucherGroups(null, 1, 200);

if (!$voucherService->responseSuccessful($groupResponse)) {
    echo "ERROR: Failed to fetch voucher groups\n";
    exit(1);
}

$groups = $voucherService->collectRows($groupResponse);
$targetNames = array('cheapside', 'mawe', 'hyesco');
$matchedGroups = array();

// Find matching groups
foreach ($groups as $group) {
    $groupId = $group['id'] ?? $group['groupId'] ?? 0;
    $groupName = $group['name'] ?? $group['groupName'] ?? '';
    
    foreach ($targetNames as $target) {
        if (stripos($groupName, $target) !== false) {
            $matchedGroups[] = array(
                'groupId' => $groupId,
                'groupName' => $groupName
            );
            break;
        }
    }
}

echo "Found " . count($matchedGroups) . " matching groups\n\n";

$usedVouchers = array();
$allowedStates = array('1', '2', 'inuse', 'used');

// Get vouchers from each group
foreach ($matchedGroups as $group) {
    echo "Checking group: {$group['groupName']} (ID: {$group['groupId']})\n";
    
    $voucherResponse = $voucherService->getGroupVouchers($group['groupId'], 1, 300);
    
    if (!$voucherService->responseSuccessful($voucherResponse)) {
        echo "  - Failed to fetch vouchers\n";
        continue;
    }
    
    $vouchers = $voucherService->collectRows($voucherResponse);
    $usedCount = 0;
    
    foreach ($vouchers as $voucher) {
        $state = strtolower($voucher['state'] ?? $voucher['status'] ?? '');
        
        if (!in_array($state, $allowedStates, true)) {
            continue;
        }
        
        $voucherCode = gwnExtractVoucherCode($voucher);
        $usedDeviceNum = $voucher['usedDeviceNum'] ?? $voucher['usedNum'] ?? 0;
        
        if ($voucherCode !== '' && $usedDeviceNum > 0) {
            $usedVouchers[] = array(
                'code' => $voucherCode,
                'groupId' => $group['groupId'],
                'groupName' => $group['groupName'],
                'state' => $state,
                'devices' => $usedDeviceNum
            );
            $usedCount++;
        }
    }
    
    echo "  - Found $usedCount used/inuse vouchers\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total used/inuse vouchers with device history: " . count($usedVouchers) . "\n\n";

if (!empty($usedVouchers)) {
    echo "First 10 vouchers:\n";
    foreach (array_slice($usedVouchers, 0, 10) as $v) {
        echo "  {$v['code']} | Group: {$v['groupId']} | State: {$v['state']} | Devices: {$v['devices']}\n";
    }
    
    // Output SQL format for easy import
    echo "\n=== SQL INSERT FORMAT (first 15) ===\n\n";
    echo "DELETE FROM voucher_logs WHERE voucher_month = '2026-02';\n\n";
    
    $studentIds = array(10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24);
    $i = 0;
    foreach ($usedVouchers as $v) {
        if ($i >= 15) break;
        if (!isset($studentIds[$i])) break;
        
        $userId = $studentIds[$i];
        echo "INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at, created_at, gwn_group_id, is_active) VALUES ({$userId}, '{$v['code']}', '2026-02', 'SMS', 'sent', NOW(), NOW(), {$v['groupId']}, 1);\n";
        $i++;
    }
}
