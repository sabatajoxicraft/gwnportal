<?php
require_once __DIR__ . '/GwnService.php';
require_once __DIR__ . '/CommunicationLogger.php';
require_once __DIR__ . '/TwilioService.php';
require_once __DIR__ . '/../helpers/VoucherMonthHelper.php';

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

        // Reject future-month issuance to prevent pre-emptive voucher creation.
        if (VoucherMonthHelper::isFutureMonth($month)) {
            error_log("VoucherService::sendStudentVoucher: Rejected future-month issuance for student $student_id, month='$month'");
            return false;
        }

        // Begin transaction and acquire per-user lock to prevent concurrent voucher issuance
        $conn->begin_transaction();
        // Lock the user row to serialize voucher requests for the same student.
        // This prevents race conditions where two concurrent requests both pass the
        // duplicate check and create vouchers.
        $lockStmt = safeQueryPrepare($conn, 'SELECT id FROM users WHERE id = ? FOR UPDATE');
        if (!$lockStmt) {
            error_log('VoucherService::sendStudentVoucher - user lock prepare failed: ' . $conn->error);
            $conn->rollback();
            return false;
        }
        $lockStmt->bind_param('i', $student['user_id']);
        $lockStmt->execute();
        $lockStmt->get_result();
        $lockStmt->close();

        // Duplicate send prevention: block if voucher already sent this month
        // Check again under lock to prevent race conditions
        if (!$forceResend) {
            $existing = $this->getExistingVoucher($conn, $student['user_id'], $month);
            if ($existing) {
                $conn->rollback();
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
        
        // Step 1: Create and retrieve voucher (remote GWN call outside DB's rollback control)
        $result = $this->createAndRetrieveVoucher($student_id, $accommodationName, $studentName, $month);
        
        if (!$result) {
            $conn->rollback();
            error_log("VoucherService::sendStudentVoucher: Failed to create voucher for student $student_id");
            return false;
        }
        
        $voucher_code = $result['code'];
        $groupId = $result['groupId'];
        $deviceNum = $result['deviceNum'];
        $groupName = $result['groupName'] ?? '';
        
        // Step 2: Send voucher code to student via preferred method (SMS OR WhatsApp, not both)
        $messageSent = false;
        $loggedSendMethod = $sendMethod;
        if (!empty($phoneNumber)) {
            $callbackUrl = ($sendMethod === 'WhatsApp')
                ? TwilioService::buildVoucherCallbackUrl((int)$student['user_id'], $voucher_code, $month, $sendMethod)
                : '';
            $sendMeta    = $this->sendVoucherMessageWithAudit(
                (int)$student['user_id'],
                $phoneNumber,
                $studentName,
                $month,
                $voucher_code,
                $sendMethod,
                $callbackUrl
            );
            $messageSent = (bool)$sendMeta['success'];
        }

        // Fallback: if preferred method failed, try the other method
        if (!$messageSent) {
            $fallbackMethod = ($sendMethod === 'WhatsApp') ? 'SMS' : 'WhatsApp';
            $fallbackNumber = ($fallbackMethod === 'SMS') 
                ? $student['phone_number'] 
                : ($student['whatsapp_number'] ?: $student['phone_number']);
            if (!empty($fallbackNumber)) {
                $callbackUrl = ($fallbackMethod === 'WhatsApp')
                    ? TwilioService::buildVoucherCallbackUrl((int)$student['user_id'], $voucher_code, $month, $fallbackMethod)
                    : '';
                $fallbackMeta = $this->sendVoucherMessageWithAudit(
                    (int)$student['user_id'],
                    $fallbackNumber,
                    $studentName,
                    $month,
                    $voucher_code,
                    $fallbackMethod,
                    $callbackUrl,
                    ['fallback_from' => strtolower((string)$sendMethod)]
                );
                $messageSent = (bool)$fallbackMeta['success'];
                if ($messageSent) {
                    $loggedSendMethod = $fallbackMethod;
                }
            }
        }

        if (!$messageSent) {
            error_log("VoucherService::sendStudentVoucher: Voucher $voucher_code created but message delivery failed for student $student_id ($sendMethod to $phoneNumber)");
        }
        
        // Step 3: Log to voucher_logs (inside transaction)
        $sqlFull   = "INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at, gwn_voucher_id, gwn_group_id) 
                      VALUES (?, ?, ?, ?, 'sent', NOW(), ?, ?)";
        $sqlLegacy = "INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at) 
                      VALUES (?, ?, ?, ?, 'sent', NOW())";
        $stmt      = safeQueryPrepare($conn, $sqlFull, false);
        $useLegacy = !$this->isUsableStmt($stmt);
        if ($useLegacy) {
            $stmt = safeQueryPrepare($conn, $sqlLegacy, false);
        }

        $voucherLogsOk = false;
        if ($this->isUsableStmt($stmt)) {
            if ($useLegacy) {
                $stmt->bind_param("isss", $student['user_id'], $voucher_code, $month, $loggedSendMethod);
            } else {
                $gwn_voucher_id = 0;
                $stmt->bind_param("isssii", $student['user_id'], $voucher_code, $month, $loggedSendMethod, $gwn_voucher_id, $groupId);
            }
            $voucherLogsOk = $stmt->execute();
            if (!$voucherLogsOk) {
                error_log("VoucherService::sendStudentVoucher: voucher_logs execute() failed: " . $stmt->error);
            }
        } else {
            error_log("VoucherService::sendStudentVoucher: voucher_logs insert prepare failed - " . $conn->error);
        }

        // Step 4: Record the GWN voucher group for tracking (inside transaction)
        $gwnGroupsOk = false;
        $networkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
        $sqlGroup  = "INSERT INTO gwn_voucher_groups (gwn_group_id, group_name, accommodation_id, voucher_month, network_id, voucher_count, created_at)
                     VALUES (?, ?, ?, ?, ?, 1, NOW())";
        $stmtGroup = safeQueryPrepare($conn, $sqlGroup, false);
        if ($this->isUsableStmt($stmtGroup)) {
            $stmtGroup->bind_param("isiss", $groupId, $groupName, $student['accommodation_id'], $month, $networkId);
            $gwnGroupsOk = $stmtGroup->execute();
            if (!$gwnGroupsOk) {
                error_log("VoucherService::sendStudentVoucher: gwn_voucher_groups execute() failed: " . $stmtGroup->error);
            }
        } else {
            error_log("VoucherService::sendStudentVoucher: gwn_voucher_groups insert prepare failed - " . $conn->error);
        }

        // Check if both DB operations succeeded
        if ($voucherLogsOk && $gwnGroupsOk) {
            // Success: commit transaction
            $conn->commit();
            // Step 5: Create in-app notification (after successful DB commit)
            createNotification(
                $student['user_id'],
                "Your WiFi voucher for $month has been generated. Code: $voucher_code (sent via $loggedSendMethod).",
                'voucher'
            );
            // Step 5b: Send opt-in notification email copy
            sendNotificationEmail(
                $student['user_id'],
                "WiFi Voucher for $month",
                "Your WiFi voucher for $month has been generated. Code: $voucher_code (sent via $loggedSendMethod)."
            );

            return [
                'voucher_month' => $month,
                'voucher_code' => $voucher_code,
                'sent_via' => $loggedSendMethod,
                'sent_at' => date('Y-m-d H:i:s')
            ];
        } else {
            // DB operations failed: rollback transaction and cleanup remote GWN group
            $conn->rollback();
            // Best-effort cleanup of the GWN group that was successfully created
            try {
                $deleteResult = $this->deleteVoucherGroup([$groupId]);
                if (!$this->responseSuccessful($deleteResult)) {
                    error_log("VoucherService::sendStudentVoucher: Failed to cleanup GWN group $groupId after DB failure: " . json_encode($deleteResult));
                } else {
                    error_log("VoucherService::sendStudentVoucher: Successfully cleaned up GWN group $groupId after DB failure");
                }
            } catch (Exception $e) {
                error_log("VoucherService::sendStudentVoucher: Exception during GWN group cleanup for group $groupId: " . $e->getMessage());
            }

            error_log("VoucherService::sendStudentVoucher: DB operations failed for student $student_id, voucher creation rolled back");
            return false;
        }
    }

    public function createAndRetrieveVoucher($student_id, $accommodationName, $studentName, $month) {
        $deviceNum = (int)(defined('GWN_ALLOWED_DEVICES') ? GWN_ALLOWED_DEVICES : 2);
        if ($deviceNum < 1) $deviceNum = 1;
        $durationDays = 30;

        $tz     = new DateTimeZone(VOUCHER_TZ);
        $now    = new DateTimeImmutable('now', $tz);
        $window = VoucherMonthHelper::getWindow((string)$month);

        if ($window !== null) {
            // Use midnight of next-month as the GWN expiry boundary; this
            // keeps the voucher alive through 23:59:59 of the last day.
            $expiryBoundary = $window['nextMonthStart'];
        } else {
            $expiryBoundary = $now->modify('first day of next month')->setTime(0, 0, 0);
        }

        // Safety: if boundary is already in the past (e.g. reissue for an
        // expired month), push forward to the next calendar month boundary.
        if ($expiryBoundary <= $now) {
            $expiryBoundary = $now->modify('first day of next month')->setTime(0, 0, 0);
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
        if (!$this->responseSuccessful($searchResult)) {
            error_log("VoucherService::createAndRetrieveVoucher: Failed to search for created group '$groupName' for student $student_id");
            // Cannot get group ID for cleanup, but group was created - log this orphan risk
            error_log("VoucherService::createAndRetrieveVoucher: WARNING - GWN group '$groupName' may be orphaned (search failed)");
            return false;
        }
        
        $groupId = 0;
        $groups = $this->collectRows($searchResult);
        foreach ($groups as $group) {
            if (is_array($group) && isset($group['name']) && $group['name'] === $groupName) {
                $groupId = (int)($group['id'] ?? 0);
                break;
            }
        }
        
        if ($groupId <= 0) {
            error_log("VoucherService::createAndRetrieveVoucher: Created group '$groupName' not found in search results for student $student_id");
            // Cannot get group ID for cleanup, but group was created - log this orphan risk
            error_log("VoucherService::createAndRetrieveVoucher: WARNING - GWN group '$groupName' may be orphaned (not found in search)");
            return false;
        }
        
        $listResult = $this->listVouchersInGroup($groupId);
        if (!$this->responseSuccessful($listResult)) {
            error_log("VoucherService::createAndRetrieveVoucher: Failed to list vouchers in group $groupId for student $student_id");
            $this->cleanupOrphanedGroup($groupId, $groupName, "voucher list failed");
            return false;
        }
        
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
        
        if (empty($voucher_code)) {
            error_log("VoucherService::createAndRetrieveVoucher: No voucher code found in group $groupId for student $student_id");
            $this->cleanupOrphanedGroup($groupId, $groupName, "no voucher code found");
            return false;
        }
        
        return [
            'code'     => $voucher_code,
            'groupId'  => $groupId,
            'deviceNum' => $deviceNum,
            'groupName' => $groupName
        ];
    }

    /**
     * Best-effort cleanup of a GWN group that was created but cannot be used
     * @param int $groupId The GWN group ID to delete
     * @param string $groupName The group name for logging
     * @param string $reason Why the cleanup is needed
     */
    private function cleanupOrphanedGroup($groupId, $groupName, $reason) {
        try {
            error_log("VoucherService::cleanupOrphanedGroup: Attempting cleanup of group $groupId ('$groupName') - reason: $reason");
            $deleteResult = $this->deleteVoucherGroup([$groupId]);
            if (!$this->responseSuccessful($deleteResult)) {
                error_log("VoucherService::cleanupOrphanedGroup: Failed to delete orphaned GWN group $groupId: " . json_encode($deleteResult));
            } else {
                error_log("VoucherService::cleanupOrphanedGroup: Successfully deleted orphaned GWN group $groupId ('$groupName')");
            }
        } catch (Exception $e) {
            error_log("VoucherService::cleanupOrphanedGroup: Exception while deleting orphaned GWN group $groupId: " . $e->getMessage());
        }
    }

    /**
     * Replace a voucher with a new one using the correct device limit.
     * Used to fix vouchers created with incorrect deviceNum (e.g. 3 instead of 2).
     * 
     * Steps: Revoke old voucher → Create new with correct deviceNum → Send to student.
     * 
     * @param int $voucherLogId The voucher_logs.id to replace
     * @param int $revokedByUserId The manager user_id performing the replacement
     * @return array|false Result array with new voucher details, or false on failure
     */
    public function replaceVoucher($voucherLogId, $revokedByUserId) {
        $conn = getDbConnection();
        
        // Get the old voucher details
        $sql = "SELECT vl.*, s.id as student_id, s.accommodation_id, 
                       u.first_name, u.last_name, u.phone_number, u.whatsapp_number, u.preferred_communication,
                       a.name as accommodation_name
                FROM voucher_logs vl
                JOIN users u ON vl.user_id = u.id
                JOIN students s ON u.id = s.user_id
                JOIN accommodations a ON s.accommodation_id = a.id
                WHERE vl.id = ?";
        $stmt = safeQueryPrepare($conn, $sql);
        $stmt->bind_param("i", $voucherLogId);
        $stmt->execute();
        $oldVoucher = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$oldVoucher) {
            error_log("VoucherService::replaceVoucher: Voucher log ID $voucherLogId not found");
            return false;
        }
        
        // Step 1: Revoke the old voucher in our DB
        $revoked = revokeVoucher($voucherLogId, 'Replaced: incorrect device limit', $revokedByUserId);
        if (!$revoked) {
            error_log("VoucherService::replaceVoucher: Failed to revoke old voucher $voucherLogId");
            return false;
        }
        
        // Step 2: Delete from GWN Cloud if it has a gwn_voucher_id
        if (!empty($oldVoucher['gwn_voucher_id'])) {
            $networkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
            if (!empty($networkId)) {
                gwnDeleteVoucher((int)$oldVoucher['gwn_voucher_id'], $networkId);
            }
        }
        
        // Also delete the GWN group if it exists
        if (!empty($oldVoucher['gwn_group_id'])) {
            $this->deleteVoucherGroup([(int)$oldVoucher['gwn_group_id']]);
        }
        
        // Step 3: Create new voucher and send via the existing flow
        $result = $this->sendStudentVoucher($oldVoucher['student_id'], $oldVoucher['voucher_month'], true);
        
        if (!$result || (isset($result['duplicate']) && $result['duplicate'])) {
            error_log("VoucherService::replaceVoucher: Failed to create replacement voucher for student " . $oldVoucher['student_id']);
            return false;
        }
        
        return $result;
    }

    /**
     * Returns true only if $stmt is a real mysqli_stmt that can be used.
     * Catches the DummyStatement that safeQueryPrepare() returns on prepare failure.
     */
    private function isUsableStmt($stmt): bool
    {
        return $stmt !== false && !($stmt instanceof DummyStatement);
    }

    private function sendVoucherMessageWithAudit(
        int $userId,
        string $number,
        string $studentName,
        string $month,
        string $voucherCode,
        string $method,
        string $callbackUrl = '',
        array $extraTransportMeta = []
    ): array {
        $meta = TwilioService::sendVoucherMessageDetailed(
            $number,
            $studentName,
            $month,
            $voucherCode,
            $method,
            $callbackUrl !== '' ? $callbackUrl : null
        );

        $transportMeta = array_merge([
            'transport'      => 'twilio',
            'http_code'      => (int)($meta['http_code'] ?? 0),
            'transport_error'=> (string)($meta['transport_error'] ?? ($meta['error'] ?? '')),
            'twilio_code'    => isset($meta['twilio_code']) ? (int)$meta['twilio_code'] : 0,
            'message_status' => (string)($meta['message_status'] ?? ''),
            'voucher_code'   => $voucherCode,
            'voucher_month'  => $month,
        ], $extraTransportMeta);

        if (strtoupper((string)$method) === 'WHATSAPP') {
            CommunicationLogger::logWhatsApp(
                $number,
                'voucher',
                (bool)($meta['success'] ?? false),
                $userId,
                $meta['sid'] ?? null,
                $transportMeta
            );
        } else {
            CommunicationLogger::logSms(
                $number,
                'voucher',
                (bool)($meta['success'] ?? false),
                $userId,
                $meta['sid'] ?? null,
                $transportMeta
            );
        }

        return $meta;
    }
}
