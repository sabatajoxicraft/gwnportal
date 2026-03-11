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
     * Send WiFi voucher using Twilio Content Templates.
     * Template variables: {{1}} = student name, {{2}} = month, {{3}} = voucher code.
     * Falls back to plain text when no template SID is configured.
     */
    public static function sendVoucherMessage($number, $studentName, $month, $voucherCode, $method = 'SMS')
    {
        if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
            error_log("Twilio not configured for voucher send to $number");
            return false;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

        $templateSid = ($method === 'WhatsApp') ? TWILIO_WA_VOUCHER_TEMPLATE_SID : TWILIO_SMS_VOUCHER_TEMPLATE_SID;

        if (empty($templateSid)) {
            $allowedDevices = (int)(defined('GWN_ALLOWED_DEVICES') ? GWN_ALLOWED_DEVICES : 2);
            if ($allowedDevices < 1) {
                $allowedDevices = 1;
            }
            if ($method === 'SMS') {
                // Short SMS template (stays within 1 segment / 160 chars to avoid double cost)
                $message = "Hi $studentName, your monthly WiFi voucher code for $month is: $voucherCode. Max $allowedDevices devices. Need help? WhatsApp 0787426676";
            } else {
                // WhatsApp can use the longer template (no per-segment cost)
                $message = "Hi $studentName,\n\nBelow is your monthly WiFi Voucher code valid until {$month}'s month end, this code only grants you a max of {$allowedDevices} devices per month to be connected to the wifi.\n\nYour Voucher: $voucherCode\n\n*#Note: Please dont reply to this message, if you need any assistance, send us a WhatsApp message to 0787426676*";
            }
            return self::sendMessage($number, $message, $method);
        }

        $data = [
            'ContentSid'       => $templateSid,
            'ContentVariables' => json_encode([
                '1' => $studentName,
                '2' => $month,
                '3' => $voucherCode,
            ]),
        ];

        if ($method === 'WhatsApp') {
            $data['To']   = (strpos($number, 'whatsapp:') === 0) ? $number : "whatsapp:$number";
            $data['From'] = TWILIO_WHATSAPP_NO;
        } else {
            $data['To']   = $number;
            $data['From'] = TWILIO_PHONE_NUMBER;
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
            error_log("Twilio voucher cURL error: $error");
            return false;
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Voucher sent to $number via $method. SID: " . ($result['sid'] ?? 'unknown'));
            return true;
        }

        error_log("Twilio voucher failed ($httpCode): " . ($result['message'] ?? $response));
        return false;
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
