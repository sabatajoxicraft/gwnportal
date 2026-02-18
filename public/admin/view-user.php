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
if ($user['role_name'] === 'student') {
    // For students, get accommodation from students table
    $accom_stmt = safeQueryPrepare($conn, "SELECT a.* FROM accommodations a 
                                       JOIN students s ON a.id = s.accommodation_id 
                                       WHERE s.user_id = ?");
    $accom_stmt->bind_param("i", $user_id);
    $accom_stmt->execute();
    $accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // For managers/owners, get from user_accommodation table
    $accom_stmt = safeQueryPrepare($conn, "SELECT a.* FROM accommodations a 
                                       JOIN user_accommodation ua ON a.id = ua.accommodation_id 
                                       WHERE ua.user_id = ?");
    $accom_stmt->bind_param("i", $user_id);
    $accom_stmt->execute();
    $accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get user's devices (if student)
$devices = [];
$device_stats = ['total' => 0, 'blocked' => 0, 'active' => 0];
if ($user['role_name'] === 'student') {
    $device_stmt = safeQueryPrepare($conn, "SELECT ud.*, IFNULL(ud.is_blocked, 0) as is_blocked, 
                                           ud.device_name, ud.linked_via, ud.blocked_reason, ud.blocked_at
                                           FROM user_devices ud 
                                           WHERE ud.user_id = ?
                                           ORDER BY ud.created_at DESC");
    $device_stmt->bind_param("i", $user_id);
    $device_stmt->execute();
    $devices = $device_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $device_stats['total'] = count($devices);
    foreach ($devices as $dev) {
        if ($dev['is_blocked'] == 1) {
            $device_stats['blocked']++;
        } else {
            $device_stats['active']++;
        }
    }
}

// Get user's voucher history (if student)
$vouchers = [];
$voucher_stats = ['total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0];
if ($user['role_name'] === 'student') {
    $voucher_stmt = safeQueryPrepare($conn, "SELECT * FROM voucher_logs 
                                             WHERE user_id = ? 
                                             ORDER BY created_at DESC 
                                             LIMIT 20");
    $voucher_stmt->bind_param("i", $user_id);
    $voucher_stmt->execute();
    $vouchers = $voucher_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get voucher statistics
    $voucher_count_stmt = safeQueryPrepare($conn, "SELECT status, COUNT(*) as count 
                                                   FROM voucher_logs 
                                                   WHERE user_id = ? 
                                                   GROUP BY status");
    $voucher_count_stmt->bind_param("i", $user_id);
    $voucher_count_stmt->execute();
    $voucher_counts = $voucher_count_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($voucher_counts as $vc) {
        $voucher_stats['total'] += $vc['count'];
        $voucher_stats[$vc['status']] = $vc['count'];
    }
}

// Get user's notifications
$notifications = [];
$notification_stats = ['total' => 0, 'unread' => 0];
$notif_stmt = safeQueryPrepare($conn, "SELECT n.*, 
                                       u.first_name as sender_first_name, 
                                       u.last_name as sender_last_name 
                                       FROM notifications n 
                                       LEFT JOIN users u ON n.sender_id = u.id 
                                       WHERE n.recipient_id = ? 
                                       ORDER BY n.created_at DESC 
                                       LIMIT 15");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$notification_stats['total'] = count($notifications);
foreach ($notifications as $notif) {
    if ($notif['read_status'] == 0) {
        $notification_stats['unread']++;
    }
}

// Get real activity logs from database
$activity_logs = [];
$activity_stats = ['total' => 0, 'logins' => 0, 'today' => 0];
$activity_stmt = safeQueryPrepare($conn, "SELECT * FROM activity_log 
                                          WHERE user_id = ? 
                                          ORDER BY timestamp DESC 
                                          LIMIT 50");
$activity_stmt->bind_param("i", $user_id);
$activity_stmt->execute();
$activity_logs = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$activity_stats['total'] = count($activity_logs);
$today = date('Y-m-d');
foreach ($activity_logs as $log) {
    if (stripos($log['action'], 'login') !== false) {
        $activity_stats['logins']++;
    }
    if (date('Y-m-d', strtotime($log['timestamp'])) === $today) {
        $activity_stats['today']++;
    }
}

// Get onboarding code usage (if created by manager/owner or used by student)
$onboarding_codes = [];
if ($user['role_name'] === 'owner' || $user['role_name'] === 'manager') {
    // Codes created by this user
    $code_stmt = safeQueryPrepare($conn, "SELECT oc.*, 
                                          u.first_name as used_by_first_name, 
                                          u.last_name as used_by_last_name,
                                          a.name as accommodation_name
                                          FROM onboarding_codes oc 
                                          LEFT JOIN users u ON oc.used_by = u.id 
                                          LEFT JOIN accommodations a ON oc.accommodation_id = a.id
                                          WHERE oc.created_by = ? 
                                          ORDER BY oc.created_at DESC 
                                          LIMIT 20");
    $code_stmt->bind_param("i", $user_id);
    $code_stmt->execute();
    $onboarding_codes = $code_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else if ($user['role_name'] === 'student') {
    // Code used by this user
    $code_stmt = safeQueryPrepare($conn, "SELECT oc.*, 
                                          u.first_name as created_by_first_name, 
                                          u.last_name as created_by_last_name,
                                          a.name as accommodation_name
                                          FROM onboarding_codes oc 
                                          LEFT JOIN users u ON oc.created_by = u.id 
                                          LEFT JOIN accommodations a ON oc.accommodation_id = a.id
                                          WHERE oc.used_by = ? 
                                          ORDER BY oc.used_at DESC");
    $code_stmt->bind_param("i", $user_id);
    $code_stmt->execute();
    $onboarding_codes = $code_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get student info if student role
$student_info = null;
if ($user['role_name'] === 'student') {
    $student_stmt = safeQueryPrepare($conn, "SELECT * FROM students WHERE user_id = ?");
    $student_stmt->bind_param("i", $user_id);
    $student_stmt->execute();
    $student_info = $student_stmt->get_result()->fetch_assoc();
}

// Set page title
$pageTitle = "User Details";
$activePage = "users";

// Include header
require_once '../../includes/components/header.php';
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
        <div class="d-flex gap-2 align-items-center">
            <a href="edit-user.php?id=<?= $user_id ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit User
            </a>
            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                <i class="bi bi-key"></i> Reset Password
            </button>
            <a href="users.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
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
                        <?php if (!empty($user['profile_photo'])): ?>
                            <div class="avatar-photo mx-auto mb-3">
                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_photo']) ?>" 
                                     alt="Profile photo" 
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="avatar-circle" style="display:none;">
                                    <span class="avatar-initials">
                                        <?= substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1) ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="avatar-circle mx-auto mb-3">
                                <span class="avatar-initials">
                                    <?= substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1) ?>
                                </span>
                            </div>
                        <?php endif; ?>
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
                        <?php if ($student_info): ?>
                            <li class="list-group-item">
                                <strong><i class="bi bi-door-closed me-2"></i> Room Number:</strong>
                                <span class="float-end"><?= htmlspecialchars($student_info['room_number'] ?: 'Not set') ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if ($user['id_number']): ?>
                            <li class="list-group-item">
                                <strong><i class="bi bi-card-text me-2"></i> ID Number:</strong>
                                <span class="float-end"><?= htmlspecialchars($user['id_number']) ?></span>
                            </li>
                        <?php endif; ?>
                        <li class="list-group-item">
                            <strong><i class="bi bi-calendar me-2"></i> Created:</strong>
                            <span class="float-end"><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="bi bi-clock-history me-2"></i> Last Updated:</strong>
                            <span class="float-end"><?= date('M j, Y', strtotime($user['updated_at'])) ?></span>
                        </li>
                        <?php if ($user['password_reset_required']): ?>
                            <li class="list-group-item bg-warning bg-opacity-10">
                                <strong><i class="bi bi-exclamation-triangle me-2"></i> Password Reset Required</strong>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Quick Stats Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Activity Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0 text-primary"><?= $activity_stats['total'] ?></h4>
                                <small class="text-muted">Total Actions</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0 text-success"><?= $activity_stats['logins'] ?></h4>
                                <small class="text-muted">Logins</small>
                            </div>
                        </div>
                        <?php if ($user['role_name'] === 'student'): ?>
                            <div class="col-6">
                                <div class="text-center p-2 border rounded">
                                    <h4 class="mb-0 text-info"><?= $device_stats['total'] ?></h4>
                                    <small class="text-muted">Devices</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 border rounded">
                                    <h4 class="mb-0 text-warning"><?= $voucher_stats['total'] ?></h4>
                                    <small class="text-muted">Vouchers</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0 text-secondary"><?= $notification_stats['total'] ?></h4>
                                <small class="text-muted">Notifications</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0 text-danger"><?= $notification_stats['unread'] ?></h4>
                                <small class="text-muted">Unread</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Accommodations Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?php if ($user['role_name'] === 'owner'): ?>
                            <i class="bi bi-building me-2"></i>Owned Accommodations
                        <?php else: ?>
                            <i class="bi bi-building me-2"></i>Associated Accommodations
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
                <!-- Devices Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-router me-2"></i>Registered Devices (<?= $device_stats['total'] ?>)</h5>
                        <div>
                            <span class="badge bg-success"><?= $device_stats['active'] ?> Active</span>
                            <?php if ($device_stats['blocked'] > 0): ?>
                                <span class="badge bg-danger"><?= $device_stats['blocked'] ?> Blocked</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Device</th>
                                        <th>MAC Address</th>
                                        <th>Linked</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devices as $device): ?>
                                        <tr class="<?= $device['is_blocked'] == 1 ? 'table-danger' : '' ?>">
                                            <td>
                                                <i class="bi bi-<?= getDeviceIcon($device['device_type']) ?> me-2"></i>
                                                <?= htmlspecialchars($device['device_name'] ?: $device['device_type']) ?>
                                                <?php if ($device['linked_via']): ?>
                                                    <br><small class="text-muted">via <?= htmlspecialchars($device['linked_via']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($device['mac_address']) ?></code></td>
                                            <td><?= date('M j, Y', strtotime($device['created_at'])) ?></td>
                                            <td>
                                                <?php if ($device['is_blocked'] == 1): ?>
                                                    <span class="badge bg-danger">Blocked</span>
                                                    <?php if ($device['blocked_reason']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($device['blocked_reason']) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($device['is_blocked'] == 1): ?>
                                                        <button class="btn btn-outline-success" onclick="unblockDevice(<?= $device['id'] ?>, '<?= addslashes($device['mac_address']) ?>')" title="Unblock">
                                                            <i class="bi bi-unlock"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-warning" onclick="blockDevice(<?= $device['id'] ?>, '<?= addslashes($device['mac_address']) ?>')" title="Block">
                                                            <i class="bi bi-slash-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-info" onclick="editDeviceName(<?= $device['id'] ?>, '<?= addslashes($device['device_name'] ?: '') ?>')" title="Edit name">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="unlinkDevice(<?= $device['id'] ?>, '<?= addslashes($device['mac_address']) ?>')" title="Unlink">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($user['role_name'] === 'student' && count($vouchers) > 0): ?>
                <!-- Voucher History Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-ticket-perforated me-2"></i>Voucher History (<?= $voucher_stats['total'] ?>)</h5>
                        <div>
                            <span class="badge bg-success"><?= $voucher_stats['sent'] ?? 0 ?> Sent</span>
                            <span class="badge bg-warning"><?= $voucher_stats['pending'] ?? 0 ?> Pending</span>
                            <?php if (($voucher_stats['failed'] ?? 0) > 0): ?>
                                <span class="badge bg-danger"><?= $voucher_stats['failed'] ?> Failed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Month</th>
                                        <th>Sent Via</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vouchers as $voucher): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($voucher['voucher_code']) ?></code></td>
                                            <td><?= htmlspecialchars($voucher['voucher_month']) ?></td>
                                            <td><i class="bi bi-<?= $voucher['sent_via'] === 'WhatsApp' ? 'whatsapp' : 'phone' ?> me-1"></i><?= htmlspecialchars($voucher['sent_via']) ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'secondary';
                                                if ($voucher['status'] === 'sent') $badge_class = 'success';
                                                elseif ($voucher['status'] === 'failed') $badge_class = 'danger';
                                                elseif ($voucher['status'] === 'pending') $badge_class = 'warning';
                                                ?>
                                                <span class="badge bg-<?= $badge_class ?>"><?= ucfirst($voucher['status']) ?></span>
                                            </td>
                                            <td><?= $voucher['sent_at'] ? date('M j, Y H:i', strtotime($voucher['sent_at'])) : date('M j, Y', strtotime($voucher['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (count($onboarding_codes) > 0): ?>
                <!-- Onboarding Codes Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-key me-2"></i>
                            <?php if ($user['role_name'] === 'student'): ?>
                                Onboarding Code Used
                            <?php else: ?>
                                Onboarding Codes Created (<?= count($onboarding_codes) ?>)
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Accommodation</th>
                                        <?php if ($user['role_name'] === 'student'): ?>
                                            <th>Created By</th>
                                            <th>Used At</th>
                                        <?php else: ?>
                                            <th>Used By</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($onboarding_codes as $code): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($code['code']) ?></code></td>
                                            <td><?= htmlspecialchars($code['accommodation_name']) ?></td>
                                            <?php if ($user['role_name'] === 'student'): ?>
                                                <td><?= htmlspecialchars($code['created_by_first_name'] . ' ' . $code['created_by_last_name']) ?></td>
                                                <td><?= $code['used_at'] ? date('M j, Y H:i', strtotime($code['used_at'])) : '—' ?></td>
                                            <?php else: ?>
                                                <td>
                                                    <?php if ($code['used_by']): ?>
                                                        <?= htmlspecialchars($code['used_by_first_name'] . ' ' . $code['used_by_last_name']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not used</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = 'secondary';
                                                    if ($code['status'] === 'used') $status_class = 'success';
                                                    elseif ($code['status'] === 'expired') $status_class = 'danger';
                                                    elseif ($code['status'] === 'unused') $status_class = 'warning';
                                                    ?>
                                                    <span class="badge bg-<?= $status_class ?>"><?= ucfirst($code['status']) ?></span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($code['created_at'])) ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (count($notifications) > 0): ?>
                <!-- Notifications Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Recent Notifications (<?= $notification_stats['total'] ?>)</h5>
                        <span class="badge bg-danger"><?= $notification_stats['unread'] ?> Unread</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($notifications, 0, 10) as $notif): ?>
                                <div class="list-group-item <?= $notif['read_status'] == 0 ? 'bg-light' : '' ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php if ($notif['read_status'] == 0): ?>
                                                <i class="bi bi-circle-fill text-danger me-1" style="font-size: 0.5rem;"></i>
                                            <?php endif; ?>
                                            <span class="badge bg-<?= getNotificationTypeColor($notif['type']) ?> me-2"><?= htmlspecialchars($notif['type']) ?></span>
                                            From: <?= htmlspecialchars($notif['sender_first_name'] . ' ' . $notif['sender_last_name']) ?>
                                        </h6>
                                        <small><?= date('M j, Y H:i', strtotime($notif['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0 small"><?= htmlspecialchars($notif['message']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Activity Log Section -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Activity Log (Last 50)</h5>
                    <div>
                        <span class="badge bg-primary"><?= $activity_stats['total'] ?> Total</span>
                        <span class="badge bg-info"><?= $activity_stats['today'] ?> Today</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($activity_logs) > 0): ?>
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-sm table-hover">
                                <thead class="sticky-top bg-light">
                                    <tr>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity_logs as $log): ?>
                                        <?php
                                        $action_icon = 'circle-fill';
                                        $action_color = 'secondary';
                                        
                                        if (stripos($log['action'], 'login') !== false) {
                                            $action_icon = 'box-arrow-in-right';
                                            $action_color = 'success';
                                        } elseif (stripos($log['action'], 'logout') !== false) {
                                            $action_icon = 'box-arrow-left';
                                            $action_color = 'warning';
                                        } elseif (stripos($log['action'], 'password') !== false) {
                                            $action_icon = 'key';
                                            $action_color = 'info';
                                        } elseif (stripos($log['action'], 'device') !== false) {
                                            $action_icon = 'router';
                                            $action_color = 'primary';
                                        } elseif (stripos($log['action'], 'voucher') !== false) {
                                            $action_icon = 'ticket-perforated';
                                            $action_color = 'purple';
                                        } elseif (stripos($log['action'], 'block') !== false || stripos($log['action'], 'deny') !== false) {
                                            $action_icon = 'slash-circle';
                                            $action_color = 'danger';
                                        } elseif (stripos($log['action'], 'create') !== false || stripos($log['action'], 'add') !== false) {
                                            $action_icon = 'plus-circle';
                                            $action_color = 'success';
                                        } elseif (stripos($log['action'], 'delete') !== false || stripos($log['action'], 'remove') !== false) {
                                            $action_icon = 'trash';
                                            $action_color = 'danger';
                                        } elseif (stripos($log['action'], 'update') !== false || stripos($log['action'], 'edit') !== false) {
                                            $action_icon = 'pencil';
                                            $action_color = 'info';
                                        }
                                        
                                        $isToday = date('Y-m-d', strtotime($log['timestamp'])) === date('Y-m-d');
                                        ?>
                                        <tr class="<?= $isToday ? 'table-light' : '' ?>">
                                            <td>
                                                <i class="bi bi-<?= $action_icon ?> text-<?= $action_color ?> me-1"></i>
                                                <strong><?= htmlspecialchars($log['action']) ?></strong>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($log['details'] ?: '—') ?></small>
                                            </td>
                                            <td>
                                                <code class="small"><?= htmlspecialchars($log['ip_address'] ?: '—') ?></code>
                                            </td>
                                            <td>
                                                <small><?= date('M j, Y H:i:s', strtotime($log['timestamp'])) ?></small>
                                                <?php if ($isToday): ?>
                                                    <span class="badge bg-info ms-1">Today</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0"><i class="bi bi-info-circle me-2"></i>No activity recorded yet for this user.</p>
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
                <?php echo csrfField(); ?>
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

.avatar-photo {
    width: 100px;
    height: 100px;
    position: relative;
}

.avatar-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #007bff;
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

// Device management functions
function blockDevice(deviceId, macAddress) {
    const reason = prompt('Enter reason for blocking this device:');
    if (reason === null) return; // User cancelled
    
    if (confirm('Are you sure you want to block device ' + macAddress + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/admin/device-actions.php';
        
        const fields = {
            'action': 'block',
            'device_id': deviceId,
            'mac_address': macAddress,
            'reason': reason,
            'user_id': '<?= $user_id ?>',
            '<?= csrfFieldName() ?>': '<?= csrfFieldValue() ?>'
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}

function unblockDevice(deviceId, macAddress) {
    if (confirm('Are you sure you want to unblock device ' + macAddress + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/admin/device-actions.php';
        
        const fields = {
            'action': 'unblock',
            'device_id': deviceId,
            'mac_address': macAddress,
            'user_id': '<?= $user_id ?>',
            '<?= csrfFieldName() ?>': '<?= csrfFieldValue() ?>'
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editDeviceName(deviceId, currentName) {
    const newName = prompt('Enter new device name:', currentName);
    if (newName === null || newName === currentName) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= BASE_URL ?>/admin/device-actions.php';
    
    const fields = {
        'action': 'rename',
        'device_id': deviceId,
        'device_name': newName,
        'user_id': '<?= $user_id ?>',
        '<?= csrfFieldName() ?>': '<?= csrfFieldValue() ?>'
    };
    
    for (const [key, value] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

function unlinkDevice(deviceId, macAddress) {
    if (confirm('Are you sure you want to unlink device ' + macAddress + '? This will remove it from the student\'s account.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/admin/device-actions.php';
        
        const fields = {
            'action': 'unlink',
            'device_id': deviceId,
            'mac_address': macAddress,
            'user_id': '<?= $user_id ?>',
            '<?= csrfFieldName() ?>': '<?= csrfFieldValue() ?>'
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}
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

function getDeviceIcon($deviceType) {
    $type = strtolower($deviceType ?? '');
    if (strpos($type, 'phone') !== false) return 'phone';
    if (strpos($type, 'laptop') !== false || strpos($type, 'computer') !== false) return 'laptop';
    if (strpos($type, 'tablet') !== false) return 'tablet';
    if (strpos($type, 'tv') !== false) return 'tv';
    if (strpos($type, 'watch') !== false) return 'smartwatch';
    if (strpos($type, 'console') !== false || strpos($type, 'gaming') !== false) return 'controller';
    return 'device-hdd';
}

function getNotificationTypeColor($type) {
    $type_lower = strtolower($type ?? '');
    if (strpos($type_lower, 'success') !== false) return 'success';
    if (strpos($type_lower, 'error') !== false || strpos($type_lower, 'danger') !== false) return 'danger';
    if (strpos($type_lower, 'warning') !== false) return 'warning';
    if (strpos($type_lower, 'info') !== false) return 'info';
    if (strpos($type_lower, 'voucher') !== false) return 'primary';
    if (strpos($type_lower, 'device') !== false) return 'secondary';
    return 'primary';
}

require_once '../../includes/components/footer.php';
?>
