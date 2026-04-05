<?php
require_once __DIR__ . '/GwnService.php';

class CaptivePortalService extends GwnService {
    public function listPortals($networkId = null) {
        $payload = array('networkId' => $this->resolveNetworkId($networkId));
        return $this->callApi('portal/list', $payload, 'POST');
    }

    public function exportGuestListCsv($networkId, $startDate, $endDate) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'startDate' => (string)$startDate,
            'endDate' => (string)$endDate,
        );

        return $this->callApi('portal/guest/export', $payload, 'POST');
    }

    public function listGuests($networkId, $startDate, $endDate, $pageNum = 1, $pageSize = 20) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'startDate' => (string)$startDate,
            'endDate' => (string)$endDate,
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );

        return $this->callApi('portal/guest/list', $payload, 'POST');
    }

    public function listOnlineGuests($networkId, $pageNum = 1, $pageSize = 20, $search = '', $order = '', $type = '') {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );

        if ($search !== '') {
            $payload['search'] = (string)$search;
        }
        if ($order !== '') {
            $payload['order'] = (string)$order;
        }
        if ($type !== '') {
            $payload['type'] = (string)$type;
        }

        return $this->callApi('portal/monitor/list', $payload, 'POST');
    }

    public function allowClient($apMac, $clientMac, $ssidName, $authType = '', $startUseTime = '', $endUseTime = '') {
        $payload = array(
            'mac' => (string)$apMac,
            'client_mac' => (string)$clientMac,
            'ssid_name' => (string)$ssidName,
        );

        if ($authType !== '') {
            $payload['auth_type'] = (string)$authType;
        }
        if ($startUseTime !== '') {
            $payload['start_use_time'] = (string)$startUseTime;
        }
        if ($endUseTime !== '') {
            $payload['end_use_time'] = (string)$endUseTime;
        }

        return $this->callApi('portal/pass', $payload, 'POST');
    }

    public function kickGuest($apMac, $clientMac, $ssidName) {
        $payload = array(
            'mac' => (string)$apMac,
            'client_mac' => (string)$clientMac,
            'ssid_name' => (string)$ssidName,
        );

        return $this->callApi('portal/kick', $payload, 'POST');
    }

    public function getOnlineGuestsByVoucherId($networkId = null) {
        $networkId = $this->resolveNetworkId($networkId);
        $guestsByVoucherId = array();
        
        $pageNum = 1;
        $pageSize = 200;
        $maxPages = 20;
        
        do {
            $response = $this->listOnlineGuests($networkId, $pageNum, $pageSize, '', 'loginTimeStr', 'descending');
            
            if (!$this->responseSuccessful($response)) {
                $retCode = isset($response['retCode']) ? (string)$response['retCode'] : 'unknown';
                $message = trim((string)($response['msg'] ?? 'Unknown portal monitor error'));
                if ($message === '') {
                    $message = 'Unknown portal monitor error';
                }

                throw new RuntimeException('portal/monitor/list failed with retCode ' . $retCode . ': ' . $message);
            }
            
            $guests = $this->collectRows($response);
            
            foreach ($guests as $guest) {
                if (!is_array($guest)) {
                    continue;
                }
                
                $voucherId = $this->extractVoucherIdFromGuest($guest);
                if ($voucherId !== null) {
                    $normalized = $this->normalizeGuestSession($guest);
                    
                    // Only keep the first (most recent due to descending order) session per voucher ID
                    if (!isset($guestsByVoucherId[$voucherId])) {
                        $guestsByVoucherId[$voucherId] = $normalized;
                    }
                }
            }
            
            // Handle both top-level totalPage and nested data.totalPage structures  
            $totalPages = $this->extractTotalPages($response);
            $pageNum++;
        } while ($pageNum <= $totalPages && $pageNum <= $maxPages);
        
        return $guestsByVoucherId;
    }
    
    private function extractVoucherIdFromGuest($guest) {
        $portalInfo = $guest['portalInfo'] ?? '';
        if (is_string($portalInfo) && $portalInfo !== '') {
            $parsed = json_decode($portalInfo, true);
            if (is_array($parsed)) {
                foreach ($parsed as $item) {
                    if (!is_array($item) || !isset($item['key'], $item['value'])) {
                        continue;
                    }
                    
                    $key = trim((string)$item['key']);
                    $value = trim((string)$item['value']);
                    
                    // Normalize "Voucher  id", "voucher_id", etc. to "voucherid"
                    $normalizedKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $key));

                    if ($value !== '' && $normalizedKey === 'voucherid') {
                        if (is_numeric($value)) {
                            return (int)$value;
                        }
                    }
                }
            }
        }
        
        // Fallback to direct field checks for explicit voucher-ID fields only
        foreach (['voucherId', 'voucher_id'] as $field) {
            if (isset($guest[$field])) {
                $value = trim((string)$guest[$field]);
                if ($value !== '' && is_numeric($value)) {
                    return (int)$value;
                }
            }
        }
        
        return null;
    }
    
    private function normalizeGuestSession($guest) {
        $voucherId = $this->extractVoucherIdFromGuest($guest);
        $clientId = trim((string)($guest['clientId'] ?? ''));
        $name = trim((string)($guest['name'] ?? ''));
        $ssid = trim((string)($guest['ssid'] ?? ''));
        $apName = trim((string)($guest['apName'] ?? ''));
        $loginTime = trim((string)($guest['loginTimeStr'] ?? ''));
        $portalTime = trim((string)($guest['portalTimeDisplay'] ?? ''));
        $authType = trim((string)($guest['authType'] ?? ''));
        $rssi = trim((string)($guest['rssi'] ?? ''));
        $portalStats = trim((string)($guest['portalStats'] ?? ''));
        $portalInfo = $guest['portalInfo'] ?? '';
        
        // Extract useful additional fields for better service results
        $apId = trim((string)($guest['apId'] ?? $guest['ap_id'] ?? ''));
        $policyName = trim((string)($guest['policyName'] ?? $guest['policy_name'] ?? ''));
        $ssidRemark = trim((string)($guest['ssidRemark'] ?? $guest['ssid_remark'] ?? ''));
        
        $parsedPortalInfo = array();
        if (is_string($portalInfo) && $portalInfo !== '') {
            $parsed = json_decode($portalInfo, true);
            if (is_array($parsed)) {
                $parsedPortalInfo = $parsed;
            }
        }
        
        return array(
            'voucher_id' => $voucherId,
            'client_id' => $clientId,
            'mac' => $clientId,
            'name' => $name,
            'ssid' => $ssid,
            'ap_name' => $apName,
            'ap_id' => $apId,
            'policy_name' => $policyName,
            'ssid_remark' => $ssidRemark,
            'login_time' => $loginTime,
            'portal_time_display' => $portalTime,
            'auth_type' => $authType,
            'portal_stats' => $portalStats,
            'rssi' => $rssi,
            'portal_info' => $parsedPortalInfo
        );
    }
    
    private function extractTotalPages($response) {
        // Handle both top-level totalPage and nested data.totalPage structures
        if (isset($response['totalPage'])) {
            $totalPages = (int)$response['totalPage'];
        } elseif (isset($response['data']['totalPage'])) {
            $totalPages = (int)$response['data']['totalPage'];
        } else {
            $totalPages = 1;
        }
        
        return max(1, $totalPages);
    }
}
