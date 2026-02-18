<?php
require_once __DIR__ . '/GwnService.php';

class CommonService extends GwnService {
    public function getCountries() {
        return $this->callApi('country', array(), 'GET');
    }

    public function listPortProfiles($networkId = null, $pageNum = 1, $pageSize = 20) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );

        return $this->callApi('portsProfiles/list', $payload, 'POST');
    }

    public function getTimezones() {
        return $this->callApi('timezone', array(), 'GET');
    }

    public function listUsers() {
        return $this->callApi('user/list', array(), 'GET');
    }

    public function listSchedules($networkId = null) {
        $payload = array('networkId' => $this->resolveNetworkId($networkId));
        return $this->callApi('schedule/list', $payload, 'POST');
    }

    public function listTimePolicies($networkId = null, $pageNum = 1, $pageSize = 20, $type = '', $order = '') {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );

        if ($type !== '') {
            $payload['type'] = (string)$type;
        }
        if ($order !== '') {
            $payload['order'] = (string)$order;
        }

        return $this->callApi('policy/list', $payload, 'POST');
    }
}
