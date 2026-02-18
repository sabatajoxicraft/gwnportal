<?php
/**
 * Daily voucher first-use + auto-link job.
 *
 * Usage:
 *   php auto_link_devices.php
 *   php auto_link_devices.php --dry-run
 *   php auto_link_devices.php --debug
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/python_interface.php';

function autoLinkLog($message) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function autoLinkDebug($message, $enabled) {
    if ($enabled) {
        autoLinkLog('DEBUG: ' . $message);
    }
}

function markVoucherFirstUseState($conn, $voucherLogId, $mac, $dryRun, $hasFirstUsedAt, $hasFirstUsedMac) {
    if ($dryRun || !$hasFirstUsedAt) {
        return true;
    }

    $now = date('Y-m-d H:i:s');
    if ($hasFirstUsedMac && $mac) {
        $stmt = safeQueryPrepare($conn,
            "UPDATE voucher_logs
             SET first_used_at = COALESCE(first_used_at, ?),
                 first_used_mac = COALESCE(NULLIF(first_used_mac, ''), ?)
             WHERE id = ?", false);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ssi", $now, $mac, $voucherLogId);
    } else {
        $stmt = safeQueryPrepare($conn,
            "UPDATE voucher_logs
             SET first_used_at = COALESCE(first_used_at, ?)
             WHERE id = ?", false);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("si", $now, $voucherLogId);
    }

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function getVoucherUseNotificationRecipients($conn, $accommodationId) {
    $recipientIds = array();

    $adminStmt = safeQueryPrepare($conn,
        "SELECT u.id
         FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE r.name = 'admin' AND u.status = 'active'");
    if ($adminStmt) {
        $adminStmt->execute();
        foreach ($adminStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $recipientIds[] = (int)$row['id'];
        }
        $adminStmt->close();
    }

    if ($accommodationId > 0) {
        $managerStmt = safeQueryPrepare($conn,
            "SELECT DISTINCT u.id
             FROM users u
             JOIN roles r ON u.role_id = r.id
             JOIN user_accommodation ua ON ua.user_id = u.id
             WHERE r.name = 'manager'
               AND u.status = 'active'
               AND ua.accommodation_id = ?");
        if ($managerStmt) {
            $managerStmt->bind_param("i", $accommodationId);
            $managerStmt->execute();
            foreach ($managerStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $recipientIds[] = (int)$row['id'];
            }
            $managerStmt->close();
        }
    }

    return array_values(array_unique($recipientIds));
}

$dryRun = in_array('--dry-run', $argv ?? array(), true);
$debug = in_array('--debug', $argv ?? array(), true);
$monthIso = date('Y-m');
$monthLabel = date('F Y');
$retryWindowDays = 7; // Keep trying to capture MACs for 7 days

$firstUseDetected = 0;
$linked = 0;
$alreadyLinked = 0;
$alreadyProcessed = 0;
$pendingManual = 0;
$conflicts = 0;
$skipped = 0;
$errors = 0;
$retriedMacs = 0;

autoLinkLog('Auto-Link Devices - Starting (mode: ' . ($dryRun ? 'DRY-RUN' : 'LIVE') . ')');
autoLinkDebug('Month context: ' . $monthIso . ' / ' . $monthLabel . ', Retry window: ' . $retryWindowDays . ' days', $debug);

$mappings = getVoucherDeviceMappings($monthIso);
autoLinkLog('Found ' . count($mappings) . ' voucher-use mappings for ' . $monthIso);
if ($debug && !empty($mappings)) {
    $sample = array_slice($mappings, 0, 5);
    autoLinkDebug('Sample mappings: ' . json_encode($sample), $debug);
}

if (empty($mappings)) {
    autoLinkLog('No used voucher mappings found. Exiting.');
    exit(0);
}

$conn = getDbConnection();
$seen = array();
$hasFirstUsedAt = false;
$hasFirstUsedMac = false;

$firstUsedColumnStmt = safeQueryPrepare($conn, "SHOW COLUMNS FROM voucher_logs LIKE 'first_used_at'", false);
if ($firstUsedColumnStmt) {
    $firstUsedColumnStmt->execute();
    $hasFirstUsedAt = $firstUsedColumnStmt->get_result()->num_rows > 0;
    $firstUsedColumnStmt->close();
}

$firstUsedMacColumnStmt = safeQueryPrepare($conn, "SHOW COLUMNS FROM voucher_logs LIKE 'first_used_mac'", false);
if ($firstUsedMacColumnStmt) {
    $firstUsedMacColumnStmt->execute();
    $hasFirstUsedMac = $firstUsedMacColumnStmt->get_result()->num_rows > 0;
    $firstUsedMacColumnStmt->close();
}

if (!$hasFirstUsedAt) {
    autoLinkLog('WARN: first_used_at column not found in voucher_logs; apply migration for first-use tracking.');
}
autoLinkDebug('first_used_at column: ' . ($hasFirstUsedAt ? 'yes' : 'no') . ', first_used_mac column: ' . ($hasFirstUsedMac ? 'yes' : 'no'), $debug);

$studentLookupSql = ($hasFirstUsedAt && $hasFirstUsedMac)
    ? "SELECT vl.id, vl.user_id, u.first_name, u.last_name, s.accommodation_id, vl.first_used_at, vl.first_used_mac
       FROM voucher_logs vl
       JOIN users u ON u.id = vl.user_id
       LEFT JOIN students s ON s.user_id = vl.user_id
       WHERE vl.voucher_code = ?
         AND (vl.voucher_month = ? OR vl.voucher_month = ? OR DATE_FORMAT(vl.created_at, '%Y-%m') = ?)
         AND (vl.is_active = 1 OR vl.is_active IS NULL)
       ORDER BY vl.id DESC
       LIMIT 1"
    : ($hasFirstUsedAt
        ? "SELECT vl.id, vl.user_id, u.first_name, u.last_name, s.accommodation_id, vl.first_used_at
           FROM voucher_logs vl
           JOIN users u ON u.id = vl.user_id
           LEFT JOIN students s ON s.user_id = vl.user_id
           WHERE vl.voucher_code = ?
             AND (vl.voucher_month = ? OR vl.voucher_month = ? OR DATE_FORMAT(vl.created_at, '%Y-%m') = ?)
             AND (vl.is_active = 1 OR vl.is_active IS NULL)
           ORDER BY vl.id DESC
           LIMIT 1"
        : "SELECT vl.id, vl.user_id, u.first_name, u.last_name, s.accommodation_id
           FROM voucher_logs vl
           JOIN users u ON u.id = vl.user_id
           LEFT JOIN students s ON s.user_id = vl.user_id
           WHERE vl.voucher_code = ?
             AND (vl.voucher_month = ? OR vl.voucher_month = ? OR DATE_FORMAT(vl.created_at, '%Y-%m') = ?)
           ORDER BY vl.id DESC
           LIMIT 1");

foreach ($mappings as $map) {
    $voucherCode = trim((string)($map['voucher_code'] ?? ''));
    $rawMac = trim((string)($map['mac'] ?? ''));
    $mac = $rawMac !== '' ? formatMacAddress($rawMac) : null;

    if ($voucherCode === '') {
        $skipped++;
        continue;
    }

    $dedupeKey = $voucherCode . '|' . ($mac ?: 'NO_MAC');
    if (isset($seen[$dedupeKey])) {
        continue;
    }
    $seen[$dedupeKey] = true;

    $studentStmt = safeQueryPrepare($conn, $studentLookupSql, false);
    if (!$studentStmt) {
        $errors++;
        autoLinkLog("ERROR: Failed to prepare student lookup for voucher {$voucherCode}");
        continue;
    }
    $studentStmt->bind_param("ssss", $voucherCode, $monthIso, $monthLabel, $monthIso);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc();
    $studentStmt->close();
    autoLinkDebug('Lookup voucher ' . $voucherCode . ' -> ' . ($student ? 'matched user_id ' . (int)$student['user_id'] : 'no match'), $debug);

    if (!$student) {
        autoLinkLog("SKIP: Voucher {$voucherCode} is not active for {$monthIso}/{$monthLabel}");
        $skipped++;
        continue;
    }

    $voucherLogId = (int)$student['id'];
    $studentUserId = (int)$student['user_id'];
    $studentName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
    $accommodationId = (int)($student['accommodation_id'] ?? 0);
    $isFirstUse = !$hasFirstUsedAt || empty($student['first_used_at']);

    // Calculate retry eligibility for missing MACs
    $firstUsedTime = ($hasFirstUsedAt && !empty($student['first_used_at'])) ? strtotime($student['first_used_at']) : 0;
    $retryDeadline = $firstUsedTime + ($retryWindowDays * 86400);
    $withinRetryWindow = $firstUsedTime > 0 && time() < $retryDeadline;
    $needsMacRetry = ($hasFirstUsedAt && $hasFirstUsedMac && !$isFirstUse 
        && empty($student['first_used_mac']) && $withinRetryWindow);

    if ($hasFirstUsedAt && !$isFirstUse && !$needsMacRetry) {
        $alreadyProcessed++;
        continue; // Skip only if we have MAC or retry window expired
    }

    if ($needsMacRetry) {
        $daysAgo = floor((time() - $firstUsedTime) / 86400);
        autoLinkDebug("Retrying MAC capture for voucher {$voucherCode} (first used {$daysAgo} days ago, within {$retryWindowDays} day window)", $debug);
        $retriedMacs++;
    }

    if (!$mac) {
        // Check if we have first_used_mac in database as fallback
        $historicalMac = ($hasFirstUsedMac && !empty($student['first_used_mac'])) ? trim((string)$student['first_used_mac']) : '';
        if ($historicalMac !== '') {
            $mac = formatMacAddress($historicalMac);
            autoLinkDebug('Using historical MAC from first_used_mac: ' . $mac, $debug);
            // Continue processing with the historical MAC
        } else {
            // Try to find MAC from client service for recent voucher usage
            if ($needsMacRetry && $firstUsedTime > 0) {
                autoLinkDebug("Attempting to find MAC via client service for voucher {$voucherCode}", $debug);
                $clientService = new ClientService();
                
                // Query clients from around the first_used_at timestamp (± 24 hours)
                $searchStart = ($firstUsedTime - 86400) * 1000; // Convert to milliseconds
                $searchEnd = ($firstUsedTime + 86400) * 1000;
                
                $clientHistory = $clientService->listClientHistory(null, 1, 100, '', '', '', array(), $searchStart, $searchEnd);
                
                if ($clientService->responseSuccessful($clientHistory)) {
                    $clients = gwnCollectRows($clientHistory);
                    autoLinkDebug("Found " . count($clients) . " clients in time window around first use", $debug);
                    
                    // Look for clients that might match this voucher
                    // This is a heuristic - we can't perfectly match without voucher code in client data
                    // but we can use timing and user patterns
                    foreach ($clients as $client) {
                        $clientMac = gwnNormalizeMac($client['clientId'] ?? ($client['mac'] ?? ''));
                        if ($clientMac !== '') {
                            // Check if this MAC is already linked to another user
                            $checkStmt = safeQueryPrepare($conn, "SELECT user_id FROM user_devices WHERE mac_address = ? LIMIT 1");
                            if ($checkStmt) {
                                $checkStmt->bind_param("s", $clientMac);
                                $checkStmt->execute();
                                $macOwner = $checkStmt->get_result()->fetch_assoc();
                                $checkStmt->close();
                                
                                // If not linked, this could be the device
                                if (!$macOwner) {
                                    $mac = $clientMac;
                                    autoLinkDebug("Found potential MAC from client history: {$mac}", $debug);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            
            // No MAC from GWN API, database, or client history
            if (!$mac) {
                if (!$hasFirstUsedAt) {
                    $skipped++;
                    autoLinkLog("SKIP: Voucher {$voucherCode} is used but no MAC is exposed by GWN; first-use migration required for manual workflow.");
                    continue;
                }

                if ($dryRun) {
                    $firstUseDetected++;
                    $pendingManual++;
                    autoLinkLog("DRY-RUN: Voucher {$voucherCode} first used by {$studentName}; no MAC exposed by GWN. Would queue manual link review.");
                    continue;
                }

                if (!markVoucherFirstUseState($conn, $voucherLogId, null, false, $hasFirstUsedAt, $hasFirstUsedMac)) {
                    $errors++;
                    autoLinkLog("ERROR: Failed to mark first_used_at for voucher {$voucherCode}");
                    continue;
                }

                $firstUseDetected++;
                $pendingManual++;
                
                if ($needsMacRetry) {
                    autoLinkLog("RETRY: Voucher {$voucherCode} used by {$studentName}; MAC still not available after retry.");
                } else {
                    autoLinkLog("FIRST-USE: Voucher {$voucherCode} first used by {$studentName}; no MAC exposed by GWN.");
                }

                $message = "Voucher {$voucherCode} for {$studentName} was first used. GWN did not return a MAC address, so link the device manually from Student Details.";
                foreach (getVoucherUseNotificationRecipients($conn, $accommodationId) as $recipientId) {
                    createNotification($recipientId, $message, 'warning', 1);
                }
                logActivity($conn, 1, 'voucher_first_use_no_mac', "Voucher {$voucherCode} first used by {$studentName} without MAC from GWN", '127.0.0.1');
                continue;
            }
        }
    }

    $existingStmt = safeQueryPrepare($conn, "SELECT id, user_id FROM user_devices WHERE mac_address = ? LIMIT 1");
    if (!$existingStmt) {
        $errors++;
        autoLinkLog("ERROR: Failed to prepare existing device lookup for MAC {$mac}");
        continue;
    }
    $existingStmt->bind_param("s", $mac);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if ($existing) {
        if ((int)$existing['user_id'] === $studentUserId) {
            autoLinkLog("SKIP: MAC {$mac} already linked to {$studentName}");
            $alreadyLinked++;
            if (!markVoucherFirstUseState($conn, $voucherLogId, $mac, $dryRun, $hasFirstUsedAt, $hasFirstUsedMac)) {
                $errors++;
                autoLinkLog("ERROR: Failed to update first-use state for voucher {$voucherCode}");
            } else {
                $firstUseDetected++;
            }
        } else {
            autoLinkLog("SKIP: MAC {$mac} already linked to another student (user ID " . (int)$existing['user_id'] . ')');
            $skipped++;
            $conflicts++;

            if (markVoucherFirstUseState($conn, $voucherLogId, $mac, $dryRun, $hasFirstUsedAt, $hasFirstUsedMac)) {
                $firstUseDetected++;
            } else {
                $errors++;
                autoLinkLog("ERROR: Failed to update first-use state for conflicting voucher {$voucherCode}");
            }

            if (!$dryRun) {
                $conflictMessage = "Voucher {$voucherCode} for {$studentName} was first used by MAC {$mac}, but that MAC is already linked to another user.";
                foreach (getVoucherUseNotificationRecipients($conn, $accommodationId) as $recipientId) {
                    createNotification($recipientId, $conflictMessage, 'warning', 1);
                }
                logActivity($conn, 1, 'voucher_first_use_conflict', "Conflict for voucher {$voucherCode} and MAC {$mac}", '127.0.0.1');
            }
        }
        continue;
    }

    $deviceType = 'Other';
    $deviceName = 'Auto-linked device';
    $clientInfo = gwnGetClientInfo($mac);
    if ($clientInfo !== false && is_array($clientInfo)) {
        $clientPayload = (isset($clientInfo['result']) && is_array($clientInfo['result'])) ? $clientInfo['result'] : $clientInfo;
        $deviceType = autoDetectDeviceType($clientPayload['dhcpOs'] ?? '');
        $deviceName = trim((string)($clientPayload['name'] ?? $deviceName));
        if ($deviceName === '') {
            $deviceName = 'Auto-linked device';
        }
        autoLinkDebug('Client info for ' . $mac . ' -> type ' . $deviceType . ', name ' . $deviceName, $debug);
    }

    if ($dryRun) {
        autoLinkLog("DRY-RUN: Would link MAC {$mac} -> {$studentName} ({$deviceType})");
        $linked++;
        $firstUseDetected++;
        continue;
    }

    $insertStmt = safeQueryPrepare($conn,
        "INSERT INTO user_devices (user_id, device_type, mac_address, device_name, linked_via)
         VALUES (?, ?, ?, ?, 'auto')", false);
    $bindMode = 'new';
    if (!$insertStmt) {
        $insertStmt = safeQueryPrepare($conn,
            "INSERT INTO user_devices (user_id, device_type, mac_address)
             VALUES (?, ?, ?)");
        $bindMode = 'legacy';
    }
    if (!$insertStmt) {
        $errors++;
        autoLinkLog("ERROR: Failed to prepare insert for MAC {$mac}");
        continue;
    }

    if ($bindMode === 'new') {
        $insertStmt->bind_param("isss", $studentUserId, $deviceType, $mac, $deviceName);
    } else {
        $insertStmt->bind_param("iss", $studentUserId, $deviceType, $mac);
    }

    if (!$insertStmt->execute()) {
        $isDuplicate = isset($conn->errno) && (int)$conn->errno === 1062;
        if ($isDuplicate) {
            $alreadyLinked++;
            autoLinkLog("SKIP: MAC {$mac} already linked by another process.");
        } else {
            $errors++;
            autoLinkLog("ERROR: Failed to insert MAC {$mac} for {$studentName}: " . $conn->error);
        }
        $insertStmt->close();
        continue;
    }
    $insertStmt->close();

    if (!markVoucherFirstUseState($conn, $voucherLogId, $mac, false, $hasFirstUsedAt, $hasFirstUsedMac)) {
        $errors++;
        autoLinkLog("ERROR: Failed to mark first-use state for voucher {$voucherCode}");
        continue;
    }

    $firstUseDetected++;
    $linked++;
    autoLinkLog("LINKED: MAC {$mac} -> {$studentName} ({$deviceType}, auto-detected)");

    $clientLabel = substr($studentName . ' - ' . $deviceType, 0, 64);
    gwnEditClientName($mac, $clientLabel);
    logActivity($conn, 1, 'auto_link_device', "Auto-linked MAC {$mac} to {$studentName} (user ID {$studentUserId})", '127.0.0.1');
}

autoLinkLog('=== Summary ===');
autoLinkLog("First-use detected: {$firstUseDetected} | Linked: {$linked} | MAC retries attempted: {$retriedMacs} | Manual review needed: {$pendingManual} | Already linked: {$alreadyLinked} | Already processed: {$alreadyProcessed} | Conflicts: {$conflicts} | Skipped: {$skipped} | Errors: {$errors}");

if (!$dryRun) {
    $summary = "Auto-link completed for {$monthIso}: {$firstUseDetected} first-used, {$linked} linked, {$retriedMacs} MAC-retries, {$pendingManual} manual-review, {$alreadyLinked} already-linked, {$conflicts} conflicts, {$skipped} skipped, {$errors} errors.";
    $adminStmt = safeQueryPrepare($conn, "SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin'");
    if ($adminStmt) {
        $adminStmt->execute();
        $admins = $adminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $adminStmt->close();
        foreach ($admins as $admin) {
            createNotification((int)$admin['id'], $summary, 'info', 1);
        }
    }
}

autoLinkLog('Done.');
exit($errors > 0 ? 1 : 0);
