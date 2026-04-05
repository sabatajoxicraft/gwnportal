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
require_once '../includes/helpers/ActivityLogHelper.php';

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
        // Store in session for switcher
        $_SESSION['manager_accommodations'] = $accommodations;
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

// Handle accommodation switching for owners
if ($currentRole === 'owner' && isset($_GET['switch_accommodation'])) {
    $newAccommodationId = (int)($_GET['switch_accommodation'] ?? 0);
    
    // Verify owner has access to this accommodation
    $ownerAccommodations = QueryService::getUserAccommodations($conn, $_SESSION['user_id'], 'owner');
    $hasAccess = false;
    
    foreach ($ownerAccommodations as $accom) {
        if ($accom['id'] == $newAccommodationId) {
            $hasAccess = true;
            break;
        }
    }
    
    if ($hasAccess) {
        $_SESSION['current_accommodation'] = ['id' => $newAccommodationId];
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
        'total' => countCodes($conn, $accommodationId),
        'unused' => countCodes($conn, $accommodationId, 'unused'),
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
    $selectedAccommodation = null;
    
    // Get all accommodations owned by this user
    $allAccommodations = QueryService::getUserAccommodations($conn, $userId, 'owner');
    if (!is_array($allAccommodations)) {
        $allAccommodations = [];
    }
    
    if (empty($allAccommodations)) {
        $data['accommodations'] = [];
    } else {
        // Get current accommodation from session or default to first
        $currentAccommodationId = null;
        if (isset($_SESSION['current_accommodation']['id'])) {
            $currentAccommodationId = $_SESSION['current_accommodation']['id'];
        } else {
            $currentAccommodationId = $allAccommodations[0]['id'] ?? null;
            if ($currentAccommodationId) {
                $_SESSION['current_accommodation'] = ['id' => $currentAccommodationId];
            }
        }
        
        // Only return the current accommodation
        if ($currentAccommodationId) {
            $data['accommodations'] = array_values(array_filter($allAccommodations, function($acc) use ($currentAccommodationId) {
                return ($acc['id'] ?? null) === $currentAccommodationId;
            }));
        } else {
            $data['accommodations'] = [];
        }
        
        // If current accommodation is invalid, reset to first
        if (empty($data['accommodations']) && !empty($allAccommodations)) {
            $firstId = $allAccommodations[0]['id'] ?? null;
            if ($firstId) {
                $_SESSION['current_accommodation'] = ['id' => $firstId];
                $data['accommodations'] = [$allAccommodations[0]];
            }
        }

        $selectedAccommodation = $data['accommodations'][0] ?? null;
    }

    $data['accommodations'] = array_values($allAccommodations);
    
    // Calculate statistics for all accommodations
    $totalStudents = 0;
    $totalManagers = 0;
    $totalDevices = 0;
    
    foreach ($data['accommodations'] as $accommodation) {
        $totalStudents += countStudentsByStatus($conn, $accommodation['id']);
        $totalManagers += countAccommodationManagers($conn, $accommodation['id']);
        $totalDevices += countAccommodationDevices($conn, $accommodation['id']);
    }
    
    $data['stats'] = [
        'accommodations' => count($allAccommodations),  // Show total count
        'students' => $totalStudents,
        'managers' => $totalManagers,
        'devices' => $totalDevices,
    ];
    
    // Get recent activity for current accommodation only
    if (!empty($selectedAccommodation['id'])) {
        $accommodationId = $selectedAccommodation['id'];
        $data['recentActivity'] = ActivityLogger::getAccommodationActivityLog($accommodationId, 10, 0);
    } else {
        $data['recentActivity'] = [];
    }
    
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
    
    $stmt = safeQueryPrepare($conn, $query);
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
    
    $stmt = safeQueryPrepare($conn, $query);
    if (!$stmt) return [];
    
    $stmt->bind_param("ii", $accommodationId, $limit);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $students ?: [];
}

function countCodes($conn, $accommodationId, $status = null) {
    $query = "SELECT COUNT(*) as count FROM onboarding_codes WHERE accommodation_id = ?";
    $params = [$accommodationId];
    $types = "i";
    
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $stmt = safeQueryPrepare($conn, $query);
    if (!$stmt) return 0;
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)($result['count'] ?? 0);
}

function countAccommodationManagers($conn, $accommodationId) {
    $query = "SELECT COUNT(*) as count FROM user_accommodation WHERE accommodation_id = ?";
    $stmt = safeQueryPrepare($conn, $query);
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
    
    $stmt = safeQueryPrepare($conn, $query);
    if (!$stmt) return 0;
    
    $stmt->bind_param("i", $accommodationId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)($result['count'] ?? 0);
}

require_once '../includes/components/header.php';
?>

<div class="container mt-4">

    
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
        
        <!-- Accommodation Switcher Bar Component -->
        <?php include __DIR__ . '/../includes/components/accommodation-switcher-bar.php'; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white stat-card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $dashboardData['students']['active'] ?></h5>
                        <p class="mb-0">Active Students</p>
                        <small class="text-light">/<?= $dashboardData['students']['total'] ?> total</small>
                        <div class="icon"><i class="bi bi-person-check"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white stat-card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $dashboardData['students']['pending'] ?></h5>
                        <p class="mb-0">Pending Students</p>
                        <small class="text-light">Awaiting activation</small>
                        <div class="icon"><i class="bi bi-person-exclamation"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white stat-card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $dashboardData['codes']['unused'] ?></h5>
                        <p class="mb-0">Codes Available</p>
                        <small class="text-light">/<?= $dashboardData['codes']['total'] ?> total</small>
                        <div class="icon"><i class="bi bi-qr-code"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white stat-card">
                    <div class="card-body">
                        <h5 class="card-title">Manage</h5>
                        <p class="mb-0">Quick Actions</p>
                        <small><a href="students.php" class="btn btn-sm btn-light">View Students</a></small>
                        <div class="icon"><i class="bi bi-gear"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Students -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Recent Students</h5>
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
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (!empty($dashboardData['recentActivity'])): ?>
                            <?php foreach (array_slice($dashboardData['recentActivity'], 0, 10) as $activity): ?>
                                <?php
                                $actAction  = (string)($activity['action'] ?? '');
                                $actDetails = (string)($activity['details'] ?? '');
                                $actTs      = (string)($activity['timestamp'] ?? '');
                                ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= ActivityLogHelper::normalizeActionLabel($actAction, $actDetails) ?></h6>
                                        <small><?= ActivityLogHelper::formatTimestamp($actTs) ?></small>
                                    </div>
                                    <?php $_detail_snip = ActivityLogHelper::formatDetails($actAction, $actDetails); ?>
                                    <?php if ($_detail_snip !== '<span class="text-muted">—</span>'): ?>
                                        <p class="mb-1 small text-muted"><?= $_detail_snip ?></p>
                                    <?php endif; ?>
                                    <small>
                                        By:
                                        <?php if (!empty($activity['first_name']) || !empty($activity['last_name'])): ?>
                                            <?= htmlspecialchars(trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
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
                <div class="card bg-primary text-white stat-card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $dashboardData['stats']['accommodations'] ?></h5>
                        <p class="mb-0">Accommodations</p>
                        <div class="icon"><i class="bi bi-building"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white stat-card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $dashboardData['stats']['students'] ?></h5>
                        <p class="mb-0">Total Students</p>
                        <div class="icon"><i class="bi bi-people"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white stat-card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $dashboardData['stats']['managers'] ?></h5>
                        <p class="mb-0">Managers</p>
                        <div class="icon"><i class="bi bi-person-badge"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white stat-card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $dashboardData['stats']['devices'] ?></h5>
                        <p class="mb-0">Devices</p>
                        <div class="icon"><i class="bi bi-router"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Accommodations List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list me-2"></i>Your Accommodations</h5>
                        <a href="create-accommodation.php" class="btn btn-sm btn-light">
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

