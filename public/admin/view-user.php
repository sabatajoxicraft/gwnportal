<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    redirect(BASE_URL . '/admin/users.php', 'Invalid user ID', 'danger');
}

$conn = getDbConnection();

// Get user details with role information
$stmt = safeQueryPrepare($conn, "SELECT u.*, r.name as role_name 
                              FROM users u 
                              JOIN roles r ON u.role_id = r.id 
                              WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    redirect(BASE_URL . '/admin/users.php', 'User not found', 'danger');
}

// Get user's accommodations (if any)
$accommodations = [];
$accom_stmt = safeQueryPrepare($conn, "SELECT a.* FROM accommodations a 
                                   JOIN user_accommodation ua ON a.id = ua.accommodation_id 
                                   WHERE ua.user_id = ?");
$accom_stmt->bind_param("i", $user_id);
$accom_stmt->execute();
$accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user's devices (if student)
$devices = [];
if ($user['role_name'] === 'student') {
    $device_stmt = safeQueryPrepare($conn, "SELECT * FROM user_devices WHERE user_id = ?");
    $device_stmt->bind_param("i", $user_id);
    $device_stmt->execute();
    $devices = $device_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get user's activity logs (recent logins, etc.)
$activity_logs = [];
// This would typically come from an activity_logs table
// Using placeholder data for now
$activity_logs = [
    [
        'action' => 'Login',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'details' => 'Successful login from IP: 192.168.1.1'
    ],
    [
        'action' => 'Password Change',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-1 month')),
        'details' => 'User changed password'
    ]
];

// Set page title
$pageTitle = "User Details";
$activePage = "users";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/users.php">Users</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($user['username']) ?></li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
        <div>
            <a href="edit-user.php?id=<?= $user_id ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit User
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mx-auto mb-3">
                            <span class="avatar-initials">
                                <?= substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1) ?>
                            </span>
                        </div>
                        <h4 class="mb-0"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                        <p class="text-muted mb-2"><?= htmlspecialchars($user['username']) ?></p>
                        <span class="badge <?= getRoleBadgeClass($user['role_name']) ?>">
                            <?= ucfirst($user['role_name']) ?>
                        </span>
                        <span class="badge <?= getStatusBadgeClass($user['status']) ?> ms-1">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong><i class="bi bi-envelope me-2"></i> Email:</strong>
                            <span class="float-end"><?= htmlspecialchars($user['email']) ?></span>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="bi bi-telephone me-2"></i> Phone:</strong>
                            <span class="float-end"><?= htmlspecialchars($user['phone_number'] ?: 'Not set') ?></span>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="bi bi-whatsapp me-2"></i> WhatsApp:</strong>
                            <span class="float-end"><?= htmlspecialchars($user['whatsapp_number'] ?: 'Not set') ?></span>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="bi bi-chat me-2"></i> Preferred Contact:</strong>
                            <span class="float-end"><?= htmlspecialchars($user['preferred_communication'] ?: 'SMS') ?></span>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="bi bi-calendar me-2"></i> Created:</strong>
                            <span class="float-end"><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="bi bi-clock-history me-2"></i> Last Updated:</strong>
                            <span class="float-end"><?= date('M j, Y', strtotime($user['updated_at'])) ?></span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer">
                    <div class="d-grid gap-2">
                        <a href="edit-user.php?id=<?= $user_id ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil"></i> Edit Information
                        </a>
                        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                            <i class="bi bi-key"></i> Reset Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?php if ($user['role_name'] === 'owner'): ?>
                            Owned Accommodations
                        <?php else: ?>
                            Associated Accommodations
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($accommodations) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($accommodations as $accommodation): ?>
                                <a href="view-accommodation.php?id=<?= $accommodation['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($accommodation['name']) ?></h6>
                                        <small><?= date('M j, Y', strtotime($accommodation['created_at'])) ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No accommodations associated with this user.</p>
                    <?php endif; ?>
                </div>
                <?php if ($user['role_name'] === 'manager' || $user['role_name'] === 'student'): ?>
                    <div class="card-footer">
                        <a href="assign-accommodation.php?user_id=<?= $user_id ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-building-add"></i> Assign to Accommodation
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($user['role_name'] === 'student' && count($devices) > 0): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Registered Devices</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Device Type</th>
                                    <th>MAC Address</th>
                                    <th>Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($devices as $device): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($device['device_type']) ?></td>
                                        <td><code><?= htmlspecialchars($device['mac_address']) ?></code></td>
                                        <td><?= date('M j, Y', strtotime($device['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (count($activity_logs) > 0): ?>
                        <div class="timeline">
                            <?php foreach ($activity_logs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-0">
                                            <?= htmlspecialchars($log['action']) ?>
                                            <small class="text-muted"><?= date('M j, Y H:i', strtotime($log['timestamp'])) ?></small>
                                        </h6>
                                        <p class="mb-0 small"><?= htmlspecialchars($log['details']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No recent activity recorded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="reset-password.php" method="post">
                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Choose how to reset the password for <strong><?= htmlspecialchars($user['username']) ?></strong>:</p>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="reset_type" id="generatePassword" value="generate" checked>
                        <label class="form-check-label" for="generatePassword">
                            Generate random password
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="reset_type" id="specifyPassword" value="specify">
                        <label class="form-check-label" for="specifyPassword">
                            Specify new password
                        </label>
                    </div>
                    
                    <div id="password-fields" class="d-none">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="send_email" name="send_email" checked>
                        <label class="form-check-label" for="send_email">
                            Send new password to user by email
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.avatar-initials {
    color: white;
    font-size: 2.5rem;
    line-height: 1;
    font-weight: bold;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 6px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background-color: #0d6efd;
}

.timeline-item:not(:last-child):before {
    content: '';
    position: absolute;
    left: -23px;
    top: 21px;
    height: calc(100% - 21px);
    width: 2px;
    background-color: #dee2e6;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle password reset form
    const radios = document.querySelectorAll('input[name="reset_type"]');
    const passwordFields = document.getElementById('password-fields');
    
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'specify') {
                passwordFields.classList.remove('d-none');
                document.getElementById('new_password').required = true;
                document.getElementById('confirm_password').required = true;
            } else {
                passwordFields.classList.add('d-none');
                document.getElementById('new_password').required = false;
                document.getElementById('confirm_password').required = false;
            }
        });
    });
});
</script>

<?php
// Helper functions
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active': return 'bg-success';
        case 'pending': return 'bg-warning';
        case 'inactive': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

require_once '../../includes/components/footer.php';
?>
