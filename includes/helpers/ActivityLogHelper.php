<?php
/**
 * ActivityLogHelper
 *
 * Shared presentation helpers for activity_log entries.
 * Used by public/admin/activity-log.php and public/admin/dashboard.php
 * to guarantee consistent labels, timestamps, and detail summaries.
 */
class ActivityLogHelper {

    /** Human-readable labels keyed by lowercase action code. */
    private static $ACTION_LABELS = [
        // Authentication
        'auth_login_success'                => 'Login Success',
        'auth_login_failure'                => 'Login Failed',
        'auth_login_failed'                 => 'Login Failed',
        'auth_logout'                       => 'Logout',
        'logout'                            => 'Logout',
        'auth_password_changed'             => 'Password Changed',
        'auth_password_reset_requested'     => 'Password Reset Requested',
        'auth_password_reset_completed'     => 'Password Reset Completed',
        'auth_password_reset_token_invalid' => 'Reset Token Invalid',
        // Vouchers
        'voucher_self_request'              => 'Voucher Self Request',
        'voucher_issued'                    => 'Voucher Issued',
        'voucher_used'                      => 'Voucher Used',
        'voucher_revoked'                   => 'Voucher Revoked',
        'voucher_sent'                      => 'Voucher Sent',
        // Devices
        'device_register'                   => 'Device Registered',
        'device_block'                      => 'Device Blocked',
        'device_unblock'                    => 'Device Unblocked',
        'device_unlink'                     => 'Device Unlinked',
        'device_update'                     => 'Device Updated',
        // Students
        'student_created'                   => 'Student Created',
        'student_updated'                   => 'Student Updated',
        'student_disabled'                  => 'Student Disabled',
        'student_assigned'                  => 'Student Assigned',
        'student_removed'                   => 'Student Removed',
        // Accommodations
        'accommodation_created'             => 'Accommodation Created',
        'accommodation_updated'             => 'Accommodation Updated',
        'accommodation_deleted'             => 'Accommodation Deleted',
        // Permissions
        'permission_assigned'               => 'Permission Assigned',
        'permission_removed'                => 'Permission Removed',
        'permission_role_changed'           => 'Role Changed',
        // Communications
        'communication_email_sent'          => 'Email Sent',
        'communication_email_failed'        => 'Email Failed',
        'communication_sms_sent'            => 'SMS Sent',
        'communication_sms_failed'          => 'SMS Failed',
        'communication_whatsapp_sent'       => 'WhatsApp Sent',
        'communication_whatsapp_failed'     => 'WhatsApp Failed',
        'communication_notification_sent'   => 'In-App Notification',
        'communication_notification_failed' => 'In-App Notification Failed',
        // Misc
        'page_visit'                        => 'Page Visit',
    ];

    /**
     * Normalize an action code to a human-readable label (HTML-escaped).
     *
     * @param string $action  Raw action value from activity_log.action
     * @param string $details Raw JSON details (used as fallback for legacy rows)
     * @return string         HTML-safe display label
     */
    public static function normalizeActionLabel(string $action, string $details = ''): string {
        $action = trim($action);
        if ($action === '') {
            return htmlspecialchars(
                self::inferActionFromDetails($details),
                ENT_QUOTES,
                'UTF-8'
            );
        }

        $key = strtolower($action);
        if (isset(self::$ACTION_LABELS[$key])) {
            return htmlspecialchars(self::$ACTION_LABELS[$key], ENT_QUOTES, 'UTF-8');
        }

        return htmlspecialchars(ucwords(str_replace('_', ' ', $action)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Return a friendly dropdown label that preserves the raw value for server-side filtering.
     * Known actions: "Friendly Label (raw_code)"
     * Unknown actions: ucwords version of the raw code
     *
     * @param string $action  Raw action value
     * @return string         Plain text (not HTML-escaped – caller should escape)
     */
    public static function getFriendlyActionLabel(string $action): string {
        $key = strtolower(trim($action));
        if (isset(self::$ACTION_LABELS[$key])) {
            return self::$ACTION_LABELS[$key] . ' (' . $action . ')';
        }
        return ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Format a raw timestamp string for display.
     * Returns a styled "Legacy / unavailable" placeholder for invalid/zero timestamps.
     *
     * @param string $rawTimestamp Value from activity_log.timestamp
     * @return string              HTML snippet (already HTML-safe)
     */
    public static function formatTimestamp(string $rawTimestamp): string {
        if (empty($rawTimestamp) || strpos($rawTimestamp, '0000-00-00') === 0) {
            return '<span class="text-muted" title="Timestamp unavailable">Legacy / unavailable</span>';
        }

        $ts = strtotime($rawTimestamp);
        if ($ts === false || $ts <= 0) {
            return '<span class="text-muted" title="Timestamp unavailable">Legacy / unavailable</span>';
        }

        return htmlspecialchars(date('M j, Y g:i A', $ts), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format activity details to a human-readable summary (HTML-safe output).
     * Action-aware: provides context-sensitive rendering for password-reset
     * and communication events; falls back to a generic summary for all others.
     *
     * @param string $action  Raw action value
     * @param string $details Raw JSON string from activity_log.details
     * @return string         HTML snippet
     */
    public static function formatDetails(string $action, string $details): string {
        if (trim($details) === '') {
            return '<span class="text-muted">—</span>';
        }

        $decoded = json_decode($details, true);
        if (!is_array($decoded)) {
            return htmlspecialchars(trim($details), ENT_QUOTES, 'UTF-8');
        }

        $actionKey = strtolower(trim($action));

        // ------------------------------------------------------------------
        // Password reset – request
        // ------------------------------------------------------------------
        if ($actionKey === 'auth_password_reset_requested') {
            $parts = [];
            if (!empty($decoded['email_hint'])) {
                $parts[] = 'Email: ' . $decoded['email_hint'];
            }
            if (array_key_exists('token_issued', $decoded)) {
                $parts[] = $decoded['token_issued']
                    ? 'Token issued'
                    : 'No token issued (unknown account or throttled)';
            }
            if (array_key_exists('email_sent', $decoded)) {
                $parts[] = 'Email sent: ' . ($decoded['email_sent'] ? 'yes' : 'no');
            }
            if (!empty($decoded['ip_address'])) {
                $parts[] = 'IP: ' . $decoded['ip_address'];
            }
            return !empty($parts)
                ? htmlspecialchars(implode(' • ', $parts), ENT_QUOTES, 'UTF-8')
                : '<span class="text-muted">—</span>';
        }

        // ------------------------------------------------------------------
        // Password reset – completion
        // ------------------------------------------------------------------
        if ($actionKey === 'auth_password_reset_completed') {
            $parts = [];
            if (!empty($decoded['method'])) {
                $parts[] = 'Method: ' . ucwords(str_replace('_', ' ', $decoded['method']));
            }
            if (!empty($decoded['channel'])) {
                $parts[] = 'Channel: ' . ucfirst($decoded['channel']);
            }
            if (!empty($decoded['email_hint'])) {
                $parts[] = 'Email: ' . $decoded['email_hint'];
            }
            if (!empty($decoded['ip_address'])) {
                $parts[] = 'IP: ' . $decoded['ip_address'];
            }
            if (!empty($decoded['reason'])) {
                $parts[] = ucfirst($decoded['reason']);
            }
            return !empty($parts)
                ? htmlspecialchars(implode(' • ', $parts), ENT_QUOTES, 'UTF-8')
                : '<span class="text-muted">—</span>';
        }

        // ------------------------------------------------------------------
        // Password reset – invalid/expired token
        // ------------------------------------------------------------------
        if ($actionKey === 'auth_password_reset_token_invalid') {
            $parts = [];
            if (!empty($decoded['reason'])) {
                $parts[] = ucfirst($decoded['reason']);
            }
            if (!empty($decoded['ip_address'])) {
                $parts[] = 'IP: ' . $decoded['ip_address'];
            }
            return !empty($parts)
                ? htmlspecialchars(implode(' • ', $parts), ENT_QUOTES, 'UTF-8')
                : '<span class="text-muted">—</span>';
        }

        // ------------------------------------------------------------------
        // Communication events (email / SMS / WhatsApp / in-app)
        // ------------------------------------------------------------------
        if (strpos($actionKey, 'communication_') === 0) {
            $parts = [];
            if (!empty($decoded['category'])) {
                $parts[] = ucwords(str_replace('_', ' ', $decoded['category']));
            }
            if (!empty($decoded['masked_recipient'])) {
                $parts[] = 'To: ' . $decoded['masked_recipient'];
            }
            if (!empty($decoded['subject'])) {
                $parts[] = 'Subject: ' . $decoded['subject'];
            }
            if (!empty($decoded['type'])) {
                $parts[] = 'Type: ' . $decoded['type'];
            }
            if (!empty($decoded['message_preview'])) {
                $parts[] = '"' . $decoded['message_preview'] . '"';
            }
            if (isset($decoded['success'])) {
                $parts[] = $decoded['success'] ? '✓ Delivered' : '✗ Failed';
            }
            return !empty($parts)
                ? htmlspecialchars(implode(' • ', $parts), ENT_QUOTES, 'UTF-8')
                : '<span class="text-muted">—</span>';
        }

        // ------------------------------------------------------------------
        // Generic fallback
        // ------------------------------------------------------------------
        if (!empty($decoded['reason'])) {
            return htmlspecialchars($decoded['reason'], ENT_QUOTES, 'UTF-8');
        }

        $summary = [];
        if (isset($decoded['success'])) {
            $summary[] = $decoded['success'] ? 'Success' : 'Failed';
        }
        if (!empty($decoded['ip_address'])) {
            $summary[] = 'IP: ' . $decoded['ip_address'];
        }
        if (!empty($summary)) {
            return htmlspecialchars(implode(' • ', $summary), ENT_QUOTES, 'UTF-8');
        }

        // Last resort: key=value pairs (skip ip_address to avoid IP-only noise)
        $pairs = [];
        foreach ($decoded as $k => $v) {
            if ($k === 'ip_address') {
                continue;
            }
            if (is_scalar($v)) {
                $pairs[] = str_replace('_', ' ', (string)$k) . ': ' . $v;
            }
        }
        if (!empty($pairs)) {
            return htmlspecialchars(implode(' • ', $pairs), ENT_QUOTES, 'UTF-8');
        }

        return '<span class="text-muted">—</span>';
    }

    /**
     * Format an IP address, falling back to the ip_address key in the details JSON
     * when the dedicated column is empty (legacy rows).
     *
     * @param string $ipAddress Value from activity_log.ip_address
     * @param string $details   Raw JSON string from activity_log.details
     * @return string           HTML-safe IP string or em-dash placeholder
     */
    public static function formatIp(string $ipAddress, string $details): string {
        $ip = trim($ipAddress);
        if ($ip !== '') {
            return htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
        }

        $decoded = json_decode($details, true);
        if (is_array($decoded) && !empty($decoded['ip_address'])) {
            return htmlspecialchars((string)$decoded['ip_address'], ENT_QUOTES, 'UTF-8');
        }

        return '<span class="text-muted">—</span>';
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Infer a fallback action label from the details string.
     * Used for legacy rows that have no action field.
     */
    private static function inferActionFromDetails(string $details): string {
        $decoded = json_decode($details, true);
        $reason  = '';

        if (is_array($decoded) && !empty($decoded['reason'])) {
            $reason = strtolower(trim((string)$decoded['reason']));
        } else {
            $reason = strtolower(trim($details));
        }

        if ($reason === '') {
            return 'Uncategorized';
        }

        if (strpos($reason, 'logged in successfully') !== false) return 'Login Success';
        if (strpos($reason, 'failed login attempt') !== false)   return 'Login Failed';
        if (strpos($reason, 'logged out') !== false)             return 'Logout';
        if (strpos($reason, 'self-requested voucher') !== false) return 'Voucher Self Request';
        if (strpos($reason, 'account created') !== false)        return 'Account Created';

        return 'System Event';
    }
}
