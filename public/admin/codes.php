<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Handle code actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfToken();
    $code_id = $_POST['code_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    $conn = getDbConnection();
    
    if ($action === 'delete' && $code_id > 0) {
        // Delete the code
        $stmt = safeQueryPrepare($conn, "DELETE FROM onboarding_codes WHERE id = ? AND status = 'unused'");
        $stmt->bind_param("i", $code_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            redirect(BASE_URL . '/admin/codes.php', "Code deleted successfully", "success");
        } else {
            redirect(BASE_URL . '/admin/codes.php', "Failed to delete code or code is already used", "danger");
        }
    } elseif ($action === 'expire' && $code_id > 0) {
        // Mark code as expired
        $stmt = safeQueryPrepare($conn, "UPDATE onboarding_codes SET status = 'expired' WHERE id = ? AND status = 'unused'");
        $stmt->bind_param("i", $code_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            redirect(BASE_URL . '/admin/codes.php', "Code marked as expired", "success");
        } else {
            redirect(BASE_URL . '/admin/codes.php', "Failed to expire code or code is already used", "danger");
        }
    }
}

// Get filter criteria
$status_filter = $_GET['status'] ?? 'all';
$accommodation_filter = $_GET['accommodation'] ?? 'all';
$role_filter = $_GET['role'] ?? 'all';

// Build query
$conn = getDbConnection();
$where_clauses = [];
$params = [];
$types = "";

// Add status filter
if ($status_filter !== 'all') {
    $where_clauses[] = "oc.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add accommodation filter
if ($accommodation_filter !== 'all') {
    $where_clauses[] = "oc.accommodation_id = ?";
    $params[] = $accommodation_filter;
    $types .= "i";
}

// Add role filter
if ($role_filter !== 'all') {
    $where_clauses[] = "oc.role_id = ?";
    $params[] = $role_filter;
    $types .= "i";
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get codes with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 15;
$offset = ($page - 1) * $items_per_page;

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

if (!empty($types)) {
    $params[] = $items_per_page;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $items_per_page, $offset);
}

$stmt->execute();
$codes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM onboarding_codes oc
              JOIN accommodations a ON oc.accommodation_id = a.id
              JOIN roles r ON oc.role_id = r.id
              $where_clause";

if (!empty($types)) {
    $count_stmt = safeQueryPrepare($conn, $count_sql);
    $types = rtrim($types, "ii"); // Remove the last two integer types
    if (!empty($types)) {
        $count_params = array_slice($params, 0, -2); // Remove LIMIT/OFFSET values
        $count_stmt->bind_param($types, ...$count_params);
    }
    $count_stmt->execute();
    $total_codes = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_codes = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_codes / $items_per_page);

// Get accommodations and roles for filters
$accommodations = [];
$roles = [];
$accom_stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations ORDER BY name");
$accom_stmt->execute();
$accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$roles_stmt = safeQueryPrepare($conn, "SELECT id, name FROM roles WHERE id > 1 ORDER BY name");
$roles_stmt->execute();
$roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "Manage Onboarding Codes";
$activePage = "codes";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
?>

<div class="container mt-4">
    <?php require_once '../../includes/components/messages.php'; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Onboarding Codes</h2>
        <a href="create-code.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Create New Code
        </a>
    </div>
    
    <!-- Filter Card -->
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
                    <a href="codes.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Codes Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Onboarding Codes</h5>
        </div>
        <div class="card-body p-0">
            <?php if (count($codes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Accommodation</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($codes as $code): ?>
                                <tr>
                                    <td><code><?= $code['code'] ?></code></td>
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
                                    <td>
                                        <?php if ($code['status'] === 'unused'): ?>
                                            <span class="badge bg-success">Unused</span>
                                        <?php elseif ($code['status'] === 'used'): ?>
                                            <span class="badge bg-info">Used</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($code['created_at'])) ?></td>
                                    <td><?= $code['expires_at'] ? date('M j, Y', strtotime($code['expires_at'])) : 'Never' ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($code['status'] === 'unused'): ?>
                                                <button type="button" class="btn btn-outline-primary copy-code" data-code="<?= $code['code'] ?>">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
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
                                                <button type="button" class="btn btn-outline-secondary" disabled>
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
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
                    <i class="bi bi-ticket-perforated text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-0">No onboarding codes found matching your criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copy code to clipboard functionality
    document.querySelectorAll('.copy-code').forEach(button => {
        button.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            navigator.clipboard.writeText(code).then(() => {
                // Change button icon temporarily
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check-lg"></i>';
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 2000);
            });
        });
    });
});
</script>

<?php require_once '../../includes/components/footer.php'; ?>
