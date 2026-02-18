<?php
/**
 * Auto-Link Student Devices (Daily Cron)
 *
 * Matches GWN voucher usage data with the portal database to
 * automatically link student devices by MAC address.
 *
 * Usage:
 *   php cron/auto_link_devices.php
 *   php cron/auto_link_devices.php --dry-run
 */

// ============================================================================
// CLI Guard
// ============================================================================

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// ============================================================================
// Bootstrap
// ============================================================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/python_interface.php';

$conn = getDbConnection();
if (!$conn) {
    fwrite(STDERR, "FATAL: Could not connect to database.\n");
    exit(1);
}

// ============================================================================
// Options
// ============================================================================

$dryRun = in_array('--dry-run', $argv ?? []);
$mode   = $dryRun ? 'dry-run' : 'live';
$month  = date('Y-m');

// ============================================================================
// Helpers
// ============================================================================

function cliLog(string $message): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
}

// ============================================================================
// Main
// ============================================================================

cliLog("Auto-Link Devices - Starting (mode: $mode)");

// 1. Fetch voucher→MAC mappings from GWN
$mappings = getVoucherDeviceMappings($month);

if (empty($mappings)) {
    cliLog("No voucher-device mappings found for $month. Exiting.");
    exit(0);
}

cliLog('Found ' . count($mappings) . " voucher-device mappings for $month");

// Counters
$linked        = 0;
$alreadyLinked = 0;
$skipped       = 0;
$errors        = 0;

// 2. Process each mapping
foreach ($mappings as $map) {
    $voucherCode = $map['voucher_code'];
    $mac         = $map['mac'];

    // Look up student via voucher_logs
    $stmt = safeQueryPrepare($conn,
        "SELECT vl.user_id, u.first_name, u.last_name
         FROM voucher_logs vl
         JOIN users u ON vl.user_id = u.id
         WHERE vl.voucher_code = ? AND vl.is_active = 1");
    if (!$stmt) { $errors++; continue; }

    $stmt->bind_param('s', $voucherCode);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        cliLog("SKIP: No active student found for voucher $voucherCode");
        $skipped++;
        continue;
    }

    $userId    = (int) $student['user_id'];
    $fullName  = trim($student['first_name'] . ' ' . $student['last_name']);

    // Check if MAC already exists in user_devices
    $stmt2 = safeQueryPrepare($conn,
        "SELECT id, user_id FROM user_devices WHERE mac_address = ?");
    if (!$stmt2) { $errors++; continue; }

    $stmt2->bind_param('s', $mac);
    $stmt2->execute();
    $existing = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    if ($existing) {
        if ((int) $existing['user_id'] === $userId) {
            cliLog("SKIP: MAC $mac → Already linked to $fullName");
            $alreadyLinked++;
        } else {
            cliLog("WARNING: MAC $mac → Linked to user #{$existing['user_id']}, expected $fullName (#$userId). Skipping.");
            $skipped++;
        }
        continue;
    }

    // --- New device – gather info and link ---

    if ($dryRun) {
        cliLog("DRY-RUN: Would link MAC $mac → Student $fullName");
        $linked++;
        continue;
    }

    // Get device info from GWN
    $clientInfo = gwnGetClientInfo($mac);
    $dhcpOs     = $clientInfo['dhcpOs'] ?? '';
    $deviceType = autoDetectDeviceType($dhcpOs);
    $deviceName = $clientInfo['name'] ?? 'Auto-linked device';

    // Insert into user_devices
    $stmt3 = safeQueryPrepare($conn,
        "INSERT INTO user_devices (user_id, device_type, mac_address, device_name, linked_via)
         VALUES (?, ?, ?, ?, 'auto')");
    if (!$stmt3) { $errors++; continue; }

    $stmt3->bind_param('isss', $userId, $deviceType, $mac, $deviceName);
    if ($stmt3->execute()) {
        cliLog("LINKED: MAC $mac → Student $fullName ($deviceType, auto-detected)");
        $linked++;
    } else {
        cliLog("ERROR: Failed to insert device $mac for $fullName – " . $conn->error);
        $errors++;
        $stmt3->close();
        continue;
    }
    $stmt3->close();

    // Try to label the device on GWN (non-fatal)
    $gwnLabel = substr("$fullName - $deviceType", 0, 64);
    gwnEditClientName($mac, $gwnLabel);

    // Log activity
    logActivity($conn, 0, 'auto_link_device',
        "Auto-linked MAC $mac to $fullName (type: $deviceType)", 'CLI');
}

// ============================================================================
// Summary
// ============================================================================

cliLog('=== Summary ===');
cliLog("Linked: $linked | Already linked: $alreadyLinked | Skipped: $skipped | Errors: $errors");

// ============================================================================
// Notify Admins
// ============================================================================

$admins = $conn->query(
    "SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'admin')"
);

if ($admins) {
    $summary = "Auto-link completed: {$linked} devices linked, {$alreadyLinked} already linked, {$skipped} skipped, {$errors} errors";
    while ($admin = $admins->fetch_assoc()) {
        createNotification((int) $admin['id'], $summary, 'info', 1);
    }
}

cliLog('Done.');
exit($errors > 0 ? 1 : 0);
