<?php
require_once __DIR__ . '/GwnService.php';

class ClientService extends GwnService {
    public function listClients($networkId = null, $pageNum = 1, $pageSize = 20, $search = '', $order = '', $type = '', $filter = array(), $startDate = null, $endDate = null, $untilNow = 0) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
            'untilNow' => (int)$untilNow,
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
        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }
        if ($startDate !== null) {
            $payload['startDate'] = (int)$startDate;
        }
        if ($endDate !== null) {
            $payload['endDate'] = (int)$endDate;
        }

        return $this->callApi('client/list', $payload, 'POST');
    }

    public function setClientBlockStatus($clientId, $networkId = null, $block = 0) {
        $payload = array(
            'clientId' => (string)$clientId,
            'networkId' => $this->resolveNetworkId($networkId),
            'block' => (int)$block,
        );

        return $this->callApi('client/block', $payload, 'POST');
    }

    public function getClientDetails($clientId, $networkId = null) {
        $payload = array(
            'clientId' => (string)$clientId,
            'networkId' => $this->resolveNetworkId($networkId),
        );

        return $this->callApi('client/info', $payload, 'POST');
    }

    public function editClient($clientId, $name = '') {
        $payload = array('clientId' => (string)$clientId);
        if ($name !== '') {
            $payload['name'] = (string)$name;
        }

        return $this->callApi('client/edit', $payload, 'POST');
    }

    public function clearClientUsage($clientId, $networkId = null) {
        $payload = array(
            'clientId' => (string)$clientId,
            'networkId' => $this->resolveNetworkId($networkId),
        );

        return $this->callApi('client/clear', $payload, 'POST');
    }

    public function listClientHistory($networkId = null, $pageNum = 1, $pageSize = 20, $search = '', $order = '', $type = '', $filter = array(), $startTime = null, $endTime = null) {
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
        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }
        if ($startTime !== null) {
            $payload['startTime'] = (int)$startTime;
        }
        if ($endTime !== null) {
            $payload['endTime'] = (int)$endTime;
        }

        return $this->callApi('client/history/list', $payload, 'POST');
    }

    public function getClientGeoanalytics($networkId = null, $apMacs = array(), $pageNum = null, $pageSize = null, $type = null) {
        $payload = array('networkId' => $this->resolveNetworkId($networkId));

        if (!empty($apMacs)) {
            $payload['apMacs'] = $apMacs;
        }
        if ($pageNum !== null) {
            $payload['pageNum'] = (int)$pageNum;
        }
        if ($pageSize !== null) {
            $payload['pageSize'] = (int)$pageSize;
        }
        if ($type !== null) {
            $payload['type'] = (int)$type;
        }

        return $this->callApi('client/geoanalytics', $payload, 'POST');
    }

    public function setGlobalBypassMacs($deviceMac, $clientMacs, $action) {
        $payload = array(
            'deviceMac' => (string)$deviceMac,
            'clientMacs' => (string)$clientMacs,
            'action' => (string)$action,
        );

        return $this->callApi('client/send/global-bypass', $payload, 'POST');
    }
}
