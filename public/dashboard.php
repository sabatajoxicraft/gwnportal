<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure database connection is available
$conn = getDbConnection();

// Require login (redirects to login page if not logged in)
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php', 'Please login to access your dashboard', 'warning');
}

// Get user role and set appropriate title
$userRole = $_SESSION['user_role'] ?? '';

// Redirect admin users to admin dashboard
if ($userRole === 'admin') {
    redirect(BASE_URL . '/admin/dashboard.php');
}

// Get user ID
$userId = $_SESSION['user_id'] ?? 0;

// Check if manager/owner needs assignment before accessing dashboard
if ($userRole === 'manager') {
    // Check if manager has an assigned accommodation
    $accommodationId = $_SESSION['accommodation_id'] ?? null;
    
    if (!$accommodationId) {
        // Try to get accommodation from database
        $stmtAcc = safeQueryPrepare($conn, "SELECT ua.accommodation_id FROM user_accommodation ua WHERE ua.user_id = ? LIMIT 1");
        if ($stmtAcc) {
            $stmtAcc->bind_param("i", $userId);
            $stmtAcc->execute();
            $rowAcc = $stmtAcc->get_result()->fetch_assoc();
            
            if ($rowAcc) {
                $accommodationId = (int)$rowAcc['accommodation_id'];
                $_SESSION['accommodation_id'] = $accommodationId;
            } else {
                // No accommodation assigned - redirect to assignment page
                redirect(BASE_URL . '/manager-setup.php', 'You need to be assigned to an accommodation before accessing your dashboard.', 'warning');
            }
        }
    }
} elseif ($userRole === 'owner') {
    // Check if owner has created any accommodations
    $stmtAccom = safeQueryPrepare($conn, "SELECT COUNT(*) as count FROM accommodations WHERE owner_id = ?");
    if ($stmtAccom) {
        $stmtAccom->bind_param("i", $userId);
        $stmtAccom->execute();
        $result = $stmtAccom->get_result()->fetch_assoc();
        
        if ($result['count'] === 0) {
            // No accommodations - redirect to setup
            redirect(BASE_URL . '/owner-setup.php', 'You need to create an accommodation first.', 'warning');
        }
    }
}

$pageTitle = ucfirst($userRole) . " Dashboard";
$activePage = "dashboard";

// Initialize variables for dashboard stats
$stats = [];
$recentActivity = [];


// Role-specific dashboard data
switch ($userRole) {
    case 'owner':
        // Owner dashboard stats
        $stmt = safeQueryPrepare($conn, "SELECT COUNT(*) as count 
                                         FROM accommodations WHERE owner_id = ?");
        if ($stmt === false) {
            $error = "Unable to load dashboard data. Please try again later.";
        } else {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stats = $stmt->get_result()->fetch_assoc();
        }
        
        // Get accommodations for this owner
        $stmt_accom = safeQueryPrepare($conn, "SELECT * FROM accommodations WHERE owner_id = ?");
        if ($stmt_accom === false) {
            $error = "Unable to load accommodations. Please try again later.";
        } else {
            $stmt_accom->bind_param("i", $userId);
            $stmt_accom->execute();
            $accommodations = $stmt_accom->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        break;

    case 'manager':
        // Manager dashboard stats
        
        // Handle accommodation switch if requested
        if (isset($_GET['switch_accommodation']) && !empty($_GET['switch_accommodation'])) {
            $requestedAccomId = (int)$_GET['switch_accommodation'];
            
            // Verify manager has access to this accommodation
            $verifyStmt = safeQueryPrepare($conn, "SELECT accommodation_id FROM user_accommodation WHERE user_id = ? AND accommodation_id = ?");
            $verifyStmt->bind_param("ii", $userId, $requestedAccomId);
            $verifyStmt->execute();
            if ($verifyStmt->get_result()->num_rows > 0) {
                $_SESSION['accommodation_id'] = $requestedAccomId;
                $_SESSION['manager_id'] = $requestedAccomId;
                redirect(BASE_URL . '/dashboard.php', 'Switched accommodation successfully', 'success');
            }
        }
        
        // Get all accommodations for this manager
        $managerAccommodations = [];
        $stmtAllAccom = safeQueryPrepare($conn, "SELECT a.id, a.name FROM accommodations a 
                                                  JOIN user_accommodation ua ON a.id = ua.accommodation_id 
                                                  WHERE ua.user_id = ? ORDER BY a.name");
        if ($stmtAllAccom) {
            $stmtAllAccom->bind_param("i", $userId);
            $stmtAllAccom->execute();
            $managerAccommodations = $stmtAllAccom->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        $accommodationId = $_SESSION['accommodation_id'] ?? $_SESSION['manager_id'] ?? 0;
        if (!$accommodationId && count($managerAccommodations) > 0) {
            // Use the first accommodation if none is selected
            $accommodationId = $managerAccommodations[0]['id'];
            $_SESSION['accommodation_id'] = $accommodationId;
            $_SESSION['manager_id'] = $accommodationId;
        }

        // Get current accommodation details
        $stmt = safeQueryPrepare($conn, "SELECT * FROM accommodations WHERE id = ?");
        if ($stmt === false) {
            $error = "Unable to load accommodation data. Please try again later.";
        } else {
            $stmt->bind_param("i", $accommodationId);
            $stmt->execute();
            $accommodation = $stmt->get_result()->fetch_assoc();
        }
        
        // Count students
        $stmt_students = safeQueryPrepare($conn, "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                                FROM students WHERE accommodation_id = ?");
        if ($stmt_students === false) {
            $error = "Unable to load student data. Please try again later.";
        } else {
            $stmt_students->bind_param("i", $accommodationId);
            $stmt_students->execute();
            $stats = $stmt_students->get_result()->fetch_assoc();
        }
        
        // Get recent students with user details
        $stmt_recent = safeQueryPrepare($conn, "SELECT s.id, s.status, s.created_at, u.first_name, u.last_name, u.email
                                            FROM students s
                                            JOIN users u ON s.user_id = u.id
                                            WHERE s.accommodation_id = ?
                                            ORDER BY s.created_at DESC LIMIT 5");
        if ($stmt_recent === false) {
            $error = "Unable to load recent students. Please try again later.";
        } else {
            $stmt_recent->bind_param("i", $accommodationId);
            $stmt_recent->execute();
            $recentStudents = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        // Count codes - Updated query to use the correct column
        $stmt_codes = safeQueryPrepare($conn, "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused
                                FROM onboarding_codes WHERE created_by = ?");
        if ($stmt_codes === false) {
            $error = "Unable to load code data. Please try again later.";
        } else {
            $stmt_codes->bind_param("i", $userId); // Use the user ID instead of manager_id
            $stmt_codes->execute();
            $codeStats = $stmt_codes->get_result()->fetch_assoc();
        }
        break;
        
    case 'student':
        // Student dashboard stats
        $student_id = $_SESSION['student_id'] ?? 0;
        
        // Get student details including accommodation and user info
        $stmt_student = safeQueryPrepare($conn, "SELECT s.*, a.id as accommodation_id, a.name as accommodation_name,
                                            u.first_name, u.last_name, u.username, u.email, u.phone_number
                                            FROM students s 
                                            JOIN accommodations a ON s.accommodation_id = a.id 
                                            JOIN users u ON s.user_id = u.id
                                            WHERE s.user_id = ?");
        if ($stmt_student !== false) {
            $stmt_student->bind_param("i", $userId);
            $stmt_student->execute();
            $student_result = $stmt_student->get_result();
            if ($student_result->num_rows > 0) {
                $user = $student_result->fetch_assoc();
                // Set session variables for student name and username
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['username'] = $user['username'];
            }
        }
        
        // Handle wifi request submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_wifi'])) {
            // Get student's accommodation id
            $accommodation_id = $user['accommodation_id'] ?? 0;
            if ($accommodation_id) {
                // Get a manager for that accommodation
                $stmt_mgr = safeQueryPrepare($conn,
                    "SELECT u.* FROM users u 
                     JOIN user_accommodation ua ON u.id = ua.user_id 
                     WHERE ua.accommodation_id = ? AND u.role_id = 3 LIMIT 1");
                if ($stmt_mgr !== false) {
                    $stmt_mgr->bind_param("i", $accommodation_id);
                    $stmt_mgr->execute();
                    $manager = $stmt_mgr->get_result()->fetch_assoc();
                    if ($manager) {
                        // Prepare the notification message
                        $message = "Student " . $_SESSION['user_name'] . " (" . $_SESSION['username'] . ") has requested WiFi access.";
                        
                        // Insert notification
                        $stmt_notif = safeQueryPrepare($conn,
                          "INSERT INTO notifications (recipient_id, sender_id, message, type, read_status, created_at) 
                           VALUES (?, ?, ?, ?, 0, NOW())");
                        if ($stmt_notif !== false) {
                            $type = "wifi_request";
                            $stmt_notif->bind_param("iiss", $manager['id'], $userId, $message, $type);
                            $stmt_notif->execute();
                        }
                        
                        // Simulate sending SMS or WhatsApp based on manager's preference
                        if ($manager['preferred_communication'] === 'WhatsApp' && function_exists('sendWhatsapp')) {
                            sendWhatsapp($manager['whatsapp_number'], $message);
                        } elseif (function_exists('sendSms')) {
                            sendSms($manager['phone_number'], $message);
                        }
                        
                        // Create a dashboard alert for the student
                        $_SESSION['dashboard_alert'] = "Your WiFi access request has been sent to your accommodation manager.";
                        
                        // Log activity
                        logActivity($conn, $userId, "WiFi Request", "Student requested WiFi access.");
                    }
                }
            }
        }
        
        // Get vouchers
        $stmt = safeQueryPrepare($conn, "SELECT * FROM voucher_logs 
                                     WHERE user_id = ? 
                                     ORDER BY sent_at DESC");
        if ($stmt === false) {
            $error = "Unable to load voucher data. Please try again later.";
        } else {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $vouchers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        break;
}

require_once '../includes/components/header.php';
?>

<?php if (isset($_SESSION['dashboard_alert'])): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>
        <?= $_SESSION['dashboard_alert'] ?>
    </div>
    <?php unset($_SESSION['dashboard_alert']); ?>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h2 class="mb-0">Welcome to your Dashboard<?= isset($_SESSION['user_name']) ? ', ' . $_SESSION['user_name'] : '' ?></h2>
                    <p class="text-muted">Here's an overview of your <?= ucfirst($userRole) ?> account</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($userRole === 'owner'): ?>
        <!-- Owner Dashboard Content -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?= $stats['count'] ?? 0 ?></h5>
                        <p class="mb-0">My Accommodations</p>
                        <div class="icon"><i class="bi bi-building"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <?php
                            // Count all manager users assigned to accommodations owned by this owner
                            $stmt = safeQueryPrepare($conn,
                                "SELECT COUNT(DISTINCT ua.user_id) AS total
                                 FROM user_accommodation ua
                                 JOIN accommodations a ON ua.accommodation_id = a.id
                                 JOIN users u ON ua.user_id = u.id
                                 JOIN roles r ON u.role_id = r.id
                                 WHERE a.owner_id = ? AND r.name = 'manager'"
                            );
                            $total_managers = 0;
                            if ($stmt !== false) {
                                $stmt->bind_param("i", $userId);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($row = $result->fetch_assoc()) {
                                    $total_managers = $row['total'];
                                }
                            }
                        ?>
                        <h5 class="card-title"><?= $total_managers ?></h5>
                        <p class="mb-0">My Managers</p>
                        <div class="icon"><i class="bi bi-people"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        My Accommodations
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (isset($accommodations) && count($accommodations) > 0): ?>
                            <?php foreach ($accommodations as $accommodation): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?= htmlspecialchars($accommodation['name']) ?></h5>
                                        <a href="<?= BASE_URL ?>/edit-accommodation.php?id=<?= $accommodation['id'] ?>" class="btn btn-sm btn-outline-primary">Manage</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item">No accommodations found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        My Managers
                    </div>
                    <div class="list-group list-group-flush">
                        <?php
                        // Get distinct managers for this owner
                        $distinct_managers = [];
                        $stmt_distinct = safeQueryPrepare($conn,
                            "SELECT DISTINCT u.id, u.username, u.first_name, u.last_name, u.status,
                                    COUNT(DISTINCT ua.accommodation_id) AS accommodation_count
                             FROM users u
                             JOIN roles r ON u.role_id = r.id
                             JOIN user_accommodation ua ON u.id = ua.user_id
                             JOIN accommodations a ON ua.accommodation_id = a.id
                             WHERE a.owner_id = ? AND r.name = 'manager'
                             GROUP BY u.id, u.username, u.first_name, u.last_name, u.status
                             ORDER BY u.first_name, u.last_name");
                        if ($stmt_distinct !== false) {
                            $stmt_distinct->bind_param("i", $userId);
                            $stmt_distinct->execute();
                            $distinct_managers = $stmt_distinct->get_result()->fetch_all(MYSQLI_ASSOC);
                        }
                        ?>
                        <?php if (!empty($distinct_managers)): ?>
                            <?php foreach ($distinct_managers as $manager): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?></h5>
                                        <span class="badge <?= $manager['status'] === 'active' ? 'bg-success' : 'bg-warning' ?>">
                                            <?= ucfirst($manager['status']) ?>
                                        </span>
                                    </div>
                                    <p class="mb-1">
                                        <small class="text-muted">
                                            <i class="bi bi-person-badge"></i> <?= htmlspecialchars($manager['username']) ?>
                                            <span class="ms-2"><i class="bi bi-buildings"></i> Managing <?= $manager['accommodation_count'] ?> accommodation<?= $manager['accommodation_count'] !== 1 ? 's' : '' ?></span>
                                        </small>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item">
                                <p class="mb-1">No managers found</p>
                                <a href="managers.php" class="btn btn-sm btn-primary mt-2">Add Managers</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($distinct_managers)): ?>
                        <div class="card-footer">
                            <a href="managers.php" class="btn btn-sm btn-primary">Manage All Managers</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    <?php elseif ($userRole === 'manager'): ?>
        <!-- Manager Dashboard Content with colorful cards -->
        
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="dashboard-card dashboard-card-manager">
                    <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                        <div>Accommodation Details</div>
                        <i class="bi bi-building-fill"></i>
                    </div>
                    <div class="card-body">
                        <h5 class="p-2 m-2"><?= htmlspecialchars($accommodation['name'] ?? 'No accommodation assigned') ?></h5>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">Student Statistics</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <h5><?= $stats['total'] ?? 0 ?></h5>
                                <p class="text-muted">Total Students</p>
                            </div>
                            <div class="col-md-3">
                                <h5><?= $stats['active'] ?? 0 ?></h5>
                                <p class="text-muted">Active Students</p>
                            </div>
                            <div class="col-md-3">
                                <h5><?= $stats['pending'] ?? 0 ?></h5>
                                <p class="text-muted">Pending Students</p>
                            </div>
                            <div class="col-md-3">
                                <h5><?= $stats['inactive'] ?? 0 ?></h5>
                                <p class="text-muted">Inactive Students</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Voucher Code Statistics -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>Voucher Code Statistics</div>
                        <i class="bi bi-qr-code"></i>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-light rounded">
                                    <h3 class="text-primary"><?= $codeStats['total'] ?? 0 ?></h3>
                                    <p class="mb-0">Total Codes Generated</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-light rounded">
                                    <h3 class="text-success"><?= $codeStats['unused'] ?? 0 ?></h3>
                                    <p class="mb-0">Available Codes</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-light rounded">
                                    <h3 class="text-info"><?= ($codeStats['total'] ?? 0) - ($codeStats['unused'] ?? 0) ?></h3>
                                    <p class="mb-0">Used Codes</p>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <a href="<?= BASE_URL ?>/codes.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle me-1"></i> Generate New Codes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Quick Actions</div>
                    <div class="list-group list-group-flush">
                        <a href="<?= BASE_URL ?>/students.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people me-2"></i> Manage Students
                        </a>
                        <a href="<?= BASE_URL ?>/codes.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-qr-code me-2"></i> Onboarding Codes
                        </a>
                        <a href="<?= BASE_URL ?>/send-vouchers.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-wifi me-2"></i> Send WiFi Vouchers
                        </a>
                        <a href="<?= BASE_URL ?>/export-students.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-file-earmark-excel me-2"></i> Export Student Data
                        </a>
                        <a href="<?= BASE_URL ?>/onboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-plus me-2"></i> Onboard New Manager
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent students -->
        <?php if (isset($recentStudents) && count($recentStudents) > 0): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">Recent Students</div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentStudents as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                        <td><?= htmlspecialchars($student['email']) ?></td>
                                        <td>
                                            <span class="badge <?= $student['status'] == 'active' ? 'bg-success' : ($student['status'] == 'pending' ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= ucfirst($student['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($student['created_at'])) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/student-details.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php elseif ($userRole === 'student'): ?>
        <!-- Student Dashboard Content with colorful cards -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="dashboard-card dashboard-card-student">
                    <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                        <div>Your WiFi Vouchers</div>
                        <i class="bi bi-wifi"></i>
                    </div>
                    <?php if (isset($vouchers) && count($vouchers) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Voucher Code</th>
                                        <th>Month</th>
                                        <th>Sent Via</th>
                                        <th>Sent At</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vouchers as $voucher): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($voucher['voucher_code']) ?></strong></td>
                                            <td><?= htmlspecialchars($voucher['voucher_month']) ?></td>
                                            <td><?= htmlspecialchars($voucher['sent_via']) ?></td>
                                            <td><?= $voucher['sent_at'] ? date('M j, Y H:i', strtotime($voucher['sent_at'])) : 'Pending' ?></td>
                                            <td>
                                                <span class="badge <?= ($voucher['status'] === 'sent' ? 'bg-success' : ($voucher['status'] === 'failed' ? 'bg-danger' : 'bg-warning')) ?>">
                                                    <?= ucfirst($voucher['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                You don't have any vouchers yet. Please contact your accommodation manager if you need WiFi access.
                            </div>
                            <!-- New Request WiFi Access Form -->
                            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                <input type="hidden" name="request_wifi" value="1">
                                <button type="submit" class="btn btn-primary">Request WiFi Access</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card dashboard-card-student">
                    <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                        <div>Account Actions</div>
                        <i class="bi bi-gear"></i>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="<?= BASE_URL ?>/profile.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person me-2"></i> Update Profile
                        </a>
                        <a href="<?= BASE_URL ?>/update_details.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-phone me-2"></i> Update Contact Details
                        </a>
                        <a href="<?= BASE_URL ?>/help.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-question-circle me-2"></i> Help & Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/components/footer.php'; ?>