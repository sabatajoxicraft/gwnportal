<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers/VoucherMonthHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireRole('admin');

$conn = getDbConnection();

// Handle student status actions (admin-level)
if (isset($_GET['action'], $_GET['id'])) {
    $student_id = (int)$_GET['id'];

    if ($_GET['action'] === 'activate') {
        $lookupStmt = safeQueryPrepare($conn, "SELECT user_id FROM students WHERE id = ?");
        $lookupStmt->bind_param("i", $student_id);
        $lookupStmt->execute();
        $row = $lookupStmt->get_result()->fetch_assoc();
        $lookupStmt->close();

        safeQueryPrepare($conn, "UPDATE students SET status = 'active' WHERE id = ?")->execute([$student_id]);
        if ($row) {
            safeQueryPrepare($conn, "UPDATE users SET status = 'active' WHERE id = ?")->execute([$row['user_id']]);
        }
        logActivity($conn, $_SESSION['user_id'], 'admin_activate_student', "Admin activated student ID {$student_id}", $_SERVER['REMOTE_ADDR']);
        redirect(BASE_URL . '/admin/students.php', 'Student activated successfully.', 'success');
    } elseif ($_GET['action'] === 'deactivate') {
        $stmt = safeQueryPrepare($conn, "UPDATE students SET status = 'inactive' WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        logActivity($conn, $_SESSION['user_id'], 'admin_deactivate_student', "Admin deactivated student ID {$student_id}", $_SERVER['REMOTE_ADDR']);
        redirect(BASE_URL . '/admin/students.php', 'Student deactivated successfully.', 'success');
    } elseif ($_GET['action'] === 'archive') {
        $lookupStmt = safeQueryPrepare($conn, "SELECT user_id FROM students WHERE id = ?");
        $lookupStmt->bind_param("i", $student_id);
        $lookupStmt->execute();
        $studentRow = $lookupStmt->get_result()->fetch_assoc();
        $lookupStmt->close();

        if ($studentRow) {
            $archiveUserId = (int)$studentRow['user_id'];

            require_once __DIR__ . '/../../includes/python_interface.php';
            $networkId = defined('GWN_NETWORK_ID') ? GWN_NETWORK_ID : '';
            if (!empty($networkId)) {
                $vStmt = safeQueryPrepare($conn,
                    "SELECT id, gwn_voucher_id FROM voucher_logs WHERE user_id = ? AND is_active = 1 AND gwn_voucher_id IS NOT NULL");
                if ($vStmt) {
                    $vStmt->bind_param("i", $archiveUserId);
                    $vStmt->execute();
                    $activeVouchers = $vStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $vStmt->close();
                    foreach ($activeVouchers as $av) {
                        gwnDeleteVoucher((int)$av['gwn_voucher_id'], $networkId);
                    }
                }
            }

            safeQueryPrepare($conn, "UPDATE voucher_logs SET is_active = 0 WHERE user_id = ? AND is_active = 1")
                ->execute([$archiveUserId]);
            safeQueryPrepare($conn, "UPDATE students SET status = 'archived' WHERE id = ?")
                ->execute([$student_id]);
            safeQueryPrepare($conn, "UPDATE users SET status = 'inactive' WHERE id = ?")
                ->execute([$archiveUserId]);

            logActivity($conn, $_SESSION['user_id'], 'admin_archive_student', "Admin archived student ID {$student_id}", $_SERVER['REMOTE_ADDR']);
        }
        redirect(BASE_URL . '/admin/students.php', 'Student archived. Their data is preserved but they can no longer log in.', 'success');
    }
}

// ── Filter / search / sort / pagination parameters ──────────────────────────
$filter             = $_GET['filter']             ?? 'all';
$search             = trim($_GET['q']             ?? '');
$device_status      = $_GET['device_status']      ?? 'all';
$accommodation_filter = isset($_GET['accommodation_id']) ? (int)$_GET['accommodation_id'] : 0;
$sort               = $_GET['sort']               ?? 'newest';
$page               = max(1, (int)($_GET['page']  ?? 1));
$per_page           = 50;
$offset             = ($page - 1) * $per_page;

$allowed_sorts = ['newest', 'oldest', 'name_asc', 'name_desc'];
if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'newest';
}

$allowed_device_statuses = ['all', 'has_devices', 'needs_approval'];
if (!in_array($device_status, $allowed_device_statuses, true)) {
    $device_status = 'all';
}

// All accommodations for the filter dropdown
$accomStmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations ORDER BY name");
$accomStmt->execute();
$all_accommodations = $accomStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$accomStmt->close();

// Current month for voucher sub-counts
$currentMonth = (new DateTimeImmutable('now', new DateTimeZone(VOUCHER_TZ)))->format('F Y');
$allowedDevices = defined('GWN_ALLOWED_DEVICES') ? (int)GWN_ALLOWED_DEVICES : 2;

// Query params to carry across pagination and tab links
$query_params = [
    'filter'           => $filter,
    'q'                => $search,
    'device_status'    => $device_status,
    'accommodation_id' => $accommodation_filter ?: '',
    'sort'             => $sort,
];

function buildAdminStudentsQS(array $params): string {
    return http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 0));
}

// Build QueryService criteria
$criteria = [
    'status'        => $filter,
    'search'        => $search,
    'device_status' => $device_status,
    'sort'          => $sort,
    'current_month' => $currentMonth,
];
if ($accommodation_filter > 0) {
    $criteria['accommodation_id'] = $accommodation_filter;
}

$students       = QueryService::getStudentList($conn, $criteria, $per_page, $offset);
$total_students = QueryService::countStudentList($conn, $criteria);
$total_pages    = (int)ceil($total_students / $per_page);

// Cross-accommodation stats
$stats_sql  = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN s.status = 'active'   THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN s.status = 'pending'  THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN s.status = 'inactive' THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN s.status = 'archived' THEN 1 ELSE 0 END) as archived
    FROM students s";
if ($accommodation_filter > 0) {
    $stats_sql .= " WHERE s.accommodation_id = {$accommodation_filter}";
}
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

$pageTitle  = "Manage Students";
$activePage = 'students';
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php require_once '../../includes/components/messages.php'; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Students <span class="badge bg-secondary fs-6"><?= $total_students ?></span></h2>
        <a href="create-user.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Create Student Account
        </a>
    </div>

    <!-- Stats Row -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row text-center responsive-stats-grid">
                <div class="col-6 col-md">
                    <h5>Total</h5>
                    <h2><?= $stats['total'] ?? 0 ?></h2>
                </div>
                <div class="col-6 col-md">
                    <h5>Active</h5>
                    <h2 class="text-success"><?= $stats['active'] ?? 0 ?></h2>
                </div>
                <div class="col-6 col-md">
                    <h5>Pending</h5>
                    <h2 class="text-warning"><?= $stats['pending'] ?? 0 ?></h2>
                </div>
                <div class="col-6 col-md">
                    <h5>Inactive</h5>
                    <h2 class="text-danger"><?= $stats['inactive'] ?? 0 ?></h2>
                </div>
                <div class="col-6 col-md">
                    <h5>Archived</h5>
                    <h2 class="text-secondary"><?= $stats['archived'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">Search &amp; Filter</h6></div>
        <div class="card-body">
            <form method="get" class="row g-3 responsive-filter-form">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <div class="col-12 col-md-3">
                    <label for="q" class="form-label">Search</label>
                    <input type="text" name="q" id="q" class="form-control form-control-responsive"
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Name, email, ID or student ID">
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label for="accommodation_id" class="form-label">Accommodation</label>
                    <select name="accommodation_id" id="accommodation_id" class="form-select">
                        <option value="">All Accommodations</option>
                        <?php foreach ($all_accommodations as $accom): ?>
                            <option value="<?= $accom['id'] ?>"
                                <?= $accommodation_filter === (int)$accom['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($accom['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label for="device_status" class="form-label">Device Status</label>
                    <select name="device_status" id="device_status" class="form-select">
                        <option value="all"            <?= $device_status === 'all'            ? 'selected' : '' ?>>All</option>
                        <option value="has_devices"    <?= $device_status === 'has_devices'    ? 'selected' : '' ?>>Has Devices</option>
                        <option value="needs_approval" <?= $device_status === 'needs_approval' ? 'selected' : '' ?>>Needs Device Approval</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label for="sort" class="form-label">Sort</label>
                    <select name="sort" id="sort" class="form-select">
                        <option value="newest"    <?= $sort === 'newest'    ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest"    <?= $sort === 'oldest'    ? 'selected' : '' ?>>Oldest First</option>
                        <option value="name_asc"  <?= $sort === 'name_asc'  ? 'selected' : '' ?>>Name (A–Z)</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z–A)</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2 responsive-filter-actions d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="students.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Student List -->
    <div class="card">
        <div class="card-header card-header-responsive">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'all'      ? 'active' : '' ?>"
                       href="?<?= buildAdminStudentsQS(array_merge($query_params, ['filter' => 'all',      'page' => 1])) ?>">All Students</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'active'   ? 'active' : '' ?>"
                       href="?<?= buildAdminStudentsQS(array_merge($query_params, ['filter' => 'active',   'page' => 1])) ?>">Active</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'pending'  ? 'active' : '' ?>"
                       href="?<?= buildAdminStudentsQS(array_merge($query_params, ['filter' => 'pending',  'page' => 1])) ?>">Pending</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'inactive' ? 'active' : '' ?>"
                       href="?<?= buildAdminStudentsQS(array_merge($query_params, ['filter' => 'inactive', 'page' => 1])) ?>">Inactive</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'archived' ? 'active' : '' ?>"
                       href="?<?= buildAdminStudentsQS(array_merge($query_params, ['filter' => 'archived', 'page' => 1])) ?>">
                        <i class="bi bi-archive me-1"></i>Archived</a>
                </li>
            </ul>
        </div>

        <div class="card-header bg-transparent border-top-0 pt-0">
            <small class="text-muted">
                Showing <?= count($students) ?> of <?= $total_students ?> student<?= $total_students !== 1 ? 's' : '' ?>
                <?php if ($search !== ''): ?>
                    for "<?= htmlspecialchars($search) ?>"
                <?php endif; ?>
            </small>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 responsive-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Accommodation</th>
                            <th>Devices</th>
                            <th>Flags</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) > 0): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        <?php if (!empty($student['id_number'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($student['id_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($student['accommodation_name']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($student['device_count'] > 0): ?>
                                            <span class="badge bg-info" title="<?= $student['device_count'] ?> device(s)">
                                                <i class="bi bi-phone"></i> <?= $student['device_count'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $flags = [];
                                        if ($student['active_vouchers_this_month'] > 1) {
                                            $flags[] = '<span class="badge bg-warning text-dark" title="' . $student['active_vouchers_this_month'] . ' active vouchers for ' . htmlspecialchars($currentMonth) . '"><i class="bi bi-exclamation-triangle"></i> ' . $student['active_vouchers_this_month'] . '</span>';
                                        }
                                        if ($student['device_count'] > $allowedDevices) {
                                            $flags[] = '<span class="badge bg-danger" title="' . $student['device_count'] . '/' . $allowedDevices . ' devices"><i class="bi bi-phone-fill"></i> ' . $student['device_count'] . '/' . $allowedDevices . '</span>';
                                        }
                                        if ($student['total_voucher_count'] > 3) {
                                            $flags[] = '<span class="badge bg-secondary" title="' . $student['total_voucher_count'] . ' total vouchers"><i class="bi bi-clipboard-data"></i> ' . $student['total_voucher_count'] . '</span>';
                                        }
                                        echo empty($flags) ? '<span class="text-muted">—</span>' : implode(' ', $flags);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($student['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($student['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($student['status'] === 'archived'): ?>
                                            <span class="badge bg-secondary">Archived</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($student['created_at'])) ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                                    id="sd<?= $student['id'] ?>"
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="sd<?= $student['id'] ?>">
                                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/student-details.php?id=<?= $student['id'] ?>"><i class="bi bi-eye me-2"></i>View Details</a></li>
                                                <li><a class="dropdown-item" href="view-user.php?id=<?= $student['user_id'] ?>"><i class="bi bi-person me-2"></i>View User Account</a></li>
                                                <?php if ($student['status'] !== 'archived'): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($student['status'] !== 'active'): ?>
                                                        <li><a class="dropdown-item text-success"
                                                               href="?action=activate&id=<?= $student['id'] ?>&<?= buildAdminStudentsQS($query_params) ?>">
                                                            <i class="bi bi-check-circle me-2"></i>Activate</a></li>
                                                    <?php endif; ?>
                                                    <?php if ($student['status'] !== 'inactive'): ?>
                                                        <li><a class="dropdown-item"
                                                               href="?action=deactivate&id=<?= $student['id'] ?>&<?= buildAdminStudentsQS($query_params) ?>">
                                                            <i class="bi bi-x-circle me-2"></i>Deactivate</a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger"
                                                           href="?action=archive&id=<?= $student['id'] ?>&<?= buildAdminStudentsQS($query_params) ?>"
                                                           onclick="return confirm('Archive this student? They will no longer be able to log in.')">
                                                        <i class="bi bi-archive me-2"></i>Archive</a></li>
                                                <?php else: ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-success"
                                                           href="?action=activate&id=<?= $student['id'] ?>&<?= buildAdminStudentsQS($query_params) ?>"
                                                           onclick="return confirm('Restore this student and reactivate their account?')">
                                                        <i class="bi bi-arrow-counterclockwise me-2"></i>Restore</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3">No Students Found</h5>
                                    <?php if ($search !== ''): ?>
                                        <p class="text-muted">No students match "<strong><?= htmlspecialchars($search) ?></strong>".</p>
                                    <?php else: ?>
                                        <p class="text-muted">No students match the current filters.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Student list pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildAdminStudentsQS(array_merge($query_params, ['page' => $page - 1])) ?>">Previous</a>
                        </li>
                        <?php
                        $sp = max(1, $page - 2);
                        $ep = min($total_pages, $page + 2);
                        if ($sp > 1): ?>
                            <li class="page-item"><a class="page-link" href="?<?= buildAdminStudentsQS(array_merge($query_params, ['page' => 1])) ?>">1</a></li>
                            <?php if ($sp > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $sp; $i <= $ep; $i++): ?>
                            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= buildAdminStudentsQS(array_merge($query_params, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($ep < $total_pages): ?>
                            <?php if ($ep < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?<?= buildAdminStudentsQS(array_merge($query_params, ['page' => $total_pages])) ?>"><?= $total_pages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildAdminStudentsQS(array_merge($query_params, ['page' => $page + 1])) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
