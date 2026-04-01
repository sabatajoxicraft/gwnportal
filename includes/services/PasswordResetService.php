<?php
/**
 * PasswordResetService
 *
 * Manages the self-service email-based password reset flow.
 * Tokens are 32 random bytes (64 hex chars) stored as a SHA-256 hash.
 * One-time use; outstanding pending tokens for the same user are revoked
 * whenever a new request is issued.
 *
 * Public API
 * ----------
 *   PasswordResetService::requestReset($conn, $email, $requestIp)
 *       Returns the plaintext token on success, null when the email is unknown
 *       or the user is inactive (caller shows the same generic response), and
 *       false on a hard database error.
 *
 *   PasswordResetService::validateToken($conn, $tokenPlain)
 *       Returns the DB row (array) for a valid, unexpired, pending token whose
 *       owner is still active.  Returns false otherwise.
 *
 *   PasswordResetService::consumeToken($conn, $tokenPlain, $newPassword)
 *       Validates the token, sets the new password, clears
 *       password_reset_required, marks the token used, and revokes any other
 *       outstanding tokens for the same user.  Returns true on success.
 *
 *   PasswordResetService::isThrottled($conn, $userId, $requestIp)
 *       Returns true if the request should be rejected due to rate limits.
 */
class PasswordResetService
{
    /** Token lifetime in seconds (1 hour). */
    const TOKEN_TTL_SECONDS = 3600;

    /** Minimum acceptable new-password length (mirrors app-wide policy). */
    const MIN_PASSWORD_LENGTH = 8;

    /**
     * Per-user: one new token allowed per this many seconds.
     * A pending OR used/revoked token within the window triggers the throttle.
     */
    const THROTTLE_USER_WINDOW_SECONDS = 900; // 15 minutes

    /** Per-IP: maximum reset requests per hour across all users. */
    const THROTTLE_IP_MAX_PER_HOUR = 10;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Look up an active user by email, apply throttle checks, revoke any
     * outstanding pending tokens, persist a new hashed token, and return the
     * plaintext token for inclusion in the reset email.
     *
     * The revoke + insert is wrapped in a transaction with a row-level lock on
     * the user record to prevent concurrent requests for the same address from
     * each inserting a separate pending token.
     *
     * @param  mysqli $conn
     * @param  string $email      Email address from the reset form
     * @param  string $requestIp  Requester IP (used for throttle + audit)
     * @return string|null|false  Plaintext token, null for unknown/inactive/throttled, false on DB error
     */
    public static function requestReset($conn, $email, $requestIp)
    {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $user = self::findActiveUserByEmail($conn, $email);
        if (!$user) {
            // Unknown email or inactive account – return null so the caller
            // shows the same generic response without disclosing existence.
            return null;
        }

        if (self::isThrottled($conn, $user['id'], $requestIp)) {
            error_log(
                'PasswordResetService::requestReset - throttled'
                . ' user_id=' . $user['id']
                . ' ip=' . $requestIp
            );
            // Return null so the caller still shows the generic success page.
            return null;
        }

        $tokenPlain = bin2hex(random_bytes(32));
        $tokenHash  = hash('sha256', $tokenPlain);
        $expiresAt  = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        $conn->begin_transaction();

        // Lock the user row so that a second concurrent reset request for the
        // same account waits here until this transaction commits, preventing
        // both from revoking-then-inserting independently and ending up with
        // two active pending tokens.
        $lockStmt = safeQueryPrepare($conn, 'SELECT id FROM users WHERE id = ? FOR UPDATE');
        if (!$lockStmt) {
            error_log('PasswordResetService::requestReset - lock prepare failed: ' . $conn->error);
            $conn->rollback();
            return false;
        }
        $lockStmt->bind_param('i', $user['id']);
        $lockStmt->execute();
        $lockStmt->get_result();
        $lockStmt->close();

        // Revoke any outstanding pending tokens before issuing the new one.
        self::revokeUserTokens($conn, $user['id']);

        $stmt = safeQueryPrepare(
            $conn,
            'INSERT INTO password_reset_tokens (user_id, token_hash, status, request_ip, expires_at)
             VALUES (?, ?, \'pending\', ?, ?)'
        );
        if (!$stmt) {
            error_log('PasswordResetService::requestReset - prepare failed: ' . $conn->error);
            $conn->rollback();
            return false;
        }
        $stmt->bind_param('isss', $user['id'], $tokenHash, $requestIp, $expiresAt);
        if (!$stmt->execute()) {
            error_log('PasswordResetService::requestReset - execute failed: ' . $stmt->error);
            $stmt->close();
            $conn->rollback();
            return false;
        }
        $stmt->close();

        $conn->commit();
        return $tokenPlain;
    }

    /**
     * Validate a plaintext token from the reset link.
     *
     * Checks that the token exists, is pending, has not expired, and that the
     * owning user is still active.
     *
     * @param  mysqli $conn
     * @param  string $tokenPlain  64-character hex token from the URL/form
     * @return array|false         DB row on success, false otherwise
     */
    public static function validateToken($conn, $tokenPlain)
    {
        if (empty($tokenPlain) || strlen($tokenPlain) !== 64) {
            return false;
        }

        $tokenHash = hash('sha256', $tokenPlain);
        $now = date('Y-m-d H:i:s');

        $stmt = safeQueryPrepare(
            $conn,
            'SELECT prt.id      AS token_id,
                    prt.user_id,
                    prt.expires_at,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.status    AS user_status
             FROM   password_reset_tokens prt
             JOIN   users u ON prt.user_id = u.id
             WHERE  prt.token_hash = ?
               AND  prt.status     = \'pending\'
               AND  prt.expires_at > ?
               AND  u.status       = \'active\'
             LIMIT  1'
        );
        if (!$stmt) {
            error_log('PasswordResetService::validateToken - prepare failed: ' . $conn->error);
            return false;
        }
        $stmt->bind_param('ss', $tokenHash, $now);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        return $row ?: false;
    }

    /**
     * Consume a valid token: hash and store the new password, clear the
     * password_reset_required flag, mark the token as used, and revoke any
     * other pending tokens for the user.
     *
     * The token is claimed with an atomic UPDATE guarded by status='pending'
     * and the expiry check.  If affected_rows is 0 the token was already
     * consumed (or expired) by a concurrent request and we abort.  Everything
     * runs inside a transaction so a failure during the password update rolls
     * back the token-used mark as well.
     *
     * @param  mysqli $conn
     * @param  string $tokenPlain  Plaintext token from the form
     * @param  string $newPassword New password (plaintext, pre-validated by caller)
     * @return bool
     */
    public static function consumeToken($conn, $tokenPlain, $newPassword)
    {
        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return false;
        }

        if (empty($tokenPlain) || strlen($tokenPlain) !== 64) {
            return false;
        }

        $tokenHash = hash('sha256', $tokenPlain);
        $now       = date('Y-m-d H:i:s');
        $usedAt    = $now;
        $newHash   = password_hash($newPassword, PASSWORD_DEFAULT);

        $conn->begin_transaction();

        // Atomically claim the token.  The WHERE guards ensure only the first
        // concurrent caller succeeds; any duplicate request gets affected_rows=0.
        $stmt = safeQueryPrepare(
            $conn,
            'UPDATE password_reset_tokens
             SET    status  = \'used\',
                    used_at = ?
             WHERE  token_hash = ?
               AND  status     = \'pending\'
               AND  expires_at > ?'
        );
        if (!$stmt) {
            error_log('PasswordResetService::consumeToken - claim prepare failed: ' . $conn->error);
            $conn->rollback();
            return false;
        }
        $stmt->bind_param('sss', $usedAt, $tokenHash, $now);
        if (!$stmt->execute()) {
            error_log('PasswordResetService::consumeToken - claim execute failed: ' . $stmt->error);
            $stmt->close();
            $conn->rollback();
            return false;
        }
        $claimed = $stmt->affected_rows;
        $stmt->close();

        if ($claimed !== 1) {
            // Race lost – another request already consumed this token, or it expired.
            $conn->rollback();
            return false;
        }

        // Resolve the owning user.  Query without the status filter because we
        // just flipped it to 'used'; verify the user is still active.
        $stmt2 = safeQueryPrepare(
            $conn,
            'SELECT prt.user_id
             FROM   password_reset_tokens prt
             JOIN   users u ON prt.user_id = u.id
             WHERE  prt.token_hash = ?
               AND  u.status       = \'active\'
             LIMIT  1'
        );
        if (!$stmt2) {
            error_log('PasswordResetService::consumeToken - fetch prepare failed: ' . $conn->error);
            $conn->rollback();
            return false;
        }
        $stmt2->bind_param('s', $tokenHash);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2    = $result2->fetch_assoc();
        $stmt2->close();

        if (!$row2) {
            // User became inactive between token issuance and consumption.
            $conn->rollback();
            return false;
        }

        $userId = (int) $row2['user_id'];

        // Update password and clear forced-reset flag.
        $stmt3 = safeQueryPrepare(
            $conn,
            'UPDATE users SET password = ?, password_reset_required = 0 WHERE id = ?'
        );
        if (!$stmt3) {
            error_log('PasswordResetService::consumeToken - users prepare failed: ' . $conn->error);
            $conn->rollback();
            return false;
        }
        $stmt3->bind_param('si', $newHash, $userId);
        if (!$stmt3->execute()) {
            error_log('PasswordResetService::consumeToken - users update failed: ' . $stmt3->error);
            $stmt3->close();
            $conn->rollback();
            return false;
        }
        $stmt3->close();

        // Revoke any other pending tokens for this user.
        self::revokeUserTokens($conn, $userId);

        $conn->commit();
        return true;
    }

    /**
     * Determine whether a new reset request should be rejected.
     *
     * Two independent checks:
     *  1. Per-user: any token (regardless of status) created within the last
     *     THROTTLE_USER_WINDOW_SECONDS seconds.
     *  2. Per-IP: more than THROTTLE_IP_MAX_PER_HOUR tokens created in the
     *     last hour from the same IP address.
     *
     * @param  mysqli   $conn
     * @param  int      $userId
     * @param  string   $requestIp
     * @return bool
     */
    public static function isThrottled($conn, $userId, $requestIp)
    {
        // Per-user window
        $userWindowStart = date('Y-m-d H:i:s', time() - self::THROTTLE_USER_WINDOW_SECONDS);
        $stmt = safeQueryPrepare(
            $conn,
            'SELECT COUNT(*) AS cnt FROM password_reset_tokens
             WHERE user_id = ? AND created_at > ?'
        );
        if ($stmt) {
            $stmt->bind_param('is', $userId, $userWindowStart);
            $stmt->execute();
            $result = $stmt->get_result();
            $row    = $result->fetch_assoc();
            $stmt->close();
            if ($row && (int) $row['cnt'] > 0) {
                return true;
            }
        }

        // Per-IP window
        if (!empty($requestIp)) {
            $ipWindowStart = date('Y-m-d H:i:s', time() - 3600);
            $stmt2 = safeQueryPrepare(
                $conn,
                'SELECT COUNT(*) AS cnt FROM password_reset_tokens
                 WHERE request_ip = ? AND created_at > ?'
            );
            if ($stmt2) {
                $stmt2->bind_param('ss', $requestIp, $ipWindowStart);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                $row2    = $result2->fetch_assoc();
                $stmt2->close();
                if ($row2 && (int) $row2['cnt'] >= self::THROTTLE_IP_MAX_PER_HOUR) {
                    return true;
                }
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch a single active user row by email address.
     *
     * @param  mysqli $conn
     * @param  string $email  Already lower-cased and trimmed
     * @return array|false
     */
    private static function findActiveUserByEmail($conn, $email)
    {
        $stmt = safeQueryPrepare(
            $conn,
            'SELECT id, email, first_name, last_name, status
             FROM   users
             WHERE  email = ? AND status = \'active\'
             LIMIT  1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /**
     * Revoke all pending tokens for the given user.
     *
     * @param  mysqli $conn
     * @param  int    $userId
     */
    private static function revokeUserTokens($conn, $userId)
    {
        $stmt = safeQueryPrepare(
            $conn,
            'UPDATE password_reset_tokens
             SET    status = \'revoked\'
             WHERE  user_id = ? AND status = \'pending\''
        );
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}
