<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Note: This is a placeholder as you might need to create an activity_logs table
// For now, let's use a dummy implementation with sample data

// Filter variables
$filter_user = $_GET['user'] ?? 'all';
$filter_action = $_GET['action'] ?? 'all';
$filter_date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$filter_date_to = $_GET['date_to'] ?? date('Y-m-d');

// In a real implementation, you'd fetch data from the database
$activity_logs = [
    [
        'id' => 1,
        'action' => 'User Login',
        'user' => 'admin',
        'user_id' => 1,
        'ip_address' => '127.0.0.1',
        'details' => 'Admin login successful',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
    ],
    [
        'id' => 2,
        'action' => 'User Created',
        'user' => 'admin',
        'user_id' => 1,
        'ip_address' => '127.0.0.1',
        'details' => 'Created new owner: Thabo Mokoena',
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
    ],
    [
        'id' => 3,
        'action' => 'Accommodation Added',
        'user' => 'Thabo Mokoena',
        'user_id' => 2,
        'ip_address' => '127.0.0.1',
        'details' => 'Added new accommodation: Thuto Residence',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ],
    [
        'id' => 4,
        'action' => 'Code Generated',
        'user' => 'admin',
        'user_id' => 1,
        'ip_address' => '127.0.0.1',
        'details' => 'Generated 5 onboarding codes for Lesedi Lodge',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
    ],
    [
        'id' => 5,
        'action' => 'User Login',
        'user' => 'thabo',
        'user_id' => 2,
        'ip_address' => '127.0.0.1',
        'details' => 'Owner login successful',
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
    ]
];

// Get activity logs - Update the query to join with the users table
$stmt = safeQueryPrepare($conn, "SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.username 
                               FROM activity_log al
                               LEFT JOIN users u ON al.user_id = u.id
                               ORDER BY al.timestamp DESC
                               LIMIT ? OFFSET ?");

if ($stmt !== false) {
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Set page title
$pageTitle = "Activity Log";
$activePage = "activity-log";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>System Activity Log</h2>
        <div>
            <button class="btn btn-outline-secondary me-2" id="refresh-log">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <a href="#" class="btn btn-outline-primary">
                <i class="bi bi-download"></i> Export Log
            </a>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Activity Log</h5>
        </div>
        <div class="card-body">
            <form class="row g-3" method="get">
                <div class="col-md-3">
                    <label for="user" class="form-label">User</label>
                    <select name="user" id="user" class="form-select">
                        <option value="all" <?= $filter_user === 'all' ? 'selected' : '' ?>>All Users</option>
                        <option value="admin" <?= $filter_user === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="thabo" <?= $filter_user === 'thabo' ? 'selected' : '' ?>>Thabo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="action" class="form-label">Action Type</label>
                    <select name="action" id="action" class="form-select">
                        <option value="all" <?= $filter_action === 'all' ? 'selected' : '' ?>>All Actions</option>
                        <option value="login" <?= $filter_action === 'login' ? 'selected' : '' ?>>Login</option>
                        <option value="create" <?= $filter_action === 'create' ? 'selected' : '' ?>>Create</option>
                        <option value="update" <?= $filter_action === 'update' ? 'selected' : '' ?>>Update</option>
                        <option value="delete" <?= $filter_action === 'delete' ? 'selected' : '' ?>>Delete</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $filter_date_from ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $filter_date_to ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
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
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= date('M j, Y g:i A', strtotime($log['timestamp'])) ?></td>
                                <td>
                                    <?php if (!empty($log['user_name'])): ?>
                                        <?= htmlspecialchars($log['user_name']) ?> 
                                        <small class="text-muted">(<?= htmlspecialchars($log['username']) ?>)</small>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td><?= htmlspecialchars($log['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <nav aria-label="Activity log pagination">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </ul>
            </nav>
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
