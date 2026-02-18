<?php
require_once __DIR__ . '/../python_interface.php';

class GwnService {
    const API_VERSION = 'v1.0.0';

    protected function resolveNetworkId($networkId) {
        if ($networkId === null || $networkId === '') {
            return defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
        }

        return $networkId;
    }

    protected function buildEndpoint($path) {
        $version = self::API_VERSION;
        return '/oapi/' . $version . '/' . ltrim($path, '/');
    }

    protected function callApi($path, $payload, $method) {
        return gwnApiCall($this->buildEndpoint($path), $payload, $method);
    }

    public function responseSuccessful($response) {
        return gwnResponseSuccessful($response);
    }

    public function collectRows($response) {
        return gwnCollectRows($response);
    }
}
