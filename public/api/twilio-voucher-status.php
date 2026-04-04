<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/services/CommunicationLogger.php';
require_once '../../includes/services/TwilioService.php';

header('Content-Type: text/plain');

function twilioVoucherCallbackOk(): void
{
    http_response_code(200);
    echo 'OK';
    exit;
}

function twilioVoucherUsableStmt($stmt): bool
{
    return $stmt !== false && !($stmt instanceof DummyStatement);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('twilio-voucher-status: invalid request method');
    twilioVoucherCallbackOk();
}

$accountSid    = trim((string)($_POST['AccountSid'] ?? ''));
$messageSid    = trim((string)($_POST['MessageSid'] ?? ''));
$messageStatus = strtolower(trim((string)($_POST['MessageStatus'] ?? '')));
$errorCode     = isset($_POST['ErrorCode']) ? (int)$_POST['ErrorCode'] : 0;
$errorMessage  = trim((string)($_POST['ErrorMessage'] ?? ($_POST['SmsErrorMessage'] ?? '')));

$kind          = trim((string)($_GET['kind'] ?? ''));
$userId        = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$voucherCode   = trim((string)($_GET['voucher_code'] ?? ''));
$voucherMonth  = trim((string)($_GET['voucher_month'] ?? ''));
$primaryMethod = trim((string)($_GET['primary_method'] ?? ''));
$token         = trim((string)($_GET['token'] ?? ''));

if ($kind !== 'voucher' || $userId <= 0 || $voucherCode === '' || $voucherMonth === '' || $primaryMethod === '' || $token === '') {
    error_log('twilio-voucher-status: missing or invalid callback context');
    twilioVoucherCallbackOk();
}

if (empty(TWILIO_ACCOUNT_SID) || $accountSid !== TWILIO_ACCOUNT_SID) {
    error_log('twilio-voucher-status: AccountSid mismatch');
    twilioVoucherCallbackOk();
}

if ($messageSid === '') {
    error_log('twilio-voucher-status: missing MessageSid');
    twilioVoucherCallbackOk();
}

$expectedToken = TwilioService::buildVoucherCallbackToken($userId, $voucherCode, $voucherMonth, $primaryMethod);
if (!hash_equals($expectedToken, $token)) {
    error_log('twilio-voucher-status: invalid callback token');
    twilioVoucherCallbackOk();
}

if (strtoupper($primaryMethod) !== 'WHATSAPP') {
    twilioVoucherCallbackOk();
}

if (!in_array($messageStatus, ['failed', 'undelivered'], true)) {
    twilioVoucherCallbackOk();
}

$conn     = getDbConnection();
$sidLike  = '%"message_sid":"' . $messageSid . '"%';
$flagLike = '%"status_callback":true%';
$stmtSeen = safeQueryPrepare(
    $conn,
    "SELECT id FROM activity_log WHERE action = 'communication_whatsapp_failed' AND details LIKE ? AND details LIKE ? LIMIT 1",
    false
);

if (twilioVoucherUsableStmt($stmtSeen)) {
    $stmtSeen->bind_param('ss', $sidLike, $flagLike);
    $stmtSeen->execute();
    $seen = $stmtSeen->get_result()->fetch_assoc();
    $stmtSeen->close();
    if ($seen) {
        twilioVoucherCallbackOk();
    }
}

$monthAlt = '';
$dt = DateTime::createFromFormat('F Y', $voucherMonth);
if ($dt) {
    $monthAlt = $dt->format('Y-m');
}

$stmtVoucher = safeQueryPrepare(
    $conn,
    "SELECT u.id AS user_id,
            u.first_name,
            u.last_name,
            u.phone_number,
            u.whatsapp_number,
            vl.id AS voucher_log_id,
            vl.sent_via
     FROM users u
     LEFT JOIN voucher_logs vl
       ON vl.user_id = u.id
      AND vl.voucher_code = ?
      AND (vl.voucher_month = ? OR vl.voucher_month = ?)
     WHERE u.id = ?
     ORDER BY vl.sent_at DESC
     LIMIT 1",
    false
);

if (!twilioVoucherUsableStmt($stmtVoucher)) {
    error_log('twilio-voucher-status: voucher lookup prepare failed');
    twilioVoucherCallbackOk();
}

$stmtVoucher->bind_param('sssi', $voucherCode, $voucherMonth, $monthAlt, $userId);
$stmtVoucher->execute();
$voucher = $stmtVoucher->get_result()->fetch_assoc();
$stmtVoucher->close();

if (!$voucher) {
    error_log('twilio-voucher-status: user or voucher context not found');
    twilioVoucherCallbackOk();
}

if (!empty($voucher['sent_via']) && strcasecmp((string)$voucher['sent_via'], 'SMS') === 0) {
    twilioVoucherCallbackOk();
}

$waNumber = (string)($voucher['whatsapp_number'] ?: $voucher['phone_number']);
CommunicationLogger::logWhatsApp(
    $waNumber !== '' ? $waNumber : (string)($voucher['phone_number'] ?? ''),
    'voucher',
    false,
    $userId,
    $messageSid,
    [
        'transport'       => 'twilio',
        'status_callback' => true,
        'message_status'  => $messageStatus,
        'twilio_code'     => $errorCode,
        'transport_error' => $errorMessage,
        'voucher_code'    => $voucherCode,
        'voucher_month'   => $voucherMonth,
    ]
);

$phoneNumber = trim((string)($voucher['phone_number'] ?: $voucher['whatsapp_number']));
if ($phoneNumber === '') {
    error_log('twilio-voucher-status: no phone number available for SMS fallback');
    twilioVoucherCallbackOk();
}

$studentName = trim((string)($voucher['first_name'] ?? '') . ' ' . (string)($voucher['last_name'] ?? ''));
if ($studentName === '') {
    $studentName = 'Student';
}

$smsMeta = TwilioService::sendVoucherMessageDetailed($phoneNumber, $studentName, $voucherMonth, $voucherCode, 'SMS');
CommunicationLogger::logSms(
    $phoneNumber,
    'voucher',
    (bool)($smsMeta['success'] ?? false),
    $userId,
    $smsMeta['sid'] ?? null,
    [
        'transport'       => 'twilio',
        'http_code'       => (int)($smsMeta['http_code'] ?? 0),
        'transport_error' => (string)($smsMeta['transport_error'] ?? ($smsMeta['error'] ?? '')),
        'twilio_code'     => isset($smsMeta['twilio_code']) ? (int)$smsMeta['twilio_code'] : 0,
        'message_status'  => (string)($smsMeta['message_status'] ?? ''),
        'fallback_from'   => 'whatsapp',
        'voucher_code'    => $voucherCode,
        'voucher_month'   => $voucherMonth,
    ]
);

if (!empty($smsMeta['success']) && !empty($voucher['voucher_log_id'])) {
    $stmtUpdate = safeQueryPrepare($conn, "UPDATE voucher_logs SET sent_via = 'SMS' WHERE id = ? AND sent_via <> 'SMS'", false);
    if (twilioVoucherUsableStmt($stmtUpdate)) {
        $voucherLogId = (int)$voucher['voucher_log_id'];
        $stmtUpdate->bind_param('i', $voucherLogId);
        if (!$stmtUpdate->execute()) {
            error_log('twilio-voucher-status: failed to update voucher_logs sent_via for id=' . $voucherLogId . ': ' . $stmtUpdate->error);
        }
        $stmtUpdate->close();
    } else {
        error_log('twilio-voucher-status: update prepare failed for voucher_logs sent_via');
    }
}

twilioVoucherCallbackOk();
