<?php
/**
 * Find Target Group Vouchers
 * 
 * Searches for used/in-use vouchers in specific groups (Cheapside, Mawe, Hyesco)
 * Uses gwn_cloud helpers for API calls
 * 
 * Usage: 
 * - Normal: /public/admin/find-target-group-vouchers.php
 * - Debug mode: /public/admin/find-target-group-vouchers.php?debug=1
 */
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/python_interface.php';
requireRole('admin');

$debug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';

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

$appendDebugContext = static function (&$lines) {
    $apiUrl = defined('GWN_API_URL') ? (string)GWN_API_URL : '';
    if ($apiUrl !== '') {
        $apiUrl = preg_replace('/\?.*/', '', $apiUrl);
    }

    $appId = defined('GWN_APP_ID') ? (string)GWN_APP_ID : '';
    $networkId = defined('GWN_NETWORK_ID') ? (string)GWN_NETWORK_ID : '';
    $secretSet = defined('GWN_SECRET_KEY') && (string)GWN_SECRET_KEY !== '' ? 'yes' : 'no';
    $staticToken = defined('GWN_ACCESS_TOKEN') ? (string)GWN_ACCESS_TOKEN : '';
    $staticTokenSet = $staticToken !== '' ? 'yes' : 'no';
    $curlAvailable = function_exists('curl_init') ? 'yes' : 'no';
    $phpVersion = PHP_VERSION;

    $tokenStatus = 'false';
    $tokenPreview = '';
    if (function_exists('gwnGetToken')) {
        $token = gwnGetToken();
        if ($token) {
            $tokenStatus = 'ok';
            $tokenPreview = substr($token, 0, 6) . '... (' . strlen($token) . ')';
        }
    }

    $lines[] = 'DEBUG api_url=' . $apiUrl;
    $lines[] = 'DEBUG app_id_length=' . strlen($appId);
    $lines[] = 'DEBUG network_id=' . $networkId;
    $lines[] = 'DEBUG secret_key_set=' . $secretSet;
    $lines[] = 'DEBUG access_token_set=' . $staticTokenSet;
    $lines[] = 'DEBUG curl_available=' . $curlAvailable;
    $lines[] = 'DEBUG php_version=' . $phpVersion;
    $lines[] = 'DEBUG token_status=' . $tokenStatus . ($tokenPreview !== '' ? ' token=' . $tokenPreview : '');
};

$outputLines = array();

$gwnService = new VoucherService();

$groupResponse = $gwnService->listVoucherGroups(null, 1, 200);

if (!is_array($groupResponse) || !$gwnService->responseSuccessful($groupResponse)) {
    $outputLines[] = 'GROUP_API_FAIL';
    if ($debug) {
        $outputLines[] = $buildDiagnosticLine('/oapi/v1.0.0/voucher/group/list', $groupResponse);
        $appendDebugContext($outputLines);
        if (!empty($GLOBALS['gwn_token_debug'])) {
            $outputLines[] = 'DEBUG token_attempts=' . json_encode($GLOBALS['gwn_token_debug']);
        }
        if (!empty($GLOBALS['gwn_api_debug'])) {
            $outputLines[] = 'DEBUG api_calls=' . json_encode($GLOBALS['gwn_api_debug']);
        }
    }
    echo '<pre>' . htmlspecialchars(implode("\n", $outputLines), ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

$groups = $gwnService->collectRows($groupResponse);
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
    $outputLines[] = 'NO_MATCHING_GROUPS';
    echo '<pre>' . htmlspecialchars(implode("\n", $outputLines), ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

$printed = 0;
$allowedStates = array('1', '2', 'inuse', 'used');

foreach ($matchedGroups as $matchedGroup) {
    $voucherResponse = $gwnService->getGroupVouchers(
        (int)$matchedGroup['groupId'],
        1,
        200
    );

    if ($voucherResponse === false) {
        if ($debug) {
            $outputLines[] = $buildDiagnosticLine('/oapi/v1.0.0/voucher/vouchers/list', $voucherResponse) . ' groupId=' . (int)$matchedGroup['groupId'];
        }
        continue;
    }

    if (!$gwnService->responseSuccessful($voucherResponse)) {
        if ($debug) {
            $outputLines[] = $buildDiagnosticLine('/oapi/v1.0.0/voucher/vouchers/list', $voucherResponse) . ' groupId=' . (int)$matchedGroup['groupId'];
        }
        continue;
    }

    $vouchers = $gwnService->collectRows($voucherResponse);
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

        $outputLines[] = $matchedGroup['groupId'] . ' | ' . $matchedGroup['groupName'] . ' | ' . $voucherId . ' | ' . $voucherCode . ' | ' . $state . ' | ' . $usedDeviceNum;
        $printed++;
    }
}

if ($printed === 0) {
    $outputLines[] = 'NO_USED_VOUCHERS_FOUND';
}

echo '<pre>' . htmlspecialchars(implode("\n", $outputLines), ENT_QUOTES, 'UTF-8') . '</pre>';
