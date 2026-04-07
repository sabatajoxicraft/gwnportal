<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/services/ReportService.php';
require_once '../../includes/helpers/ActivityLogHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireRole('admin');

$conn = getDbConnection();

// ----- Read filters -------------------------------------------------------
$report_type      = $_GET['type'] ?? '';
$start_date       = $_GET['start_date'] ?? date('Y-m-01');
$end_date         = $_GET['end_date'] ?? date('Y-m-t');
$accommodation_id = $_GET['accommodation_id'] ?? 'all';
$action_filter    = $_GET['action_filter'] ?? 'all';
$export           = $_GET['export'] ?? '';

// All known report types (M2-T8 first, then legacy)
$report_types = [
    'monthly_voucher_usage',
    'student_enrollment',
    'device_authorization',
    'manager_activity',
    'system_audit_log',
    'user_activity',
    'accommodation_usage',
    'onboarding_codes',
];

// ----- Fetch report data --------------------------------------------------
$report_data = [];
if ($report_type && in_array($report_type, $report_types, true)) {
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
            $limit = ($export === 'csv') ? 0 : 2000;
            $report_data = ReportService::getSystemAuditLog($conn, $start_date, $end_date, $action_filter, 0, $limit);
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
}

// ----- Server-side CSV export ---------------------------------------------
if ($export === 'csv' && $report_type && in_array($report_type, $report_types, true)) {
    $columns  = ReportService::getReportColumns($report_type);
    $filename = 'report_' . $report_type . '_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($output, array_column($columns, 'label'));

    // Timezone converter for audit/activity timestamps displayed as UTC
    $storageTz = defined('ACTIVITY_LOG_STORAGE_TIMEZONE') ? ACTIVITY_LOG_STORAGE_TIMEZONE : 'UTC';
    $displayTz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Johannesburg';
    $tzObj     = new DateTimeZone($displayTz);
    $plainTs   = function (string $raw) use ($storageTz, $tzObj): string {
        if (empty($raw) || strpos($raw, '0000-00-00') === 0) return '';
        try {
            $dt = new DateTimeImmutable($raw, new DateTimeZone($storageTz));
            return $dt->getTimestamp() > 0 ? $dt->setTimezone($tzObj)->format('Y-m-d H:i:s') : '';
        } catch (\Exception $e) { return ''; }
    };
    $stripHtml = fn(string $html): string => html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');

    foreach ($report_data as $row) {
        $csvRow = [];
        foreach ($columns as $col) {
            $val = $row[$col['key']] ?? '';

            // Special formatting for certain columns
            if ($col['key'] === 'timestamp' && in_array($report_type, ['system_audit_log', 'manager_activity'])) {
                $val = $plainTs((string)$val);
            } elseif (in_array($col['key'], ['first_activity', 'last_activity'])) {
                $val = $plainTs((string)$val);
            } elseif ($col['key'] === 'action' && $report_type === 'system_audit_log') {
                $val = $stripHtml(ActivityLogHelper::normalizeActionLabel((string)($row['action'] ?? ''), (string)($row['details'] ?? '')));
            } elseif ($col['key'] === 'details' && $report_type === 'system_audit_log') {
                $val = $stripHtml(ActivityLogHelper::formatDetails((string)($row['action'] ?? ''), (string)($row['details'] ?? '')));
            } elseif ($col['key'] === 'ip_address' && $report_type === 'system_audit_log') {
                $ip = (string)($row['ip_address'] ?? '');
                if ($ip === '') {
                    $decoded = json_decode((string)($row['details'] ?? ''), true);
                    if (is_array($decoded) && !empty($decoded['ip_address'])) {
                        $ip = (string)$decoded['ip_address'];
                    }
                }
                $val = $ip;
            } elseif ($col['key'] === 'role_name') {
                $val = ucfirst((string)$val);
            } elseif ($col['key'] === 'status') {
                $val = ucfirst((string)$val);
            }

            $csvRow[] = (string)$val;
        }
        fputcsv($output, $csvRow);
    }

    fclose($output);
    exit;
}

// ----- Data for filter dropdowns ------------------------------------------
$accommodations = [];
$accom_stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations ORDER BY name");
if ($accom_stmt) {
    $accom_stmt->execute();
    $accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $accom_stmt->close();
}

// Distinct actions for system_audit_log filter
$distinct_actions = [];
if ($report_type === 'system_audit_log') {
    $actionsStmt = safeQueryPrepare($conn, "SELECT DISTINCT action FROM activity_log ORDER BY action");
    if ($actionsStmt) {
        $actionsStmt->execute();
        $distinct_actions = $actionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $actionsStmt->close();
    }
}

// Columns for current report
$columns = $report_type ? ReportService::getReportColumns($report_type) : [];

// Build CSV export URL preserving current filters
$csvExportUrl = '';
if ($report_type && count($report_data) > 0) {
    $csvParams = $_GET;
    $csvParams['export'] = 'csv';
    $csvExportUrl = 'reports.php?' . http_build_query($csvParams);
}

$pageTitle  = "Generate Reports";
$activePage = "reports";
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php require_once '../../includes/components/messages.php'; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Generate Reports</h2>
    </div>

    <div class="row">
        <!-- Sidebar: report type list -->
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Report Types</h5></div>
                <div class="list-group list-group-flush">
                    <?php foreach ($report_types as $rt): ?>
                        <a href="?type=<?= $rt ?>"
                           class="list-group-item list-group-item-action <?= $report_type === $rt ? 'active' : '' ?>">
                            <i class="bi <?= ReportService::getReportIcon($rt) ?> me-2"></i>
                            <?= htmlspecialchars(ReportService::getReportTitle($rt)) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9">
            <?php if ($report_type && in_array($report_type, $report_types, true)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi <?= ReportService::getReportIcon($report_type) ?> me-2"></i>
                            <?= htmlspecialchars(ReportService::getReportTitle($report_type)) ?> Report
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Filter form -->
                        <form method="get" class="row g-3 mb-4">
                            <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">

                            <?php if (ReportService::hasDateFilter($report_type)): ?>
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date"
                                           value="<?= htmlspecialchars($start_date) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date"
                                           value="<?= htmlspecialchars($end_date) ?>">
                                </div>
                            <?php endif; ?>

                            <?php if (ReportService::hasAccommodationFilter($report_type)): ?>
                                <div class="col-md-3">
                                    <label for="accommodation_id" class="form-label">Accommodation</label>
                                    <select class="form-select" id="accommodation_id" name="accommodation_id">
                                        <option value="all" <?= $accommodation_id === 'all' ? 'selected' : '' ?>>All Accommodations</option>
                                        <?php foreach ($accommodations as $acc): ?>
                                            <option value="<?= (int)$acc['id'] ?>" <?= $accommodation_id == $acc['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($acc['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <?php if ($report_type === 'system_audit_log'): ?>
                                <div class="col-md-3">
                                    <label for="action_filter" class="form-label">Action</label>
                                    <select class="form-select" id="action_filter" name="action_filter">
                                        <option value="all" <?= $action_filter === 'all' ? 'selected' : '' ?>>All Actions</option>
                                        <?php foreach ($distinct_actions as $act):
                                            $aVal = $act['action'] ?? '';
                                            if ($aVal === '') continue;
                                        ?>
                                            <option value="<?= htmlspecialchars($aVal) ?>" <?= $action_filter === $aVal ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ActivityLogHelper::getFriendlyActionLabel($aVal)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel me-1"></i> Generate Report
                                </button>
                                <a href="reports.php" class="btn btn-secondary ms-2">Reset</a>
                            </div>
                        </form>

                        <?php if (count($report_data) > 0): ?>
                            <!-- Result count & export -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted"><?= number_format(count($report_data)) ?> row(s) returned</span>
                                <a href="<?= htmlspecialchars($csvExportUrl) ?>" class="btn btn-success btn-sm">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                                </a>
                            </div>

                            <!-- Data table -->
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <?php foreach ($columns as $col): ?>
                                                <th><?= htmlspecialchars($col['label']) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <?php foreach ($columns as $col):
                                                    $val = $row[$col['key']] ?? '';

                                                    // Format specific columns for HTML display
                                                    if ($col['key'] === 'timestamp' && in_array($report_type, ['system_audit_log', 'manager_activity'])) {
                                                        echo '<td>' . ActivityLogHelper::formatTimestamp((string)$val) . '</td>';
                                                    } elseif (in_array($col['key'], ['first_activity', 'last_activity'])) {
                                                        echo '<td>' . ActivityLogHelper::formatTimestamp((string)$val) . '</td>';
                                                    } elseif ($col['key'] === 'action' && $report_type === 'system_audit_log') {
                                                        echo '<td>' . ActivityLogHelper::normalizeActionLabel((string)($row['action'] ?? ''), (string)($row['details'] ?? '')) . '</td>';
                                                    } elseif ($col['key'] === 'details' && $report_type === 'system_audit_log') {
                                                        $formatted = ActivityLogHelper::formatDetails((string)($row['action'] ?? ''), (string)($row['details'] ?? ''));
                                                        echo '<td class="text-truncate" style="max-width:300px;" title="' . htmlspecialchars(strip_tags($formatted)) . '">' . $formatted . '</td>';
                                                    } elseif ($col['key'] === 'ip_address' && $report_type === 'system_audit_log') {
                                                        $ip = (string)($row['ip_address'] ?? '');
                                                        if ($ip === '') {
                                                            $decoded = json_decode((string)($row['details'] ?? ''), true);
                                                            if (is_array($decoded) && !empty($decoded['ip_address'])) {
                                                                $ip = (string)$decoded['ip_address'];
                                                            }
                                                        }
                                                        echo '<td>' . htmlspecialchars($ip) . '</td>';
                                                    } elseif ($col['key'] === 'role_name') {
                                                        echo '<td>' . htmlspecialchars(ucfirst((string)$val)) . '</td>';
                                                    } elseif ($col['key'] === 'status') {
                                                        echo '<td>' . htmlspecialchars(ucfirst((string)$val)) . '</td>';
                                                    } elseif (in_array($col['key'], ['created_at', 'first_enrollment', 'last_enrollment', 'first_code_date', 'last_code_date'])) {
                                                        echo '<td>' . (!empty($val) ? htmlspecialchars(date('Y-m-d', strtotime($val))) : '<span class="text-muted">—</span>') . '</td>';
                                                    } else {
                                                        echo '<td>' . htmlspecialchars((string)$val) . '</td>';
                                                    }
                                                endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                No data found for the selected report criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-file-earmark-bar-graph" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Select a Report Type</h4>
                        <p class="text-muted">Choose a report type from the left menu to generate reports.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
