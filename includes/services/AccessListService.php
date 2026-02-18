<?php
require_once __DIR__ . '/GwnService.php';

class AccessListService extends GwnService {
    public function listAccessLists($networkId = null, $pageNum = 1, $pageSize = 20, $type = '', $order = '') {
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

        return $this->callApi('access/list', $payload, 'POST');
    }

    public function editAccessList($accessListId, array $macs, $networkId = null) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'id' => (int)$accessListId,
            'macs' => $macs,
        );

        return $this->callApi('access/edit', $payload, 'POST');
    }

    public function getAccessListDetails($accessListId, $networkId = null) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'id' => (int)$accessListId,
        );

        return $this->callApi('access/info', $payload, 'POST');
    }
}
