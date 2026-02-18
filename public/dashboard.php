<?php
/**
 * Dashboard Page
 * Role-based dashboard with activity feeds and statistics
 * 
 * Refactored to use service-oriented architecture:
 * - PermissionHelper for access control
 * - QueryService for dashboards data
 * - AccommodationService for accommodation details
 * - StudentService for student statistics
 * - DeviceManagementService for device info
 * - ActivityLogger for activity tracking
 * - ActivityDashboardWidget for rendering
 */

// Include page template (provides $conn, $currentUserId, $currentUserRole)
include '../includes/page-template.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = "Dashboard";
$activePage = "dashboard";
$currentRole = $_SESSION['user_role'] ?? 'student';

// Handle role-based access and redirect
switch ($currentRole) {
    case 'admin':
        // Admin redirects to admin dashboard
        header('Location: admin/dashboard.php');
        exit;
        
    case 'manager':
        // Manager must have accommodation assigned
        if (empty($_SESSION['accommodation_id'])) {
            header('Location: manager-setup.php');
            exit;
        }
        $accommodationId = $_SESSION['accommodation_id'];
        break;
        
    case 'owner':
        // Owner must have created accommodation(s)
        $accommodations = QueryService::getUserAccommodations($conn, $_SESSION['user_id'], 'owner');
        if (empty($accommodations)) {
            header('Location: owner-setup.php');
            exit;
        }
        break;
        
    case 'student':
        // Student must be registered
        $student = StudentService::getStudentRecord($conn, $_SESSION['user_id']);
        if (!$student) {
            header('Location: onboard.php');
            exit;
        }
        $_SESSION['accommodation_id'] = $student['accommodation_id'];
        $_SESSION['student_id'] = $student['id'];
        // Redirect to dedicated student dashboard
        header('Location: student/dashboard.php');
        exit;
        
    default:
        header('Location: login.php');
        exit;
}

// Handle accommodation switching for managers
if ($currentRole === 'manager' && isset($_GET['switch_accommodation'])) {
    $newAccommodationId = (int)($_GET['switch_accommodation'] ?? 0);
    
    // Verify manager has access to this accommodation
    $managerAccommodations = QueryService::getUserAccommodations($conn, $_SESSION['user_id'], 'manager');
    $hasAccess = false;
    
    foreach ($managerAccommodations as $accom) {
        if ($accom['id'] == $newAccommodationId) {
            $hasAccess = true;
            break;
        }
    }
    
    if ($hasAccess) {
        $_SESSION['accommodation_id'] = $newAccommodationId;
        $_SESSION['accommodation_name'] = null; // Will be fetched below
        header('Location: dashboard.php');
        exit;
    }
}

// Fetch dashboard data based on role
$dashboardData = [];

switch ($currentRole) {
    case 'manager':
        $dashboardData = getDashboardDataManager($conn, $_SESSION['user_id'], $accommodationId);
        break;
        
    case 'owner':
        $dashboardData = getDashboardDataOwner($conn, $_SESSION['user_id']);
        break;
        
    case 'student':
        $dashboardData = getDashboardDataStudent($conn, $_SESSION['user_id']);
        break;
}

/**
 * Get dashboard data for manager
 */
function getDashboardDataManager($conn, $userId, $accommodationId) {
    $data = [];
    
    // Get accommodation details
    $accommodation = AccommodationService::getAccommodation($conn, $accommodationId);
    $data['accommodation'] = $accommodation;
    
    // Get student statistics
    $data['students'] = [
        'total' => countStudentsByStatus($conn, $accommodationId),
        'active' => countStudentsByStatus($conn, $accommodationId, 'active'),
        'pending' => countStudentsByStatus($conn, $accommodationId, 'pending'),
        'inactive' => countStudentsByStatus($conn, $accommodationId, 'inactive'),
    ];
    
    // Get recent students
    $data['recentStudents'] = getRecentStudents($conn, $accommodationId, 5);
    
    // Get code statistics
    $data['codes'] = [
        'total' => countCodes($conn, $userId),
        'unused' => countCodes($conn, $userId, 'unused'),
    ];
    
    // Get manager's accommodations (for switching)
    $data['accommodations'] = QueryService::getUserAccommodations($conn, $userId, 'manager');
    
    // Get recent activity
    $data['recentActivity'] = ActivityLogger::getAccommodationActivityLog($accommodationId, 10, 0);
    
    return $data;
}

/**
 * Get dashboard data for owner
 */
function getDashboardDataOwner($conn, $userId) {
    $data = [];
    
    // Get accommodations owned by this user
    $data['accommodations'] = QueryService::getUserAccommodations($conn, $userId, 'owner');
    
    if (empty($data['accommodations'])) {
        $data['accommodations'] = [];
    }
    
    // Calculate statistics across all accommodations
    $totalStudents = 0;
    $totalManagers = 0;
    $totalDevices = 0;
    
    foreach ($data['accommodations'] as $accommodation) {
        $totalStudents += countStudentsByStatus($conn, $accommodation['id']);
        $totalManagers += countAccommodationManagers($conn, $accommodation['id']);
        $totalDevices += countAccommodationDevices($conn, $accommodation['id']);
    }
    
    $data['stats'] = [
        'accommodations' => count($data['accommodations']),
        'students' => $totalStudents,
        'managers' => $totalManagers,
        'devices' => $totalDevices,
    ];
    
    // Get recent activity across all accommodations
    $data['recentActivity'] = ActivityLogger::getAllActivityLogs([], 10, 0);
    
    return $data;
}

/**
 * Get dashboard data for student
 */
function getDashboardDataStudent($conn, $userId) {
    $data = [];
    
    // Get student details
    $student = StudentService::getStudentRecord($conn, $userId);
    $data['student'] = $student;

    // Get user details (for profile photo)
    $data['user'] = QueryService::getUserWithRole($conn, $userId);
    
    // Get accommodation details
    if ($student && isset($student['accommodation_id'])) {
        $data['accommodation'] = AccommodationService::getAccommodation($conn, $student['accommodation_id']);
    }
    
    // Get registered devices
    $data['devices'] = DeviceManagementService::getUserDevices($conn, $userId);
    
    // Get recent student activity
    $data['recentActivity'] = ActivityLogger::getActivityLog($userId, 5, 0);
    
    return $data;
}

// Helper functions
function countStudentsByStatus($conn, $accommodationId, $status = null) {
    $query = "SELECT COUNT(*) as count FROM students WHERE accommodation_id = ?";
    $params = [$accommodationId];
    $types = "i";
    
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) return 0;
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)($result['count'] ?? 0);
}

function getRecentStudents($conn, $accommodationId, $limit = 5) {
    $query = "SELECT s.id, s.status, s.created_at, s.room_number,
              u.first_name, u.last_name, u.email,
              COUNT(DISTINCT d.id) as device_count
              FROM students s
              JOIN users u ON s.user_id = u.id
              LEFT JOIN user_devices d ON d.user_id = u.id
              WHERE s.accommodation_id = ?
              GROUP BY s.id, s.status, s.created_at, s.room_number, u.first_name, u.last_name, u.email
              ORDER BY s.created_at DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) return [];
    
    $stmt->bind_param("ii", $accommodationId, $limit);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $students ?: [];
}

function countCodes($conn, $userId, $status = null) {
    $query = "SELECT COUNT(*) as count FROM onboarding_codes WHERE created_by = ?";
    $params = [$userId];
    $types = "i";
    
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) return 0;
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)($result['count'] ?? 0);
}

function countAccommodationManagers($conn, $accommodationId) {
    $query = "SELECT COUNT(*) as count FROM user_accommodation WHERE accommodation_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) return 0;
    
    $stmt->bind_param("i", $accommodationId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)($result['count'] ?? 0);
}

function countAccommodationDevices($conn, $accommodationId) {
    $query = "SELECT COUNT(*) as count FROM user_devices d
              JOIN students s ON d.user_id = s.user_id
              WHERE s.accommodation_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) return 0;
    
    $stmt->bind_param("i", $accommodationId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)($result['count'] ?? 0);
}

require_once '../includes/components/header.php';
?>

<div class="container-fluid py-4">
    <?php if ($currentRole === 'manager'): ?>
        <!-- Manager Dashboard -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-1">
                    <i class="bi bi-speedometer2"></i> Manager Dashboard
                </h1>
                <p class="text-muted mb-3">
                    <?= htmlspecialchars($dashboardData['accommodation']['name'] ?? 'Accommodation', ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>
        
        <!-- Accommodation Switcher (if multiple) -->
        <?php if (count($dashboardData['accommodations']) > 1): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="btn-group" role="group">
                        <?php foreach ($dashboardData['accommodations'] as $accom): ?>
                            <a href="?switch_accommodation=<?= $accom['id'] ?>" 
                               class="btn btn-outline-primary<?= ($accom['id'] == $accommodationId) ? ' active' : '' ?>">
                                <?= htmlspecialchars($accom['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Active Students</h5>
                        <h2 class="text-primary"><?= $dashboardData['students']['active'] ?></h2>
                        <small class="text-muted">/<?= $dashboardData['students']['total'] ?> total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Pending Students</h5>
                        <h2 class="text-warning"><?= $dashboardData['students']['pending'] ?></h2>
                        <small class="text-muted">Awaiting activation</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Codes Available</h5>
                        <h2 class="text-success"><?= $dashboardData['codes']['unused'] ?></h2>
                        <small class="text-muted">/<?= $dashboardData['codes']['total'] ?> total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">
                            <a href="accommodations.php" class="text-decoration-none">Manage</a>
                        </h5>
                        <small><a href="students.php" class="btn btn-sm btn-outline-primary">View Students</a></small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Students -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Recent Students</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Room</th>
                                    <th>Status</th>
                                    <th>Devices</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboardData['recentStudents'] as $student): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($student['room_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($student['status'] === 'active') ? 'success' : (($student['status'] === 'pending') ? 'warning' : 'danger') ?>">
                                                <?= ucfirst($student['status']) ?>
                                            </span>
                                        </td>
                                        <td><small><?= $student['device_count'] ?> device(s)</small></td>
                                        <td>
                                            <a href="student-details.php?student_id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($dashboardData['recentActivity'])): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach (array_slice($dashboardData['recentActivity'], 0, 10) as $activity): ?>
                                    <li class="list-group-item">
                                        <small class="text-muted"><?= htmlspecialchars($activity['action_type'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></small>
                                        <br>
                                        <small><?= htmlspecialchars($activity['action_details'] ?? 'No details', ENT_QUOTES, 'UTF-8') ?></small>
                                        <br>
                                        <small class="text-muted"><?= $activity['created_at'] ?? 'N/A' ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($currentRole === 'owner'): ?>
        <!-- Owner Dashboard -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-3"><i class="bi bi-building"></i> Owner Dashboard</h1>
            </div>
        </div>
        
        <!-- Overview Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Accommodations</h5>
                        <h2 class="text-primary"><?= $dashboardData['stats']['accommodations'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Total Students</h5>
                        <h2 class="text-success"><?= $dashboardData['stats']['students'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Managers</h5>
                        <h2 class="text-info"><?= $dashboardData['stats']['managers'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Devices</h5>
                        <h2 class="text-warning"><?= $dashboardData['stats']['devices'] ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Accommodations List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list"></i> Your Accommodations</h5>
                        <a href="create-accommodation.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus"></i> New Accommodation
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($dashboardData['accommodations'])): ?>
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>Students</th>
                                        <th>Managers</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardData['accommodations'] as $accom): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($accom['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                            <td><?= htmlspecialchars($accom['address'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= countStudentsByStatus($conn, $accom['id']) ?></td>
                                            <td><?= countAccommodationManagers($conn, $accom['id']) ?></td>
                                            <td><span class="badge bg-success">Active</span></td>
                                            <td>
                                                <a href="view-accommodation.php?id=<?= $accom['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit-accommodation.php?id=<?= $accom['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <p>No accommodations yet. <a href="create-accommodation.php">Create one now</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($currentRole === 'student'): ?>
        <!-- Student Dashboard -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-3"><i class="bi bi-person-circle"></i> Student Dashboard</h1>
            </div>
        </div>
        
        <!-- Student Info -->
        <?php if (!empty($dashboardData['student'])): ?>
            <?php
                $studentPhotoUrl = '';
                if (!empty($dashboardData['user']['profile_photo'])) {
                    $relativePath = ltrim($dashboardData['user']['profile_photo'], '/');
                    $publicPath = PUBLIC_PATH . '/' . $relativePath;
                    if (file_exists($publicPath)) {
                        $studentPhotoUrl = BASE_URL . '/' . $relativePath;
                    }
                }
                $studentDisplayName = trim((string)($dashboardData['user']['first_name'] ?? ''));
                $studentLastName = trim((string)($dashboardData['user']['last_name'] ?? ''));
                if ($studentLastName !== '') {
                    $studentDisplayName .= ($studentDisplayName !== '' ? ' ' : '') . $studentLastName;
                }
                if ($studentDisplayName === '') {
                    $studentDisplayName = $_SESSION['user_name'] ?? 'Student';
                }
            ?>
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Registration</h5>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <?php if (!empty($studentPhotoUrl)): ?>
                                    <img src="<?= htmlspecialchars($studentPhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="Student photo"
                                         style="width: 56px; height: 56px; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-light"
                                         style="width: 56px; height: 56px; border-radius: 50%;">
                                        <i class="bi bi-person" aria-hidden="true"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($studentDisplayName, ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-muted small">Student</div>
                                </div>
                            </div>
                            <p class="mb-1"><strong>Status:</strong></p>
                            <p class="mb-3">
                                <span class="badge bg-<?= ($dashboardData['student']['status'] === 'active') ? 'success' : 'warning' ?>">
                                    <?= ucfirst($dashboardData['student']['status']) ?>
                                </span>
                            </p>
                            <p class="mb-1"><strong>Room:</strong></p>
                            <p class="mb-0"><?= htmlspecialchars($dashboardData['student']['room_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Devices</h5>
                            <p class="mb-1"><strong>Registered:</strong></p>
                            <h3 class="text-primary mb-3"><?= count($dashboardData['devices']) ?></h3>
                            <a href="student-details.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-phone"></i> Manage Devices
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Accommodation</h5>
                            <p class="mb-1"><strong>Location:</strong></p>
                            <p class="mb-0">
                                <?= htmlspecialchars($dashboardData['accommodation']['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($dashboardData['devices'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-wifi"></i> Request Your WiFi Voucher</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3 text-muted">
                                You do not have WiFi access yet. Request your voucher first to get online.
                            </p>
                            <a href="student/request-voucher.php" class="btn btn-primary">
                                <i class="bi bi-ticket-perforated me-1"></i> Request Voucher
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Registered Devices -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-phone"></i> Your Devices</h5>
                            <a href="student-details.php?action=add_device" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus"></i> Add Device
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Device Name</th>
                                        <th>MAC Address</th>
                                        <th>Added</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardData['devices'] as $device): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($device['device_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><code><?= htmlspecialchars($device['mac_address'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></code></td>
                                            <td><small><?= $device['created_at'] ?? 'N/A' ?></small></td>
                                            <td>
                                                <form method="POST" action="student-details.php" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_device">
                                                    <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this device?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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
        
    <?php endif; ?>
</div>

<?php require_once '../includes/components/footer.php'; ?>
