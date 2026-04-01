<?php
/**
 * VoucherMonthHelper
 *
 * Shared helpers for voucher-month boundary calculations.
 * Supports both 'F Y' ("February 2026") and 'Y-m' ("2026-02") formats.
 * All boundaries are computed in the VOUCHER_TZ business timezone.
 */

if (!defined('VOUCHER_TZ')) {
    define('VOUCHER_TZ', 'Africa/Johannesburg');
}

class VoucherMonthHelper {

    /**
     * Parse a voucher month string into a DateTimeImmutable at midnight of the
     * first day of that month, in the business timezone.
     *
     * @param  string $month  e.g. "February 2026" or "2026-02"
     * @return DateTimeImmutable|null  null on parse failure
     */
    public static function parse(string $month): ?DateTimeImmutable {
        $tz    = new DateTimeZone(VOUCHER_TZ);
        $month = trim($month);

        // Try 'F Y' format first ("February 2026")
        $dt = DateTimeImmutable::createFromFormat('F Y', $month, $tz);
        if ($dt) {
            return $dt->modify('first day of this month')->setTime(0, 0, 0);
        }

        // Try 'Y-m' format ("2026-02")
        $dt = DateTimeImmutable::createFromFormat('Y-m', $month, $tz);
        if ($dt) {
            return $dt->modify('first day of this month')->setTime(0, 0, 0);
        }

        return null;
    }

    /**
     * Return key time boundaries for a voucher month.
     *
     * @param  string $month
     * @return array{monthStart: DateTimeImmutable, nextMonthStart: DateTimeImmutable, expiresAt: DateTimeImmutable}|null
     *         null if the month string cannot be parsed
     */
    public static function getWindow(string $month): ?array {
        $monthStart = self::parse($month);
        if ($monthStart === null) {
            return null;
        }

        $nextMonthStart = $monthStart->modify('first day of next month')->setTime(0, 0, 0);
        // Vouchers remain valid through 23:59:59 on the last day of the month.
        $expiresAt = $nextMonthStart->modify('-1 second');

        return [
            'monthStart'     => $monthStart,
            'nextMonthStart' => $nextMonthStart,
            'expiresAt'      => $expiresAt,
        ];
    }

    /**
     * Return true if the given voucher month is the current calendar month
     * in the business timezone.
     */
    public static function isCurrentMonth(string $month): bool {
        $window = self::getWindow($month);
        if ($window === null) {
            return false;
        }
        $now = new DateTimeImmutable('now', new DateTimeZone(VOUCHER_TZ));
        return $now >= $window['monthStart'] && $now < $window['nextMonthStart'];
    }

    /**
     * Return true if the given voucher month has not started yet in the
     * business timezone (i.e. it is a future month).
     */
    public static function isFutureMonth(string $month): bool {
        $window = self::getWindow($month);
        if ($window === null) {
            return false;
        }
        $now = new DateTimeImmutable('now', new DateTimeZone(VOUCHER_TZ));
        return $now < $window['monthStart'];
    }

    /**
     * Return true if the given voucher month has fully expired (past 23:59:59
     * on the last day) in the business timezone.
     * Unparseable month strings are treated as expired.
     */
    public static function isExpired(string $month): bool {
        $window = self::getWindow($month);
        if ($window === null) {
            return true;
        }
        $now = new DateTimeImmutable('now', new DateTimeZone(VOUCHER_TZ));
        return $now > $window['expiresAt'];
    }
}
