<?php
/**
 * Send a voucher to a student
 * 
 * Simplified voucher creation: create with guaranteed unique name, retrieve voucher code, send.
 * 
 * @param int $student_id The student ID
 * @param string $month The month for the voucher (e.g., "January 2026")
 * @return bool True if sent successfully, false otherwise
 */
function sendStudentVoucher($student_id, $month) {
    $conn = getDbConnection();
    
    // Get student details
    $sql = "SELECT s.id, s.user_id, s.accommodation_id, u.first_name, u.last_name, u.phone_number, u.whatsapp_number, u.preferred_communication,
                   a.name AS accommodation_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN accommodations a ON s.accommodation_id = a.id
            WHERE s.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    if (!$student) {
        error_log("sendStudentVoucher: Student ID $student_id not found");
        return false;
    }
    
    $studentName = $student['first_name'] . ' ' . $student['last_name'];
    $accommodationName = $student['accommodation_name'];
    $sendMethod = $student['preferred_communication'] ?: 'SMS';
    $phoneNumber = ($sendMethod === 'WhatsApp') 
        ? ($student['whatsapp_number'] ?: $student['phone_number'])
        : ($student['phone_number'] ?: $student['whatsapp_number']);
    
    // Step 1: Create and retrieve voucher
    $result = createAndRetrieveVoucher($student_id, $accommodationName, $studentName, $month);
    
    if (!$result) {
        error_log("sendStudentVoucher: Failed to create voucher for student $student_id");
        return false;
    }
    
    $voucher_code = $result['code'];
    $groupId = $result['groupId'];
    $deviceNum = $result['deviceNum'];
    
    // Step 2: Send voucher code to student via SMS/WhatsApp
    $messageSent = false;
    $loggedSendMethod = $sendMethod;
    if (!empty($phoneNumber)) {
        $messageSent = sendVoucherMessage($phoneNumber, $studentName, $month, $voucher_code, $sendMethod);
    }

    // SMS backup: always send SMS after WhatsApp attempt
    if ($sendMethod === 'WhatsApp' && !empty($student['phone_number'])) {
        $smsBody = "Hi $studentName, your monthly WiFi voucher code for $month is: $voucher_code. Max {$deviceNum} devices. Need help? WhatsApp 0846983888";
        $smsSent = sendSMS($student['phone_number'], $smsBody);
        $messageSent = ($messageSent || $smsSent);
        if ($smsSent) {
            $loggedSendMethod = 'SMS';
        }
    }

    if (!$messageSent) {
        error_log("sendStudentVoucher: Voucher $voucher_code created but message delivery failed for student $student_id ($sendMethod to $phoneNumber)");
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
            $gwn_voucher_id = 0; // TODO: Extract from result if available
            $stmt->bind_param("isssii", $student['user_id'], $voucher_code, $month, $loggedSendMethod, $gwn_voucher_id, $groupId);
        }
        $stmt->execute();
    } else {
        error_log("sendStudentVoucher: voucher_logs insert failed – " . $conn->error);
    }
    
    // Step 5: Record the GWN voucher group for tracking
    $networkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
    $sqlGroup = "INSERT INTO gwn_voucher_groups (gwn_group_id, group_name, accommodation_id, voucher_month, network_id, voucher_count, created_at) 
                 VALUES (?, ?, ?, ?, ?, 1, NOW())";
    $stmtGroup = safeQueryPrepare($conn, $sqlGroup, false);
    if ($stmtGroup) {
        $stmtGroup->bind_param("isiss", $groupId, $groupName, $student['accommodation_id'], $month, $networkId);
        $stmtGroup->execute();
    } else {
        error_log("sendStudentVoucher: gwn_voucher_groups insert failed – " . $conn->error);
    }
    
    return true;
}

/**
 * Create a voucher group on GWN Cloud and retrieve the voucher code.
 * Centralized logic with guaranteed unique naming.
 * 
 * @param int $student_id Student ID (for logging)
 * @param string $accommodationName Accommodation name
 * @param string $studentName Student full name
 * @param string $month Voucher month (e.g., "January 2026")
 * @return array|false Array with keys: code, groupId, deviceNum; or false on failure
 */
function createAndRetrieveVoucher($student_id, $accommodationName, $studentName, $month) {
    require_once __DIR__ . '/../services/VoucherService.php';
    $voucherService = new VoucherService();
    
    // Get device limit and expiry from environment
    $deviceNum = (int)(defined('GWN_ALLOWED_DEVICES') ? GWN_ALLOWED_DEVICES : 2);
    if ($deviceNum < 1) {
        $deviceNum = 1;
    }
    $durationDays = 30;

    // Calculate expiry: first day of next month
    $voucherMonth = DateTime::createFromFormat('F Y', trim((string)$month));
    if (!$voucherMonth) {
        $voucherMonth = new DateTime('first day of this month');
    }

    $expiryBoundary = (clone $voucherMonth)->modify('first day of next month')->setTime(0, 0, 0);
    $now = new DateTime();
    if ($expiryBoundary <= $now) {
        $expiryBoundary = (clone $now)->modify('first day of next month')->setTime(0, 0, 0);
    }

    $secondsToBoundary = $expiryBoundary->getTimestamp() - $now->getTimestamp();
    $expirationDays = (int)max(1, ceil($secondsToBoundary / 86400));
    
    // Generate guaranteed unique group name: randomize with microtime
    $randomSuffix = bin2hex(random_bytes(4)); // 8-char hex string
    $groupName = $accommodationName . ' - ' . $studentName . ' - ' . $month . ' - ' . $randomSuffix;
    
    // Construct GWN API payload
    $createPayload = [
        'name'              => $groupName,
        'vocherNum'         => 1,
        'voucherNum'        => 1,
        'deviceNum'         => (string)$deviceNum,  // CRITICAL: Send as string
        'expiration'        => $expirationDays,
        'effectDurationMap' => [
            'd' => (string)$durationDays,
            'h' => '0',
            'm' => '0',
        ],
        'usageLimitType'    => 0,
        'description'       => "Student voucher: $studentName - $month",
    ];
    
    error_log("createAndRetrieveVoucher: Creating GWN group '$groupName' with deviceNum=$deviceNum, expirationDays=$expirationDays");
    
    // Create the voucher group
    $createResult = $voucherService->createVoucherGroup($createPayload);
    if (!$voucherService->responseSuccessful($createResult)) {
        error_log("createAndRetrieveVoucher: Failed to create group for student $student_id: " . json_encode($createResult));
        return false;
    }
    
    // Wait for GWN Cloud to sync
    usleep(750000); // 0.75 seconds
    
    // Retrieve the voucher code from the group
    // Note: We must search by name to get the group ID
    $searchResult = $voucherService->listVoucherGroups(null, 1, 20, $groupName);
    if (!$voucherService->responseSuccessful($searchResult)) {
        error_log("createAndRetrieveVoucher: Failed to search for group '$groupName' after creation");
        return false;
    }
    
    $groupId = 0;
    $groups = $voucherService->collectRows($searchResult);
    foreach ($groups as $group) {
        if (is_array($group) && isset($group['name']) && $group['name'] === $groupName) {
            $groupId = (int)($group['id'] ?? 0);
            error_log("createAndRetrieveVoucher: Found group ID $groupId for name '$groupName'");
            break;
        }
    }
    
    if ($groupId <= 0) {
        error_log("createAndRetrieveVoucher: Group created but not found in search for student $student_id");
        return false;
    }
    
    // Retrieve vouchers from the group
    $listResult = $voucherService->listVouchersInGroup($groupId);
    if (!$voucherService->responseSuccessful($listResult)) {
        error_log("createAndRetrieveVoucher: Failed to list vouchers in group $groupId for student $student_id");
        return false;
    }
    
    $voucher_code = '';
    $rows = $voucherService->collectRows($listResult);
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $code = gwnExtractVoucherCode($row);
        if ($code !== '') {
            $voucher_code = $code;
            break;
        }
    }
    
    if (empty($voucher_code)) {
        error_log("createAndRetrieveVoucher: No voucher code found in group $groupId for student $student_id");
        return false;
    }
    
    error_log("createAndRetrieveVoucher: Successfully created voucher '$voucher_code' for student $student_id with deviceNum=$deviceNum");
    
    return [
        'code'     => $voucher_code,
        'groupId'  => $groupId,
        'deviceNum' => $deviceNum,
    ];
}
?>
