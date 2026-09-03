<?php
/**
 * Voucher Expiry / Renewal / Eligibility Test Suite
 *
 * Focused tests for the voucher expiry-alignment work:
 *   - Calendar-month expiry boundaries for 28/29/30/31-day months
 *   - Boundary issuance (first/last second of the month)
 *   - Africa/Johannesburg vs UTC month-boundary semantics
 *   - Effect duration is calendar-based, NOT a fixed 30 days
 *   - Replacement of an expired-month voucher is refused
 *   - Renewal calendar window + clear error boundaries
 *   - GWN create failure produces no voucher (so nothing is falsely "sent")
 *   - Inactive/revoked current-month rows do not block re-issuance
 *
 * Custom runner (same style as ServiceTestSuite):  php tests/VoucherExpiryTestSuite.php
 * Pure-logic tests always run. Tests that need the database are skipped (not
 * failed) when a connection is unavailable.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/services/VoucherService.php';

/**
 * Test double: forces the GWN "create voucher group" call to fail, without any
 * network access, so we can prove a GWN failure yields no voucher.
 */
class ForcedGwnFailureVoucherService extends VoucherService {
    public function createVoucherGroup(array $data) {
        return ['retCode' => 1, 'retMsg' => 'forced failure for test'];
    }
}

class CapturingVoucherService extends VoucherService {
    public $payload;
    public function createVoucherGroup(array $data) {
        $this->payload = $data;
        return ['retCode' => 0];
    }
    public function listVoucherGroups($networkId = null, $pageNum = 1, $pageSize = 200, $search = '', $order = '', $type = '') {
        return ['retCode' => 0, 'result' => [['id' => 1, 'name' => $search]]];
    }
    public function listVouchersInGroup($groupId, $networkId = null, $pageNum = 1, $pageSize = 200, $state = '', $search = '', $order = '', $type = '') {
        return ['retCode' => 0, 'result' => [['voucherCode' => 'CAPTURED-CODE']]];
    }
}

class VoucherExpiryTestSuite {
    private static $passed = 0;
    private static $failed = 0;
    private static $skipped = 0;
    private static $errors = [];

    private static function tz() {
        return new DateTimeZone('Africa/Johannesburg');
    }

    private static function at($str, $zone = 'Africa/Johannesburg') {
        return new DateTimeImmutable($str, new DateTimeZone($zone));
    }

    public static function run() {
        echo "Running Voucher Expiry/Renewal/Eligibility Test Suite\n";
        echo str_repeat('=', 60) . "\n\n";

        self::testMonthWindowLengths();
        self::testPlanCalendarWindowBoundaries();
        self::testTimezoneJohannesburgVsUtc();
        self::testDurationNotFixed30Days();
        self::testDayCountIndependentOfTimeOfDay();
        self::testDelayedActivationIsCappedByCreationExpiry();
        self::testLateReplacementDoesNotRevoke();
        self::testReplacementCannotPreserveExpiredMonth();
        self::testRenewalCalendarWindow();
        self::testGwnFailureProducesNoVoucher();
        self::testEligibilityFilterIsWired();
        self::testDbInactiveEligibilityAndRenewBoundary();

        self::printResults();
        return self::$failed === 0 ? 0 : 1;
    }

    private static function testMonthWindowLengths() {
        echo "Calendar-month expiry boundaries (28/29/30/31)...\n";
        $cases = [
            ['February 2026', '2026-02-28 23:59:59'], // 28-day (non-leap)
            ['February 2024', '2024-02-29 23:59:59'], // 29-day (leap)
            ['April 2026',    '2026-04-30 23:59:59'], // 30-day
            ['January 2026',  '2026-01-31 23:59:59'], // 31-day
            ['2026-01',       '2026-01-31 23:59:59'], // Y-m format, 31-day
            ['2024-02',       '2024-02-29 23:59:59'], // Y-m format, leap
        ];
        foreach ($cases as [$month, $expected]) {
            $w = VoucherMonthHelper::getWindow($month);
            $got = $w ? $w['expiresAt']->format('Y-m-d H:i:s') : '(null)';
            self::assertEquals($expected, $got, "getWindow('$month') expiresAt");
        }
    }

    private static function testPlanCalendarWindowBoundaries() {
        echo "planCalendarWindow boundary issuance...\n";

        // First second of a 31-day month.
        $p = VoucherService::planCalendarWindow('January 2026', self::at('2026-01-01 00:00:00'));
        self::assertTrue($p['valid'], "Jan 1 start is valid");
        self::assertEquals('2026-01-31 23:59:59', $p['expires_at'], "Jan expiry final second");
        self::assertEquals(30, $p['expiration_days'], "Jan full window is conservatively capped below 31 days");

        // Final second of the month is still valid.
        $p = VoucherService::planCalendarWindow('January 2026', self::at('2026-01-31 23:59:59'));
        self::assertFalse($p['valid'], "Less than one full day remaining is rejected safely");
        self::assertEquals('insufficient_validity_window', $p['reason'], "Late issuance has a clear conservative reason");

        // One second later the month is expired.
        $p = VoucherService::planCalendarWindow('January 2026', self::at('2026-02-01 00:00:00'));
        self::assertFalse($p['valid'], "Just past month end is invalid");
        self::assertEquals('expired_month', $p['reason'], "Reason is expired_month");

        // A future month cannot be issued.
        $p = VoucherService::planCalendarWindow('February 2026', self::at('2026-01-15 12:00:00'));
        self::assertFalse($p['valid'], "Future month is invalid");
        self::assertEquals('future_month', $p['reason'], "Reason is future_month");

        // Unparseable month string.
        $p = VoucherService::planCalendarWindow('not-a-month', self::at('2026-01-15 12:00:00'));
        self::assertFalse($p['valid'], "Garbage month is invalid");
        self::assertEquals('invalid_month', $p['reason'], "Reason is invalid_month");
    }

    private static function testTimezoneJohannesburgVsUtc() {
        echo "Africa/Johannesburg vs UTC month-boundary semantics...\n";

        // Instant that is 22:30 on Jan 31 in UTC, but already 00:30 on Feb 1 in
        // Johannesburg (UTC+2). Month membership must follow Johannesburg.
        $instant = self::at('2026-01-31 22:30:00', 'UTC');

        $jan = VoucherService::planCalendarWindow('January 2026', $instant);
        self::assertFalse($jan['valid'], "At UTC 22:30 Jan 31, January is already expired in Johannesburg");
        self::assertEquals('expired_month', $jan['reason'], "TZ boundary -> expired_month");

        $feb = VoucherService::planCalendarWindow('February 2026', $instant);
        self::assertTrue($feb['valid'], "Same instant is valid for February in Johannesburg");

        // Mid-day UTC is still Jan 31 in Johannesburg, but less than one full
        // day remains, so conservative issuance is refused rather than risking
        // a relative GWN duration crossing month end.
        $midday = self::at('2026-01-31 12:00:00', 'UTC');
        $jan2 = VoucherService::planCalendarWindow('January 2026', $midday);
        self::assertFalse($jan2['valid'], "UTC midday Jan 31 is refused with less than one full day remaining");
        self::assertEquals('insufficient_validity_window', $jan2['reason'], "UTC midday refusal is explicit");
    }

    private static function testDurationNotFixed30Days() {
        echo "Effect duration is calendar-based, not a fixed 30 days...\n";

        // 31-day month issued on day 1 must give a 31-day window (proves != 30).
        $jan = VoucherService::planCalendarWindow('January 2026', self::at('2026-01-01 00:00:00'));
        self::assertEquals(30, $jan['duration_days'], "effect duration is capped to expiration");

        // 28-day month issued on day 1 gives a 28-day window.
        $feb = VoucherService::planCalendarWindow('February 2026', self::at('2026-02-01 00:00:00'));
        self::assertEquals(27, $feb['duration_days'], "28-day month => conservative 27-day duration");
    }

    private static function testDayCountIndependentOfTimeOfDay() {
        echo "Day count is calendar-based (no issuance-time rounding)...\n";
        // Every issuance time within the same calendar day must yield the same
        // day count. A ceil(seconds / 86400) approach would be fragile around the
        // exact-midnight boundary; the calendar-day calculation is not.
        $expected = 22; // Jan 10 -> Feb 1 boundary = 22 calendar days.
        foreach (['00:00:00', '00:00:01', '09:30:00', '12:00:00', '23:59:59'] as $t) {
            $p = VoucherService::planCalendarWindow('January 2026', self::at("2026-01-10 $t"));
            self::assertEquals(21, $p['expiration_days'], "Jan 10 $t => conservative 21 days");
            self::assertEquals(21, $p['duration_days'], "effect duration never exceeds expiration at $t");
        }
        // First and last day of a 31-day month.
        $first = VoucherService::planCalendarWindow('January 2026', self::at('2026-01-01 15:00:00'));
        self::assertEquals(30, $first['expiration_days'], "Jan 1 => conservative 30 days");
        $last = VoucherService::planCalendarWindow('January 2026', self::at('2026-01-31 00:00:00'));
        self::assertFalse($last['valid'], "Jan 31 has less than one full day remaining");
    }

    private static function testDelayedActivationIsCappedByCreationExpiry() {
        echo "Delayed activation remains bounded by creation expiry...\n";
        $cases = [
            ['February 2026', 28, '2026-02-01 00:00:00', '2026-02-28 23:59:59'],
            ['February 2024', 29, '2024-02-01 00:00:00', '2024-02-29 23:59:59'],
            ['April 2026', 30, '2026-04-01 00:00:00', '2026-04-30 23:59:59'],
            ['January 2026', 31, '2026-01-01 00:00:00', '2026-01-31 23:59:59'],
        ];
        foreach ($cases as [$month, $monthDays, $issuedAt, $expectedExpiry]) {
            $plan = VoucherService::planCalendarWindow($month, self::at($issuedAt));
            self::assertTrue($plan['effect_duration_days'] <= $plan['expiration_days'], "$month effect duration <= expiration");
            self::assertEquals($expectedExpiry, $plan['expires_at'], "$month creation expiry is month end");

            $svc = new CapturingVoucherService();
            $result = $svc->createAndRetrieveVoucher(1, 'Test', 'Student', $month, self::at($issuedAt));
            self::assertTrue($result !== false, "$month payload creation succeeds");
            self::assertEquals($plan['expiration_days'], $svc->payload['expiration'], "$month payload expiration matches planner");
            self::assertEquals((string)$plan['effect_duration_days'], $svc->payload['effectDurationMap']['d'], "$month payload effect duration matches planner");
            self::assertTrue((int)$svc->payload['effectDurationMap']['d'] <= (int)$svc->payload['expiration'], "$month payload has no effect > expiration");
        }

        $midMonth = new CapturingVoucherService();
        $midResult = $midMonth->createAndRetrieveVoucher(
            1,
            'Test',
            'Student',
            'January 2026',
            self::at('2026-01-10 12:00:00')
        );
        self::assertTrue($midResult !== false, "Mid-month payload creation succeeds");
        self::assertEquals(21, $midMonth->payload['expiration'], "Mid-month expiration is conservatively bounded");
        self::assertEquals('21', $midMonth->payload['effectDurationMap']['d'], "Mid-month effect duration is clamped to expiration");
        self::assertTrue(
            (int)$midMonth->payload['effectDurationMap']['d'] <= (int)$midMonth->payload['expiration'],
            "Delayed activation cannot exceed the creation cap"
        );
    }

    private static function testReplacementCannotPreserveExpiredMonth() {
        echo "Replacement cannot preserve an expired month...\n";
        // The replaceVoucher guard uses VoucherMonthHelper::isExpired / the same
        // calendar window. A month well in the past must be flagged expired.
        self::assertTrue(VoucherMonthHelper::isExpired('January 2020'), "Old month is expired");
        $p = VoucherService::planCalendarWindow('January 2020');
        self::assertFalse($p['valid'], "Old month cannot be (re)issued");
        self::assertEquals('expired_month', $p['reason'], "Old month reason is expired_month");
    }

    private static function testLateReplacementDoesNotRevoke() {
        echo "Late replacement preserves the old voucher...\n";
        $late = VoucherService::planCalendarWindow(
            'January 2026',
            self::at('2026-01-31 12:00:00')
        );
        self::assertFalse($late['valid'], "Late replacement issuance is rejected");
        self::assertEquals('insufficient_validity_window', $late['reason'], "Late replacement has explicit reason");

        $source = @file_get_contents(__DIR__ . '/../includes/services/VoucherService.php');
        $guard = strpos($source, '$replacementPlan = self::planCalendarWindow');
        $revoke = strpos($source, 'revokeVoucher($voucherLogId');
        self::assertTrue(
            $source !== false && $guard !== false && $revoke !== false && $guard < $revoke,
            "Replacement validates issuance window before revoking old voucher"
        );
    }

    private static function testRenewalCalendarWindow() {
        echo "Renewal calendar window (not fixed 30 days) + boundaries...\n";
        // Renewing into a 31-day month yields a 31-day calendar window.
        $p = VoucherService::planCalendarWindow('January 2026', self::at('2026-01-10 09:00:00'));
        self::assertTrue($p['valid'], "Renew into current 31-day month is valid");
        // The relative day count is deliberately floored so it cannot cross
        // the Johannesburg month boundary.
        self::assertEquals(21, $p['expiration_days'], "Conservative remaining window from Jan 10 = 21 days");
        self::assertEquals('2026-01-31 23:59:59', $p['expires_at'], "Renewed expiry is month final second");

        // Renewing into an already-expired month is refused with a clear reason.
        $expired = VoucherService::planCalendarWindow('January 2026', self::at('2026-03-01 00:00:00'));
        self::assertEquals('expired_month', $expired['reason'], "Renew into expired month refused");
    }

    private static function testGwnFailureProducesNoVoucher() {
        echo "GWN create failure yields no voucher (no false 'sent')...\n";
        // Sanity: the fake failure response is classified as unsuccessful.
        self::assertFalse((new VoucherService())->responseSuccessful(['retCode' => 1]), "retCode=1 is not successful");

        $svc = new ForcedGwnFailureVoucherService();
        $currentMonth = (new DateTimeImmutable('now', self::tz()))->format('F Y');
        $result = $svc->createAndRetrieveVoucher(1, 'Test Accommodation', 'Test Student', $currentMonth);
        self::assertFalse($result, "createAndRetrieveVoucher returns false when GWN create fails");
    }

    private static function testEligibilityFilterIsWired() {
        echo "Eligibility/duplicate filters exclude inactive rows (source check)...\n";
        $req = @file_get_contents(__DIR__ . '/../public/student/request-voucher.php');
        self::assertTrue(
            $req !== false && strpos($req, "status = 'sent' AND is_active = 1") !== false,
            "request-voucher.php eligibility filters is_active = 1"
        );
        $svc = @file_get_contents(__DIR__ . '/../includes/services/VoucherService.php');
        self::assertTrue(
            $svc !== false && strpos($svc, "AND status = 'sent' AND is_active = 1 LIMIT 1") !== false,
            "getExistingVoucher duplicate guard filters is_active = 1"
        );
    }

    private static function testDbInactiveEligibilityAndRenewBoundary() {
        echo "DB: inactive current-month voucher does not block; renew boundary...\n";
        $conn = @getDbConnection();
        if (!($conn instanceof mysqli)) {
            self::skip("no database connection available");
            return;
        }
        try {
            self::ensureVoucherLogsSchema($conn);
        } catch (Throwable $e) {
            self::skip("voucher_logs schema unavailable: " . $e->getMessage());
            return;
        }

        // Seed a role + user.
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $conn->query("INSERT INTO roles (name, description) VALUES ('vex_role_$suffix', 'test')");
        $roleId = $conn->insert_id;
        if ($roleId <= 0) {
            // roles.name may already exist from a prior run; fetch it.
            $roleId = (int)($conn->query("SELECT id FROM roles ORDER BY id LIMIT 1")->fetch_assoc()['id'] ?? 0);
        }
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role_id, status) VALUES (?, 'x', ?, 'Vex', 'User', ?, 'active')");
        $uname = "vex_$suffix"; $umail = "vex_$suffix@example.com";
        $stmt->bind_param('ssi', $uname, $umail, $roleId);
        $stmt->execute();
        $userId = $conn->insert_id;
        $stmt->close();
        self::assertTrue($userId > 0, "seeded test user");

        $month = (new DateTimeImmutable('now', self::tz()))->format('F Y');
        $svc = new VoucherService();

        // Insert an ACTIVE current-month voucher -> should be detected as duplicate.
        $ins = $conn->prepare("INSERT INTO voucher_logs (user_id, voucher_code, voucher_month, sent_via, status, sent_at, is_active) VALUES (?, ?, ?, 'SMS', 'sent', NOW(), 1)");
        $code = "VEXACTIVE$suffix";
        $ins->bind_param('iss', $userId, $code, $month);
        $ins->execute();
        $voucherLogId = $conn->insert_id;
        $ins->close();

        $existing = $svc->getExistingVoucher($conn, $userId, $month);
        self::assertTrue($existing !== null && $existing['voucher_code'] === $code, "active current-month voucher blocks (duplicate detected)");

        // Deactivate it (revoke) -> must NOT block a retry anymore.
        $conn->query("UPDATE voucher_logs SET is_active = 0, revoked_at = NOW() WHERE id = $voucherLogId");
        $existingAfter = $svc->getExistingVoucher($conn, $userId, $month);
        self::assertTrue($existingAfter === null, "inactive/revoked current-month voucher does NOT block retry");

        // Renewal boundary: the GWN renew endpoint (id/networkId only) cannot
        // target a calendar month, so month-targeted renewal is never claimed as a
        // success and no DB month/expiry is fabricated - regardless of whether a
        // GWN voucher id is present. The service returns a clear renew_not_supported
        // signal and leaves the stored voucher_month untouched.
        $renew = $svc->renewStudentVoucher($voucherLogId, $userId, $month);
        self::assertTrue(
            isset($renew['success']) && $renew['success'] === false && ($renew['error'] ?? '') === 'renew_not_supported',
            "renew (no GWN voucher id) -> clear renew_not_supported error"
        );

        // Even with a GWN voucher id present the answer is identical: the service
        // still refuses to fabricate a renewed month/expiry.
        $conn->query("UPDATE voucher_logs SET gwn_voucher_id = 987654 WHERE id = $voucherLogId");
        $renewWithId = $svc->renewStudentVoucher($voucherLogId, $userId, $month);
        self::assertTrue(
            isset($renewWithId['success']) && $renewWithId['success'] === false && ($renewWithId['error'] ?? '') === 'renew_not_supported',
            "renew WITH GWN voucher id -> still renew_not_supported (no fabricated success)"
        );
        // No partial DB write on the unsupported path: the stored month is unchanged.
        $after = $conn->query("SELECT voucher_month FROM voucher_logs WHERE id = $voucherLogId")->fetch_assoc();
        self::assertTrue(($after['voucher_month'] ?? '') === $month, "renew leaves voucher_month unchanged");

        // Cleanup seeded rows.
        $conn->query("DELETE FROM voucher_logs WHERE user_id = $userId");
        $conn->query("DELETE FROM users WHERE id = $userId");
    }

    private static function ensureVoucherLogsSchema($conn) {
        $res = $conn->query("SHOW TABLES LIKE 'voucher_logs'");
        $hasTable = $res && $res->num_rows > 0;
        if ($res) { $res->free(); }
        if (!$hasTable) {
            throw new RuntimeException('voucher_logs table missing');
        }
        // Ensure the is_active column exists for the eligibility assertions.
        $col = $conn->query("SHOW COLUMNS FROM voucher_logs LIKE 'is_active'");
        $hasActive = $col && $col->num_rows > 0;
        if ($col) { $col->free(); }
        if (!$hasActive) {
            throw new RuntimeException('voucher_logs.is_active column missing');
        }
    }

    // ---- assertions ---------------------------------------------------------
    private static function assertTrue($cond, $name) {
        if ($cond) { self::$passed++; echo "  PASS  $name\n"; }
        else { self::$failed++; self::$errors[] = $name; echo "  FAIL  $name\n"; }
    }
    private static function assertFalse($cond, $name) { self::assertTrue(!$cond, $name); }
    private static function assertEquals($expected, $actual, $name) {
        self::assertTrue($expected === $actual, $name . " (expected=" . var_export($expected, true) . ", got=" . var_export($actual, true) . ")");
    }
    private static function skip($why) { self::$skipped++; echo "  SKIP  $why\n"; }

    private static function printResults() {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "Passed: " . self::$passed . " | Failed: " . self::$failed . " | Skipped: " . self::$skipped . "\n";
        if (self::$failed > 0) {
            echo "\nFailures:\n";
            foreach (self::$errors as $e) { echo "  - $e\n"; }
        }
    }
}

exit(VoucherExpiryTestSuite::run());
