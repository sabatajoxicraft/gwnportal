<?php
require_once __DIR__ . '/GwnService.php';

class DeviceService extends GwnService {
    public function listDevices($networkId = null, $pageNum = 1, $pageSize = 10, $search = '', $order = '', $type = '', $filter = array()) {
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

        return $this->callApi('ap/list', $payload, 'POST');
    }

    public function upgradeDevices(array $macs) {
        $payload = array('macs' => $macs);
        return $this->callApi('upgrade/add', $payload, 'POST');
    }

    public function getRecommendedFirmware($networkId = null) {
        $payload = array('networkId' => $this->resolveNetworkId($networkId));
        return $this->callApi('upgrade/version', $payload, 'POST');
    }

    public function returnDevices(array $macs, $unlockIspConfig = false) {
        $payload = array(
            'mac' => $macs,
            'unlockIspConfig' => (bool)$unlockIspConfig,
        );

        return $this->callApi('ap/return', $payload, 'POST');
    }

    public function getDeviceDetails($networkId, $mac) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'mac' => (string)$mac,
        );

        return $this->callApi('device/info', $payload, 'POST');
    }

    public function editDeviceLocation(array $macs, $latitude, $longitude) {
        $payload = array(
            'mac' => $macs,
            'ap_latitude' => (string)$latitude,
            'ap_longitude' => (string)$longitude,
        );

        return $this->callApi('ap/config/location', $payload, 'POST');
    }

    public function addDevice($networkId, $mac, $name = '', $password = '', $model = '') {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'mac' => (string)$mac,
        );

        if ($name !== '') {
            $payload['name'] = (string)$name;
        }
        if ($password !== '') {
            $payload['password'] = (string)$password;
        }
        if ($model !== '') {
            $payload['model'] = (string)$model;
        }

        return $this->callApi('ap/add', $payload, 'POST');
    }

    public function editAp(array $settings) {
        return $this->callApi('ap/config/edit', $settings, 'POST');
    }

    public function getDeviceInfo(array $macs) {
        $payload = array('mac' => $macs);
        return $this->callApi('ap/info', $payload, 'POST');
    }

    public function getDeviceInfoDetailed(array $macs) {
        $payload = array('mac' => $macs);
        return $this->callApiVersion('v2.0.0', 'ap/info', $payload, 'POST');
    }

    public function getRadioChannel(array $macs) {
        $payload = array('mac' => $macs);
        return $this->callApi('ap/config/channel', $payload, 'POST');
    }

    public function moveDevices(array $macs, $networkId) {
        $payload = array(
            'mac' => $macs,
            'networkId' => $this->resolveNetworkId($networkId),
        );

        return $this->callApi('ap/move', $payload, 'POST');
    }

    public function rebootDevices(array $macs) {
        $payload = array('mac' => $macs);
        return $this->callApi('ap/reboot', $payload, 'POST');
    }

    public function resetDevices(array $macs) {
        $payload = array('mac' => $macs);
        return $this->callApi('ap/reset', $payload, 'POST');
    }

    public function deleteDevices(array $macs, $unlockIspConfig = false) {
        $payload = array(
            'mac' => $macs,
            'unlockIspConfig' => (bool)$unlockIspConfig,
        );

        return $this->callApi('ap/delete', $payload, 'POST');
    }

    protected function callApiVersion($version, $path, $payload, $method) {
        $endpoint = '/oapi/' . (string)$version . '/' . ltrim($path, '/');
        return gwnApiCall($endpoint, $payload, $method);
    }
}
