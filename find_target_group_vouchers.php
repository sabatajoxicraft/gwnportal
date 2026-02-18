<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/python_interface.php';

$debug = false;
if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    $debug = in_array('--debug', $argv, true);
}

$buildDiagnosticLine = static function ($endpoint, $response) {
    $retCode = '';
    $msg = '';
    $httpCode = '';

    if (is_array($response)) {
        if (isset($response['retCode']) && !is_array($response['retCode'])) {
            $retCode = trim((string)$response['retCode']);
        } elseif (isset($response['code']) && !is_array($response['code'])) {
            $retCode = trim((string)$response['code']);
        }

        if (isset($response['msg']) && !is_array($response['msg'])) {
            $msg = trim((string)$response['msg']);
        } elseif (isset($response['message']) && !is_array($response['message'])) {
            $msg = trim((string)$response['message']);
        }

        if (isset($response['httpCode']) && !is_array($response['httpCode'])) {
            $httpCode = trim((string)$response['httpCode']);
        }
    } else {
        $msg = 'non_array_response';
    }

    $msg = str_replace(array("\r", "\n"), ' ', $msg);

    return 'DIAG endpoint=' . $endpoint . ' retCode=' . $retCode . ' msg=' . $msg . ' httpCode=' . $httpCode;
};

$networkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
$groupPayload = array(
    'networkId' => $networkId,
    'pageNum' => 1,
    'pageSize' => 200,
);

$groupEndpoints = array(
    '/oapi/v1.0.0/voucher/list',
    '/oapi/v1.0.0/voucher/group/list',
);
$groupResponse = false;
$groupDiagnostics = array();

$firstGroupEndpoint = $groupEndpoints[0];
$groupResponse = gwnApiCall($firstGroupEndpoint, $groupPayload);
$firstGroupSuccessful = gwnResponseSuccessful($groupResponse);
$firstGroupRows = $firstGroupSuccessful ? gwnCollectRows($groupResponse) : array();

if (!$firstGroupSuccessful) {
    $groupDiagnostics[] = $buildDiagnosticLine($firstGroupEndpoint, $groupResponse);
}

if ((!$firstGroupSuccessful || empty($firstGroupRows)) && isset($groupEndpoints[1])) {
    $secondGroupEndpoint = $groupEndpoints[1];
    $secondGroupResponse = gwnApiCall($secondGroupEndpoint, $groupPayload);
    $secondGroupSuccessful = gwnResponseSuccessful($secondGroupResponse);

    if ($secondGroupSuccessful) {
        $groupResponse = $secondGroupResponse;
    } elseif (!$firstGroupSuccessful) {
        $groupResponse = $secondGroupResponse;
    }

    if (!$secondGroupSuccessful) {
        $groupDiagnostics[] = $buildDiagnosticLine($secondGroupEndpoint, $secondGroupResponse);
    }
}

if (!gwnResponseSuccessful($groupResponse)) {
    echo "GROUP_API_FAIL\n";
    foreach ($groupDiagnostics as $diagnosticLine) {
        echo $diagnosticLine . "\n";
    }
    exit;
}

$groups = gwnCollectRows($groupResponse);
$matchedGroups = array();

foreach ($groups as $group) {
    if (!is_array($group)) {
        continue;
    }

    $groupId = 0;
    if (isset($group['groupId']) && !is_array($group['groupId'])) {
        $groupId = (int)$group['groupId'];
    } elseif (isset($group['voucherGroupId']) && !is_array($group['voucherGroupId'])) {
        $groupId = (int)$group['voucherGroupId'];
    } elseif (isset($group['id']) && !is_array($group['id'])) {
        $groupId = (int)$group['id'];
    }

    if ($groupId <= 0) {
        continue;
    }

    $groupName = '';
    if (isset($group['groupName']) && !is_array($group['groupName'])) {
        $groupName = trim((string)$group['groupName']);
    } elseif (isset($group['name']) && !is_array($group['name'])) {
        $groupName = trim((string)$group['name']);
    } elseif (isset($group['voucherGroupName']) && !is_array($group['voucherGroupName'])) {
        $groupName = trim((string)$group['voucherGroupName']);
    }

    $groupNameLower = strtolower($groupName);
    if (
        strpos($groupNameLower, 'cheapside') !== false ||
        strpos($groupNameLower, 'mawe') !== false ||
        strpos($groupNameLower, 'hyesco') !== false
    ) {
        $matchedGroups[] = array(
            'groupId' => $groupId,
            'groupName' => $groupName,
        );
    }
}

if (empty($matchedGroups)) {
    echo "NO_MATCHING_GROUPS\n";
    exit;
}

$printed = 0;
$allowedStates = array('1', '2', 'inuse', 'used');

foreach ($matchedGroups as $matchedGroup) {
    $voucherPayload = array(
        'networkId' => $networkId,
        'groupId' => (int)$matchedGroup['groupId'],
        'pageNum' => 1,
        'pageSize' => 200,
    );

    $voucherEndpoint = '/oapi/v1.0.0/voucher/vouchers/list';
    $voucherResponse = gwnApiCall($voucherEndpoint, $voucherPayload);
    if (!gwnResponseSuccessful($voucherResponse)) {
        if ($debug) {
            echo $buildDiagnosticLine($voucherEndpoint, $voucherResponse) . ' groupId=' . (int)$matchedGroup['groupId'] . "\n";
        }
        continue;
    }

    $vouchers = gwnCollectRows($voucherResponse);
    foreach ($vouchers as $voucher) {
        if (!is_array($voucher)) {
            continue;
        }

        $state = '';
        if (isset($voucher['state']) && !is_array($voucher['state'])) {
            $state = trim((string)$voucher['state']);
        } elseif (isset($voucher['status']) && !is_array($voucher['status'])) {
            $state = trim((string)$voucher['status']);
        }

        $stateCheck = strtolower($state);
        if (!in_array($stateCheck, $allowedStates, true)) {
            continue;
        }

        $voucherId = '';
        if (isset($voucher['voucherId']) && !is_array($voucher['voucherId'])) {
            $voucherId = trim((string)$voucher['voucherId']);
        } elseif (isset($voucher['id']) && !is_array($voucher['id'])) {
            $voucherId = trim((string)$voucher['id']);
        }

        $voucherCode = gwnExtractVoucherCode($voucher);

        $usedDeviceNum = '';
        if (isset($voucher['usedDeviceNum']) && !is_array($voucher['usedDeviceNum'])) {
            $usedDeviceNum = trim((string)$voucher['usedDeviceNum']);
        } elseif (isset($voucher['usedNum']) && !is_array($voucher['usedNum'])) {
            $usedDeviceNum = trim((string)$voucher['usedNum']);
        }

        echo $matchedGroup['groupId'] . ' | ' . $matchedGroup['groupName'] . ' | ' . $voucherId . ' | ' . $voucherCode . ' | ' . $state . ' | ' . $usedDeviceNum . "\n";
        $printed++;
    }
}

if ($printed === 0) {
    echo "NO_USED_VOUCHERS_FOUND\n";
}
