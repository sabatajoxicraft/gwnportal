<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$pageTitle = "Student Dashboard";
$activePage = "student-dashboard";

// Ensure the user is logged in as student
requireRole('student');

$userId = $_SESSION['user_id'] ?? 0;
$studentId = $_SESSION['student_id'] ?? 0;

$conn = getDbConnection();

// If student_id not in session, look it up from user_id
if ($studentId === 0 && $userId > 0) {
    $stmtLookup = safeQueryPrepare($conn, "SELECT id FROM students WHERE user_id = ?");
    $stmtLookup->bind_param("i", $userId);
    $stmtLookup->execute();
    $result = $stmtLookup->get_result()->fetch_assoc();
    if ($result) {
        $studentId = $result['id'];
        $_SESSION['student_id'] = $studentId;
    }
}

// Get student personal information
$stmt = safeQueryPrepare($conn, "SELECT s.*, u.username, u.email as user_email, u.phone_number, u.whatsapp_number,
                                 CONCAT(u.first_name, ' ', u.last_name) as full_name,
                                 a.name as accommodation_name
                          FROM students s
                          INNER JOIN users u ON s.user_id = u.id
                          LEFT JOIN accommodations a ON s.accommodation_id = a.id
                          WHERE s.id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get device summary
$stmt_devices = safeQueryPrepare($conn, "SELECT COUNT(*) as total,
                                         COUNT(*) as approved,
                                         0 as pending,
                                         0 as rejected
                                  FROM user_devices
                                  WHERE user_id = ?");
$stmt_devices->bind_param("i", $userId);
$stmt_devices->execute();
$deviceStats = $stmt_devices->get_result()->fetch_assoc();

// Get recent devices
$stmt_recent_devices = safeQueryPrepare($conn, "SELECT device_type, mac_address, created_at
                                        FROM user_devices
                                        WHERE user_id = ?
                                        ORDER BY created_at DESC
                                        LIMIT 5");
$stmt_recent_devices->bind_param("i", $userId);
$stmt_recent_devices->execute();
$recentDevices = $stmt_recent_devices->get_result()->fetch_all(MYSQLI_ASSOC);

// Get voucher history
$stmt_vouchers = safeQueryPrepare($conn, "SELECT voucher_code, voucher_month, sent_via, status, sent_at
                                  FROM voucher_logs
                                  WHERE user_id = ?
                                  ORDER BY sent_at DESC
                                  LIMIT 5");
$stmt_vouchers->bind_param("i", $userId);
$stmt_vouchers->execute();
$voucherHistory = $stmt_vouchers->get_result()->fetch_all(MYSQLI_ASSOC);

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

include '../../includes/components/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Student Dashboard</h1>
            
            <?php include '../../includes/components/messages.php'; ?>
            
            <!-- Personal Information Card -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-student text-white">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?= htmlEscape($student['full_name']) ?></p>
                                    <p><strong>Student Number:</strong> <?= htmlEscape($student['username']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlEscape($student['email'] ?? $student['user_email']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Phone:</strong> <?= htmlEscape($student['phone_number'] ?? 'Not set') ?></p>
                                    <p><strong>WhatsApp:</strong> <?= htmlEscape($student['whatsapp_number'] ?? 'Not set') ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?= $student['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= htmlEscape(ucfirst($student['status'])) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <p><strong>Accommodation:</strong> <?= htmlEscape($student['accommodation_name'] ?? 'Not assigned') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-student text-white">
                            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="request-voucher.php" class="btn btn-outline-warning">
                                    <i class="fas fa-wifi me-2"></i>Request WiFi Voucher
                                </a>
                                <a href="profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-edit me-2"></i>Update Profile
                                </a>
                                <a href="request-device.php" class="btn btn-outline-success">
                                    <i class="fas fa-plus-circle me-2"></i>Request Device Authorization
                                </a>
                                <a href="devices.php" class="btn btn-outline-info">
                                    <i class="fas fa-laptop me-2"></i>View My Devices
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Device Summary -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header bg-student text-white">
                            <h5 class="mb-0"><i class="fas fa-laptop me-2"></i>My Devices</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-md-3">
                                    <h3><?= htmlEscape($deviceStats['total']) ?></h3>
                                    <p class="text-muted">Total Devices</p>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-success"><?= htmlEscape($deviceStats['approved']) ?></h3>
                                    <p class="text-muted">Approved</p>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-warning"><?= htmlEscape($deviceStats['pending']) ?></h3>
                                    <p class="text-muted">Pending</p>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-danger"><?= htmlEscape($deviceStats['rejected']) ?></h3>
                                    <p class="text-muted">Rejected</p>
                                </div>
                            </div>
                            
                            <?php if (count($recentDevices) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Device Name</th>
                                                <th>MAC Address</th>
                                                <th>Status</th>
                                                <th>Added</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentDevices as $device): ?>
                                                <tr>
                                                    <td><?= htmlEscape($device['device_type']) ?></td>
                                                    <td><code><?= htmlEscape($device['mac_address']) ?></code></td>
                                                    <td>
                                                        <span class="badge bg-success">
                                                            Active
                                                        </span>
                                                    </td>
                                                    <td><?= htmlEscape(date('Y-m-d H:i', strtotime($device['created_at']))) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end">
                                    <a href="devices.php" class="btn btn-sm btn-outline-primary">View All Devices</a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>No devices registered yet. 
                                    <a href="request-device.php" class="alert-link">Request your first device</a>.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Voucher History -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header bg-student text-white">
                            <h5 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Voucher History</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($voucherHistory) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Voucher Code</th>
                                                <th>Month</th>
                                                <th>Sent Via</th>
                                                <th>Status</th>
                                                <th>Date Sent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($voucherHistory as $voucher): ?>
                                                <tr>
                                                    <td><code><?= htmlEscape($voucher['voucher_code']) ?></code></td>
                                                    <td><?= htmlEscape(formatVoucherMonth($voucher['voucher_month'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= htmlEscape(ucfirst($voucher['sent_via'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $voucher['status'] === 'sent' ? 'success' : 'secondary' ?>">
                                                            <?= htmlEscape(ucfirst($voucher['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlEscape(date('Y-m-d H:i', strtotime($voucher['sent_at']))) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>No voucher history available.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/components/footer.php'; ?>
