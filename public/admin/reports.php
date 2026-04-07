<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers/ActivityLogHelper.php';
require_once '../../includes/services/ReportService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireRole('admin');

$conn = getDbConnection();

// Common filters
$report_type      = $_GET['type']             ?? '';
$start_date       = $_GET['start_date']       ?? date('Y-m-01');
$end_date         = $_GET['end_date']         ?? date('Y-m-t');
$accommodation_id = $_GET['accommodation_id'] ?? 'all';

// Additional filters for the system_audit_log report
$filter_action = $_GET['action'] ?? 'all';
$filter_user   = (int)($_GET['user'] ?? 0);

// Fetch report data via ReportService
$report_data = [];
if ($report_type) {
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
            $report_data = ReportService::getSystemAuditLog($conn, $start_date, $end_date, $filter_action, $filter_user, 2000);
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

// Accommodation dropdown data
$accom_stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations ORDER BY name");
$accom_stmt->execute();
$accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// User / action dropdowns (loaded only when the audit log is selected)
$audit_users   = [];
$audit_actions = [];
if ($report_type === 'system_audit_log') {
    $usersStmt = safeQueryPrepare($conn,
        "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, username
         FROM users ORDER BY first_name, last_name");
    if ($usersStmt) {
        $usersStmt->execute();
        $audit_users = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $usersStmt->close();
    }
    $actionsStmt = safeQueryPrepare($conn, "SELECT DISTINCT action FROM activity_log ORDER BY action");
    if ($actionsStmt) {
        $actionsStmt->execute();
        $audit_actions = array_column($actionsStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'action');
        $actionsStmt->close();
    }
}

// Timestamp display helper: UTC-stored value -> local time string
$storageTz = defined('ACTIVITY_LOG_STORAGE_TIMEZONE') ? ACTIVITY_LOG_STORAGE_TIMEZONE : 'UTC';
$displayTz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Johannesburg';
$tzObj     = new DateTimeZone($displayTz);

$displayTimestamp = function (string $raw) use ($storageTz, $tzObj): string {
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return '<span class="text-muted">-</span>';
    }
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone($storageTz));
        if ($dt->getTimestamp() <= 0) {
            return '<span class="text-muted">-</span>';
        }
        return htmlspecialchars($dt->setTimezone($tzObj)->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8');
    } catch (\Exception $e) {
        return '<span class="text-muted">-</span>';
    }
};

// Columns that hold UTC timestamps and need conversion for display
$timestampKeys = [
    'timestamp', 'first_activity', 'last_activity',
    'first_enrollment', 'last_enrollment',
    'created_at', 'first_code_date', 'last_code_date',
];

$pageTitle  = 'Generate Reports';
$activePage = 'reports';

require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php require_once '../../includes/components/messages.php'; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Generate Reports</h2>
    </div>

    <div class="row">
        <!-- Sidebar nav -->
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Report Types</h5>
                </div>
                <?php
                $reportNav = [
                    ['type' => 'monthly_voucher_usage', 'section' => 'M2-T8 Reports'],
                    ['type' => 'student_enrollment',    'section' => 'M2-T8 Reports'],
                    ['type' => 'device_authorization',  'section' => 'M2-T8 Reports'],
                    ['type' => 'manager_activity',      'section' => 'M2-T8 Reports'],
                    ['type' => 'system_audit_log',      'section' => 'M2-T8 Reports'],
                    ['type' => 'user_activity',         'section' => 'Additional'],
                    ['type' => 'accommodation_usage',   'section' => 'Additional'],
                    ['type' => 'onboarding_codes',      'section' => 'Additional'],
                ];
                $currentSection = '';
                ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($reportNav as $nav):
                        if ($nav['section'] !== $currentSection):
                            $currentSection = $nav['section'];
                            ?>
                            <div class="list-group-item list-group-item-secondary py-1 px-3">
                                <small class="fw-semibold text-muted"><?= htmlspecialchars($currentSection) ?></small>
                            </div>
                        <?php endif; ?>
                        <a href="?type=<?= $nav['type'] ?>"
                           class="list-group-item list-group-item-action <?= $report_type === $nav['type'] ? 'active' : '' ?>">
                            <i class="bi <?= ReportService::getReportIcon($nav['type']) ?> me-2"></i>
                            <?= htmlspecialchars(ReportService::getReportTitle($nav['type'])) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9">
            <?php if ($report_type):
                $columns = ReportService::getReportColumns($report_type);
                if (empty($columns)):
            ?>
                    <div class="alert alert-danger">Unknown report type.</div>
                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi <?= ReportService::getReportIcon($report_type) ?> me-2"></i>
                            <?= htmlspecialchars(ReportService::getReportTitle($report_type)) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Filters form -->
                        <form method="get" class="row g-3 mb-4">
                            <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">

                            <?php if (ReportService::hasDateFilter($report_type)): ?>
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date"
                                           value="<?= htmlspecialchars($start_date) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date"
                                           value="<?= htmlspecialchars($end_date) ?>">
                                </div>
                            <?php endif; ?>

                            <?php if (ReportService::hasAccommodationFilter($report_type)): ?>
                                <div class="col-md-4">
                                    <label for="accommodation_id" class="form-label">Accommodation</label>
                                    <select class="form-select" id="accommodation_id" name="accommodation_id">
                                        <option value="all" <?= $accommodation_id === 'all' ? 'selected' : '' ?>>All Accommodations</option>
                                        <?php foreach ($accommodations as $accom): ?>
                                            <option value="<?= $accom['id'] ?>"
                                                <?= $accommodation_id == $accom['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($accom['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <?php if ($report_type === 'system_audit_log'): ?>
                                <div class="col-md-4">
                                    <label for="user_filter" class="form-label">User</label>
                                    <select class="form-select" id="user_filter" name="user">
                                        <option value="0">All Users</option>
                                        <?php foreach ($audit_users as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>"
                                                <?= $filter_user === (int)$u['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['full_name'] . ' (' . $u['username'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="action_filter" class="form-label">Action</label>
                                    <select class="form-select" id="action_filter" name="action">
                                        <option value="all">All Actions</option>
                                        <?php foreach ($audit_actions as $act): ?>
                                            <option value="<?= htmlspecialchars($act) ?>"
                                                <?= $filter_action === $act ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ActivityLogHelper::getFriendlyActionLabel($act)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i> Generate Report
                                </button>
                                <a href="reports.php" class="btn btn-secondary ms-2">Reset</a>
                            </div>
                        </form>

                        <?php if (!empty($report_data)):
                            // Build server-side export URL preserving all active filters
                            $exportParams = [
                                'type'             => $report_type,
                                'start_date'       => $start_date,
                                'end_date'         => $end_date,
                                'accommodation_id' => $accommodation_id,
                            ];
                            if ($report_type === 'system_audit_log') {
                                $exportParams['action'] = $filter_action;
                                $exportParams['user']   = $filter_user;
                            }
                            $exportUrl = 'export-reports.php?' . http_build_query($exportParams);
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small">
                                    <?= count($report_data) ?> row(s) shown
                                    <?php if ($report_type === 'system_audit_log'): ?>
                                        &mdash; display capped at 2,000; export streams full dataset
                                    <?php endif; ?>
                                </span>
                                <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-success btn-sm">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to CSV
                                </a>
                            </div>

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
                                                    $key = $col['key'];
                                                    $val = (string)($row[$key] ?? '');

                                                    if (in_array($key, $timestampKeys, true) && $val !== '') {
                                                        echo '<td>' . $displayTimestamp($val) . '</td>';
                                                    } elseif ($report_type === 'system_audit_log' && $key === 'action') {
                                                        echo '<td>' . ActivityLogHelper::normalizeActionLabel($val, (string)($row['details'] ?? '')) . '</td>';
                                                    } elseif ($report_type === 'system_audit_log' && $key === 'details') {
                                                        $formatted = ActivityLogHelper::formatDetails((string)($row['action'] ?? ''), $val);
                                                        $plain     = htmlspecialchars(strip_tags($formatted), ENT_QUOTES, 'UTF-8');
                                                        echo '<td class="text-truncate" style="max-width:220px" title="' . $plain . '">' . $formatted . '</td>';
                                                    } elseif (in_array($key, ['status', 'role_name'], true) && $val !== '') {
                                                        echo '<td>' . htmlspecialchars(ucfirst($val)) . '</td>';
                                                    } else {
                                                        echo '<td>' . htmlspecialchars($val) . '</td>';
                                                    }
                                                endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php else: ?>
                            <div class="alert alert-info">
                                No data found for the selected criteria.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; // valid columns
            else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-file-earmark-bar-graph" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Select a Report Type</h4>
                        <p class="text-muted">Choose a report type from the left menu to generate and export reports.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>