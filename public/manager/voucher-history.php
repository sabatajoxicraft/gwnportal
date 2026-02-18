<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

requireManagerLogin();

$accommodation_id = $_SESSION['accommodation_id'] ?? 0;
$conn = getDbConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$student_search = $_GET['student_search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$month_filter = $_GET['month_filter'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'sent_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Build query
$where_clauses = ["s.accommodation_id = ?"];
$params = [$accommodation_id];
$param_types = "i";

if (!empty($date_from)) {
    $where_clauses[] = "vl.sent_at >= ?";
    $params[] = $date_from . ' 00:00:00';
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_clauses[] = "vl.sent_at <= ?";
    $params[] = $date_to . ' 23:59:59';
    $param_types .= "s";
}

if (!empty($student_search)) {
    $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$student_search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($status_filter)) {
    $where_clauses[] = "vl.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($month_filter)) {
    $where_clauses[] = "vl.voucher_month = ?";
    $params[] = $month_filter;
    $param_types .= "s";
}

$where_sql = implode(' AND ', $where_clauses);

// Validate sort columns
$allowed_sorts = ['sent_at', 'voucher_code', 'voucher_month', 'status', 'student_name'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'sent_at';
}
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// Map sort_by to actual column
$sort_column = $sort_by;
if ($sort_by === 'student_name') {
    $sort_column = 'u.first_name';
}

// Count total
$count_sql = "SELECT COUNT(*) as total 
              FROM voucher_logs vl
              JOIN users u ON vl.user_id = u.id
              JOIN students s ON u.id = s.user_id
              WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Fetch vouchers
$sql = "SELECT vl.*, u.first_name, u.last_name, u.email,
               CONCAT(u.first_name, ' ', u.last_name) as student_name
        FROM voucher_logs vl
        JOIN users u ON vl.user_id = u.id
        JOIN students s ON u.id = s.user_id
        WHERE $where_sql
        ORDER BY $sort_column $sort_order
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$vouchers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique months for filter
$months_sql = "SELECT DISTINCT voucher_month FROM voucher_logs 
               JOIN users u ON voucher_logs.user_id = u.id
               JOIN students s ON u.id = s.user_id
               WHERE s.accommodation_id = ?
               ORDER BY voucher_month DESC";
$months_stmt = $conn->prepare($months_sql);
$months_stmt->bind_param("i", $accommodation_id);
$months_stmt->execute();
$available_months = $months_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper function to format month display
function formatVoucherMonth($month) {
    if (empty($month)) return '';
    
    // Try to parse as YYYY-MM format
    if (preg_match('/^(\d{4})-(\d{2})$/', $month)) {
        $timestamp = strtotime($month . '-01');
        return date('M Y', $timestamp); // e.g., "Feb 2026"
    }
    
    // Try to parse as "Month YYYY" format
    $timestamp = strtotime('1 ' . $month);
    if ($timestamp !== false) {
        return date('M Y', $timestamp); // e.g., "Feb 2026"
    }
    
    // If all else fails, return original
    return $month;
}

$pageTitle = "Voucher History";
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php displayFlashMessage(); ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-clock-history me-2"></i>Voucher History</h2>
        <div>
            <a href="export-vouchers.php?<?= http_build_query($_GET) ?>" class="btn btn-success me-2">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
            <a href="<?= BASE_URL ?>/send-voucher.php" class="btn btn-primary">
                <i class="bi bi-send"></i> Send Voucher
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters</h5>
        </div>
        <div class="card-body">
            <form method="get" action="" id="filter-form">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="student_search" class="form-label">Student Search</label>
                        <input type="text" class="form-control" id="student_search" name="student_search" 
                               placeholder="Name or email" value="<?= htmlspecialchars($student_search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status_filter" class="form-label">Status</label>
                        <select class="form-select" id="status_filter" name="status_filter">
                            <option value="">All Statuses</option>
                            <option value="sent" <?= $status_filter === 'sent' ? 'selected' : '' ?>>Sent</option>
                            <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="month_filter" class="form-label">Voucher Month</label>
                        <select class="form-select" id="month_filter" name="month_filter">
                            <option value="">All Months</option>
                            <?php foreach ($available_months as $month): ?>
                                <option value="<?= htmlspecialchars($month['voucher_month']) ?>" 
                                        <?= $month_filter === $month['voucher_month'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(formatVoucherMonth($month['voucher_month'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-9 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search"></i> Apply Filters
                        </button>
                        <a href="voucher-history.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Results Summary -->
    <div class="alert alert-info">
        <strong><?= number_format($total_records) ?></strong> voucher(s) found
        <?php if ($total_records > 0): ?>
            (Page <?= $page ?> of <?= $total_pages ?>)
        <?php endif; ?>
    </div>
    
    <!-- Vouchers Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($vouchers)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">No vouchers found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'student_name', 'sort_order' => ($sort_by === 'student_name' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])) ?>">
                                        Student 
                                        <?php if ($sort_by === 'student_name'): ?>
                                            <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'voucher_code', 'sort_order' => ($sort_by === 'voucher_code' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])) ?>">
                                        Voucher Code
                                        <?php if ($sort_by === 'voucher_code'): ?>
                                            <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'voucher_month', 'sort_order' => ($sort_by === 'voucher_month' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])) ?>">
                                        Month
                                        <?php if ($sort_by === 'voucher_month'): ?>
                                            <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Sent Via</th>
                                <th>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'status', 'sort_order' => ($sort_by === 'status' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])) ?>">
                                        Status
                                        <?php if ($sort_by === 'status'): ?>
                                            <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'sent_at', 'sort_order' => ($sort_by === 'sent_at' && $sort_order === 'ASC') ? 'DESC' : 'ASC'])) ?>">
                                        Sent Date
                                        <?php if ($sort_by === 'sent_at'): ?>
                                            <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vouchers as $voucher): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($voucher['student_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($voucher['email']) ?></small>
                                    </td>
                                    <td><code><?= htmlspecialchars($voucher['voucher_code']) ?></code></td>
                                    <td><?= htmlspecialchars(formatVoucherMonth($voucher['voucher_month'])) ?></td>
                                    <td>
                                        <?php if ($voucher['sent_via'] === 'SMS'): ?>
                                            <i class="bi bi-chat-text text-primary" title="SMS"></i> SMS
                                        <?php else: ?>
                                            <i class="bi bi-whatsapp text-success" title="WhatsApp"></i> WhatsApp
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'secondary';
                                        if ($voucher['status'] === 'sent') $badge_class = 'success';
                                        elseif ($voucher['status'] === 'failed') $badge_class = 'danger';
                                        elseif ($voucher['status'] === 'pending') $badge_class = 'warning';
                                        
                                        // Check if revoked
                                        $is_revoked = isset($voucher['is_active']) && $voucher['is_active'] == 0;
                                        if ($is_revoked) {
                                            $badge_class = 'dark';
                                            $status_text = 'Revoked';
                                        } else {
                                            $status_text = ucfirst($voucher['status']);
                                        }
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>"><?= $status_text ?></span>
                                        <?php if ($is_revoked): ?>
                                            <span class="badge bg-dark ms-1"><i class="bi bi-x-circle-fill"></i> Revoked</span>
                                        <?php elseif ($voucher['status'] === 'sent'): ?>
                                            <span class="badge bg-info ms-1"><i class="bi bi-check-circle-fill"></i> Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($voucher['sent_at']): ?>
                                            <?= date('M j, Y H:i', strtotime($voucher['sent_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not sent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="voucher-details.php?id=<?= $voucher['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($voucher['status'] === 'sent' && (!isset($voucher['is_active']) || $voucher['is_active'] == 1)): ?>
                                            <button class="btn btn-sm btn-outline-danger revoke-btn" 
                                                    data-voucher-id="<?= $voucher['id'] ?>"
                                                    data-voucher-code="<?= htmlspecialchars($voucher['voucher_code']) ?>"
                                                    title="Revoke Voucher">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Voucher history pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Revoke Modal -->
<div class="modal fade" id="revokeModal" tabindex="-1" aria-labelledby="revokeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="revokeModalLabel">Revoke Voucher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="revoke-form" method="post" action="revoke-voucher.php">
                <?= csrfField() ?>
                <input type="hidden" name="voucher_log_id" id="revoke-voucher-id">
                <div class="modal-body">
                    <p>Are you sure you want to revoke voucher <strong id="revoke-voucher-code"></strong>?</p>
                    <div class="mb-3">
                        <label for="revoke_reason" class="form-label">Reason for revoking (required)</label>
                        <textarea class="form-control" id="revoke_reason" name="revoke_reason" 
                                  rows="3" required placeholder="Enter reason for revoking this voucher..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        This action cannot be undone. The voucher will be marked as revoked and will no longer be valid.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Revoke Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const revokeModal = new bootstrap.Modal(document.getElementById('revokeModal'));
    const revokeBtns = document.querySelectorAll('.revoke-btn');
    
    revokeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const voucherId = this.dataset.voucherId;
            const voucherCode = this.dataset.voucherCode;
            
            document.getElementById('revoke-voucher-id').value = voucherId;
            document.getElementById('revoke-voucher-code').textContent = voucherCode;
            
            revokeModal.show();
        });
    });
});
</script>

<?php require_once '../../includes/components/footer.php'; ?>
