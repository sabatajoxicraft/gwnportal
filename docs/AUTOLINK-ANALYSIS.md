# Auto-Link Device Mechanism - Deep Analysis

**Date:** February 16, 2026  
**Status:** ⚠️ CRITICAL ISSUE IDENTIFIED

## Executive Summary

The current auto-linking mechanism has a **race condition vulnerability** that causes permanent data loss when the GWN Cloud API doesn't return MAC addresses at the moment of first use detection. This affects approximately **58% of vouchers** based on current data (7 out of 12 vouchers missing MAC addresses despite being marked as used).

---

## How Auto-Linking Currently Works

### Data Flow

```
1. Daily cron runs auto_link_devices.php
2. Queries local DB for active vouchers (voucher_logs table)
3. For each voucher, queries GWN Cloud API to check usage
4. GWN API returns:
   - voucher_code
   - mac (if currently in an ACTIVE session)
   - usage state
5. If voucher is "used" AND has a MAC, auto-link device to student
6. Mark voucher as processed (set first_used_at)
```

### Code Flow (auto_link_devices.php)

```php
// Line 117: Get voucher-device mappings from GWN API
$mappings = getVoucherDeviceMappings($monthIso);

foreach ($mappings as $map) {
    // Line 223: Check if already processed
    $isFirstUse = !$hasFirstUsedAt || empty($student['first_used_at']);

    if ($hasFirstUsedAt && !$isFirstUse) {
        $alreadyProcessed++;
        continue; // ❌ SKIPS THIS VOUCHER FOREVER
    }

    if (!$mac) {
        // Line 229: Try to use historical MAC from database
        $historicalMac = $student['first_used_mac'];

        if ($historicalMac !== '') {
            $mac = $historicalMac; // ✅ Fallback works
        } else {
            // ❌ No MAC available
            markVoucherFirstUseState($conn, $voucherLogId, null, ...);
            // This sets first_used_at but leaves first_used_mac as NULL
            // Future runs will SKIP because first_used_at is now set!
        }
    }
}
```

---

## The Critical Problem

### Issue: One-Shot Processing WindowOnce `first_used_at` is set, the voucher is **permanently** marked as "already processed" and will be **skipped on all future runs** (line 223-225).

### Real-World Impact

From current database (Feb 16, 2026):

```
Total active vouchers:        12
Has first_used_at:            12 (100%)
Has first_used_mac:           5 (42%)
Missing MAC despite use:      7 (58%)  ❌
```

**Vouchers with missing MACs:**

- ID 35-41 (voucher codes: 14419546051, 11459946049, 12449646047, 18449746041, 10429246040, 16499546036, 18469746035)
- All have `first_used_at` set
- All have `first_used_mac = NULL`
- **Will NEVER auto-capture MACs even if GWN API returns them later**

### Why GWN API Doesn't Always Return MACs

The GWN Cloud API only returns MAC addresses for vouchers with **currently active sessions**:

1. **Device connects** → Uses voucher → Active session → GWN API returns MAC ✅
2. **Device disconnects** → Session ends → GWN API returns NO MAC ❌
3. **Session expires** (typically 1-7 days) → GWN API returns NO MAC ❌
4. **API sync delay** → GWN API may not have MAC yet ❌

### Timeline Example

```
Day 1, 08:00 AM:
- Student uses voucher, connects to WiFi
- Device gets IP, establishes session

Day 1, 10:00 AM:
- auto_link_devices.php runs (scheduled cron)
- GWN API returns MAC ✅
- Device auto-linked ✅
- first_used_at AND first_used_mac both set ✅

Day 1, 11:00 AM:
- Student disconnects from WiFi
- Session ends in GWN Cloud
```

vs

```
Day 1, 08:00 AM:
- Student uses voucher, connects to WiFi
- Device gets IP, establishes session

Day 1, 08:30 AM:
- Student disconnects (e.g., leaves accommodation)
- Session ends in GWN Cloud

Day 1, 10:00 AM:
- auto_link_devices.php runs (scheduled cron)
- GWN API returns NO MAC ❌ (session already ended)
- first_used_at set, first_used_mac = NULL ❌
- Voucher marked as "already processed"

Day 2, 10:00 AM:
- Student connects again
- GWN API now HAS the MAC
- auto_link_devices.php runs
- SKIPS this voucher (already processed) ❌
- MAC is NEVER captured ❌
```

---

## Fallback Mechanism Analysis

### Current Fallback (Lines 229-234)

```php
$historicalMac = ($hasFirstUsedMac && !empty($student['first_used_mac']))
    ? trim((string)$student['first_used_mac']) : '';

if ($historicalMac !== '') {
    $mac = formatMacAddress($historicalMac);
    autoLinkDebug('Using historical MAC from first_used_mac: ' . $mac, $debug);
}
```

**Purpose:** Use previously captured MAC if GWN API doesn't return one

**Status:** ✅ Works correctly **when** first_used_mac was populated on first run

**Limitation:** ❌ Doesn't help if first run also had no MAC

### When Fallback Helps

1. ✅ First run: GWN API returns MAC → first_used_mac populated
2. ✅ Second run: GWN API returns NO MAC → Uses first_used_mac from DB

### When Fallback Fails

1. ❌ First run: GWN API returns NO MAC → first_used_mac = NULL
2. ❌ Second run: Voucher is skipped (already processed)
3. ❌ No opportunity to capture MAC even if available later

---

## Is This The "Correct" Way?

### ❌ NO - Current Implementation Has Flaws

**Problems:**

1. **Race Condition:** Success depends on device being actively connected when cron runs
2. **No Retry Logic:** Failed MAC captures are never retried
3. **Permanent Gaps:** 58% of vouchers currently have no MAC address
4. **Manual Fallback Broken:** Notifications sent, but no easy way for admins to manually link devices
5. **Future Processing Uncertain:** Data loss is permanent

### ✅ YES - Core Design is Sound

**What Works Well:**

1. **Database backup:** first_used_mac field correctly stores MACs when available
2. **COALESCE protection:** Won't overwrite existing data
3. **Historical fallback:** Works when data exists
4. **GWN API integration:** Correctly queries voucher status
5. **Notifications:** Alerts admins when MACs are missing

---

## Recommended Solutions

### Solution 1: **Retry Logic for Missing MACs** (Recommended)

**Change the skip condition to allow retries when MAC is missing:**

```php
// OLD CODE (Line 223-225):
if ($hasFirstUsedAt && !$isFirstUse) {
    $alreadyProcessed++;
    continue; // Skips FOREVER
}

// NEW CODE:
$needsMacRetry = ($hasFirstUsedAt && $hasFirstUsedMac
    && !$isFirstUse
    && empty($student['first_used_mac']));

if ($hasFirstUsedAt && !$isFirstUse && !$needsMacRetry) {
    $alreadyProcessed++;
    continue; // Only skip if we HAVE the MAC
}

// If $needsMacRetry is true, continue processing to try capturing MAC
if ($needsMacRetry) {
    autoLinkDebug("Retrying MAC capture for voucher {$voucherCode} (first_used_mac is NULL)", $debug);
}
```

**Benefits:**

- ✅ Continues checking GWN API until MAC is captured
- ✅ No data loss
- ✅ Minimal code change
- ✅ Backward compatible

**Risks:**

- ⚠️ Slightly more API calls (only for vouchers missing MACs)
- ⚠️ May send duplicate notifications if MAC never appears

### Solution 2: **Extended Retry Window**

Add a retry window (e.g., 7 days) after first use:

```php
$retryWindowDays = 7;
$firstUsedTime = strtotime($student['first_used_at']);
$cutoffTime = $firstUsedTime + ($retryWindowDays * 86400);
$needsMacRetry = ($hasFirstUsedAt && $hasFirstUsedMac
    && !$isFirstUse
    && empty($student['first_used_mac'])
    && time() < $cutoffTime);
```

**Benefits:**

- ✅ Limits retry period
- ✅ Reduces API load after retry window
- ✅ Clearer logic (definite end point)

### Solution 3: **ClientService Direct Query Fallback**

When GWN API doesn't return MAC in voucher data, query ClientService for recent connections:

```php
if (!$mac) {
    // Try to find recent connections for this student
    $clientService = new ClientService();
    $allClients = $clientService->listClients();

    // Match by approximate time window
    foreach ($allClients as $client) {
        if (/* client connection time near first_used_at */) {
            $mac = $client['mac'];
            break;
        }
    }
}
```

**Benefits:**

- ✅ More comprehensive data source
- ✅ May capture MACs GWN voucher API misses

**Risks:**

- ⚠️ Matching logic complex (time-based heuristics)
- ⚠️ May link wrong device if multiple students connect at same time
- ⚠️ More API calls

### Solution 4: **Manual Link UI Enhancement**

Accept that auto-linking won't work for all cases, but make manual linking easier:

1. Add "Recent Unlinked Devices" panel to student details page
2. Show devices connected around first_used_at time
3. One-click link button with confirmation

**Benefits:**

- ✅ Accepts limitations gracefully
- ✅ Provides clear manual workflow
- ✅ No risk of incorrect auto-linking

---

## Recommended Implementation Plan

### Phase 1: Immediate Fix (Today)

**Implement Solution 1 + Solution 2 combined**

```php
// Configuration
$retryWindowDays = 7; // Keep trying for 7 days

// Modified skip logic
$
$firstUsedTime = $hasFirstUsedAt && $student['first_used_at']
    ? strtotime($student['first_used_at']) : 0;
$retryDeadline = $firstUsedTime + ($retryWindowDays * 86400);
$withinRetryWindow = time() < $retryDeadline;

$needsMacRetry = ($hasFirstUsedAt
    && $hasFirstUsedMac
    && !$isFirstUse
    && empty($student['first_used_mac'])
    && $withinRetryWindow);

if ($hasFirstUsedAt && !$isFirstUse && !$needsMacRetry) {
    $alreadyProcessed++;
    continue; // Skip only if we have MAC or retry window expired
}

if ($needsMacRetry) {
    autoLinkDebug("Retrying MAC capture for voucher {$voucherCode} (within {$retryWindowDays} day window)", $debug);
    // Continue processing...
}
```

### Phase 2: Enhanced Manual Workflow (This Week)

**Implement Solution 4**

1. Create "Unlinked Devices" section in student-details.php
2. Query ClientService for devices near first_used_at timestamp
3. Add "Link This Device" button with confirmation

### Phase 3: Monitoring & Validation (Ongoing)

1. Add retry attempt counter to voucher_logs
2. Log when MAC capture succeeds after retries
3. Report on retry success rate
4. Alert if retry window expires without MAC

---

## Testing Strategy

### Current State Validation

```sql
-- Check current MAC capture rate
SELECT
    COUNT(*) as total_used,
    SUM(CASE WHEN first_used_mac IS NOT NULL THEN 1 ELSE 0 END) as has_mac,
    ROUND(100.0 * SUM(CASE WHEN first_used_mac IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*), 2) as capture_rate
FROM voucher_logs
WHERE first_used_at IS NOT NULL;

-- Find candidates for retry
SELECT
    id, voucher_code, first_used_at, first_used_mac,
    DATEDIFF(NOW(), first_used_at) as days_since_first_use
FROM voucher_logs
WHERE first_used_at IS NOT NULL
  AND (first_used_mac IS NULL OR first_used_mac = '')
ORDER BY first_used_at DESC;
```

### Test Cases for Solution

1. **Test 1:** Voucher with MAC on first run → Should set first_used_mac ✅
2. **Test 2:** Voucher without MAC on first run → Should retry on next run ✅
3. **Test 3:** Voucher without MAC after 7 days → Should stop retrying ✅
4. **Test 4:** Voucher gets MAC on 3rd run → Should capture and stop retrying ✅
5. **Test 5:** Voucher with existing first_used_mac → Should not overwrite ✅

---

## Conclusion

### Current Answer: ❌ NO, it's NOT fully reliable

**The current auto-linking mechanism will fail for ~58% of vouchers** where the GWN API doesn't return MAC addresses at the exact moment the daily cron job runs.

### Future Processing: ⚠️ UNCERTAIN without fix

**Without fixes:** Data loss is permanent. Failed MAC captures are never retried.

**With recommended fixes:** System will be **reliable and self-healing** with:

- 7-day retry window to capture MACs
- Graceful degradation after retry window
- Manual link UI for edge cases

### Critical Action Items

1. ✅ **IMMEDIATE:** Implement Solution 1+2 (retry logic)
2. ✅ **THIS WEEK:** Implement Solution 4 (manual link UI)
3. ✅ **ONGOING:** Monitor capture rates and retry success

---

## Appendix: Related Code Files

- `auto_link_devices.php` - Main auto-linking job (Lines: 223-257 critical)
- `includes/python_interface/gwn_cloud.php:1070-1227` - getVoucherDeviceMappings()
- `db/migrations/add_device_management.sql:158-168` - first_used_mac schema
- `public/student-details.php` - Device management UI (candidate for manual link feature)
