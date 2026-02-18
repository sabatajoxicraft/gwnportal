<?php
require_once __DIR__ . '/GwnService.php';

class SiteSurveyService extends GwnService {
    public function sendSurvey($networkId = null) {
        $payload = array('networkId' => $this->resolveNetworkId($networkId));
        return $this->callApi('survey/send', $payload, 'POST');
    }

    public function listSurveyResults($networkId = null, $pageNum = 1, $pageSize = 10, $search = '', $startDate = '', $endDate = '', $filter = array()) {
        $payload = array(
            'networkId' => $this->resolveNetworkId($networkId),
            'pageNum' => (int)$pageNum,
            'pageSize' => (int)$pageSize,
        );

        if ($search !== '') {
            $payload['search'] = (string)$search;
        }
        if ($startDate !== '') {
            $payload['startDate'] = (string)$startDate;
        }
        if ($endDate !== '') {
            $payload['endDate'] = (string)$endDate;
        }
        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }

        return $this->callApi('survey/list', $payload, 'POST');
    }
}
