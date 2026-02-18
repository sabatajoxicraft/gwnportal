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
}
