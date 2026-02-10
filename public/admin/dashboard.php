<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$pageTitle = "Admin Dashboard";

// Ensure the user is logged in and has admin privileges
requireAdminLogin();

// Get user ID
$userId = $_SESSION['user_id'] ?? 0;

// Initialize variables for dashboard stats
$stats = [];
$error = null;

// Admin dashboard stats
$conn = getDbConnection();
$stmt = safeQueryPrepare($conn, "SELECT COUNT(*) as count, 
                                 (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                                 (SELECT COUNT(*) FROM accommodations) as accommodations,
                                 (SELECT COUNT(*) FROM onboarding_codes) as codes
                              FROM users");
if ($stmt === false) {
    $error = "Unable to load dashboard data. Please try again later.";
} else {
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
}

// Get recent activity - Update to join with users table and limit to 5
$stmt_activity = safeQueryPrepare($conn, "SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.username 
                                 FROM activity_log al
                                 LEFT JOIN users u ON al.user_id = u.id
                                 ORDER BY al.timestamp DESC LIMIT 5");
if ($stmt_activity === false) {
    $error = "Unable to load recent activity. Please try again later.";
} else {
    $stmt_activity->execute();
    $recentActivity = $stmt_activity->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h2 class="mb-0">Admin Dashboard</h2>
                    <p class="text-muted">Here's an overview of your system</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Dashboard Content -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white stat-card">
                <div class="card-body">
                    <h5 class="card-title"><?= $stats['count'] ?? 0 ?></h5>
                    <p class="mb-0">Total Users</p>
                    <div class="icon"><i class="bi bi-people"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white stat-card">
                <div class="card-body">
                    <h5 class="card-title"><?= $stats['active_users'] ?? 0 ?></h5>
                    <p class="mb-0">Active Users</p>
                    <div class="icon"><i class="bi bi-person-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white stat-card">
                <div class="card-body">
                    <h5 class="card-title"><?= $stats['accommodations'] ?? 0 ?></h5>
                    <p class="mb-0">Accommodations</p>
                    <div class="icon"><i class="bi bi-building"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white stat-card">
                <div class="card-body">
                    <h5 class="card-title"><?= $stats['codes'] ?? 0 ?></h5>
                    <p class="mb-0">Onboarding Codes</p>
                    <div class="icon"><i class="bi bi-qr-code"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Quick Actions
                </div>
                <div class="list-group list-group-flush">
                    <a href="<?= BASE_URL ?>/admin/create-accommodation.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-building-add me-2"></i> Add New Accommodation
                    </a>
                    <a href="<?= BASE_URL ?>/admin/create-owner.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-person-plus-fill me-2"></i> Add New Owner
                    </a>
                    <a href="<?= BASE_URL ?>/admin/create-user.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-person-plus me-2"></i> Add New User
                    </a>
                    <a href="<?= BASE_URL ?>/admin/create-code.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-qr-code me-2"></i> Generate Onboarding Code
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Activity</h5>
                    <a href="activity-log.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (count($recentActivity) > 0): ?>
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($activity['action']) ?></h6>
                                    <small><?= date('M j, Y g:i A', strtotime($activity['timestamp'])) ?></small>
                                </div>
                                <p class="mb-1"><?= htmlspecialchars($activity['details']) ?></p>
                                <small>
                                    By: 
                                    <?php if (!empty($activity['user_name'])): ?>
                                        <?= htmlspecialchars($activity['user_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item">No recent activity found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
