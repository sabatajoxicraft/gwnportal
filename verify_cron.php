#!/usr/bin/env php
<?php
/**
 * Cron Job Verification Script
 * 
 * Checks if auto_link_devices.php cron job is properly configured.
 * 
 * Usage:
 *   php verify_cron.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

echo "===================================\n";
echo "Auto-Link + Rollover Cleanup Cron Verification\n";
echo "===================================\n\n";

// Check 1: Script exists and is readable
echo "[1] Checking if auto_link_devices.php exists...";
if (file_exists(__DIR__ . '/auto_link_devices.php')) {
    echo " ✓ FOUND\n";
} else {
    echo " ✗ NOT FOUND\n";
    exit(1);
}

// Check 2: Script is executable
echo "[2] Checking execute permissions...";
if (is_readable(__DIR__ . '/auto_link_devices.php')) {
    echo " ✓ READABLE\n";
} else {
    echo " ✗ NOT READABLE\n";
    exit(1);
}

// Check 3: Database connection
echo "[3] Checking database connection...";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $conn = getDbConnection();
    if ($conn && !$conn->connect_error) {
        echo " ✓ CONNECTED\n";
    } else {
        echo " ✗ FAILED: " . ($conn->connect_error ?? 'unknown') . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo " ✗ EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}

// Check 4: Required database columns
echo "[4] Checking database schema...\n";
$required_columns = ['gwn_group_id', 'is_active', 'first_used_at', 'first_used_mac', 'revoke_reason', 'revoked_at'];
$stmt = $conn->query("DESCRIBE voucher_logs");
if (!$stmt) {
    echo " ✗ FAILED: Could not query voucher_logs table\n";
    exit(1);
}

$existing_columns = [];
while ($row = $stmt->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

$missing = [];
foreach ($required_columns as $col) {
    if (in_array($col, $existing_columns)) {
        echo "    - $col: ✓\n";
    } else {
        echo "    - $col: ✗ MISSING\n";
        $missing[] = $col;
    }
}

if (!empty($missing)) {
    echo "\n⚠️  WARNING: Missing columns. Run these migrations:\n";
    echo "   - db/migrations/create_gwn_voucher_groups.sql\n";
    echo "   - db/migrations/add_voucher_revoke_fields.sql\n";
    echo "   - db/migrations/add_device_management.sql\n\n";
}

// Check 4b: VoucherMonthHelper availability
echo "[4b] Checking VoucherMonthHelper...\n";
if (file_exists(__DIR__ . '/includes/helpers/VoucherMonthHelper.php')) {
    require_once __DIR__ . '/includes/helpers/VoucherMonthHelper.php';
    $_vNow      = new DateTimeImmutable('now', new DateTimeZone(VOUCHER_TZ));
    $testWindow = VoucherMonthHelper::getWindow($_vNow->format('F Y'));
    unset($_vNow);
    if ($testWindow !== null) {
        echo "     ✓ VoucherMonthHelper loaded; current month window OK\n";
        echo "     - Expires at: " . $testWindow['expiresAt']->format('Y-m-d H:i:s T') . "\n";
    } else {
        echo "     ✗ VoucherMonthHelper::getWindow() returned null for current month\n";
    }
} else {
    echo "     ✗ NOT FOUND: includes/helpers/VoucherMonthHelper.php\n";
}


echo "[5] Checking log directory...\n";
$log_paths = [
    '/var/log/autolink.log',
    '/home/joxicaxs/logs/autolink.log',
    __DIR__ . '/logs/autolink.log'
];

$log_found = false;
foreach ($log_paths as $path) {
    $dir = dirname($path);
    if (is_dir($dir) && is_writable($dir)) {
        echo "    - $dir: ✓ WRITABLE\n";
        $log_found = true;
    }
}

if (!$log_found) {
    echo "    ⚠️  No writable log directory found. Create one:\n";
    echo "       mkdir -p logs && chmod 755 logs\n\n";
}

// Check 6: Crontab (only if running as same user)
echo "[6] Checking crontab configuration...\n";
$crontab_output = shell_exec('crontab -l 2>&1');

if ($crontab_output && stripos($crontab_output, 'auto_link_devices.php') !== false) {
    echo "    ✓ FOUND in crontab:\n";
    $lines = explode("\n", $crontab_output);
    foreach ($lines as $line) {
        if (stripos($line, 'auto_link_devices.php') !== false && $line[0] !== '#') {
            echo "    » $line\n";
        }
    }
} else {
    echo "    ✗ NOT FOUND in crontab\n";
    echo "    ℹ️  Add with: crontab -e\n";
    echo "    Example: 0 0 * * * cd " . __DIR__ . " && php auto_link_devices.php >> logs/autolink.log 2>&1\n\n";
}

// Check 7: GWN API credentials
echo "[7] Checking GWN API credentials...\n";
if (defined('GWN_APP_ID') && !empty(GWN_APP_ID)) {
    echo "    - GWN_APP_ID: ✓ SET\n";
} else {
    echo "    - GWN_APP_ID: ✗ NOT SET\n";
}

if (defined('GWN_SECRET_KEY') && !empty(GWN_SECRET_KEY)) {
    echo "    - GWN_SECRET_KEY: ✓ SET\n";
} else {
    echo "    - GWN_SECRET_KEY: ✗ NOT SET\n";
}

if (defined('GWN_NETWORK_ID') && !empty(GWN_NETWORK_ID)) {
    echo "    - GWN_NETWORK_ID: ✓ SET\n";
} else {
    echo "    - GWN_NETWORK_ID: ✗ NOT SET\n";
}

// Check 8: Recent voucher activity
echo "[8] Checking recent voucher activity...\n";
$month_iso = (new DateTimeImmutable('now', new DateTimeZone(VOUCHER_TZ)))->format('Y-m');
$stmt = $conn->prepare(
    "SELECT COUNT(*) as count FROM voucher_logs 
     WHERE voucher_month = ? OR DATE_FORMAT(created_at, '%Y-%m') = ?"
);
$stmt->bind_param("ss", $month_iso, $month_iso);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$voucher_count = (int)$result['count'];

echo "    - Vouchers for $month_iso: $voucher_count\n";

if ($voucher_count === 0) {
    echo "    ℹ️  No vouchers found for current month. Auto-link won't have data to process.\n";
}

// Check 9: Test dry run
echo "\n[9] Testing dry-run execution...\n";
ob_start();
passthru('php ' . escapeshellarg(__DIR__ . '/auto_link_devices.php') . ' --dry-run 2>&1', $exit_code);
$output = ob_get_clean();

if ($exit_code === 0) {
    echo "    ✓ SCRIPT EXECUTED SUCCESSFULLY\n";
    // Show first 5 lines of output
    $lines = array_slice(explode("\n", $output), 0, 5);
    foreach ($lines as $line) {
        if (!empty(trim($line))) {
            echo "    » $line\n";
        }
    }
} else {
    echo "    ✗ SCRIPT FAILED (exit code: $exit_code)\n";
    echo "    Output:\n";
    echo $output;
}

// Summary
echo "\n===================================\n";
echo "Verification Summary\n";
echo "===================================\n\n";

if (empty($missing) && $exit_code === 0) {
    echo "✅ AUTO-LINK + ROLLOVER CLEANUP SCRIPT IS READY\n\n";
    
    if (stripos($crontab_output ?? '', 'auto_link_devices.php') === false) {
        echo "📋 Next Steps:\n";
        echo "   1. Add cron job: crontab -e\n";
        echo "   2. Use this schedule: 0 0 * * * cd " . __DIR__ . " && php auto_link_devices.php >> logs/autolink.log 2>&1\n";
        echo "   3. Verify after 24h: tail -n 50 logs/autolink.log\n\n";
    } else {
        echo "✅ CRON JOB IS CONFIGURED\n\n";
        echo "📋 Monitoring:\n";
        echo "   - View logs: tail -n 50 logs/autolink.log\n";
        echo "   - Live monitoring: tail -f logs/autolink.log\n";
        echo "   - Manual test: php auto_link_devices.php --dry-run --debug\n\n";
    }
} else {
    echo "⚠️  ISSUES DETECTED - FIX BEFORE SCHEDULING\n\n";
    
    if (!empty($missing)) {
        echo "1. Apply missing migrations\n";
    }
    
    if ($exit_code !== 0) {
        echo "2. Fix script execution errors (see output above)\n";
    }
    
    echo "\nSee docs/CRON-SETUP.md for detailed instructions.\n\n";
}

echo "Documentation: docs/CRON-SETUP.md\n";
echo "===================================\n";
