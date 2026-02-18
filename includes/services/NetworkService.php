<?php
require_once __DIR__ . '/GwnService.php';

class NetworkService extends GwnService {
    public function listNetworks($pageNum = 1, $pageSize = 5, $search = '', $order = '', $type = '') {
        $payload = array(
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

        return $this->callApi('network/list', $payload, 'POST');
    }

    public function getNetworkDetails($networkId) {
        $payload = array('id' => (int)$networkId);
        return $this->callApi('network/detail', $payload, 'POST');
    }

    public function createNetwork($networkName, $country, $timezone, array $networkAdministrators, $cloneNetworkId = null) {
        $payload = array(
            'networkName' => (string)$networkName,
            'country' => (string)$country,
            'timezone' => (string)$timezone,
            'networkAdministrators' => $networkAdministrators,
        );

        if ($cloneNetworkId !== null && $cloneNetworkId !== '') {
            $payload['cloneNetworkId'] = (int)$cloneNetworkId;
        }

        return $this->callApi('network/create', $payload, 'POST');
    }

    public function updateNetwork($networkId, $networkName, $country, $timezone, array $networkAdministrators) {
        $payload = array(
            'id' => (int)$networkId,
            'networkName' => (string)$networkName,
            'country' => (string)$country,
            'timezone' => (string)$timezone,
            'networkAdministrators' => $networkAdministrators,
        );

        return $this->callApi('network/update', $payload, 'POST');
    }

    public function deleteNetwork($networkId) {
        $payload = array('id' => (int)$networkId);
        return $this->callApi('network/delete', $payload, 'POST');
    }
}
