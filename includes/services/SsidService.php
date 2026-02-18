<?php
require_once __DIR__ . '/GwnService.php';

class SsidService extends GwnService {
    public function listSsids($networkId = null, $pageNum = 1, $pageSize = 5) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );

        return $this->callApi('ssid/list', $payload, 'POST');
    }

    public function listSsidsSimplified($networkId = null) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
        );

        return $this->callApi('ssid/ssids', $payload, 'POST');
    }

    public function createSsid(array $data) {
        if (!isset($data['networkId'])) {
            $data['networkId'] = $this->resolveNetworkId(null);
        }

        return $this->callApi('ssid/create', $data, 'POST');
    }

    public function updateSsid(array $data) {
        return $this->callApi('ssid/update', $data, 'POST');
    }

    public function getSsidConfiguration($ssidId) {
        $payload = array('id' => (int)$ssidId);
        return $this->callApi('ssid/configuration', $payload, 'GET');
    }

    public function deleteSsid($ssidId) {
        $payload = array('id' => (int)$ssidId);
        return $this->callApi('ssid/delete', $payload, 'POST');
    }
}
