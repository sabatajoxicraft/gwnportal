<?php
   /**
    * GWN Cloud API Connection Manager
    * Singleton pattern - reuses token across multiple API calls
    */

   class GwnConnection {
       private static $instance = null;
       private $token = null;
       private $tokenExpiry = 0;

       private function __construct() {
           // Private constructor for singleton
       }

       /**
        * Get singleton instance
        */
       public static function getInstance() {
           if (self::$instance === null) {
               self::$instance = new self();
           }
           return self::$instance;
       }

       /**
        * Get valid token (cached or fetch new)
        */
       public function getToken() {
           // Check if we have a valid cached token
           if ($this->token !== null && time() < $this->tokenExpiry) {
               return $this->token;
           }

           // Try to fetch new token
           $this->token = gwnGetToken();

           if ($this->token) {
               // Token valid for 1 hour (adjust based on GWN API specs)
               $this->tokenExpiry = time() + 3600;
           }

           return $this->token;
       }

       /**
        * Make API call with automatic token handling
        */
       public function apiCall($endpoint, $bodyData = array(), $method = 'POST') {
           return gwnApiCall($endpoint, $bodyData, $method);
       }

       /**
        * Create voucher in GWN Cloud
        */
       public function createVoucher($data) {
           $defaults = array(
               'networkId' => (int)(defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : 0),
               'groupId' => 0,
               'count' => 1,
               'duration' => 30,
               'durationType' => 'day'
           );

           $voucherData = array_merge($defaults, $data);
           return $this->apiCall('/oapi/v1.0.0/voucher/save', $voucherData);
       }

       /**
        * List vouchers with filters
        */
       public function listVouchers($filters = array()) {
           $defaults = array(
               'networkId' => (int)(defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : 0),
               'pageNum' => 1,
               'pageSize' => 100
           );

           $requestData = array_merge($defaults, $filters);
           return $this->apiCall('/oapi/v1.0.0/voucher/list', $requestData);
       }

       /**
        * List voucher groups
        */
       public function listVoucherGroups($networkId = null) {
           if ($networkId === null) {
               $networkId = (int)(defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : 0);
           }

           return $this->apiCall('/oapi/v1.0.0/voucher/group/list', array(
               'networkId' => (int)$networkId
           ));
       }

       /**
        * Get vouchers for a specific group
        */
       public function getGroupVouchers($groupId, $pageNum = 1, $pageSize = 100) {
           return $this->apiCall('/oapi/v1.0.0/voucher/vouchers/list', array(
               'groupId' => (int)$groupId,
               'pageNum' => (int)$pageNum,
               'pageSize' => (int)$pageSize
           ));
       }

       /**
        * List network clients (devices)
        */
       public function listClients($filters = array()) {
           $defaults = array(
               'networkId' => (int)(defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : 0),
               'pageNum' => 1,
               'pageSize' => 100
           );

           $requestData = array_merge($defaults, $filters);
           return $this->apiCall('/oapi/v1.0.0/client/list', $requestData);
       }

       /**
        * Get client info by MAC address
        */
       public function getClientInfo($mac) {
           return $this->apiCall('/oapi/v1.0.0/client/info', array(
               'networkId' => (int)(defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : 0),
               'mac' => (string)$mac
           ));
       }

       /**
        * Clear cached token (force refresh on next call)
        */
       public function clearToken() {
           $this->token = null;
           $this->tokenExpiry = 0;
       }
   }