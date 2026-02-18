<?php
require_once __DIR__ . '/GwnService.php';

class StatisticsService extends GwnService {
    public function getNetworkClientStats($networkId, $type, $ssidId = '') {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'type' => (int)$type,
        );

        if ($ssidId !== '') {
            $payload['ssidId'] = (string)$ssidId;
        }

        return $this->callApi('statistics/client', $payload, 'POST');
    }

    public function getNetworkBandwidthStats($networkId, $type, $clientId = '', $ssidId = '') {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'type' => (int)$type,
        );

        if ($clientId !== '') {
            $payload['clientId'] = (string)$clientId;
        }
        if ($ssidId !== '') {
            $payload['ssidId'] = (string)$ssidId;
        }

        return $this->callApi('statistics/bandwidth', $payload, 'POST');
    }

    public function getApClientStats($networkId, $type, $mac) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'type' => (int)$type,
            'mac' => (string)$mac,
        );

        return $this->callApi('statistics/ap/client', $payload, 'POST');
    }

    public function getSsidClientStats($networkId, $type, $ssidId = 'all_ssid') {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'type' => (int)$type,
            'ssidId' => (string)$ssidId,
        );

        return $this->callApi('statistics/ssid/client', $payload, 'POST');
    }

    public function getSsidBandwidthStats($networkId, $type, $ssidId = 'all_ssid') {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'type' => (int)$type,
            'ssidId' => (string)$ssidId,
        );

        return $this->callApi('statistics/ssid/bandwidth', $payload, 'POST');
    }

    public function getApBandwidthStats($networkId, $type, $mac) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'type' => (int)$type,
            'mac' => (string)$mac,
        );

        return $this->callApi('statistics/ap/bandwidth', $payload, 'POST');
    }

    public function getNetworkOverview($networkId = null) {
        $payload = array();
        if ($networkId !== null && $networkId !== '') {
            $payload['networkId'] = $this->resolveNetworkId($networkId);
        }

        return $this->callApi('statistics/monitor/device/all', $payload, 'POST');
    }
}
