<?php

/**
 * Reusable Twilio messaging service.
 * All logic extracted verbatim from includes/functions.php.
 */
class TwilioService
{
    /**
     * Normalize a phone number to E.164 format.
     * Mirrors the global formatPhoneNumber() function.
     */
    private static function normalizePhone($number)
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        if (substr($number, 0, 2) === '27') {
            return '+' . $number;
        }

        if (substr($number, 0, 1) === '0') {
            return '+27' . substr($number, 1);
        }

        if (strlen($number) === 9) {
            return '+27' . $number;
        }

        return '+' . $number;
    }

    private static function normalizeMethod($method)
    {
        return strtoupper((string) $method) === 'WHATSAPP' ? 'WhatsApp' : 'SMS';
    }

    private static function buildMessagesUrl()
    {
        return "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";
    }

    private static function createTransportMeta($method)
    {
        return [
            'success'        => false,
            'transport'      => 'twilio',
            'method'         => self::normalizeMethod($method),
            'sid'            => null,
            'http_code'      => 0,
            'message_status' => '',
            'twilio_code'    => 0,
            'error'          => '',
        ];
    }

    private static function executeTwilioRequest($url, array $data, $logPrefix, $method)
    {
        $meta = self::createTransportMeta($method);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $meta['http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError         = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $meta['error'] = $curlError;
            error_log($logPrefix . " cURL error: " . $curlError);
            return $meta;
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if (!empty($decoded['sid'])) {
            $meta['sid'] = (string) $decoded['sid'];
        }
        if (!empty($decoded['status'])) {
            $meta['message_status'] = (string) $decoded['status'];
        }
        if (!empty($decoded['code'])) {
            $meta['twilio_code'] = (int) $decoded['code'];
        }

        if ($meta['http_code'] >= 200 && $meta['http_code'] < 300) {
            $meta['success'] = true;
            if ($meta['message_status'] === '') {
                $meta['message_status'] = 'queued';
            }
            error_log($logPrefix . " sent via " . self::normalizeMethod($method) . ". SID: " . ($meta['sid'] ?: 'unknown'));
            return $meta;
        }

        $meta['error'] = (string) ($decoded['message'] ?? $response ?? 'Unknown Twilio error');
        error_log($logPrefix . " failed (" . $meta['http_code'] . "): " . $meta['error']);
        return $meta;
    }

    private static function buildVoucherBody($studentName, $month, $voucherCode, $method)
    {
        $allowedDevices = (int)(defined('GWN_ALLOWED_DEVICES') ? GWN_ALLOWED_DEVICES : 2);
        if ($allowedDevices < 1) {
            $allowedDevices = 1;
        }

        if (self::normalizeMethod($method) === 'SMS') {
            return "Hi $studentName, your monthly WiFi voucher code for $month is: $voucherCode. Max $allowedDevices devices. Need help? WhatsApp 0787426676";
        }

        return "Hi $studentName,\n\nBelow is your monthly WiFi Voucher code valid until {$month}'s month end, this code only grants you a max of {$allowedDevices} devices per month to be connected to the wifi.\n\nYour Voucher: $voucherCode\n\n*#Note: Please dont reply to this message, if you need any assistance, send us a WhatsApp message to 0787426676*";
    }

    public static function buildVoucherCallbackToken($userId, $voucherCode, $voucherMonth, $primaryMethod)
    {
        $payload = (int) $userId . '|' . (string) $voucherCode . '|' . (string) $voucherMonth . '|' . self::normalizeMethod($primaryMethod);
        return hash_hmac('sha256', $payload, (string) TWILIO_AUTH_TOKEN);
    }

    public static function buildVoucherStatusCallbackUrl(array $context)
    {
        $baseUrl = defined('ABSOLUTE_APP_URL') ? trim((string) ABSOLUTE_APP_URL) : '';
        if ($baseUrl === '' || empty(TWILIO_AUTH_TOKEN)) {
            return null;
        }

        $userId       = isset($context['user_id']) ? (int) $context['user_id'] : 0;
        $voucherCode  = trim((string) ($context['voucher_code'] ?? ''));
        $voucherMonth = trim((string) ($context['voucher_month'] ?? ''));
        $primaryMethod = self::normalizeMethod($context['primary_method'] ?? 'WhatsApp');

        if ($userId <= 0 || $voucherCode === '' || $voucherMonth === '') {
            return null;
        }

        $endpoint = rtrim($baseUrl, '/') . '/public/api/twilio-voucher-status.php';
        $token    = self::buildVoucherCallbackToken($userId, $voucherCode, $voucherMonth, $primaryMethod);

        return $endpoint . '?' . http_build_query([
            'kind'           => 'voucher',
            'user_id'        => $userId,
            'voucher_code'   => $voucherCode,
            'voucher_month'  => $voucherMonth,
            'primary_method' => $primaryMethod,
            'token'          => $token,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Send SMS via Twilio REST API.
     * Uses From phone number, falls back to MessagingServiceSid.
     */
    public static function sendSMS($number, $message)
    {
        if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
            error_log("Twilio SMS not configured. Message to $number: $message");
            return false;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

        $normalized = self::normalizePhone($number);
        if (!preg_match('/^\+\d{8,15}$/', $normalized)) {
            error_log("Twilio SMS: invalid To number '$number' (normalized: '$normalized')");
            return false;
        }

        $data = [
            'To'   => $normalized,
            'Body' => $message,
        ];

        if (!empty(TWILIO_PHONE_NUMBER)) {
            $data['From'] = TWILIO_PHONE_NUMBER;
        } elseif (!empty(TWILIO_MESSAGING_SERVICE_SID)) {
            $data['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
        } else {
            error_log("Twilio SMS: No From number or MessagingServiceSid configured");
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Twilio SMS cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("SMS sent to $number. SID: " . ($result['sid'] ?? 'unknown'));
            return true;
        }

        error_log("Twilio SMS failed ($httpCode): " . ($result['message'] ?? $response));
        return false;
    }

    /**
     * Send WhatsApp message via Twilio REST API.
     * Uses freeform Content Template SID when configured (TWILIO_WA_FREEFORM_TEMPLATE_SID),
     * otherwise falls back to plain Body send.
     */
    public static function sendWhatsApp($number, $message)
    {
        if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN) || empty(TWILIO_WHATSAPP_NO)) {
            error_log("Twilio WhatsApp not configured. Message to $number: $message");
            return false;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

        $toRaw        = preg_replace('/^whatsapp:/', '', $number);
        $toNormalized = self::normalizePhone($toRaw);
        if (!preg_match('/^\+\d{8,15}$/', $toNormalized)) {
            error_log("Twilio WhatsApp: invalid To number '$number' (normalized: '$toNormalized')");
            return false;
        }

        $fromRaw        = preg_replace('/^whatsapp:/', '', TWILIO_WHATSAPP_NO);
        $fromNormalized = self::normalizePhone($fromRaw);
        if (!preg_match('/^\+\d{8,15}$/', $fromNormalized)) {
            error_log("Twilio WhatsApp: invalid From number '" . TWILIO_WHATSAPP_NO . "' (normalized: '$fromNormalized')");
            return false;
        }

        $freeformSid = defined('TWILIO_WA_FREEFORM_TEMPLATE_SID') ? TWILIO_WA_FREEFORM_TEMPLATE_SID : '';

        if (!empty($freeformSid)) {
            $data = [
                'To'               => 'whatsapp:' . $toNormalized,
                'From'             => 'whatsapp:' . $fromNormalized,
                'ContentSid'       => $freeformSid,
                'ContentVariables' => json_encode(['1' => $message], JSON_UNESCAPED_UNICODE),
            ];
        } else {
            $data = [
                'To'   => 'whatsapp:' . $toNormalized,
                'From' => 'whatsapp:' . $fromNormalized,
                'Body' => $message,
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Twilio WhatsApp cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("WhatsApp sent to $number. SID: " . ($result['sid'] ?? 'unknown'));
            return true;
        }

        $twilioCode = $result['code'] ?? 0;
        if ((int)$twilioCode === 63016) {
            error_log("Twilio WhatsApp error 63016 to $number: outside 24-hour session window. " .
                      "Set TWILIO_WA_FREEFORM_TEMPLATE_SID to an approved Content Template (single {{1}} variable) " .
                      "or have the recipient message your number first.");
        } else {
            error_log("Twilio WhatsApp failed ($httpCode): " . ($result['message'] ?? $response));
        }
        return false;
    }

    /**
     * Send invitation code via WhatsApp using Twilio Content Template only (no freeform body).
     * Template variables: {{1}} first name, {{2}} invitation code, {{3}} register URL, {{4}} expiry date.
     * Uses TWILIO_WA_INVITATION_TEMPLATE_SID; falls back to TWILIO_SMS_INVITATION_TEMPLATE_SID.
     * Returns false (without sending) if neither SID is configured.
     */
    public static function sendInvitationCodeWhatsAppMessage($number, $firstName, $invitationCode, $registerUrl, $expiryDate)
    {
        if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN) || empty(TWILIO_WHATSAPP_NO)) {
            error_log("Twilio WhatsApp not configured for invitation send to $number");
            return false;
        }

        $toRaw        = preg_replace('/^whatsapp:/', '', $number);
        $toNormalized = self::normalizePhone($toRaw);
        if (!preg_match('/^\+\d{8,15}$/', $toNormalized)) {
            error_log("Twilio invitation WhatsApp: invalid To number '$number' (normalized: '$toNormalized')");
            return false;
        }

        $fromRaw        = preg_replace('/^whatsapp:/', '', TWILIO_WHATSAPP_NO);
        $fromNormalized = self::normalizePhone($fromRaw);
        if (!preg_match('/^\+\d{8,15}$/', $fromNormalized)) {
            error_log("Twilio invitation WhatsApp: invalid From number '" . TWILIO_WHATSAPP_NO . "' (normalized: '$fromNormalized')");
            return false;
        }

        $templateSid = defined('TWILIO_WA_INVITATION_TEMPLATE_SID') ? TWILIO_WA_INVITATION_TEMPLATE_SID : '';
        if (empty($templateSid)) {
            $templateSid = defined('TWILIO_SMS_INVITATION_TEMPLATE_SID') ? TWILIO_SMS_INVITATION_TEMPLATE_SID : '';
        }

        if (empty($templateSid)) {
            error_log("Twilio invitation WhatsApp: no template SID configured (TWILIO_WA_INVITATION_TEMPLATE_SID or TWILIO_SMS_INVITATION_TEMPLATE_SID). Send blocked.");
            return false;
        }

        $url  = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";
        $data = [
            'To'               => 'whatsapp:' . $toNormalized,
            'From'             => 'whatsapp:' . $fromNormalized,
            'ContentSid'       => $templateSid,
            'ContentVariables' => json_encode([
                '1' => $firstName,
                '2' => $invitationCode,
                '3' => $registerUrl,
                '4' => $expiryDate,
            ], JSON_UNESCAPED_UNICODE),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Twilio invitation WhatsApp cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Invitation WhatsApp sent to $number. SID: " . ($result['sid'] ?? 'unknown'));
            return true;
        }

        error_log("Twilio invitation WhatsApp failed ($httpCode): " . ($result['message'] ?? $response));
        return false;
    }

    /**
     * Route message to SMS or WhatsApp based on preferred method.
     */
    public static function sendMessage($number, $message, $method = 'SMS')
    {
        if ($method === 'WhatsApp') {
            return self::sendWhatsApp($number, $message);
        }
        return self::sendSMS($number, $message);
    }

    /**
     * Build a HMAC-signed StatusCallback URL for voucher WhatsApp sends.
     */
    public static function buildVoucherCallbackUrl(int $userId, string $voucherCode, string $voucherMonth, string $primaryMethod): string
    {
        return (string) (self::buildVoucherStatusCallbackUrl([
            'user_id'        => $userId,
            'voucher_code'   => $voucherCode,
            'voucher_month'  => $voucherMonth,
            'primary_method' => $primaryMethod,
        ]) ?? '');
    }

    /**
     * Send WiFi voucher using Twilio Content Templates.
     * Returns structured transport metadata.
     *
     * Template variables: {{1}} = student name, {{2}} = month, {{3}} = voucher code.
     * Falls back to plain text when no template SID is configured.
     *
     * @param string      $callbackUrl  Optional StatusCallback URL (attach for WhatsApp sends only)
     * @return array ['success', 'sid', 'http_code', 'transport_error', 'twilio_code', 'message_status']
     */
    public static function sendVoucherMessageDetailed($number, $studentName, $month, $voucherCode, $method = 'SMS', $callbackUrl = null): array
    {
        $method   = self::normalizeMethod($method);
        $isWa     = ($method === 'WhatsApp');
        $noResult = self::createTransportMeta($method);

        if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
            error_log("Twilio not configured for voucher send to $number");
            $noResult['error'] = 'Twilio not configured';
            return $noResult;
        }

        if ($isWa) {
            if (empty(TWILIO_WHATSAPP_NO)) {
                error_log("Twilio WhatsApp not configured for voucher send to $number");
                $noResult['error'] = 'Twilio WhatsApp not configured';
                return $noResult;
            }
            $toRaw        = preg_replace('/^whatsapp:/', '', $number);
            $toNormalized = self::normalizePhone($toRaw);
            if (!preg_match('/^\+\d{8,15}$/', $toNormalized)) {
                error_log("Twilio voucher WhatsApp: invalid To '$number' (normalized: '$toNormalized')");
                $noResult['error'] = 'Invalid WhatsApp recipient';
                return $noResult;
            }
            $fromRaw        = preg_replace('/^whatsapp:/', '', TWILIO_WHATSAPP_NO);
            $fromNormalized = self::normalizePhone($fromRaw);
            if (!preg_match('/^\+\d{8,15}$/', $fromNormalized)) {
                error_log("Twilio voucher WhatsApp: invalid From '" . TWILIO_WHATSAPP_NO . "' (normalized: '$fromNormalized')");
                $noResult['error'] = 'Invalid WhatsApp sender';
                return $noResult;
            }
            $toForTwilio   = 'whatsapp:' . $toNormalized;
            $fromForTwilio = 'whatsapp:' . $fromNormalized;
        } else {
            $toNormalized = self::normalizePhone($number);
            if (!preg_match('/^\+\d{8,15}$/', $toNormalized)) {
                error_log("Twilio voucher SMS: invalid To '$number' (normalized: '$toNormalized')");
                $noResult['error'] = 'Invalid SMS recipient';
                return $noResult;
            }
            $toForTwilio = $toNormalized;
        }

        $templateSid = $isWa ? TWILIO_WA_VOUCHER_TEMPLATE_SID : TWILIO_SMS_VOUCHER_TEMPLATE_SID;
        $smsSender   = [];

        if (!$isWa) {
            if (!empty(TWILIO_PHONE_NUMBER)) {
                $smsSender['From'] = self::normalizePhone(TWILIO_PHONE_NUMBER);
            } elseif (!empty(TWILIO_MESSAGING_SERVICE_SID)) {
                $smsSender['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
            } else {
                error_log("Twilio voucher SMS: No From number or MessagingServiceSid configured");
                $noResult['error'] = 'Missing SMS sender configuration';
                return $noResult;
            }
        }

        if (empty($templateSid)) {
            $body = self::buildVoucherBody($studentName, $month, $voucherCode, $method);

            if ($isWa) {
                $freeformSid = defined('TWILIO_WA_FREEFORM_TEMPLATE_SID') ? TWILIO_WA_FREEFORM_TEMPLATE_SID : '';
                if (!empty($freeformSid)) {
                    $data = [
                        'To'               => $toForTwilio,
                        'From'             => $fromForTwilio,
                        'ContentSid'       => $freeformSid,
                        'ContentVariables' => json_encode(['1' => $body], JSON_UNESCAPED_UNICODE),
                    ];
                } else {
                    $data = [
                        'To'   => $toForTwilio,
                        'From' => $fromForTwilio,
                        'Body' => $body,
                    ];
                }
            } else {
                $data = array_merge(['To' => $toForTwilio, 'Body' => $body], $smsSender);
            }
        } else {
            $data = [
                'ContentSid'       => $templateSid,
                'ContentVariables' => json_encode(['1' => $studentName, '2' => $month, '3' => $voucherCode], JSON_UNESCAPED_UNICODE),
                'To'               => $toForTwilio,
            ];
            if ($isWa) {
                $data['From'] = $fromForTwilio;
            } else {
                $data = array_merge($data, $smsSender);
            }
        }

        if ($isWa && !empty($callbackUrl)) {
            $data['StatusCallback'] = $callbackUrl;
        }

        $result = self::executeTwilioRequest(self::buildMessagesUrl(), $data, 'Twilio voucher', $method);
        if (!empty($result['error']) && empty($result['transport_error'])) {
            $result['transport_error'] = $result['error'];
        }
        return $result;
    }

    /**
     * Send WiFi voucher using Twilio Content Templates.
     * Bool wrapper around sendVoucherMessageDetailed for existing callers.
     * Template variables: {{1}} = student name, {{2}} = month, {{3}} = voucher code.
     * Falls back to plain text when no template SID is configured.
     */
    public static function sendVoucherMessage($number, $studentName, $month, $voucherCode, $method = 'SMS')
    {
        return self::sendVoucherMessageDetailed($number, $studentName, $month, $voucherCode, $method)['success'];
    }

    /**
     * Send login credentials using Twilio Content Template (SMS only).
     * Template variables: {{1}} = first name, {{2}} = username, {{3}} = temp password.
     */
    public static function sendCredentialsMessage($number, $firstName, $username, $tempPassword)
    {
        if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
            error_log("Twilio not configured for credentials send to $number");
            return false;
        }

        $url         = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";
        $templateSid = TWILIO_SMS_LOGIN_TEMPLATE_SID;

        if (empty($templateSid)) {
            $message = "Hello $firstName,\n\nHere are your login details for the WiFi Portal:\n\nUsername: $username\nTemporary Password: $tempPassword\n\nPlease login and change your password immediately.\n\n- WiFi Management Team";
            return self::sendSMS($number, $message);
        }

        $data = [
            'ContentSid'       => $templateSid,
            'ContentVariables' => json_encode([
                '1' => $firstName,
                '2' => $username,
                '3' => $tempPassword,
            ]),
            'To'   => $number,
            'From' => TWILIO_PHONE_NUMBER,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Twilio credentials cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Credentials sent to $number via SMS. SID: " . ($result['sid'] ?? 'unknown'));
            return true;
        }

        error_log("Twilio credentials failed ($httpCode): " . ($result['message'] ?? $response));
        return false;
    }

    /**
     * Send invitation code using Twilio Content Template (SMS).
     * Template variables: {{1}} first name, {{2}} invitation code, {{3}} register URL, {{4}} expiry date.
     * Falls back to plain SMS if TWILIO_SMS_INVITATION_TEMPLATE_SID is not set.
     */
    public static function sendInvitationCodeMessage($number, $firstName, $invitationCode, $registerUrl, $expiryDate)
    {
        if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
            error_log("Twilio not configured for invitation send to $number");
            return false;
        }

        $normalized = self::normalizePhone($number);
        if (!preg_match('/^\+\d{8,15}$/', $normalized)) {
            error_log("Twilio invitation SMS: invalid To number '$number' (normalized: '$normalized')");
            return false;
        }

        $templateSid = defined('TWILIO_SMS_INVITATION_TEMPLATE_SID') ? TWILIO_SMS_INVITATION_TEMPLATE_SID : '';

        if (empty($templateSid)) {
            $message  = "Your invitation code for " . APP_NAME . " is: $invitationCode\n\n";
            $message .= "Please visit $registerUrl to create your account.\n\n";
            $message .= "This code will expire on $expiryDate.";
            return self::sendSMS($number, $message);
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

        $data = [
            'ContentSid'       => $templateSid,
            'ContentVariables' => json_encode([
                '1' => $firstName,
                '2' => $invitationCode,
                '3' => $registerUrl,
                '4' => $expiryDate,
            ]),
            'To' => $normalized,
        ];

        if (!empty(TWILIO_PHONE_NUMBER)) {
            $data['From'] = TWILIO_PHONE_NUMBER;
        } elseif (!empty(TWILIO_MESSAGING_SERVICE_SID)) {
            $data['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
        } else {
            error_log("Twilio invitation SMS: No From number or MessagingServiceSid configured");
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ":" . TWILIO_AUTH_TOKEN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Twilio invitation SMS cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Invitation SMS sent to $number. SID: " . ($result['sid'] ?? 'unknown'));
            return true;
        }

        error_log("Twilio invitation SMS failed ($httpCode): " . ($result['message'] ?? $response));
        return false;
    }
}
