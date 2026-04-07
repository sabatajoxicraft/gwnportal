<?php
/**
 * export-reports.php
 *
 * Server-side CSV export for all admin report types.
 * Mirrors the filter parameters accepted by reports.php.
 * Streams the full dataset (no row cap) directly to the browser.
 */
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers/ActivityLogHelper.php';
require_once '../../includes/services/ReportService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireRole('admin');

$conn = getDbConnection();

$report_type      = $_GET['type']             ?? '';
$start_date       = $_GET['start_date']       ?? date('Y-m-01');
$end_date         = $_GET['end_date']         ?? date('Y-m-t');
$accommodation_id = $_GET['accommodation_id'] ?? 'all';
$filter_action    = $_GET['action']           ?? 'all';
$filter_user      = (int)($_GET['user']       ?? 0);

$columns = ReportService::getReportColumns($report_type);
if (empty($columns)) {
    http_response_code(400);
    exit('Invalid or unknown report type.');
}

// Fetch data — no row cap for exports
$report_data = [];
switch ($report_type) {
    case 'monthly_voucher_usage':
        $report_data = ReportService::getMonthlyVoucherUsage($conn, $start_date, $end_date, $accommodation_id);
        break;
    case 'student_enrollment':
        $report_data = ReportService::getStudentEnrollmentByAccommodation($conn, $start_date, $end_date, $accommodation_id);
        break;
    case 'device_authorization':
        $report_data = ReportService::getDeviceAuthorizationSummary($conn, $start_date, $end_date, $accommodation_id);
        break;
    case 'manager_activity':
        $report_data = ReportService::getManagerActivity($conn, $start_date, $end_date, $accommodation_id);
        break;
    case 'system_audit_log':
        $report_data = ReportService::getSystemAuditLog($conn, $start_date, $end_date, $filter_action, $filter_user, 0);
        break;
    case 'user_activity':
        $report_data = ReportService::getUserActivity($conn, $start_date, $end_date);
        break;
    case 'accommodation_usage':
        $report_data = ReportService::getAccommodationUsage($conn, $accommodation_id);
        break;
    case 'onboarding_codes':
        $report_data = ReportService::getOnboardingCodes($conn, $start_date, $end_date, $accommodation_id);
        break;
}

// Timestamp formatter: UTC stored value → local display string
$storageTz = defined('ACTIVITY_LOG_STORAGE_TIMEZONE') ? ACTIVITY_LOG_STORAGE_TIMEZONE : 'UTC';
$displayTz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Johannesburg';
$tzObj     = new DateTimeZone($displayTz);

$plainTimestamp = function (string $raw) use ($storageTz, $tzObj): string {
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone($storageTz));
        if ($dt->getTimestamp() <= 0) {
            return '';
        }
        return $dt->setTimezone($tzObj)->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
        return '';
    }
};

$plainText = fn(string $html): string =>
    html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');

// Columns whose values are UTC timestamps
$timestampKeys = [
    'timestamp', 'first_activity', 'last_activity',
    'first_enrollment', 'last_enrollment',
    'created_at', 'first_code_date', 'last_code_date',
];

// Stream CSV
$filename = 'report_' . $report_type . '_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header row
fputcsv($output, array_column($columns, 'label'));

// Data rows
foreach ($report_data as $row) {
    $csvRow = [];
    foreach ($columns as $col) {
        $key = $col['key'];
        $val = (string)($row[$key] ?? '');

        if (in_array($key, $timestampKeys, true) && $val !== '') {
            $val = $plainTimestamp($val);
        }

        if ($report_type === 'system_audit_log') {
            if ($key === 'action') {
                $val = $plainText(ActivityLogHelper::normalizeActionLabel($val, (string)($row['details'] ?? '')));
            } elseif ($key === 'details') {
                $val = $plainText(ActivityLogHelper::formatDetails((string)($row['action'] ?? ''), $val));
            }
        }

        if (in_array($key, ['status', 'role_name'], true) && $val !== '') {
            $val = ucfirst($val);
        }

        $csvRow[] = $val;
    }
    fputcsv($output, $csvRow);
}

fclose($output);
exit;
