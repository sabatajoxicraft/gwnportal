<?php
require_once 'includes/config.php';
require_once 'includes/python_interface/gwn_cloud.php';

echo "=== Raw Voucher Group Data ===\n\n";

$groupId = 241639;

$data = gwnGetVouchersInGroup($groupId, 1, 10);

if ($data === false) {
    echo "ERROR: Failed to fetch vouchers\n";
    exit(1);
}

echo "=== FULL RAW RESPONSE ===\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
