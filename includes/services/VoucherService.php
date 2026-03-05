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

    /**
     * Check if a voucher has already been sent for this user/month.
     * Returns the existing voucher_logs row or null.
     */
    public function getExistingVoucher($conn, $userId, $month) {
        $monthAlt = '';
        $dt = DateTime::createFromFormat('F Y', trim($month));
        if ($dt) {
            $monthAlt = $dt->format('Y-m');
        }
        $sql = "SELECT voucher_code, voucher_month, sent_via, sent_at FROM voucher_logs WHERE user_id = ? AND (voucher_month = ? OR voucher_month = ?) AND status = 'sent' LIMIT 1";
        $stmt = safeQueryPrepare($conn, $sql, false);
        if (!$stmt || $stmt instanceof DummyStatement) return null;
        $stmt->bind_param("iss", $userId, $month, $monthAlt);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function sendStudentVoucher($student_id, $month, $forceResend = false) {
        $conn = getDbConnection();
        
        // Get student details
        $sql = "SELECT s.id, s.user_id, s.accommodation_id, u.first_name, u.last_name, u.phone_number, u.whatsapp_number, u.preferred_communication,
                       a.name AS accommodation_name
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN accommodations a ON s.accommodation_id = a.id
                WHERE s.id = ?";
        $stmt = safeQueryPrepare($conn, $sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        
        if (!$student) {
            error_log("VoucherService::sendStudentVoucher: Student ID $student_id not found");
            return false;
        }

        // Duplicate send prevention: block if voucher already sent this month
        if (!$forceResend) {
            $existing = $this->getExistingVoucher($conn, $student['user_id'], $month);
            if ($existing) {
                error_log("VoucherService::sendStudentVoucher: Duplicate blocked - student $student_id already has voucher for $month");
                return [
                    'voucher_month' => $existing['voucher_month'],
                    'voucher_code' => $existing['voucher_code'],
                    'sent_via' => $existing['sent_via'],
                    'sent_at' => $existing['sent_at'],
                    'duplicate' => true,
                ];
            }
        }
        
        $studentName = $student['first_name'] . ' ' . $student['last_name'];
        $accommodationName = $student['accommodation_name'];
        $sendMethod = $student['preferred_communication'] ?: 'SMS';
        $phoneNumber = ($sendMethod === 'WhatsApp') 
            ? ($student['whatsapp_number'] ?: $student['phone_number'])
            : ($student['phone_number'] ?: $student['whatsapp_number']);
        
        // Step 1: Create and retrieve voucher
        $result = $this->createAndRetrieveVoucher($student_id, $accommodationName, $studentName, $month);
        
        if (!$result) {
            error_log("VoucherService::sendStudentVoucher: Failed to create voucher for student $student_id");
            return false;
        }
        
        $voucher_code = $result['code'];
        $groupId = $result['groupId'];
        $deviceNum = $result['deviceNum'];
        
        // Step 2: Send voucher code to student via preferred method (SMS OR WhatsApp, not both)
        $messageSent = false;
        $loggedSendMethod = $sendMethod;
        if (!empty($phoneNumber)) {
            $messageSent = sendVoucherMessage($phoneNumber, $studentName, $month, $voucher_code, $sendMethod);
        }

        // Fallback: if preferred method failed, try the other method
        if (!$messageSent) {
            $fallbackMethod = ($sendMethod === 'WhatsApp') ? 'SMS' : 'WhatsApp';
            $fallbackNumber = ($fallbackMethod === 'SMS') 
                ? $student['phone_number'] 
                : ($student['whatsapp_number'] ?: $student['phone_number']);
            if (!empty($fallbackNumber)) {
                $messageSent = sendVoucherMessage($fallbackNumber, $studentName, $month, $voucher_code, $fallbackMethod);
                if ($messageSent) {
                    $loggedSendMethod = $fallbackMethod;
                }
            }
        }

        if (!$messageSent) {
            error_log("VoucherService::sendStudentVoucher: Voucher $voucher_code created but message delivery failed for student $student_id ($sendMethod to $phoneNumber)");
        }
        
        // Step 3: Create in-app notification
        createNotification(
            $student['user_id'],
            "Your WiFi voucher for $month has been generated. Code: $voucher_code (sent via $loggedSendMethod).",
            'voucher'
        );
        
        // Step 4: Log to voucher_logs
        $sqlFull   = "INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at, gwn_voucher_id, gwn_group_id) 
                      VALUES (?, ?, ?, ?, 'sent', NOW(), ?, ?)";
        $sqlLegacy = "INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at) 
                      VALUES (?, ?, ?, ?, 'sent', NOW())";
        $stmt = safeQueryPrepare($conn, $sqlFull, false);
        $useLegacy = ($stmt === false);
        if ($useLegacy) {
            $stmt = safeQueryPrepare($conn, $sqlLegacy, false);
        }
        
        if ($stmt) {
            if ($useLegacy) {
                $stmt->bind_param("isss", $student['user_id'], $voucher_code, $month, $loggedSendMethod);
            } else {
                $gwn_voucher_id = 0;
                $stmt->bind_param("isssii", $student['user_id'], $voucher_code, $month, $loggedSendMethod, $gwn_voucher_id, $groupId);
            }
            $stmt->execute();
        } else {
            error_log("VoucherService::sendStudentVoucher: voucher_logs insert failed - " . $conn->error);
        }
        
        // Step 5: Record the GWN voucher group for tracking
        $networkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
        $sqlGroup = "INSERT INTO gwn_voucher_groups (gwn_group_id, group_name, accommodation_id, voucher_month, network_id, voucher_count, created_at) 
                     VALUES (?, ?, ?, ?, ?, 1, NOW())";
        $stmtGroup = safeQueryPrepare($conn, $sqlGroup, false);
        if ($stmtGroup) {
            $groupName = $result['groupName'] ?? '';
            $stmtGroup->bind_param("isiss", $groupId, $groupName, $student['accommodation_id'], $month, $networkId);
            $stmtGroup->execute();
        } else {
            error_log("VoucherService::sendStudentVoucher: gwn_voucher_groups insert failed - " . $conn->error);
        }
        
        return [
            'voucher_month' => $month,
            'voucher_code' => $voucher_code,
            'sent_via' => $loggedSendMethod,
            'sent_at' => date('Y-m-d H:i:s')
        ];
    }

    public function createAndRetrieveVoucher($student_id, $accommodationName, $studentName, $month) {
        $deviceNum = (int)(defined('GWN_ALLOWED_DEVICES') ? GWN_ALLOWED_DEVICES : 2);
        if ($deviceNum < 1) $deviceNum = 1;
        $durationDays = 30;

        $voucherMonth = DateTime::createFromFormat('F Y', trim((string)$month));
        if (!$voucherMonth) $voucherMonth = new DateTime('first day of this month');

        $expiryBoundary = (clone $voucherMonth)->modify('first day of next month')->setTime(0, 0, 0);
        $now = new DateTime();
        if ($expiryBoundary <= $now) {
            $expiryBoundary = (clone $now)->modify('first day of next month')->setTime(0, 0, 0);
        }

        $secondsToBoundary = $expiryBoundary->getTimestamp() - $now->getTimestamp();
        $expirationDays = (int)max(1, ceil($secondsToBoundary / 86400));

        // Duration must not exceed expiration (GWN API rejects it otherwise)
        if ($durationDays > $expirationDays) {
            $durationDays = $expirationDays;
        }
        
        $randomSuffix = bin2hex(random_bytes(4));
        $groupName = $accommodationName . ' - ' . $studentName . ' - ' . $month . ' - ' . $randomSuffix;
        
        error_log("VoucherService::createAndRetrieveVoucher: expirationDays=$expirationDays, durationDays=$durationDays");

        $createPayload = [
            'name'              => $groupName,
            'vocherNum'         => 1,
            'voucherNum'        => 1,
            'deviceNum'         => (string)$deviceNum,
            'expiration'        => $expirationDays,
            'effectDurationMap' => [
                'd' => (string)$durationDays,
                'h' => '0',
                'm' => '0',
            ],
            'usageLimitType'    => 0,
            'description'       => "Student voucher: $studentName - $month",
        ];
        
        $createResult = $this->createVoucherGroup($createPayload);
        if (!$this->responseSuccessful($createResult)) {
            error_log("VoucherService::createAndRetrieveVoucher: Failed to create group for student $student_id: " . json_encode($createResult));
            return false;
        }
        
        usleep(750000); 
        
        $searchResult = $this->listVoucherGroups(null, 1, 20, $groupName);
        if (!$this->responseSuccessful($searchResult)) return false;
        
        $groupId = 0;
        $groups = $this->collectRows($searchResult);
        foreach ($groups as $group) {
            if (is_array($group) && isset($group['name']) && $group['name'] === $groupName) {
                $groupId = (int)($group['id'] ?? 0);
                break;
            }
        }
        
        if ($groupId <= 0) return false;
        
        $listResult = $this->listVouchersInGroup($groupId);
        if (!$this->responseSuccessful($listResult)) return false;
        
        $voucher_code = '';
        $rows = $this->collectRows($listResult);
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $code = gwnExtractVoucherCode($row);
            if ($code !== '') {
                $voucher_code = $code;
                break;
            }
        }
        
        if (empty($voucher_code)) return false;
        
        return [
            'code'     => $voucher_code,
            'groupId'  => $groupId,
            'deviceNum' => $deviceNum,
            'groupName' => $groupName
        ];
    }
}

