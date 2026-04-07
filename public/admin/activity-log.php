<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers/ActivityLogHelper.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

$conn = getDbConnection();

// Filter variables
$filter_user = (int)($_GET['user'] ?? 0);
$filter_action = $_GET['action'] ?? 'all';
// Default date range expressed in APP_TIMEZONE (local dates shown to user)
$_localTz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Johannesburg');
$filter_date_from = $_GET['date_from'] ?? (new DateTimeImmutable('-7 days', $_localTz))->format('Y-m-d');
$filter_date_to   = $_GET['date_to']   ?? (new DateTimeImmutable('now',    $_localTz))->format('Y-m-d');
unset($_localTz);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$logs = [];
$totalLogs = 0;
$totalPages = 1;

// Convert local date range to UTC boundaries for SQL timestamp filtering
$_utcRange       = ActivityLogHelper::localDateRangeToUtc($filter_date_from, $filter_date_to);
$utc_filter_from = $_utcRange['utc_from'] ?? ($filter_date_from . ' 00:00:00');
$utc_filter_to   = $_utcRange['utc_to']   ?? ($filter_date_to   . ' 23:59:59');
unset($_utcRange);

$countSql = "SELECT COUNT(*) as total
             FROM activity_log al
             WHERE (? = 0 OR al.user_id = ?)
               AND (? = 'all' OR al.action LIKE CONCAT('%', ?, '%'))
               AND al.timestamp >= ?
               AND al.timestamp < ?";
$countStmt = safeQueryPrepare($conn, $countSql);
if ($countStmt !== false) {
    $countStmt->bind_param("iissss", $filter_user, $filter_user, $filter_action, $filter_action, $utc_filter_from, $utc_filter_to);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $totalLogs = (int)($countRow['total'] ?? 0);
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($totalLogs / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$logsSql = "SELECT
                al.id          AS id,
                al.user_id     AS user_id,
                al.action      AS action,
                al.details     AS details,
                al.ip_address  AS ip_address,
                al.user_agent  AS user_agent,
                al.timestamp   AS timestamp,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                u.username
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE (? = 0 OR al.user_id = ?)
              AND (? = 'all' OR al.action LIKE CONCAT('%', ?, '%'))
              AND al.timestamp >= ?
              AND al.timestamp < ?
            ORDER BY al.timestamp DESC
            LIMIT ? OFFSET ?";
$logsStmt = safeQueryPrepare($conn, $logsSql);
if ($logsStmt !== false) {
    $logsStmt->bind_param("iissssii", $filter_user, $filter_user, $filter_action, $filter_action, $utc_filter_from, $utc_filter_to, $limit, $offset);
    $logsStmt->execute();
    $logs = $logsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $logsStmt->close();
}

$users = [];
$usersStmt = safeQueryPrepare($conn, "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, username FROM users ORDER BY first_name, last_name");
if ($usersStmt !== false) {
    $usersStmt->execute();
    $users = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $usersStmt->close();
}

$actions = [];
$actionsStmt = safeQueryPrepare($conn, "SELECT DISTINCT action AS action FROM activity_log ORDER BY action");
if ($actionsStmt !== false) {
    $actionsStmt->execute();
    $actions = $actionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $actionsStmt->close();
}

// Set page title
$pageTitle = "Activity Log";
$activePage = "activity-log";

// ActivityLogHelper already loaded at top of file

// Include header
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2>System Activity Log</h2>
        <div class="mobile-stack-actions d-flex flex-wrap gap-2">
            <button class="btn btn-outline-secondary" id="refresh-log">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <a href="export-activity-log.php?<?= http_build_query(['user' => $filter_user, 'action' => $filter_action, 'date_from' => $filter_date_from, 'date_to' => $filter_date_to]) ?>" class="btn btn-outline-primary">
                <i class="bi bi-download"></i> Export Log
            </a>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Activity Log</h5>
        </div>
        <div class="card-body">
            <form class="row g-3 responsive-filter-form" method="get">
                <div class="col-12 col-sm-6 col-md-3">
                    <label for="user" class="form-label">User</label>
                    <select name="user" id="user" class="form-select">
                        <option value="0" <?= $filter_user === 0 ? 'selected' : '' ?>>All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int)$user['id'] ?>" <?= $filter_user === (int)$user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label for="action" class="form-label">Action Type</label>
                    <select name="action" id="action" class="form-select">
                        <option value="all" <?= $filter_action === 'all' ? 'selected' : '' ?>>All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <?php $actionValue = (string)($action['action'] ?? ''); ?>
                            <?php if ($actionValue !== ''): ?>
                                <option value="<?= htmlspecialchars($actionValue) ?>" <?= $filter_action === $actionValue ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ActivityLogHelper::getFriendlyActionLabel($actionValue)) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $filter_date_from ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $filter_date_to ?>">
                </div>
                <div class="col-12 responsive-filter-actions d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="activity-log.php" class="btn btn-secondary">Reset Filters</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Activity Entries</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= ActivityLogHelper::formatTimestamp((string)($log['timestamp'] ?? '')) ?></td>
                                    <td>
                                        <?php if (!empty($log['user_name'])): ?>
                                            <?= htmlspecialchars($log['user_name']) ?>
                                            <small class="text-muted">(<?= htmlspecialchars($log['username']) ?>)</small>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= ActivityLogHelper::normalizeActionLabel((string)($log['action'] ?? ''), (string)($log['details'] ?? '')) ?></td>
                                    <td><?= ActivityLogHelper::formatIp((string)($log['ip_address'] ?? ''), (string)($log['details'] ?? '')) ?></td>
                                    <td><?= ActivityLogHelper::formatDetails((string)($log['action'] ?? ''), (string)($log['details'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No activity logs found for the selected filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Activity log pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php $prevPage = max(1, $page - 1); ?>
                        <?php $nextPage = min($totalPages, $page + 1); ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $prevPage])) ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a>
                        </li>

                        <?php $startPage = max(1, $page - 2); ?>
                        <?php $endPage = min($totalPages, $page + 2); ?>
                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $nextPage])) ?>" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('refresh-log').addEventListener('click', function() {
        location.reload();
    });
});
</script>

<?php require_once '../../includes/components/footer.php'; ?>
