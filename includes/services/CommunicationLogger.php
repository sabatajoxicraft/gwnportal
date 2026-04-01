<?php
/**
 * CommunicationLogger
 *
 * Centralises audit logging for all outbound communications:
 * email, SMS, WhatsApp, and in-app notifications.
 *
 * Action names follow the pattern: communication_{channel}_{sent|failed}
 * Recipient values are always masked before persisting.
 *
 * Usage (called automatically from send helpers in functions.php):
 *   CommunicationLogger::logEmail($to, $subject, 'password_reset', $success);
 *   CommunicationLogger::logSms($number, 'voucher', $success);
 *   CommunicationLogger::logWhatsApp($number, 'invitation_code', $success);
 *   CommunicationLogger::logInApp($recipientId, 'voucher', $message, $success);
 */
class CommunicationLogger {

    /**
     * Mask an email address for audit logging.
     * "john.doe@example.com" → "joh***@example.com"
     *
     * @param string $email
     * @return string Masked representation
     */
    public static function maskEmail(string $email): string {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return '***@***';
        }
        $local  = substr($email, 0, $at);
        $domain = substr($email, $at); // includes the @
        $show   = min(3, strlen($local));
        return substr($local, 0, $show) . '***' . $domain;
    }

    /**
     * Mask a phone number for audit logging.
     * "+27821234567" → "+27***4567"
     *
     * @param string $phone
     * @return string Masked representation
     */
    public static function maskPhone(string $phone): string {
        $phone = preg_replace('/\s+/', '', (string)$phone);
        if (strlen($phone) <= 4) {
            return '***';
        }
        $suffix  = substr($phone, -4);
        $prefix  = substr($phone, 0, max(1, strlen($phone) - 4));
        $showLen = min(3, strlen($prefix));
        return substr($prefix, 0, $showLen) . '***' . $suffix;
    }

    /**
     * Log an outbound email send attempt.
     *
     * @param string   $to            Recipient email (will be masked)
     * @param string   $subject       Email subject
     * @param string   $category      Context: password_reset, invitation_code, credentials, voucher, general, …
     * @param bool     $success       Whether the send succeeded
     * @param int|null $userId        Acting user (null = system / unauthenticated)
     * @param array    $transportMeta Optional transport diagnostics (transport, http_code, sender, error, fallback_used)
     */
    public static function logEmail(
        string $to,
        string $subject,
        string $category,
        bool   $success,
        ?int   $userId = null,
        array  $transportMeta = []
    ): void {
        $action  = $success ? 'communication_email_sent' : 'communication_email_failed';
        $details = [
            'channel'          => 'email',
            'category'         => $category,
            'masked_recipient' => self::maskEmail($to),
            'subject'          => $subject,
            'success'          => $success,
        ];

        if (!empty($transportMeta)) {
            if (isset($transportMeta['transport'])) {
                $details['transport'] = $transportMeta['transport'];
            }
            if (isset($transportMeta['http_code']) && $transportMeta['http_code'] > 0) {
                $details['http_code'] = (int) $transportMeta['http_code'];
            }
            if (!empty($transportMeta['error'])) {
                $details['transport_error'] = substr((string) $transportMeta['error'], 0, 200);
            }
            if (isset($transportMeta['fallback_used'])) {
                $details['fallback_used'] = (bool) $transportMeta['fallback_used'];
            }
            // Store only the sender domain (not the full address) for diagnostics
            if (!empty($transportMeta['sender'])) {
                $atPos = strpos((string) $transportMeta['sender'], '@');
                if ($atPos !== false) {
                    $details['sender_domain'] = substr((string) $transportMeta['sender'], $atPos + 1);
                }
            }
        }

        ActivityLogger::logAction(self::actingUserId($userId), $action, $details);
    }

    /**
     * Log an outbound SMS send attempt.
     *
     * @param string      $to       Recipient phone (will be masked)
     * @param string      $category Context category
     * @param bool        $success  Whether the send succeeded
     * @param int|null    $userId   Acting user
     * @param string|null $sid      Twilio message SID if available
     */
    public static function logSms(
        string  $to,
        string  $category,
        bool    $success,
        ?int    $userId = null,
        ?string $sid    = null
    ): void {
        $action  = $success ? 'communication_sms_sent' : 'communication_sms_failed';
        $details = [
            'channel'          => 'sms',
            'category'         => $category,
            'masked_recipient' => self::maskPhone($to),
            'success'          => $success,
        ];
        if ($sid !== null) {
            $details['message_sid'] = $sid;
        }
        ActivityLogger::logAction(self::actingUserId($userId), $action, $details);
    }

    /**
     * Log an outbound WhatsApp send attempt.
     *
     * @param string      $to       Recipient phone (will be masked)
     * @param string      $category Context category
     * @param bool        $success  Whether the send succeeded
     * @param int|null    $userId   Acting user
     * @param string|null $sid      Twilio message SID if available
     */
    public static function logWhatsApp(
        string  $to,
        string  $category,
        bool    $success,
        ?int    $userId = null,
        ?string $sid    = null
    ): void {
        $action  = $success ? 'communication_whatsapp_sent' : 'communication_whatsapp_failed';
        $details = [
            'channel'          => 'whatsapp',
            'category'         => $category,
            'masked_recipient' => self::maskPhone($to),
            'success'          => $success,
        ];
        if ($sid !== null) {
            $details['message_sid'] = $sid;
        }
        ActivityLogger::logAction(self::actingUserId($userId), $action, $details);
    }

    /**
     * Log an in-app notification creation attempt.
     *
     * @param int      $recipientId  Recipient user ID
     * @param string   $type         Notification type (e.g. 'voucher', 'device_request')
     * @param string   $message      Notification message (truncated to 80 chars)
     * @param bool     $success      Whether the notification row was inserted
     * @param int|null $userId       Acting user (sender); null resolves to session or system
     */
    public static function logInApp(
        int    $recipientId,
        string $type,
        string $message,
        bool   $success,
        ?int   $userId = null
    ): void {
        $action  = $success ? 'communication_notification_sent' : 'communication_notification_failed';
        $details = [
            'channel'         => 'in_app',
            'category'        => $type,
            'recipient_id'    => $recipientId,
            'message_preview' => function_exists('mb_substr') ? mb_substr($message, 0, 80) : substr($message, 0, 80),
            'success'         => $success,
        ];
        ActivityLogger::logAction(self::actingUserId($userId), $action, $details);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Resolve the acting user ID from the argument or the current session.
     */
    private static function actingUserId(?int $userId): ?int {
        if ($userId !== null) {
            return $userId;
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}
