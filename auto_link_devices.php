<?php
/**
 * Cron (every 6 hours): Phase 1 — rollover cleanup of prior-month vouchers.
 *                       Phase 2 — first-use MAC linking for current-month vouchers.
 *
 * Phase 2 processes the full filtered set on every run: current-month, active
 * vouchers that GWN currently reports with at least one used-device signal
 * (usedDeviceNum / usedNum > 0, or a MAC address returned by the controller).
 * No rotation cursor or offset is used.
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
require_once __DIR__ . '/includes/helpers/VoucherMonthHelper.php';
require_once __DIR__ . '/includes/services/VoucherService.php';
require_once __DIR__ . '/includes/services/CaptivePortalService.php';

function autoLinkLog($message) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function autoLinkDebug($message, $enabled) {
    if ($enabled) {
        autoLinkLog('DEBUG: ' . $message);
    }
}

function getPortalMonitorLookupKeysForMapping($mapping) {
    $keys = [];

    $voucherCode = trim((string)($mapping['voucher_code'] ?? ''));
    if ($voucherCode !== '' && ctype_digit($voucherCode)) {
        $keys[] = (int)$voucherCode;
    }

    $voucherId = $mapping['voucher_id'] ?? null;
    if ($voucherId !== null && $voucherId !== '' && is_numeric($voucherId)) {
        $keys[] = (int)$voucherId;
    }

    return array_values(array_unique($keys));
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

/**
 * Preflight checks — run before any Phase 1 or Phase 2 work.
 * Returns an array of fatal error messages; empty array means all clear.
 */
function runPreflightChecks($conn, $monthIso, $monthLabel) {
    $failures = [];

    // 1. Required voucher_logs columns
    $requiredCols = ['gwn_group_id', 'is_active', 'revoked_at', 'revoke_reason', 'first_used_at', 'first_used_mac'];
    $colResult = $conn->query("SHOW COLUMNS FROM voucher_logs");
    $existingCols = [];
    if ($colResult) {
        while ($row = $colResult->fetch_assoc()) {
            $existingCols[] = $row['Field'];
        }
    } else {
        $failures[] = 'PREFLIGHT-FATAL: Cannot query voucher_logs schema: ' . $conn->error;
    }
    foreach ($requiredCols as $col) {
        if (!in_array($col, $existingCols, true)) {
            $failures[] = "PREFLIGHT-FATAL: Required column voucher_logs.{$col} is missing. Apply pending migrations (create_gwn_voucher_groups.sql / add_voucher_revoke_fields.sql / add_device_management.sql).";
        }
    }

    // 2. Unique constraint on user_devices.mac_address
    $idxResult = $conn->query("SHOW INDEX FROM user_devices WHERE Column_name = 'mac_address' AND Non_unique = 0");
    if (!$idxResult || $idxResult->num_rows === 0) {
        $failures[] = 'PREFLIGHT-FATAL: No unique index found on user_devices.mac_address. Apply the add_device_management.sql migration to prevent duplicate device rows.';
    }

    // 3. Detect missing gwn_group_id data when voucher rows exist for current month.
    // Only run if gwn_group_id and is_active columns are confirmed present.
    $hasMissingSchemaForCheck3 = !empty(array_intersect(['gwn_group_id', 'is_active'], array_diff($requiredCols, $existingCols)));
    if (!$hasMissingSchemaForCheck3) {
        $chkStmt = $conn->prepare(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN gwn_group_id > 0 THEN 1 ELSE 0 END) AS with_group
             FROM voucher_logs
             WHERE (voucher_month = ? OR voucher_month = ? OR DATE_FORMAT(created_at, '%Y-%m') = ?)
               AND (is_active = 1 OR is_active IS NULL)"
        );
        if (!$chkStmt) {
            $failures[] = 'PREFLIGHT-FATAL: Cannot prepare gwn_group_id coverage query: ' . $conn->error;
        } else {
            $chkStmt->bind_param('sss', $monthLabel, $monthIso, $monthIso);
            $chkStmt->execute();
            $chkRow = $chkStmt->get_result()->fetch_assoc();
            $chkStmt->close();
            $total     = (int)($chkRow['total']      ?? 0);
            $withGroup = (int)($chkRow['with_group'] ?? 0);
            if ($total > 0 && $withGroup === 0) {
                $failures[] = "PREFLIGHT-FATAL: Found {$total} active voucher_logs row(s) for {$monthIso} but none have gwn_group_id set. Phase 2 cannot auto-link any devices. Check that vouchers were issued via the GWN group flow and that gwn_group_id was recorded correctly.";
            }
        }
    }

    return $failures;
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
// Derive the current month from the business timezone so Phase 2 stays
// consistent with the Phase 1 rollover-cleanup boundaries.
$_vNow      = new DateTimeImmutable('now', new DateTimeZone(VOUCHER_TZ));
$monthIso   = $_vNow->format('Y-m');
$monthLabel = $_vNow->format('F Y');
unset($_vNow);
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

// =========================================================================
// Phase 1: Rollover Cleanup
// Find active sent vouchers from prior months, retire them from GWN, then
// mark them inactive locally. If GWN deletion fails the row stays active so
// the next cron run can retry.  Local audit rows (voucher_logs) are NEVER
// hard-deleted; only is_active is flipped to 0 with a revoke_reason.
// =========================================================================
autoLinkLog('--- Phase 1: Rollover Cleanup ---');

$conn = getDbConnection();
$tz         = new DateTimeZone(VOUCHER_TZ);
$nowCleanup = new DateTimeImmutable('now', $tz);

// --- Preflight: fail loudly on missing schema prerequisites ---
$preflightFailures = runPreflightChecks($conn, $monthIso, $monthLabel);
if (!empty($preflightFailures)) {
    foreach ($preflightFailures as $pf) {
        autoLinkLog($pf);
    }
    autoLinkLog('PREFLIGHT: Critical prerequisites missing. Aborting — no changes were made. Fix the issues above and re-run.');
    exit(2);
}
autoLinkLog('Preflight checks passed.');

// Both format variants of the current month (legacy rows may use Y-m).
$currentMonthFY = $nowCleanup->format('F Y');
$currentMonthYM = $nowCleanup->format('Y-m');

$expiredCandidateSql =
    "SELECT id, user_id, voucher_code, voucher_month, gwn_group_id, gwn_voucher_id
     FROM voucher_logs
     WHERE status = 'sent'
       AND (is_active = 1 OR is_active IS NULL)
       AND voucher_month != ?
       AND voucher_month != ?
     ORDER BY id ASC
     LIMIT 500";

$expiredCandidates = [];
$expiredStmt = safeQueryPrepare($conn, $expiredCandidateSql, false);
if ($expiredStmt) {
    $expiredStmt->bind_param("ss", $currentMonthFY, $currentMonthYM);
    $expiredStmt->execute();
    $expiredCandidates = $expiredStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $expiredStmt->close();
} else {
    autoLinkLog('CLEANUP-WARN: Could not query expired voucher candidates; skipping cleanup phase.');
}

// Keep only vouchers whose month boundary has genuinely passed.
$toRetire = array_filter($expiredCandidates, function ($ev) use ($nowCleanup) {
    $window = VoucherMonthHelper::getWindow($ev['voucher_month']);
    return $window !== null && $nowCleanup > $window['expiresAt'];
});

$cleanupRetired   = 0;
$cleanupGwnFailed = 0;
$cleanupSkipped   = 0;

if (!empty($toRetire)) {
    $voucherService = new VoucherService();

    foreach ($toRetire as $ev) {
        $voucherLogId = (int)$ev['id'];
        $voucherCode  = $ev['voucher_code'];
        $vMonth       = $ev['voucher_month'];
        $gwnGroupId   = (int)($ev['gwn_group_id'] ?? 0);
        $gwnVoucherId = (int)($ev['gwn_voucher_id'] ?? 0);

        // Attempt remote GWN cleanup — prefer group-level deletion.
        $remoteClean = true;
        if ($gwnGroupId > 0) {
            $deleteResult = $voucherService->deleteVoucherGroup([$gwnGroupId]);
            if (!$voucherService->responseSuccessful($deleteResult)) {
                autoLinkLog("CLEANUP-WARN: GWN group {$gwnGroupId} deletion failed for voucher {$voucherCode} ({$vMonth}); will retry next run.");
                $remoteClean = false;
                $cleanupGwnFailed++;
            }
        } elseif ($gwnVoucherId > 0) {
            $deleteResult = $voucherService->deleteVoucher($gwnVoucherId);
            if (!$voucherService->responseSuccessful($deleteResult)) {
                autoLinkLog("CLEANUP-WARN: GWN voucher ID {$gwnVoucherId} deletion failed for {$voucherCode} ({$vMonth}); will retry next run.");
                $remoteClean = false;
                $cleanupGwnFailed++;
            }
        }
        // No remote IDs: nothing to clean remotely — proceed straight to local retire.

        if (!$remoteClean) {
            $cleanupSkipped++;
            continue;
        }

        if ($dryRun) {
            autoLinkLog("DRY-RUN CLEANUP: Would retire voucher {$voucherCode} ({$vMonth}) - expired at end of calendar month.");
            $cleanupRetired++;
            continue;
        }

        $retireStmt = safeQueryPrepare($conn,
            "UPDATE voucher_logs
             SET is_active = 0, revoked_at = NOW(), revoke_reason = ?
             WHERE id = ? AND (is_active = 1 OR is_active IS NULL)", false);
        if ($retireStmt) {
            $retireReason = 'Expired at calendar month end';
            $retireStmt->bind_param("si", $retireReason, $voucherLogId);
            $retireStmt->execute();
            $retireStmt->close();
            $cleanupRetired++;
            autoLinkLog("CLEANUP: Retired voucher {$voucherCode} ({$vMonth}) - expired at end of calendar month.");
        } else {
            autoLinkLog("CLEANUP-ERROR: Failed to update voucher_logs for voucher {$voucherCode}; leaving active.");
            $cleanupGwnFailed++;
            $cleanupSkipped++;
        }
    }
}

autoLinkLog("Rollover Cleanup: Retired={$cleanupRetired}, GWN-cleanup-failed={$cleanupGwnFailed}, Skipped/Retry={$cleanupSkipped}");
autoLinkLog('--- Phase 1: Rollover Cleanup Done ---');
autoLinkLog('');

// =========================================================================
// Phase 2: First-use MAC linking (full filtered pass)
// Every run processes the complete set of current-month active vouchers
// that GWN currently reports with at least one used-device signal.
// =========================================================================
autoLinkLog('--- Phase 2: First-use MAC Linking ---');

// Schema capability checks must come before the processing loop so we can
// conditionally include first_used_at / first_used_mac in the SELECT.
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

// Fetch GWN used-voucher mappings — current-month active vouchers that GWN
// currently reports with at least one used-device signal.
$mappings = getVoucherDeviceMappings($monthIso);
autoLinkLog('GWN used-voucher mappings found: ' . count($mappings) . ' for ' . $monthIso);
if ($debug && !empty($mappings)) {
    $sample = array_slice($mappings, 0, 5);
    autoLinkDebug('Sample GWN mappings: ' . json_encode($sample), $debug);
}

// Index mappings by upper-case voucher code for O(1) lookup below.
$mappingsByCode = [];
foreach ($mappings as $mappingsEntry) {
    $mappingsKey = strtoupper(trim((string)($mappingsEntry['voucher_code'] ?? '')));
    if ($mappingsKey !== '') {
        $mappingsByCode[$mappingsKey] = $mappingsEntry;
    }
}

// Build voucher ID lookup for missing-MAC fallback using portal monitor
$mappingsWithoutMac = [];
foreach ($mappings as $mappingsEntry) {
    if (empty($mappingsEntry['mac']) && !empty(getPortalMonitorLookupKeysForMapping($mappingsEntry))) {
        $mappingsWithoutMac[] = $mappingsEntry;
    }
}

$guestsByVoucherId = [];
if (!empty($mappingsWithoutMac)) {
    autoLinkLog('Found ' . count($mappingsWithoutMac) . ' mappings without MAC, querying portal monitor...');
    try {
        $captivePortalService = new CaptivePortalService();
        $guestsByVoucherId = $captivePortalService->getOnlineGuestsByVoucherId();
        autoLinkLog('Portal monitor lookup returned ' . count($guestsByVoucherId) . ' online guests with voucher IDs');
        if ($debug && !empty($guestsByVoucherId)) {
            $sampleGuests = array_slice($guestsByVoucherId, 0, 3, true);
            autoLinkDebug('Sample portal guests: ' . json_encode($sampleGuests), $debug);
        }
    } catch (Exception $e) {
        autoLinkLog('WARN: Portal monitor lookup failed: ' . $e->getMessage());
        $guestsByVoucherId = [];
    }
}

// Process the full filtered set every run — all vouchers GWN currently reports
// with at least one used-device signal, intersected with active current-month
// entries in voucher_logs.  No rotation or offset is applied.
$processingList = $mappings;

autoLinkLog('Vouchers to process this run: ' . count($processingList) . ' (GWN-reported used-device, full pass)');

if (empty($processingList)) {
    autoLinkLog('Nothing to process in Phase 2 for ' . $monthIso . '. Done.');
    exit($cleanupGwnFailed > 0 ? 1 : 0);
}

$seen = array();

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

foreach ($processingList as $map) {
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
            // Try portal monitor lookup by voucher identifier before manual review
            $portalGuestMac = null;
            $portalGuestName = '';
            $currentMapping = $mappingsByCode[strtoupper($voucherCode)] ?? null;
            $portalLookupKeys = $currentMapping ? getPortalMonitorLookupKeysForMapping($currentMapping) : [];
            $matchedPortalKey = null;

            foreach ($portalLookupKeys as $portalLookupKey) {
                if (!isset($guestsByVoucherId[$portalLookupKey])) {
                    continue;
                }

                $matchedPortalKey = $portalLookupKey;
                $portalGuest = $guestsByVoucherId[$portalLookupKey];
                $clientMac = trim((string)($portalGuest['client_id'] ?? ''));
                if ($clientMac !== '' && preg_match('/^[0-9a-fA-F:.-]{12,17}$/', $clientMac)) {
                    $portalGuestMac = formatMacAddress($clientMac);
                    $portalGuestName = trim((string)($portalGuest['name'] ?? ''));
                    autoLinkDebug("Found portal monitor session for voucher {$voucherCode} using key {$matchedPortalKey}: MAC={$portalGuestMac}, name={$portalGuestName}", $debug);
                    break;
                } else {
                    autoLinkDebug("Portal monitor session for voucher {$voucherCode} using key {$matchedPortalKey} has invalid client MAC: '{$clientMac}'", $debug);
                }
            }
            
            if ($portalGuestMac) {
                $mac = $portalGuestMac;
                autoLinkDebug("Using MAC from portal monitor for voucher {$voucherCode}: {$mac}", $debug);
                // Continue processing with the portal-resolved MAC
            } else {
                // No MAC from GWN API, database, or portal monitor; uncertain cases go to manual review.
                // Heuristic client-history lookup is intentionally disabled: picking an
                // unlinked MAC from a ±24h time window cannot reliably identify the right
                // device and must not be used for automatic linking.
                if (!$hasFirstUsedAt) {
                    $skipped++;
                    autoLinkLog("SKIP: Voucher {$voucherCode} is used but no MAC is exposed by GWN; first-use migration required for manual workflow.");
                    continue;
                }

                if ($dryRun) {
                    $firstUseDetected++;
                    $pendingManual++;
                    autoLinkLog("DRY-RUN: Voucher {$voucherCode} first used by {$studentName}; no MAC found via GWN or portal monitor. Would queue manual link review.");
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
                    autoLinkLog("RETRY: Voucher {$voucherCode} used by {$studentName}; MAC still not available after retry (checked GWN, database, and portal monitor).");
                } else {
                    autoLinkLog("FIRST-USE: Voucher {$voucherCode} first used by {$studentName}; no MAC found via GWN or portal monitor.");
                }

                $message = "Voucher {$voucherCode} for {$studentName} was first used. No MAC address found via GWN or portal monitor, so link the device manually from Student Details.";
                foreach (getVoucherUseNotificationRecipients($conn, $accommodationId) as $recipientId) {
                    createNotification($recipientId, $message, 'warning', 1);
                }
                logActivity($conn, 1, 'voucher_first_use_no_mac', "Voucher {$voucherCode} first used by {$studentName} without MAC from GWN or portal monitor", '127.0.0.1');
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

            // Conflict: MAC belongs to a different student.
            // Do NOT stamp the conflicting MAC into first_used_mac so the
            // manual-review / retry path remains open. Record first_used_at only.
            if (markVoucherFirstUseState($conn, $voucherLogId, null, $dryRun, $hasFirstUsedAt, $hasFirstUsedMac)) {
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
    } else {
        // If GWN client info is unavailable, try using portal guest name as fallback
        $currentMapping = $mappingsByCode[strtoupper($voucherCode)] ?? null;
        $portalLookupKeys = $currentMapping ? getPortalMonitorLookupKeysForMapping($currentMapping) : [];
        foreach ($portalLookupKeys as $portalLookupKey) {
            if (!isset($guestsByVoucherId[$portalLookupKey])) {
                continue;
            }

            $portalGuest = $guestsByVoucherId[$portalLookupKey];
            $portalGuestName = trim((string)($portalGuest['name'] ?? ''));
            if ($portalGuestName !== '' && $portalGuestName !== $deviceName) {
                $deviceName = $portalGuestName;
                autoLinkDebug('Using portal guest name as device name: ' . $deviceName, $debug);
            }
            break;
        }
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
autoLinkLog("Rollover cleanup: Retired={$cleanupRetired} | GWN-failed/retry={$cleanupGwnFailed} | Skipped={$cleanupSkipped}");
autoLinkLog("First-use detected: {$firstUseDetected} | Linked: {$linked} | MAC retries attempted: {$retriedMacs} | Manual review needed: {$pendingManual} | Already linked: {$alreadyLinked} | Already processed: {$alreadyProcessed} | Conflicts: {$conflicts} | Skipped: {$skipped} | Errors: {$errors}");

if (!$dryRun) {
    $summary = "Auto-link completed for {$monthIso}: cleanup retired={$cleanupRetired}, {$firstUseDetected} first-used, {$linked} linked, {$retriedMacs} MAC-retries, {$pendingManual} manual-review, {$alreadyLinked} already-linked, {$conflicts} conflicts, {$skipped} skipped, {$errors} errors.";
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
exit(($errors > 0 || $cleanupGwnFailed > 0) ? 1 : 0);
