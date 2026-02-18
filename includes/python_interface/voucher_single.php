<?php
/**
 * Send a voucher to a student
 * 
 * Uses VoucherService to create a voucher group on GWN Cloud,
 * extracts the voucher code, sends it via SMS/WhatsApp, and logs it.
 * 
 * @param int $student_id The student ID
 * @param string $month The month for the voucher (e.g., "January 2026")
 * @return bool True if sent successfully, false otherwise
 */
function sendStudentVoucher($student_id, $month) {
    $conn = getDbConnection();
    
    // Get student details with user info and accommodation name
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
    
    // --- Step 1: Create a single-voucher group on GWN Cloud ---
    require_once __DIR__ . '/../services/VoucherService.php';
    $voucherService = new VoucherService();
    
    $groupName = $accommodationName . ' - ' . $studentName . ' - ' . $month;
    $deviceNum = (int)(defined('GWN_ALLOWED_DEVICES') ? GWN_ALLOWED_DEVICES : 2);
    $durationDays = 30; // Voucher effect duration in days
    $expirationDays = 45; // Validity window: how many days before unused voucher expires
    
    $createResult = $voucherService->createVoucherGroup([
        'name'              => $groupName,
        'vocherNum'         => 1,              // GWN API uses "vocherNum" (their typo)
        'deviceNum'         => $deviceNum,     // Max devices per voucher (0 = no limit)
        'expiration'        => $expirationDays, // Required: validity time in days (1-1095)
        'effectDurationMap' => [               // Required: effect duration (strings!)
            'd' => (string)$durationDays,
            'h' => '0',
            'm' => '0',
        ],
        'usageLimitType'    => 0,              // 0 = per voucher, 1 = per client
        'description'       => "Student voucher: $studentName - $month",
    ]);
    
    if (!$voucherService->responseSuccessful($createResult)) {
        error_log("sendStudentVoucher: GWN API createVoucherGroup failed for student $student_id: " . json_encode($createResult));
        return false;
    }
    
    // The GWN API /voucher/save returns data:"" on success with no group ID.
    // We must find the newly created group by searching for its name.
    usleep(500000); // 0.5 seconds for GWN Cloud propagation
    
    $groupId = 0;
    $searchResult = $voucherService->listVoucherGroups(null, 1, 10, $groupName);
    if ($voucherService->responseSuccessful($searchResult)) {
        $groups = $voucherService->collectRows($searchResult);
        foreach ($groups as $group) {
            if (is_array($group) && isset($group['name']) && $group['name'] === $groupName) {
                $groupId = (int)($group['id'] ?? 0);
                break;
            }
        }
    }
    
    if ($groupId <= 0) {
        error_log("sendStudentVoucher: Voucher group created but could not find it by name '$groupName'. Search result: " . json_encode($searchResult));
        return false;
    }
    
    // --- Step 2: Retrieve the voucher code from the newly created group ---
    $voucher_code = '';
    $gwn_voucher_id = 0;
    
    $listResult = $voucherService->listVouchersInGroup($groupId);
    
    if ($voucherService->responseSuccessful($listResult)) {
        $rows = $voucherService->collectRows($listResult);
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $code = gwnExtractVoucherCode($row);
            if ($code !== '') {
                $voucher_code = $code;
                // Extract voucher ID for future management
                foreach (['voucherId', 'id'] as $idKey) {
                    if (isset($row[$idKey]) && !is_array($row[$idKey]) && (int)$row[$idKey] > 0) {
                        $gwn_voucher_id = (int)$row[$idKey];
                        break;
                    }
                }
                break;
            }
        }
    }
    
    if (empty($voucher_code)) {
        error_log("sendStudentVoucher: Voucher group $groupId created but could not extract voucher code. Response: " . json_encode($listResult));
        return false;
    }
    
    // --- Step 3: Send voucher code to student via SMS/WhatsApp ---
    $messageSent = false;
    if (!empty($phoneNumber)) {
        // Use the template-aware sendVoucherMessage() which supports Twilio Content Templates
        // Template variables: {{1}} = student name, {{2}} = month, {{3}} = voucher code
        $messageSent = sendVoucherMessage($phoneNumber, $studentName, $month, $voucher_code, $sendMethod);
    }
    
    // Log even if messaging fails (voucher was created on GWN Cloud)
    if (!$messageSent) {
        error_log("sendStudentVoucher: Voucher $voucher_code created but message delivery failed for student $student_id ($sendMethod to $phoneNumber)");
    }
    
    // --- Step 3b: Create in-app notification for the student ---
    createNotification(
        $student['user_id'],
        "Your WiFi voucher for $month has been generated. Code: $voucher_code (sent via $sendMethod).",
        'voucher'
    );
    
    // --- Step 4: Log to voucher_logs ---
    $sql = "INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at, gwn_voucher_id, gwn_group_id) 
            VALUES (?, ?, ?, ?, 'sent', NOW(), ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("isssii", $student['user_id'], $voucher_code, $month, $sendMethod, $gwn_voucher_id, $groupId);
        $stmt->execute();
    }
    
    // --- Step 5: Record the GWN voucher group for tracking ---
    $networkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
    $sqlGroup = "INSERT INTO gwn_voucher_groups (gwn_group_id, group_name, accommodation_id, voucher_month, network_id, voucher_count, created_at) 
                 VALUES (?, ?, ?, ?, ?, 1, NOW())";
    $stmtGroup = $conn->prepare($sqlGroup);
    if ($stmtGroup) {
        $stmtGroup->bind_param("isiss", $groupId, $groupName, $student['accommodation_id'], $month, $networkId);
        $stmtGroup->execute();
    }
    
    return true;
}
?>
