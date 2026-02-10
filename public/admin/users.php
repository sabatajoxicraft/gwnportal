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

$where_clause = !empty($sql_where) ? "WHERE " . implode(" AND ", $sql_where) : "";

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$sql = "SELECT u.*, r.name as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        $where_clause
        ORDER BY u.created_at DESC
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
$roles_stmt = safeQueryPrepare($conn, "SELECT * FROM roles ORDER BY name");
$roles_stmt->execute();
$roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "Manage Users";
$activePage = "users";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
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
                    <label for="role" class="form-label">Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['name'] ?>" <?= $role_filter === $role['name'] ? 'selected' : '' ?>>
                                <?= ucfirst($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="users.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">User List</h5>
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
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
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
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($user['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
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
                                                        <?php if ($user['status'] !== 'active'): ?>
                                                            <button type="submit" name="status" value="active" class="dropdown-item text-success">
                                                                Activate User
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($user['status'] !== 'inactive'): ?>
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
                                <td colspan="7" class="text-center py-4">
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
                            <a class="page-link" href="?page=<?= $page-1 ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>">Next</a>
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
