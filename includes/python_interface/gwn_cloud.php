<?php

if (!function_exists('gwnIsListArray')) {
    function gwnIsListArray($value) {
        if (!is_array($value)) {
            return false;
        }
        $index = 0;
        foreach ($value as $key => $_unused) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }
        return true;
    }
}

if (!function_exists('gwnPrepare')) {
    function gwnPrepare($conn, $sql) {
        if (!$conn || !is_string($sql) || $sql === '') {
            return false;
        }

        if (function_exists('safeQueryPrepare')) {
            return safeQueryPrepare($conn, $sql, false);
        }

        try {
            return $conn->prepare($sql);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('gwnNormalizeMac')) {
    function gwnNormalizeMac($mac) {
        if (function_exists('formatMacAddress')) {
            $formatted = formatMacAddress($mac);
            if (!empty($formatted)) {
                return $formatted;
            }
        }

        $clean = preg_replace('/[^a-fA-F0-9]/', '', (string)$mac);
        if (strlen($clean) !== 12) {
            return '';
        }

        return strtoupper(
            substr($clean, 0, 2) . ':' .
            substr($clean, 2, 2) . ':' .
            substr($clean, 4, 2) . ':' .
            substr($clean, 6, 2) . ':' .
            substr($clean, 8, 2) . ':' .
            substr($clean, 10, 2)
        );
    }
}

if (!function_exists('gwnTokenCacheFile')) {
    function gwnTokenCacheFile() {
        $api = defined('GWN_API_URL') ? (string)GWN_API_URL : '';
        $app = defined('GWN_APP_ID') ? (string)GWN_APP_ID : '';
        return rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'gwn_portal_token_' . md5($api . '|' . $app) . '.json';
    }
}

if (!function_exists('gwnGetStaticToken')) {
    function gwnGetStaticToken() {
        $candidates = array(
            getenv('GWN_ACCESS_TOKEN'),
            getenv('GWN_TOKEN'),
            getenv('GWN_STATIC_TOKEN'),
            defined('GWN_ACCESS_TOKEN') ? GWN_ACCESS_TOKEN : '',
            defined('GWN_TOKEN') ? GWN_TOKEN : '',
            defined('GWN_STATIC_TOKEN') ? GWN_STATIC_TOKEN : '',
        );

        foreach ($candidates as $token) {
            $token = trim((string)$token);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }
}

if (!function_exists('gwnExtractTokenFromResponse')) {
    function gwnExtractTokenFromResponse($response) {
        if (!is_array($response)) {
            return '';
        }

        $tokenKeys = array('access_token', 'token', 'accessToken');
        foreach ($tokenKeys as $key) {
            if (!empty($response[$key])) {
                return trim((string)$response[$key]);
            }
        }

        foreach (array('data', 'result') as $container) {
            if (!empty($response[$container]) && is_array($response[$container])) {
                foreach ($tokenKeys as $key) {
                    if (!empty($response[$container][$key])) {
                        return trim((string)$response[$container][$key]);
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('gwnResponseSuccessful')) {
    function gwnResponseSuccessful($response) {
        if (!is_array($response)) {
            return false;
        }

        if (isset($response['httpCode']) && (int)$response['httpCode'] >= 400) {
            return false;
        }

        if (isset($response['retCode'])) {
            return ((int)$response['retCode'] === 0);
        }

        if (isset($response['code']) && is_numeric($response['code'])) {
            return ((int)$response['code'] === 0);
        }

        if (isset($response['success'])) {
            return (bool)$response['success'];
        }

        if (isset($response['error']) && $response['error']) {
            return false;
        }

        return true;
    }
}

if (!function_exists('gwnExtractPayload')) {
    function gwnExtractPayload($response) {
        if (!is_array($response)) {
            return array();
        }
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }
        return $response;
    }
}

if (!function_exists('gwnNormalizeListResponse')) {
    function gwnNormalizeListResponse($response, $pageNum = 1, $pageSize = 100) {
        $payload = gwnExtractPayload($response);

        if (isset($payload['result']) && is_array($payload['result'])) {
            return $payload;
        }

        if (isset($payload['list']) && is_array($payload['list'])) {
            $payload['result'] = $payload['list'];
            if (!isset($payload['totalPage'])) {
                $payload['totalPage'] = 1;
            }
            return $payload;
        }

        if (gwnIsListArray($payload)) {
            return array(
                'result' => $payload,
                'totalPage' => 1,
                'pageNum' => (int)$pageNum,
                'pageSize' => (int)$pageSize,
            );
        }

        return array(
            'result' => array(),
            'totalPage' => 1,
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );
    }
}

if (!function_exists('gwnCollectRows')) {
    function gwnCollectRows($payload) {
        if (!is_array($payload)) {
            return array();
        }

        if (gwnIsListArray($payload)) {
            return $payload;
        }

        $keys = array('result', 'list', 'rows', 'items', 'records', 'vouchers', 'voucherList', 'clients', 'data');
        foreach ($keys as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            if (gwnIsListArray($payload[$key])) {
                return $payload[$key];
            }

            $nested = gwnCollectRows($payload[$key]);
            if (!empty($nested)) {
                return $nested;
            }
        }

        return array();
    }
}

if (!function_exists('gwnExtractVoucherCode')) {
    function gwnExtractVoucherCode($row) {
        if (!is_array($row)) {
            return '';
        }

        $keys = array('voucherCode', 'voucher_code', 'code', 'password', 'voucherPwd');
        foreach ($keys as $key) {
            if (isset($row[$key]) && !is_array($row[$key])) {
                $value = trim((string)$row[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if (!empty($row['voucher']) && is_array($row['voucher'])) {
            return gwnExtractVoucherCode($row['voucher']);
        }

        return '';
    }
}

if (!function_exists('gwnExtractMacFromVoucherRow')) {
    function gwnExtractMacFromVoucherRow($row) {
        if (!is_array($row)) {
            return '';
        }

        $keys = array('clientMac', 'clientId', 'mac', 'macAddress', 'staMac', 'stationMac', 'userMac');
        foreach ($keys as $key) {
            if (!isset($row[$key]) || is_array($row[$key])) {
                continue;
            }
            $mac = gwnNormalizeMac($row[$key]);
            if ($mac !== '') {
                return $mac;
            }
        }

        foreach (array('client', 'device', 'terminal') as $container) {
            if (isset($row[$container]) && is_array($row[$container])) {
                $mac = gwnExtractMacFromVoucherRow($row[$container]);
                if ($mac !== '') {
                    return $mac;
                }
            }
        }

        if (!empty($row['clients']) && is_array($row['clients'])) {
            foreach ($row['clients'] as $client) {
                $mac = gwnExtractMacFromVoucherRow($client);
                if ($mac !== '') {
                    return $mac;
                }
            }
        }

        return '';
    }
}

if (!function_exists('gwnVoucherRowLooksUsed')) {
    function gwnVoucherRowLooksUsed($row) {
        if (!is_array($row)) {
            return false;
        }

        $stateKeys = array('state', 'status', 'voucherState', 'voucher_status');
        foreach ($stateKeys as $key) {
            if (!isset($row[$key]) || is_array($row[$key])) {
                continue;
            }
            $value = strtolower(trim((string)$row[$key]));
            if ($value === '') {
                continue;
            }
            if (in_array($value, array('used', 'inuse', 'in_use', 'active', 'online', '1', '2', 'true', 'enable', 'enabled'), true)) {
                return true;
            }
        }

        $countKeys = array('usedNum', 'usedCount', 'useCount', 'authCount', 'authorizedCount', 'onlineCount', 'usedDeviceNum', 'usedVoucherNum');
        foreach ($countKeys as $key) {
            if (!isset($row[$key])) {
                continue;
            }
            if ((int)$row[$key] > 0) {
                return true;
            }
        }

        foreach (array('voucher', 'client', 'device') as $container) {
            if (!empty($row[$container]) && is_array($row[$container]) && gwnVoucherRowLooksUsed($row[$container])) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('gwnGetToken')) {
    function gwnGetToken() {
        static $memoryToken = '';
        static $memoryExpiry = 0;
        static $lastDebug = array();

        $debugEnabled = isset($_GET['debug']) && (string)$_GET['debug'] === '1';
        if ($debugEnabled) {
            $lastDebug = array();
        }

        $now = time();
        if ($memoryToken !== '' && $memoryExpiry > ($now + 5)) {
            return $memoryToken;
        }

        $cacheFile = gwnTokenCacheFile();
        if (is_readable($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['token'])) {
                $expiresAt = (int)($cached['expires_at'] ?? 0);
                if ($expiresAt > ($now + 5)) {
                    $memoryToken = trim((string)$cached['token']);
                    $memoryExpiry = $expiresAt;
                    if ($memoryToken !== '') {
                        return $memoryToken;
                    }
                }
            }
        }

        $staticToken = gwnGetStaticToken();
        if ($staticToken !== '') {
            $memoryToken = $staticToken;
            $memoryExpiry = $now + 240;
            @file_put_contents($cacheFile, json_encode(array(
                'token' => $memoryToken,
                'expires_at' => $memoryExpiry,
            ), JSON_UNESCAPED_SLASHES), LOCK_EX);
            return $memoryToken;
        }

        $apiBase = rtrim((string)(defined('GWN_API_URL') ? GWN_API_URL : ''), '/');
        $appId = (string)(defined('GWN_APP_ID') ? GWN_APP_ID : '');
        $secret = (string)(defined('GWN_SECRET_KEY') ? GWN_SECRET_KEY : '');

        if ($apiBase === '' || $appId === '' || $secret === '' || !function_exists('curl_init')) {
            return false;
        }

        $requestAttempts = array(
            array(
                'method' => 'POST',
                'endpoint' => '/oauth/token',
                'payload' => array('client_id' => $appId, 'client_secret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'form',
            ),
            array(
                'method' => 'POST',
                'endpoint' => '/oapi/v1.0.0/oauth/token',
                'payload' => array('client_id' => $appId, 'client_secret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'form',
            ),
            array(
                'method' => 'POST',
                'endpoint' => '/oauth/token',
                'payload' => array('appID' => $appId, 'secret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'json',
            ),
            array(
                'method' => 'POST',
                'endpoint' => '/oapi/v1.0.0/oauth/token',
                'payload' => array('appID' => $appId, 'secret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'json',
            ),
            array(
                'method' => 'POST',
                'endpoint' => '/oapi/v1.0.0/oauth/token',
                'payload' => array('appID' => $appId, 'appSecret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'json',
            ),
            array(
                'method' => 'GET',
                'endpoint' => '/oauth/token',
                'payload' => array('client_id' => $appId, 'client_secret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'query',
            ),
            array(
                'method' => 'GET',
                'endpoint' => '/oapi/v1.0.0/oauth/token',
                'payload' => array('client_id' => $appId, 'client_secret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'query',
            ),
            array(
                'method' => 'GET',
                'endpoint' => '/oauth/token',
                'payload' => array('appID' => $appId, 'secret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'query',
            ),
            array(
                'method' => 'GET',
                'endpoint' => '/oapi/v1.0.0/oauth/token',
                'payload' => array('appID' => $appId, 'secret' => $secret, 'grant_type' => 'client_credentials'),
                'contentType' => 'query',
            ),
        );

        foreach ($requestAttempts as $attempt) {
            $method = strtoupper((string)$attempt['method']);
            $endpoint = (string)$attempt['endpoint'];
            $payload = (array)$attempt['payload'];
            $url = $apiBase . $endpoint;
            $contentType = isset($attempt['contentType']) ? (string)$attempt['contentType'] : 'json';

            $ch = curl_init($url);
            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => array('Accept: application/json'),
            );

            if ($method === 'GET') {
                $url .= '?' . http_build_query($payload);
                $options[CURLOPT_URL] = $url;
            } else {
                $options[CURLOPT_POST] = true;
                if ($contentType === 'form') {
                    $options[CURLOPT_POSTFIELDS] = http_build_query($payload);
                    $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
                } else {
                    $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
                    if (!is_string($jsonBody) || $jsonBody === '') {
                        continue;
                    }
                    $options[CURLOPT_POSTFIELDS] = $jsonBody;
                    $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
                }
            }

            curl_setopt_array($ch, $options);
            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error || $raw === false || $raw === '') {
                if ($debugEnabled) {
                    $lastDebug[] = array(
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'contentType' => $contentType,
                        'httpCode' => $httpCode,
                        'error' => $error,
                        'raw' => $raw,
                    );
                }
                continue;
            }

            $decoded = json_decode($raw, true);
            if ($debugEnabled) {
                $lastDebug[] = array(
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'contentType' => $contentType,
                    'httpCode' => $httpCode,
                    'error' => $error,
                    'raw' => $raw,
                    'decoded' => is_array($decoded) ? $decoded : null,
                );
            }
            $token = gwnExtractTokenFromResponse($decoded);
            if ($token === '') {
                continue;
            }

            $expiresIn = 300;
            if (is_array($decoded)) {
                foreach (array('expires_in', 'expiresIn') as $expKey) {
                    if (!empty($decoded[$expKey])) {
                        $expiresIn = (int)$decoded[$expKey];
                    }
                }
                if (!empty($decoded['data']) && is_array($decoded['data'])) {
                    foreach (array('expires_in', 'expiresIn') as $expKey) {
                        if (!empty($decoded['data'][$expKey])) {
                            $expiresIn = (int)$decoded['data'][$expKey];
                        }
                    }
                }
            }

            if ($expiresIn < 60) {
                $expiresIn = 300;
            }
            if ($expiresIn > 900) {
                $expiresIn = 900;
            }

            $memoryToken = $token;
            $memoryExpiry = $now + $expiresIn - 20;
            if ($memoryExpiry <= $now) {
                $memoryExpiry = $now + 240;
            }

            @file_put_contents($cacheFile, json_encode(array(
                'token' => $memoryToken,
                'expires_at' => $memoryExpiry,
            ), JSON_UNESCAPED_SLASHES), LOCK_EX);

            return $memoryToken;
        }

        if ($debugEnabled) {
            $GLOBALS['gwn_token_debug'] = $lastDebug;
        }

        return false;
    }
}

if (!function_exists('gwnBuildSignature')) {
    function gwnBuildSignature($token, $bodyData = array()) {
        if (!is_array($bodyData)) {
            $bodyData = array();
        }

        $bodyJson = '{}';
        if (!empty($bodyData)) {
            $encoded = json_encode($bodyData, JSON_UNESCAPED_SLASHES);
            if (is_string($encoded) && $encoded !== '') {
                $bodyJson = $encoded;
            }
        }

        $timestamp = (string)round(microtime(true) * 1000);
        $secret = (string)(defined('GWN_SECRET_KEY') ? GWN_SECRET_KEY : '');
        $appId = (string)(defined('GWN_APP_ID') ? GWN_APP_ID : '');

        // GWN Cloud requires params in alphabetical order with secretKey included
        $params = 'access_token=' . (string)$token . '&appID=' . $appId . '&secretKey=' . $secret . '&timestamp=' . $timestamp;
        $bodyHash = hash('sha256', $bodyJson);
        $stringToSign = '&' . $params . '&' . $bodyHash . '&';
        // GWN uses plain SHA256, NOT HMAC-SHA256
        $signature = hash('sha256', $stringToSign);

        return array(
            'timestamp' => $timestamp,
            'signature' => $signature,
            'bodyJson' => $bodyJson,
        );
    }
}

if (!function_exists('gwnApiCall')) {
    function gwnApiCall($endpoint, $bodyData = array(), $method = 'POST') {
        static $debugInit = false;
        $debugEnabled = isset($_GET['debug']) && (string)$_GET['debug'] === '1';
        if ($debugEnabled && !$debugInit) {
            $GLOBALS['gwn_api_debug'] = array();
            $debugInit = true;
        }

        $token = gwnGetToken();
        if (!$token || !function_exists('curl_init')) {
            return false;
        }

        $apiBase = rtrim((string)(defined('GWN_API_URL') ? GWN_API_URL : ''), '/');
        if ($apiBase === '') {
            return false;
        }

        $endpoint = trim((string)$endpoint);
        if ($endpoint === '') {
            return false;
        }
        if ($endpoint[0] !== '/') {
            $endpoint = '/' . $endpoint;
        }

        // Prevent duplicated /oapi/v1.0.0 when base URL already contains it.
        if (preg_match('#/oapi/v1\\.0\\.0/?$#i', $apiBase) && strpos($endpoint, '/oapi/v1.0.0/') === 0) {
            $endpoint = substr($endpoint, strlen('/oapi/v1.0.0'));
            if ($endpoint === '') {
                $endpoint = '/';
            }
        }

        if (!is_array($bodyData)) {
            $bodyData = array();
        }

        $method = strtoupper(trim((string)$method));
        if ($method === '') {
            $method = 'POST';
        }

        $signature = gwnBuildSignature($token, $bodyData);
        $params = array(
            'access_token' => $token,
            'appID' => (string)(defined('GWN_APP_ID') ? GWN_APP_ID : ''),
            'timestamp' => $signature['timestamp'],
            'signature' => $signature['signature'],
        );

        if ($method === 'GET' && !empty($bodyData)) {
            foreach ($bodyData as $key => $value) {
                if (!is_scalar($value) || $value === '') {
                    continue;
                }
                $params[$key] = $value;
            }
        }

        $url = $apiBase . $endpoint . '?' . http_build_query($params);

        $ch = curl_init($url);
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
            ),
            CURLOPT_CUSTOMREQUEST => $method,
        );

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $signature['bodyJson'];
        } elseif ($method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = $signature['bodyJson'];
        }

        curl_setopt_array($ch, $options);
        $rawResponse = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($debugEnabled) {
            $safeUrl = preg_replace('/(access_token=)[^&]+/i', '$1REDACTED', $url);
            $safeUrl = preg_replace('/(signature=)[^&]+/i', '$1REDACTED', $safeUrl);
            $GLOBALS['gwn_api_debug'][] = array(
                'endpoint' => $endpoint,
                'method' => $method,
                'url' => $safeUrl,
                'httpCode' => $httpCode,
                'error' => $error,
                'rawPreview' => is_string($rawResponse) ? substr($rawResponse, 0, 300) : '',
                'rawLength' => is_string($rawResponse) ? strlen($rawResponse) : 0,
            );
        }

        if ($error || $rawResponse === false || $rawResponse === '') {
            return false;
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            return false;
        }

        if (!isset($decoded['httpCode'])) {
            $decoded['httpCode'] = $httpCode;
        }

        return $decoded;
    }
}

if (!function_exists('gwnListClients')) {
    function gwnListClients($pageNum = 1, $pageSize = 100) {
        $body = array(
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
            'untilNow' => 0,
        );
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        $response = gwnApiCall('/oapi/v1.0.0/client/list', $body, 'POST');
        if (!gwnResponseSuccessful($response)) {
            return false;
        }

        return gwnNormalizeListResponse($response, $pageNum, $pageSize);
    }
}

if (!function_exists('gwnGetClientInfo')) {
    function gwnGetClientInfo($mac) {
        $mac = gwnNormalizeMac($mac);
        if ($mac === '') {
            return false;
        }

        $body = array('clientId' => $mac);
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        foreach (array('/oapi/v1.0.0/client/detail', '/oapi/v1.0.0/client/info') as $endpoint) {
            $response = gwnApiCall($endpoint, $body, 'POST');
            if (!gwnResponseSuccessful($response)) {
                continue;
            }

            $payload = gwnExtractPayload($response);
            if (is_array($payload) && isset($payload['clientId'])) {
                return $payload;
            }

            if (is_array($payload) && isset($payload['result']) && is_array($payload['result']) && isset($payload['result']['clientId'])) {
                return $payload['result'];
            }

            $rows = gwnCollectRows($payload);
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rowMac = gwnNormalizeMac($row['clientId'] ?? ($row['mac'] ?? ''));
                if ($rowMac !== '' && strtoupper($rowMac) === strtoupper($mac)) {
                    return $row;
                }
            }
        }

        $clientsData = gwnListClients(1, 200);
        if ($clientsData === false) {
            return false;
        }

        $clients = $clientsData['result'] ?? array();
        foreach ($clients as $client) {
            $rowMac = gwnNormalizeMac($client['clientId'] ?? ($client['mac'] ?? ''));
            if ($rowMac !== '' && strtoupper($rowMac) === strtoupper($mac)) {
                return $client;
            }
        }

        return false;
    }
}

if (!function_exists('gwnEditClientName')) {
    function gwnEditClientName($mac, $name) {
        $mac = gwnNormalizeMac($mac);
        if ($mac === '') {
            return false;
        }

        $name = trim((string)$name);
        if ($name === '') {
            return false;
        }
        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 64);
        } else {
            $name = substr($name, 0, 64);
        }

        $body = array(
            'clientId' => $mac,
            'name' => $name,
        );
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        $response = gwnApiCall('/oapi/v1.0.0/client/edit', $body, 'POST');
        if (!gwnResponseSuccessful($response)) {
            return false;
        }

        return $response;
    }
}

if (!function_exists('gwnGetVouchersInGroup')) {
    function gwnGetVouchersInGroup($groupId, $pageNum = 1, $pageSize = 200) {
        $groupId = (int)$groupId;
        if ($groupId <= 0) {
            return false;
        }

        $body = array(
            'groupId' => $groupId,
            'groupID' => $groupId,
            'voucherGroupId' => $groupId,
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
            'page' => (int)$pageNum,
        );
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        $endpoints = array(
            '/oapi/v1.0.0/voucher/vouchers/list',
            '/oapi/v1.0.0/voucher/list',
            '/oapi/v1.0.0/voucher/getByGroup',
            '/oapi/v1.0.0/voucher/group/list',
        );

        foreach ($endpoints as $endpoint) {
            foreach (array('POST', 'GET') as $method) {
                $response = gwnApiCall($endpoint, $body, $method);
                if (gwnResponseSuccessful($response)) {
                    return gwnNormalizeListResponse($response, $pageNum, $pageSize);
                }
            }
        }

        return false;
    }
}

if (!function_exists('gwnDeleteVoucher')) {
    function gwnDeleteVoucher($voucherId, $networkId = null) {
        $voucherId = (int)$voucherId;
        if ($voucherId <= 0) {
            return false;
        }

        $body = array('voucherId' => $voucherId);
        $resolvedNetworkId = $networkId;
        if ($resolvedNetworkId === null || $resolvedNetworkId === '') {
            $resolvedNetworkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
        }
        if ($resolvedNetworkId !== '') {
            $body['networkId'] = $resolvedNetworkId;
        }

        foreach (array('/oapi/v1.0.0/voucher/delete', '/oapi/v1.0.0/voucher/remove', '/oapi/v1.0.0/voucher/revoke') as $endpoint) {
            $response = gwnApiCall($endpoint, $body, 'POST');
            if (gwnResponseSuccessful($response)) {
                return $response;
            }
        }

        return false;
    }
}

if (!function_exists('gwnListAPs')) {
    function gwnListAPs($pageNum = 1, $pageSize = 100) {
        $body = array(
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        foreach (array('/oapi/v1.0.0/ap/list', '/oapi/v1.0.0/accesspoint/list') as $endpoint) {
            $response = gwnApiCall($endpoint, $body, 'POST');
            if (gwnResponseSuccessful($response)) {
                return gwnNormalizeListResponse($response, $pageNum, $pageSize);
            }
        }

        return false;
    }
}

if (!function_exists('gwnListSSIDs')) {
    function gwnListSSIDs($pageNum = 1, $pageSize = 100) {
        $body = array(
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        foreach (array('/oapi/v1.0.0/ssid/list', '/oapi/v1.0.0/wlan/list', '/oapi/v1.0.0/ssid/getList') as $endpoint) {
            $response = gwnApiCall($endpoint, $body, 'POST');
            if (gwnResponseSuccessful($response)) {
                return gwnNormalizeListResponse($response, $pageNum, $pageSize);
            }
        }

        return false;
    }
}

if (!function_exists('gwnGetNetworkDetail')) {
    function gwnGetNetworkDetail($networkId = null) {
        $body = array();
        $resolvedNetworkId = $networkId;
        if ($resolvedNetworkId === null || $resolvedNetworkId === '') {
            $resolvedNetworkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
        }
        if ($resolvedNetworkId !== '') {
            $body['networkId'] = $resolvedNetworkId;
        }

        foreach (array('/oapi/v1.0.0/network/detail', '/oapi/v1.0.0/network/get') as $endpoint) {
            foreach (array('POST', 'GET') as $method) {
                $response = gwnApiCall($endpoint, $body, $method);
                if (!gwnResponseSuccessful($response)) {
                    continue;
                }

                $payload = gwnExtractPayload($response);
                if (is_array($payload)) {
                    return $payload;
                }
                return $response;
            }
        }

        return false;
    }
}

if (!function_exists('gwnGetNetworkStats')) {
    function gwnGetNetworkStats($type = 'ap') {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, array('ap', 'ssid', 'client'), true)) {
            $type = 'ap';
        }

        $body = array(
            'type' => $type,
            'statType' => $type,
        );
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        $endpoints = array(
            '/oapi/v1.0.0/statistic/' . $type,
            '/oapi/v1.0.0/statistic/' . $type . '/list',
            '/oapi/v1.0.0/network/statistics',
        );

        foreach ($endpoints as $endpoint) {
            foreach (array('POST', 'GET') as $method) {
                $response = gwnApiCall($endpoint, $body, $method);
                if (!gwnResponseSuccessful($response)) {
                    continue;
                }
                $payload = gwnExtractPayload($response);
                return is_array($payload) ? $payload : $response;
            }
        }

        return false;
    }
}

if (!function_exists('gwnBlockClient')) {
    function gwnBlockClient($mac, $reason = '') {
        $mac = gwnNormalizeMac($mac);
        if ($mac === '') {
            return false;
        }

        $body = array(
            'clientId' => $mac,
            'reason' => trim((string)$reason),
        );
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        foreach (array('/oapi/v1.0.0/client/block', '/oapi/v1.0.0/client/blacklist/add') as $endpoint) {
            $response = gwnApiCall($endpoint, $body, 'POST');
            if (gwnResponseSuccessful($response)) {
                return $response;
            }
        }

        return false;
    }
}

if (!function_exists('gwnUnblockClient')) {
    function gwnUnblockClient($mac, $reason = '') {
        $mac = gwnNormalizeMac($mac);
        if ($mac === '') {
            return false;
        }

        $body = array(
            'clientId' => $mac,
            'reason' => trim((string)$reason),
        );
        if (defined('GWN_NETWORK_ID') && GWN_NETWORK_ID !== '') {
            $body['networkId'] = GWN_NETWORK_ID;
        }

        foreach (array('/oapi/v1.0.0/client/unblock', '/oapi/v1.0.0/client/blacklist/remove') as $endpoint) {
            $response = gwnApiCall($endpoint, $body, 'POST');
            if (gwnResponseSuccessful($response)) {
                return $response;
            }
        }

        return false;
    }
}

if (!function_exists('autoDetectDeviceType')) {
    function autoDetectDeviceType($dhcpOs) {
        $os = strtolower((string)$dhcpOs);
        if ($os === '') {
            return 'Other';
        }

        if (strpos($os, 'iphone') !== false || strpos($os, 'ios') !== false || strpos($os, 'android') !== false || strpos($os, 'mobile') !== false || strpos($os, 'phone') !== false) {
            return 'Phone';
        }

        if (strpos($os, 'ipad') !== false || strpos($os, 'tablet') !== false) {
            return 'Tablet';
        }

        if (strpos($os, 'windows') !== false || strpos($os, 'mac') !== false || strpos($os, 'linux') !== false || strpos($os, 'ubuntu') !== false || strpos($os, 'laptop') !== false || strpos($os, 'notebook') !== false) {
            return 'Laptop';
        }

        if (strpos($os, 'playstation') !== false || strpos($os, 'xbox') !== false || strpos($os, 'nintendo') !== false) {
            return 'Console';
        }

        if (strpos($os, 'tv') !== false) {
            return 'TV';
        }

        return 'Other';
    }
}

if (!function_exists('getVoucherDeviceMappings')) {
    function getVoucherDeviceMappings($month) {
        $month = trim((string)$month);
        if ($month === '') {
            $month = date('Y-m');
        }

        $monthIso = $month;
        $monthLabel = $month;
        $parsed = false;
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $parsed = strtotime($month . '-01');
        } else {
            $parsed = strtotime('1 ' . $month);
            if ($parsed === false) {
                $parsed = strtotime($month);
            }
        }
        if ($parsed !== false) {
            $monthIso = date('Y-m', $parsed);
            $monthLabel = date('F Y', $parsed);
        }

        if (!function_exists('getDbConnection')) {
            return array();
        }

        $conn = getDbConnection();
        if (!$conn) {
            return array();
        }

        $sql = "SELECT DISTINCT voucher_code, IFNULL(gwn_group_id, 0) AS gwn_group_id
                FROM voucher_logs
                WHERE voucher_code IS NOT NULL
                  AND voucher_code <> ''
                  AND (voucher_month = ? OR voucher_month = ? OR DATE_FORMAT(created_at, '%Y-%m') = ?)
                  AND is_active = 1";
        $stmt = gwnPrepare($conn, $sql);

        if (!$stmt) {
            $sql = "SELECT DISTINCT voucher_code, IFNULL(gwn_group_id, 0) AS gwn_group_id
                    FROM voucher_logs
                    WHERE voucher_code IS NOT NULL
                      AND voucher_code <> ''
                      AND (voucher_month = ? OR voucher_month = ? OR DATE_FORMAT(created_at, '%Y-%m') = ?)";
            $stmt = gwnPrepare($conn, $sql);
        }

        if (!$stmt) {
            $sql = "SELECT DISTINCT voucher_code, 0 AS gwn_group_id
                    FROM voucher_logs
                    WHERE voucher_code IS NOT NULL
                      AND voucher_code <> ''
                      AND (voucher_month = ? OR voucher_month = ? OR DATE_FORMAT(created_at, '%Y-%m') = ?)";
            $stmt = gwnPrepare($conn, $sql);
        }

        if (!$stmt) {
            return array();
        }

        $stmt->bind_param('sss', $monthIso, $monthLabel, $monthIso);
        $stmt->execute();
        $voucherRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($voucherRows)) {
            return array();
        }

        $groupIds = array();
        foreach ($voucherRows as $row) {
            $groupId = (int)($row['gwn_group_id'] ?? 0);
            if ($groupId > 0) {
                $groupIds[$groupId] = $groupId;
            }
        }

        if (empty($groupIds)) {
            return array();
        }

        $voucherUsage = array();
        foreach ($groupIds as $groupId) {
            $pageNum = 1;
            do {
                $data = gwnGetVouchersInGroup($groupId, $pageNum, 200);
                if ($data === false) {
                    break;
                }

                $rows = gwnCollectRows($data);
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $voucherCode = gwnExtractVoucherCode($row);
                    if ($voucherCode === '') {
                        continue;
                    }
                    $mac = gwnExtractMacFromVoucherRow($row);
                    $isUsed = ($mac !== '') || gwnVoucherRowLooksUsed($row);
                    if (!$isUsed) {
                        continue;
                    }

                    $lookup = strtoupper($voucherCode);
                    if (!isset($voucherUsage[$lookup])) {
                        $voucherUsage[$lookup] = array(
                            'voucher_code' => $voucherCode,
                            'mac' => '',
                        );
                    }

                    if ($mac !== '' && $voucherUsage[$lookup]['mac'] === '') {
                        $voucherUsage[$lookup]['mac'] = $mac;
                    }
                }

                $totalPages = isset($data['totalPage']) ? (int)$data['totalPage'] : 1;
                if ($totalPages < 1) {
                    $totalPages = 1;
                }
                $pageNum++;
            } while ($pageNum <= $totalPages && $pageNum <= 10);
        }

        if (empty($voucherUsage)) {
            return array();
        }

        $mappings = array();
        $seen = array();
        foreach ($voucherRows as $row) {
            $voucherCode = trim((string)($row['voucher_code'] ?? ''));
            if ($voucherCode === '') {
                continue;
            }

            $lookup = strtoupper($voucherCode);
            if (empty($voucherUsage[$lookup])) {
                continue;
            }

            $mac = trim((string)($voucherUsage[$lookup]['mac'] ?? ''));
            $key = $lookup . '|' . ($mac !== '' ? $mac : 'NO_MAC');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $mappings[] = array(
                'voucher_code' => $voucherCode,
                'mac' => $mac !== '' ? $mac : null,
            );
        }

        return $mappings;
    }
}

if (!function_exists('gwnWriteBlockLog')) {
    function gwnWriteBlockLog($conn, $deviceId, $userId, $mac, $action, $reason, $performedBy) {
        $performedBy = (int)$performedBy;
        if ($performedBy > 0) {
            $sql = "INSERT INTO device_block_log (device_id, user_id, mac_address, action, reason, performed_by)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = gwnPrepare($conn, $sql);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('iisssi', $deviceId, $userId, $mac, $action, $reason, $performedBy);
        } else {
            $sql = "INSERT INTO device_block_log (device_id, user_id, mac_address, action, reason, performed_by)
                    VALUES (?, ?, ?, ?, ?, NULL)";
            $stmt = gwnPrepare($conn, $sql);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('iisss', $deviceId, $userId, $mac, $action, $reason);
        }

        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('blockDevice')) {
    function blockDevice($deviceId, $mac, $userId, $reason, $performedBy) {
        if (!function_exists('getDbConnection')) {
            return array('success' => false, 'message' => 'Database helpers unavailable.');
        }

        $conn = getDbConnection();
        if (!$conn) {
            return array('success' => false, 'message' => 'Could not connect to database.');
        }

        $deviceId = (int)$deviceId;
        $userId = (int)$userId;
        $performedBy = (int)$performedBy;
        $reason = trim((string)$reason);
        if ($reason === '') {
            $reason = 'Blocked by staff';
        }

        $macNormalized = gwnNormalizeMac($mac);
        if ($macNormalized === '') {
            $macNormalized = strtoupper(trim((string)$mac));
        }

        $localUpdated = false;

        $sql = "UPDATE user_devices
                SET is_blocked = 1,
                    blocked_reason = ?,
                    blocked_at = NOW(),
                    blocked_by = ?,
                    unblocked_at = NULL,
                    unblocked_by = NULL
                WHERE id = ? AND user_id = ?";
        $stmt = gwnPrepare($conn, $sql);
        if ($stmt) {
            $stmt->bind_param('siii', $reason, $performedBy, $deviceId, $userId);
            $localUpdated = (bool)$stmt->execute();
            $stmt->close();
        }

        if (!$localUpdated) {
            $sql = "UPDATE user_devices
                    SET is_blocked = 1,
                        blocked_reason = ?
                    WHERE id = ? AND user_id = ?";
            $stmt = gwnPrepare($conn, $sql);
            if ($stmt) {
                $stmt->bind_param('sii', $reason, $deviceId, $userId);
                $localUpdated = (bool)$stmt->execute();
                $stmt->close();
            }
        }

        if (!$localUpdated) {
            $sql = "UPDATE user_devices SET is_blocked = 1 WHERE id = ? AND user_id = ?";
            $stmt = gwnPrepare($conn, $sql);
            if ($stmt) {
                $stmt->bind_param('ii', $deviceId, $userId);
                $localUpdated = (bool)$stmt->execute();
                $stmt->close();
            }
        }

        if (!$localUpdated) {
            $checkStmt = gwnPrepare($conn, "SELECT id FROM user_devices WHERE id = ? AND user_id = ? LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('ii', $deviceId, $userId);
                $checkStmt->execute();
                $localUpdated = (bool)$checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();
            }
        }

        if (!$localUpdated) {
            return array('success' => false, 'message' => 'Unable to update local device record.');
        }

        gwnWriteBlockLog($conn, $deviceId, $userId, $macNormalized, 'block', $reason, $performedBy);

        $gwnResult = gwnBlockClient($macNormalized, $reason);
        $gwnSuccess = ($gwnResult !== false);

        return array(
            'success' => true,
            'message' => $gwnSuccess
                ? 'Device blocked successfully.'
                : 'Device blocked locally. GWN Cloud block request could not be confirmed.',
            'gwn_success' => $gwnSuccess,
        );
    }
}

if (!function_exists('unblockDevice')) {
    function unblockDevice($deviceId, $mac, $userId, $reason, $performedBy) {
        if (!function_exists('getDbConnection')) {
            return array('success' => false, 'message' => 'Database helpers unavailable.');
        }

        $conn = getDbConnection();
        if (!$conn) {
            return array('success' => false, 'message' => 'Could not connect to database.');
        }

        $deviceId = (int)$deviceId;
        $userId = (int)$userId;
        $performedBy = (int)$performedBy;
        $reason = trim((string)$reason);
        if ($reason === '') {
            $reason = 'Access restored';
        }

        $macNormalized = gwnNormalizeMac($mac);
        if ($macNormalized === '') {
            $macNormalized = strtoupper(trim((string)$mac));
        }

        $localUpdated = false;

        $sql = "UPDATE user_devices
                SET is_blocked = 0,
                    blocked_reason = NULL,
                    unblocked_at = NOW(),
                    unblocked_by = ?
                WHERE id = ? AND user_id = ?";
        $stmt = gwnPrepare($conn, $sql);
        if ($stmt) {
            $stmt->bind_param('iii', $performedBy, $deviceId, $userId);
            $localUpdated = (bool)$stmt->execute();
            $stmt->close();
        }

        if (!$localUpdated) {
            $sql = "UPDATE user_devices
                    SET is_blocked = 0,
                        blocked_reason = NULL
                    WHERE id = ? AND user_id = ?";
            $stmt = gwnPrepare($conn, $sql);
            if ($stmt) {
                $stmt->bind_param('ii', $deviceId, $userId);
                $localUpdated = (bool)$stmt->execute();
                $stmt->close();
            }
        }

        if (!$localUpdated) {
            $sql = "UPDATE user_devices SET is_blocked = 0 WHERE id = ? AND user_id = ?";
            $stmt = gwnPrepare($conn, $sql);
            if ($stmt) {
                $stmt->bind_param('ii', $deviceId, $userId);
                $localUpdated = (bool)$stmt->execute();
                $stmt->close();
            }
        }

        if (!$localUpdated) {
            $checkStmt = gwnPrepare($conn, "SELECT id FROM user_devices WHERE id = ? AND user_id = ? LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('ii', $deviceId, $userId);
                $checkStmt->execute();
                $localUpdated = (bool)$checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();
            }
        }

        if (!$localUpdated) {
            return array('success' => false, 'message' => 'Unable to update local device record.');
        }

        gwnWriteBlockLog($conn, $deviceId, $userId, $macNormalized, 'unblock', $reason, $performedBy);

        $gwnResult = gwnUnblockClient($macNormalized, $reason);
        $gwnSuccess = ($gwnResult !== false);

        return array(
            'success' => true,
            'message' => $gwnSuccess
                ? 'Device access restored successfully.'
                : 'Device restored locally. GWN Cloud unblock request could not be confirmed.',
            'gwn_success' => $gwnSuccess,
        );
    }
}

