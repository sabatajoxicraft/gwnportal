<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers/ActivityLogHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireRole('admin');

$conn = getDbConnection();

// Re-use the same filter variables as activity-log.php
$filter_user   = (int)($_GET['user'] ?? 0);
$filter_action = $_GET['action'] ?? 'all';

$_localTz       = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Johannesburg');
$filter_date_from = $_GET['date_from'] ?? (new DateTimeImmutable('-7 days', $_localTz))->format('Y-m-d');
$filter_date_to   = $_GET['date_to']   ?? (new DateTimeImmutable('now',    $_localTz))->format('Y-m-d');
unset($_localTz);

$_utcRange       = ActivityLogHelper::localDateRangeToUtc($filter_date_from, $filter_date_to);
$utc_filter_from = $_utcRange['utc_from'] ?? ($filter_date_from . ' 00:00:00');
$utc_filter_to   = $_utcRange['utc_to']   ?? ($filter_date_to   . ' 23:59:59');
unset($_utcRange);

$sql = "SELECT
            al.id          AS id,
            al.user_id     AS user_id,
            al.action      AS action,
            al.details     AS details,
            al.ip_address  AS ip_address,
            al.timestamp   AS timestamp,
            CONCAT(u.first_name, ' ', u.last_name) AS user_name,
            u.username
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE (? = 0 OR al.user_id = ?)
          AND (? = 'all' OR al.action LIKE CONCAT('%', ?, '%'))
          AND al.timestamp >= ?
          AND al.timestamp < ?
        ORDER BY al.timestamp DESC";

$stmt = safeQueryPrepare($conn, $sql);
if ($stmt === false) {
    http_response_code(500);
    exit('Export query failed.');
}

$stmt->bind_param("iissss",
    $filter_user, $filter_user,
    $filter_action, $filter_action,
    $utc_filter_from, $utc_filter_to
);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Plain-text timestamp converter (avoids HTML output of formatTimestamp)
$storageTz = defined('ACTIVITY_LOG_STORAGE_TIMEZONE') ? ACTIVITY_LOG_STORAGE_TIMEZONE : 'UTC';
$displayTz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Johannesburg';
$tzObj     = new DateTimeZone($displayTz);

$plainTimestamp = function (string $raw) use ($storageTz, $tzObj): string {
    if (empty($raw) || strpos($raw, '0000-00-00') === 0) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone($storageTz));
        if ($dt->getTimestamp() <= 0) return '';
        return $dt->setTimezone($tzObj)->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
        return '';
    }
};

// Strip HTML from helper output for plain-text CSV cells
$plainText = fn(string $html): string => html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');

$filename = 'activity_log_export_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['ID', 'Date & Time', 'User', 'Username', 'Action', 'IP Address', 'Details']);

foreach ($logs as $log) {
    $action  = (string)($log['action']     ?? '');
    $details = (string)($log['details']    ?? '');
    $ip      = (string)($log['ip_address'] ?? '');

    // Fall back to IP in details JSON when the column is empty
    if ($ip === '') {
        $decoded = json_decode($details, true);
        if (is_array($decoded) && !empty($decoded['ip_address'])) {
            $ip = (string)$decoded['ip_address'];
        }
    }

    $row = [
        (int)($log['id'] ?? 0),
        $plainTimestamp((string)($log['timestamp'] ?? '')),
        !empty($log['user_name']) ? $log['user_name'] : 'System',
        (string)($log['username'] ?? ''),
        $plainText(ActivityLogHelper::normalizeActionLabel($action, $details)),
        $ip,
        $plainText(ActivityLogHelper::formatDetails($action, $details)),
    ];

    fputcsv($output, $row);
}

fclose($output);
exit;
