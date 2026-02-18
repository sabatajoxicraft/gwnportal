<?php
/**
 * Unified Codes List Page
 * Consolidates public/codes.php and public/admin/codes.php
 * 
 * Supports both admin and manager roles:
 * - Admin: Sees all codes with accommodation filter and advanced features
 * - Manager: Sees only codes for their accommodation
 */
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require manager or admin role
requireRole(['manager', 'admin']);

$conn = getDbConnection();
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$userId = $_SESSION['user_id'] ?? 0;
$accommodation_id = $_SESSION['accommodation_id'] ?? 0;

$message = '';
$message_type = '';

// Handle code actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfToken();
    $code_id = $_POST['code_id'] ?? ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && $code_id > 0) {
        if ($isAdmin) {
            $stmt = safeQueryPrepare($conn, "DELETE FROM onboarding_codes WHERE id = ? AND status = 'unused'");
            $stmt->bind_param("i", $code_id);
        } else {
            $stmt = safeQueryPrepare($conn, "DELETE FROM onboarding_codes WHERE id = ? AND created_by = ? AND status = 'unused'");
            $stmt->bind_param("ii", $code_id, $userId);
        }
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Code deleted successfully.";
            $message_type = "success";
        } else {
            $message = "Failed to delete code or code is already used.";
            $message_type = "danger";
        }
    } elseif ($action === 'expire' && $code_id > 0 && $isAdmin) {
        $stmt = safeQueryPrepare($conn, "UPDATE onboarding_codes SET status = 'expired' WHERE id = ? AND status = 'unused'");
        $stmt->bind_param("i", $code_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Code marked as expired.";
            $message_type = "success";
        } else {
            $message = "Failed to expire code or code is already used.";
            $message_type = "danger";
        }
    }
}

// Handle GET-based delete for manager (legacy support)
if (!$isAdmin && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $code_id = $_GET['id'];
    $stmt = safeQueryPrepare($conn, "DELETE FROM onboarding_codes WHERE id = ? AND created_by = ? AND status = 'unused'");
    $stmt->bind_param("ii", $code_id, $userId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        redirect(BASE_URL . '/codes/', 'Code deleted successfully.', 'success');
    } else {
        redirect(BASE_URL . '/codes/', 'Code could not be deleted or does not exist.', 'danger');
    }
}

// Update expired codes
$update_expired = safeQueryPrepare($conn, "UPDATE onboarding_codes SET status = 'expired' 
                                          WHERE expires_at < NOW() AND status = 'unused'");
if ($update_expired) {
    $update_expired->execute();
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$status_filter = $_GET['status'] ?? $filter; // Support both naming conventions
$accommodation_filter = $_GET['accommodation'] ?? 'all';
$role_filter = $_GET['role'] ?? 'all';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = $isAdmin ? 15 : 50;
$offset = ($page - 1) * $items_per_page;

// Build query based on role
if ($isAdmin) {
    // Admin query with joins
    $where_clauses = [];
    $params = [];
    $types = "";
    
    if ($status_filter !== 'all') {
        $where_clauses[] = "oc.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if ($accommodation_filter !== 'all') {
        $where_clauses[] = "oc.accommodation_id = ?";
        $params[] = $accommodation_filter;
        $types .= "i";
    }
    
    if ($role_filter !== 'all') {
        $where_clauses[] = "oc.role_id = ?";
        $params[] = $role_filter;
        $types .= "i";
    }
    
    $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $sql = "SELECT oc.*, a.name AS accommodation_name, r.name AS role_name, 
                   u1.first_name AS creator_first_name, u1.last_name AS creator_last_name,
                   u2.first_name AS user_first_name, u2.last_name AS user_last_name  
            FROM onboarding_codes oc
            JOIN accommodations a ON oc.accommodation_id = a.id
            JOIN roles r ON oc.role_id = r.id
            JOIN users u1 ON oc.created_by = u1.id
            LEFT JOIN users u2 ON oc.used_by = u2.id
            $where_clause
            ORDER BY oc.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = safeQueryPrepare($conn, $sql);
    $params[] = $items_per_page;
    $params[] = $offset;
    $types .= "ii";
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $codes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get total for pagination
    $count_sql = "SELECT COUNT(*) as total FROM onboarding_codes oc
                  JOIN accommodations a ON oc.accommodation_id = a.id
                  $where_clause";
    $count_stmt = safeQueryPrepare($conn, $count_sql);
    if (!empty($where_clauses)) {
        $count_types = rtrim($types, "ii");
        $count_params = array_slice($params, 0, -2);
        if (!empty($count_types)) {
            $count_stmt->bind_param($count_types, ...$count_params);
        }
    }
    $count_stmt->execute();
    $total_codes = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_codes / $items_per_page);
    
    // Get accommodations and roles for filters
    $accom_stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations ORDER BY name");
    $accom_stmt->execute();
    $accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $roles_stmt = safeQueryPrepare($conn, "SELECT id, name FROM roles WHERE id > 1 ORDER BY name");
    $roles_stmt->execute();
    $roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} else {
    // Manager query (simple)
    $sql_where = "WHERE oc.accommodation_id = ?";
    if ($status_filter == 'unused' || $filter == 'unused') {
        $sql_where .= " AND oc.status = 'unused'";
    } else if ($status_filter == 'used' || $filter == 'used') {
        $sql_where .= " AND oc.status = 'used'";
    } else if ($status_filter == 'expired' || $filter == 'expired') {
        $sql_where .= " AND oc.status = 'expired'";
    }
    
    $sql = "SELECT oc.*, 
                   u.first_name AS user_first_name, 
                   u.last_name AS user_last_name
            FROM onboarding_codes oc
            LEFT JOIN users u ON oc.used_by = u.id
            $sql_where 
            ORDER BY oc.created_at DESC";
    $stmt = safeQueryPrepare($conn, $sql);
    $codes = [];
    if ($stmt !== false) {
        $stmt->bind_param("i", $accommodation_id);
        $stmt->execute();
        $codes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get stats for manager view
    $stats = ['total' => 0, 'unused' => 0, 'used' => 0, 'expired' => 0];
    $stmt_stats = safeQueryPrepare($conn, "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused,
        SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
        FROM onboarding_codes WHERE accommodation_id = ?");
    
    if ($stmt_stats !== false) {
        $stmt_stats->bind_param("i", $accommodation_id);
        $stmt_stats->execute();
        $result = $stmt_stats->get_result();
        if ($row = $result->fetch_assoc()) {
            $stats = $row;
        }
    }
}

// Set page variables
$pageTitle = $isAdmin ? "Manage Onboarding Codes" : "Voucher Codes";
$activePage = "codes";

// Include header (which includes navigation automatically)
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php if ($isAdmin): ?>
        <?php require_once '../../includes/components/messages.php'; ?>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $isAdmin ? 'Manage Onboarding Codes' : 'Onboarding Codes' ?></h2>
        <a href="<?= BASE_URL ?>/<?= $isAdmin ? 'admin/' : '' ?>create-code.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Create New Code
        </a>
    </div>
    
    <?php if (!$isAdmin): ?>
    <!-- Stats Card (Manager view) -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    <h5>Total Codes</h5>
                    <h2><?= intval($stats['total']) ?></h2>
                </div>
                <div class="col-md-3 text-center">
                    <h5>Active Codes</h5>
                    <h2 class="text-success"><?= intval($stats['unused']) ?></h2>
                </div>
                <div class="col-md-3 text-center">
                    <h5>Used Codes</h5>
                    <h2 class="text-info"><?= intval($stats['used']) ?></h2>
                </div>
                <div class="col-md-3 text-center">
                    <h5>Expired Codes</h5>
                    <h2 class="text-danger"><?= intval($stats['expired']) ?></h2>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($isAdmin): ?>
    <!-- Filter Card (Admin view) -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Codes</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="unused" <?= $status_filter === 'unused' ? 'selected' : '' ?>>Unused</option>
                        <option value="used" <?= $status_filter === 'used' ? 'selected' : '' ?>>Used</option>
                        <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="accommodation" class="form-label">Accommodation</label>
                    <select name="accommodation" id="accommodation" class="form-select">
                        <option value="all" <?= $accommodation_filter === 'all' ? 'selected' : '' ?>>All Accommodations</option>
                        <?php foreach ($accommodations as $accommodation): ?>
                            <option value="<?= $accommodation['id'] ?>" <?= $accommodation_filter == $accommodation['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($accommodation['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="role" class="form-label">Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $role_filter == $role['id'] ? 'selected' : '' ?>>
                                <?= ucfirst($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="<?= BASE_URL ?>/codes/" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Codes Table -->
    <div class="card">
        <?php if (!$isAdmin): ?>
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= ($filter == 'all' || $filter == '') ? 'active' : '' ?>" href="?filter=all">All Codes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter == 'unused' ? 'active' : '' ?>" href="?filter=unused">Active</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter == 'used' ? 'active' : '' ?>" href="?filter=used">Used</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter == 'expired' ? 'active' : '' ?>" href="?filter=expired">Expired</a>
                </li>
            </ul>
        </div>
        <?php else: ?>
        <div class="card-header">
            <h5 class="mb-0">Onboarding Codes</h5>
        </div>
        <?php endif; ?>
        
        <div class="card-body<?= $isAdmin ? ' p-0' : '' ?>">
            <?php if (count($codes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <?php if ($isAdmin): ?>
                                <th>Accommodation</th>
                                <th>Role</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <?php if (!$isAdmin): ?>
                                <th>Student</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($codes as $code): ?>
                                <tr>
                                    <td><?php if ($isAdmin): ?><code><?php else: ?><strong><?php endif; ?><?= htmlspecialchars($code['code']) ?><?php if ($isAdmin): ?></code><?php else: ?></strong><?php endif; ?></td>
                                    <?php if ($isAdmin): ?>
                                    <td><?= htmlspecialchars($code['accommodation_name']) ?></td>
                                    <td>
                                        <?php if ($code['role_name'] === 'manager'): ?>
                                            <span class="badge bg-primary"><?= ucfirst($code['role_name']) ?></span>
                                        <?php elseif ($code['role_name'] === 'student'): ?>
                                            <span class="badge bg-success"><?= ucfirst($code['role_name']) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= ucfirst($code['role_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($code['status'] === 'unused'): ?>
                                            <span class="badge bg-success"><?= $isAdmin ? 'Unused' : 'Active' ?></span>
                                        <?php elseif ($code['status'] === 'used'): ?>
                                            <span class="badge bg-info">Used</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($code['created_at'])) ?></td>
                                    <td><?= $code['expires_at'] ? date('M j, Y', strtotime($code['expires_at'])) : 'Never' ?></td>
                                    <?php if (!$isAdmin): ?>
                                    <td>
                                        <?php if (!empty($code['user_first_name']) || !empty($code['user_last_name'])): ?>
                                            <span class="text-primary">
                                                <i class="bi bi-person-check me-1"></i>
                                                <?= htmlspecialchars(trim($code['user_first_name'] . ' ' . $code['user_last_name'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not used yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($code['status'] === 'unused'): ?>
                                                <button type="button" class="btn btn-outline-primary copy-code" data-code="<?= htmlspecialchars($code['code']) ?>">
                                                    <i class="bi bi-clipboard"></i><?php if (!$isAdmin): ?> Copy<?php endif; ?>
                                                </button>
                                                <?php if ($isAdmin): ?>
                                                <form method="post" style="display:inline;">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="code_id" value="<?= $code['id'] ?>">
                                                    <input type="hidden" name="action" value="expire">
                                                    <button type="submit" class="btn btn-outline-warning" onclick="return confirm('Mark this code as expired?')">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </form>
                                                <form method="post" style="display:inline;">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="code_id" value="<?= $code['id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this code?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <a href="?action=delete&id=<?= $code['id'] ?>" class="btn btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this code?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($isAdmin): ?>
                                                <button type="button" class="btn btn-outline-secondary" disabled>
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($isAdmin && isset($total_pages) && $total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page-1 ?>&status=<?= $status_filter ?>&accommodation=<?= $accommodation_filter ?>&role=<?= $role_filter ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= $status_filter ?>&accommodation=<?= $accommodation_filter ?>&role=<?= $role_filter ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page+1 ?>&status=<?= $status_filter ?>&accommodation=<?= $accommodation_filter ?>&role=<?= $role_filter ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-<?= $isAdmin ? 'ticket-perforated' : 'inbox' ?> text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-<?= $isAdmin ? '0' : '1' ?> <?= $isAdmin ? '' : 'text-muted' ?>">
                        <?php if ($isAdmin): ?>
                            No onboarding codes found matching your criteria
                        <?php else: ?>
                            No codes found
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!$isAdmin): ?>
                        <?php if ($filter == 'all'): ?>
                            <p class="text-muted">You haven't created any onboarding codes yet.</p>
                        <?php elseif ($filter == 'unused'): ?>
                            <p class="text-muted">You don't have any active onboarding codes.</p>
                        <?php elseif ($filter == 'used'): ?>
                            <p class="text-muted">None of your codes have been used yet.</p>
                        <?php else: ?>
                            <p class="text-muted">You don't have any expired codes.</p>
                        <?php endif; ?>
                        
                        <a href="<?= BASE_URL ?>/create-code.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Create New Code
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const copyButtons = document.querySelectorAll('.copy-code');
        copyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const code = this.getAttribute('data-code');
                navigator.clipboard.writeText(code).then(() => {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check<?= $isAdmin ? '-lg' : '' ?>"></i><?= $isAdmin ? '' : ' Copied!' ?>';
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 2000);
                });
            });
        });
    });
</script>

<?php if (!$isAdmin): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<?php require_once '../../includes/components/footer.php'; ?>
