<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require manager login
requireManagerLogin();

$accommodation_id = $_SESSION['accommodation_id'] ?? $_SESSION['manager_id'] ?? 0;
$conn = getDbConnection();

// Get student ID from query string
$student_id = $_GET['id'] ?? 0;

// Verify student belongs to this manager and fetch user details
$stmt = $conn->prepare("SELECT s.id, s.status, s.created_at, s.user_id, u.first_name, u.last_name, u.email, u.phone_number, u.whatsapp_number, u.preferred_communication
                        FROM students s
                        JOIN users u ON s.user_id = u.id
                        WHERE s.id = ? AND s.accommodation_id = ?");
$stmt->bind_param("ii", $student_id, $accommodation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect(BASE_URL . '/students.php', 'Student not found or does not belong to your accommodation.', 'danger');
}

$student = $result->fetch_assoc();

// Get voucher history
$stmt_vouchers = $conn->prepare("SELECT * FROM voucher_logs WHERE user_id = ? ORDER BY sent_at DESC");
$stmt_vouchers->bind_param("i", $student['user_id']);
$stmt_vouchers->execute();
$vouchers = $stmt_vouchers->get_result()->fetch_all(MYSQLI_ASSOC);

// Get devices registered for this student
$devices = [];
$stmt_devices = $conn->prepare("SELECT device_type, mac_address FROM user_devices WHERE user_id = ?");
if ($stmt_devices) {
    $stmt_devices->bind_param("i", $student['user_id']);
    $stmt_devices->execute();
    $devices = $stmt_devices->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Replace the direct CSS include and HTML header with this:
$pageTitle = "Student Details";
require_once '../includes/components/header.php';
?>
<!-- Rest of your HTML content -->

    <div class="container mt-4">
        <?php displayFlashMessage(); ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Student Details</h2>
            <div>
                <a href="send-voucher.php?id=<?= $student_id ?>" class="btn btn-success me-2">
                    <i class="bi bi-send"></i> Send Voucher
                </a>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Students
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Personal Information</h5>
                        <span class="badge <?php 
                            if ($student['status'] == 'active') echo 'bg-success';
                            elseif ($student['status'] == 'pending') echo 'bg-warning';
                            else echo 'bg-danger';
                        ?>"><?= ucfirst($student['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Full Name</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= htmlspecialchars($student['first_name']) ?> <?= htmlspecialchars($student['last_name']) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Email</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= htmlspecialchars($student['email']) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Phone Number</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= htmlspecialchars($student['phone_number']) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">WhatsApp Number</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= htmlspecialchars($student['whatsapp_number'] ?? 'Not provided') ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Preferred Communication</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= $student['preferred_communication'] ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3 row">
                            <label class="col-sm-4 col-form-label">Registration Date</label>
                            <div class="col-sm-8">
                                <p class="form-control-plaintext"><?= date('M j, Y', strtotime($student['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group">
                            <?php if ($student['status'] != 'active'): ?>
                                <a href="students.php?action=activate&id=<?= $student_id ?>" class="btn btn-outline-success">
                                    <i class="bi bi-check-circle"></i> Activate
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($student['status'] != 'inactive'): ?>
                                <a href="students.php?action=deactivate&id=<?= $student_id ?>" class="btn btn-outline-warning">
                                    <i class="bi bi-pause-circle"></i> Deactivate
                                </a>
                            <?php endif; ?>
                            
                            <a href="students.php?action=delete&id=<?= $student_id ?>" class="btn btn-outline-danger" 
                               onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Device Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($devices)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Device Type</th>
                                            <th>MAC Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($devices as $device): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($device['device_type']) ?></td>
                                                <td class="font-monospace"><?= htmlspecialchars($device['mac_address']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No devices recorded for this student.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Voucher History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($vouchers) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Voucher Code</th>
                                            <th>Sent Via</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vouchers as $voucher): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($voucher['voucher_month']) ?></td>
                                                <td class="font-monospace"><?= htmlspecialchars($voucher['voucher_code']) ?></td>
                                                <td><?= $voucher['sent_via'] ?></td>
                                                <td><?= $voucher['sent_at'] ? date('M j, Y', strtotime($voucher['sent_at'])) : 'Pending' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No vouchers have been sent to this student yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once '../includes/components/footer.php'; ?>
