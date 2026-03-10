<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfToken();
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($_POST['action'] === 'status' && !empty($_POST['status'])) {
        // Update user status
        $status = $_POST['status'];
        $conn = getDbConnection();
        $stmt = safeQueryPrepare($conn, "UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $user_id);
        
        if ($stmt->execute()) {
            redirect(BASE_URL . '/admin/users.php', "User status updated successfully", "success");
        } else {
            redirect(BASE_URL . '/admin/users.php', "Failed to update user status", "danger");
        }
    }
}

// Get user filter
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search_query = trim($_GET['q'] ?? '');
$sort_by = $_GET['sort'] ?? 'newest';

$allowed_sorts = [
    'newest' => 'u.created_at DESC',
    'oldest' => 'u.created_at ASC',
    'name_asc' => 'u.first_name ASC, u.last_name ASC',
    'name_desc' => 'u.first_name DESC, u.last_name DESC',
    'username_asc' => 'u.username ASC',
    'username_desc' => 'u.username DESC',
];

if (!array_key_exists($sort_by, $allowed_sorts)) {
    $sort_by = 'newest';
}

// Prepare SQL based on filters
$conn = getDbConnection();
$sql_where = [];
$params = [];
$types = "";

if ($role_filter !== 'all') {
    $sql_where[] = "r.name = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($status_filter !== 'all') {
    $sql_where[] = "u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search_query !== '') {
    $sql_where[] = "(
        u.first_name LIKE ? OR
        u.last_name LIKE ? OR
        CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
        u.username LIKE ? OR
        u.email LIKE ? OR
        u.phone_number LIKE ? OR
        u.whatsapp_number LIKE ?
    )";

    $search_wildcard = '%' . $search_query . '%';
    for ($i = 0; $i < 7; $i++) {
        $params[] = $search_wildcard;
        $types .= "s";
    }
}

$where_clause = !empty($sql_where) ? "WHERE " . implode(" AND ", $sql_where) : "";

// Get users with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$sql = "SELECT u.*, u.status AS user_status, r.name as role_name, COUNT(DISTINCT ud.id) as device_count
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        LEFT JOIN user_devices ud ON ud.user_id = u.id
        $where_clause
        GROUP BY u.id, u.username, u.first_name, u.last_name, u.email, u.password, u.role_id, u.status, u.created_at, u.phone_number, u.whatsapp_number, u.preferred_communication, r.name
    ORDER BY {$allowed_sorts[$sort_by]}
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
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users u 
             JOIN roles r ON u.role_id = r.id 
             $where_clause";

if (!empty($types)) {
    $count_stmt = safeQueryPrepare($conn, $count_sql);
    $types = rtrim($types, "ii"); // Remove the last two integer types used for LIMIT/OFFSET
    if (!empty($types)) {
        $count_params = array_slice($params, 0, -2); // Remove LIMIT/OFFSET values
        $count_stmt->bind_param($types, ...$count_params);
    }
    $count_stmt->execute();
    $total_users = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_users = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_users / $items_per_page);

// Get all roles for filter
$roles_stmt = safeQueryPrepare($conn, "SELECT id, NAME AS name FROM roles ORDER BY NAME");
$roles_stmt->execute();
$roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "Manage Users";
$activePage = "users";

// Preserve current filters/search/sort in links.
$query_params = [
    'role' => $role_filter,
    'status' => $status_filter,
    'q' => $search_query,
    'sort' => $sort_by,
];

function buildQueryString(array $params): string {
    return http_build_query(array_filter($params, static fn($value) => $value !== '' && $value !== null));
}

// Include header
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php require_once '../../includes/components/messages.php'; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Users</h2>
        <a href="create-user.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Add New User
        </a>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Users</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="q" class="form-label">Search User</label>
                    <input
                        type="text"
                        name="q"
                        id="q"
                        class="form-control"
                        value="<?= htmlspecialchars($search_query) ?>"
                        placeholder="Name, username, email, or phone"
                    >
                </div>
                <div class="col-md-4">
                    <label for="role" class="form-label">Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <?php $role_name = (string)($role['name'] ?? $role['NAME'] ?? ''); ?>
                            <option value="<?= $role_name ?>" <?= $role_filter === $role_name ? 'selected' : '' ?>>
                                <?= ucfirst($role_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort" class="form-label">Sort</label>
                    <select name="sort" id="sort" class="form-select">
                        <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                        <option value="username_asc" <?= $sort_by === 'username_asc' ? 'selected' : '' ?>>Username (A-Z)</option>
                        <option value="username_desc" <?= $sort_by === 'username_desc' ? 'selected' : '' ?>>Username (Z-A)</option>
                    </select>
                </div>
                <div class="col-12 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="users.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <h5 class="mb-0">User List</h5>
                <small class="text-muted">
                    Showing <?= count($users) ?> of <?= (int)$total_users ?> users
                    <?php if ($search_query !== ''): ?>
                        for "<?= htmlspecialchars($search_query) ?>"
                    <?php endif; ?>
                </small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Devices</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <?php $userStatus = strtolower((string)($user['user_status'] ?? ($user['status'] ?? 'inactive'))); ?>
                                <tr>
                                    <td><?= $user['first_name'] . ' ' . $user['last_name'] ?></td>
                                    <td><?= $user['username'] ?></td>
                                    <td><?= $user['email'] ?></td>
                                    <td>
                                        <?php if ($user['role_name'] === 'admin'): ?>
                                            <span class="badge bg-danger"><?= ucfirst($user['role_name']) ?></span>
                                        <?php elseif ($user['role_name'] === 'owner'): ?>
                                            <span class="badge bg-info"><?= ucfirst($user['role_name']) ?></span>
                                        <?php elseif ($user['role_name'] === 'manager'): ?>
                                            <span class="badge bg-primary"><?= ucfirst($user['role_name']) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= ucfirst($user['role_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($userStatus === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($userStatus === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['device_count'] > 0): ?>
                                            <span class="badge bg-primary" title="<?= $user['device_count'] ?> device(s) registered">
                                                <i class="bi bi-router"></i> <?= $user['device_count'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="user<?= $user['id'] ?>Actions" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="user<?= $user['id'] ?>Actions">
                                                <li><a class="dropdown-item" href="edit-user.php?id=<?= $user['id'] ?>">Edit</a></li>
                                                <li><a class="dropdown-item" href="view-user.php?id=<?= $user['id'] ?>">View Details</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="" method="post" class="dropdown-item p-0" style="cursor: pointer;">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="action" value="status">
                                                        <?php if ($userStatus !== 'active'): ?>
                                                            <button type="submit" name="status" value="active" class="dropdown-item text-success">
                                                                Activate User
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($userStatus !== 'inactive'): ?>
                                                            <button type="submit" name="status" value="inactive" class="dropdown-item text-danger">
                                                                Deactivate User
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <p>No users found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString(array_merge($query_params, ['page' => $page - 1])) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= buildQueryString(array_merge($query_params, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString(array_merge($query_params, ['page' => $page + 1])) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../../includes/components/footer.php';
?>
