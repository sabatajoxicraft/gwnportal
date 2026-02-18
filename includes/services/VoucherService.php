<?php
require_once __DIR__ . '/GwnService.php';

class VoucherService extends GwnService {
    public function listVoucherGroups($networkId = null, $pageNum = 1, $pageSize = 200, $search = '', $order = '', $type = '') {
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

        return $this->callApi('voucher/list', $payload, 'POST');
    }

    public function createVoucherGroup(array $data) {
        if (!isset($data['networkId'])) {
            $data['networkId'] = $this->resolveNetworkId(null);
        }

        return $this->callApi('voucher/save', $data, 'POST');
    }

    public function deleteVoucherGroup(array $groupIds, $networkId = null) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'groupIds' => $groupIds,
        );

        return $this->callApi('voucher/delete', $payload, 'POST');
    }

    public function getVoucherPageInfo($networkId = null) {
        $payload = array('networkId' => $this->resolveNetworkId($networkId));
        return $this->callApi('voucher/page/show', $payload, 'POST');
    }

    public function saveVoucherPage($slogan, $logoFileId, $networkId = null) {
        $payload = array(
            'slogan' => (string)$slogan,
            'logoFileId' => (int)$logoFileId,
            'networkId' => $this->resolveNetworkId($networkId),
        );

        return $this->callApi('voucher/page/save', $payload, 'POST');
    }

    public function listVouchersInGroup($groupId, $networkId = null, $pageNum = 1, $pageSize = 200, $state = '', $search = '', $order = '', $type = '') {
        $payload = array(
            'groupId' => (int)$groupId,
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
        if ($state !== '') {
            $payload['filter'] = array('state' => (string)$state);
        }

        return $this->callApi('voucher/vouchers/list', $payload, 'POST');
    }

    public function getGroupVouchers($groupId, $pageNum = 1, $pageSize = 200) {
        return $this->listVouchersInGroup($groupId, null, $pageNum, $pageSize);
    }

    public function listVoucherStates() {
        return $this->callApi('voucher/vouchers/states', array(), 'POST');
    }

    public function renewVoucher($voucherId, $networkId = null) {
        $payload = array(
            'id' => (int)$voucherId,
            'networkId' => $this->resolveNetworkId($networkId),
        );

        return $this->callApi('voucher/vouchers/renew', $payload, 'POST');
    }

    public function deleteVoucher($voucherId, $networkId = null) {
        $payload = array(
            'id' => (int)$voucherId,
            'networkId' => $this->resolveNetworkId($networkId),
        );

        return $this->callApi('voucher/vouchers/delete', $payload, 'POST');
    }
}
