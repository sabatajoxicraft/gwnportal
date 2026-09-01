<?php
/**
 * VoucherNotificationGuard
 *
 * Concurrency-safe duplicate-send guard for voucher notifications.
 *
 * Prevents the SAME voucher code from being delivered to the SAME recipient
 * (user_id) more than once within a rolling time window (default 15
 * minutes), while still allowing:
 *   - Legitimate retries after a failed send (nothing is recorded on failure,
 *     so a retry is never blocked)
 *   - Legitimate replacement vouchers (a replacement issues a brand new
 *     voucher code, which is a new, independent guard key)
 *   - Legitimate SMS/WhatsApp fallback after a failed primary attempt (same
 *     reason: failures are never recorded)
 *
 * Concurrency safety: a UNIQUE (user_id, voucher_code) row is reserved via
 * INSERT ... and then locked with SELECT ... FOR UPDATE, so two concurrent
 * requests/routes (duplicate clicks, retried webhooks, etc.) attempting to
 * notify the same recipient about the same voucher code serialize on that
 * single row - only one can proceed to call Twilio at a time.
 *
 * IMPORTANT: reserve() must be followed by markSent() or release() within
 * the SAME database transaction that stays open across the Twilio call, so
 * the FOR UPDATE lock is actually held while the send is in flight. Callers
 * that do not already have an open transaction on $conn must wrap the
 * reserve() -> send -> markSent()/release() sequence in their own
 * begin_transaction()/commit(). Callers that are already inside an
 * ambient transaction (e.g. VoucherService::sendStudentVoucher) can simply
 * use that same connection/transaction.
 */
class VoucherNotificationGuard
{
    const DEFAULT_WINDOW_SECONDS = 900; // 15 minutes

    /**
     * Reserve the right to send $voucherCode to $userId, or report that an
     * identical successful send already happened within the window.
     *
     * @param mysqli $conn
     * @return array{allowed: bool, blocked: bool, lock_id: int|null, previous_sent_at: string|null}
     */
    public static function reserve($conn, int $userId, string $voucherCode, int $windowSeconds = self::DEFAULT_WINDOW_SECONDS): array
    {
        $failOpen = ['allowed' => true, 'blocked' => false, 'lock_id' => null, 'previous_sent_at' => null];
        $failBlocked = ['allowed' => false, 'blocked' => true, 'lock_id' => null, 'previous_sent_at' => null];

        if ($userId <= 0 || $voucherCode === '') {
            // Nothing sensible to guard against; let the caller's own
            // validation handle malformed input.
            return $failOpen;
        }

        try {
            // Reserve a row if one doesn't already exist for this (user, code) pair.
            $insertStmt = safeQueryPrepare(
                $conn,
                "INSERT IGNORE INTO voucher_notification_locks (user_id, voucher_code, status, reserved_at) VALUES (?, ?, 'pending', NOW())",
                false
            );
            if (!self::isUsableStmt($insertStmt)) {
                error_log("VoucherNotificationGuard::reserve - insert prepare failed for user $userId / voucher $voucherCode: " . $conn->error);
                // Fail-open: without the table we cannot dedupe, but sending must not break.
                return $failOpen;
            }
            $insertStmt->bind_param('is', $userId, $voucherCode);
            $insertStmt->execute();
            $insertStmt->close();

            // Lock the row (whether we just inserted it or it already existed) so
            // concurrent callers serialize on this exact (user, code) pair.
            $lockStmt = safeQueryPrepare(
                $conn,
                "SELECT id, status, TIMESTAMPDIFF(SECOND, reserved_at, NOW()) AS age_seconds, reserved_at
                 FROM voucher_notification_locks WHERE user_id = ? AND voucher_code = ? FOR UPDATE",
                false
            );
            if (!self::isUsableStmt($lockStmt)) {
                error_log("VoucherNotificationGuard::reserve - lock prepare failed for user $userId / voucher $voucherCode: " . $conn->error);
                return $failOpen;
            }
            $lockStmt->bind_param('is', $userId, $voucherCode);
            $lockStmt->execute();
            $row = $lockStmt->get_result()->fetch_assoc();
            $lockStmt->close();
        } catch (mysqli_sql_exception $e) {
            // A lock-wait-timeout (or deadlock) here means another request is
            // concurrently holding the reservation for this EXACT (user, code)
            // pair right now - i.e. a genuine concurrent duplicate attempt.
            // Fail toward blocking (not sending) rather than risk a duplicate
            // paid notification; this is logged clearly, never silent.
            error_log("VoucherNotificationGuard::reserve - lock contention for user $userId / voucher $voucherCode, blocking as duplicate-in-flight: " . $e->getMessage());
            return $failBlocked;
        }

        if (!$row) {
            // Extremely unlikely (row vanished between insert and lock); allow.
            return $failOpen;
        }

        $lockId = (int)$row['id'];

        if ($row['status'] === 'sent' && (int)$row['age_seconds'] < $windowSeconds) {
            // Duplicate: an identical notification already went out recently.
            return [
                'allowed' => false,
                'blocked' => true,
                'lock_id' => $lockId,
                'previous_sent_at' => $row['reserved_at'],
            ];
        }

        // Claim/refresh the reservation for this attempt (covers both a fresh
        // 'pending' row and a 'sent' row whose window has expired).
        try {
            $claimStmt = safeQueryPrepare(
                $conn,
                "UPDATE voucher_notification_locks SET status = 'pending', reserved_at = NOW() WHERE id = ?",
                false
            );
            if (self::isUsableStmt($claimStmt)) {
                $claimStmt->bind_param('i', $lockId);
                $claimStmt->execute();
                $claimStmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("VoucherNotificationGuard::reserve - claim update failed for lock $lockId: " . $e->getMessage());
        }

        return ['allowed' => true, 'blocked' => false, 'lock_id' => $lockId, 'previous_sent_at' => null];
    }

    /**
     * Commit the successful-notification state. Call only after Twilio has
     * confirmed the send succeeded.
     */
    public static function markSent($conn, ?int $lockId): void
    {
        if (!$lockId) {
            return;
        }
        try {
            $stmt = safeQueryPrepare($conn, "UPDATE voucher_notification_locks SET status = 'sent', reserved_at = NOW() WHERE id = ?", false);
            if (self::isUsableStmt($stmt)) {
                $stmt->bind_param('i', $lockId);
                $stmt->execute();
                $stmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("VoucherNotificationGuard::markSent - failed to commit sent state for lock $lockId: " . $e->getMessage());
        }
    }

    /**
     * Release a reservation after a failed send so the next retry is not
     * blocked. Failed deliveries are never recorded as "sent".
     */
    public static function release($conn, ?int $lockId): void
    {
        if (!$lockId) {
            return;
        }
        try {
            $stmt = safeQueryPrepare($conn, "DELETE FROM voucher_notification_locks WHERE id = ? AND status <> 'sent'", false);
            if (self::isUsableStmt($stmt)) {
                $stmt->bind_param('i', $lockId);
                $stmt->execute();
                $stmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("VoucherNotificationGuard::release - failed to release lock $lockId: " . $e->getMessage());
        }
    }

    /**
     * Returns true only if $stmt is a real mysqli_stmt that can be used.
     * Catches the DummyStatement that safeQueryPrepare() returns on prepare failure.
     */
    private static function isUsableStmt($stmt): bool
    {
        return $stmt !== false && !($stmt instanceof DummyStatement);
    }
}
