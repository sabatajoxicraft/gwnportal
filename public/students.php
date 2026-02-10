<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// Require manager login
requireManagerLogin();

$userId = $_SESSION['user_id'] ?? 0;
$accommodation_id = $_SESSION['accommodation_id'] ?? 0;
$conn = getDbConnection();

// Handle student action if requested
if (isset($_GET['action']) && isset($_GET['id'])) {
    $student_id = (int)$_GET['id'];
    
    // Use RBAC permission check
    if (!canEditStudent($student_id)) {
        denyAccess('You do not have permission to manage this student', BASE_URL . '/students.php');
    }
    
    if ($_GET['action'] == 'activate') {
        $stmt = $conn->prepare("UPDATE students SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        redirect(BASE_URL . '/students.php', 'Student activated successfully.', 'success');
    } 
    elseif ($_GET['action'] == 'deactivate') {
        $stmt = $conn->prepare("UPDATE students SET status = 'inactive' WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        redirect(BASE_URL . '/students.php', 'Student deactivated successfully.', 'success');
    }
    elseif ($_GET['action'] == 'delete') {
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        redirect(BASE_URL . '/students.php', 'Student deleted successfully.', 'success');
    }
}

// Get filter from query param
$filter = $_GET['filter'] ?? 'all';

// Prepare the SQL query based on the filter
$sql_where = "WHERE s.accommodation_id = ?";
if ($filter == 'active') {
    $sql_where .= " AND status = 'active'";
} else if ($filter == 'pending') {
    $sql_where .= " AND status = 'pending'";
} else if ($filter == 'inactive') {
    $sql_where .= " AND status = 'inactive'";
}

// Get students for this manager
$sql = "SELECT s.id, s.status, s.created_at, u.first_name, u.last_name, u.email, u.phone_number, u.whatsapp_number, u.preferred_communication
    FROM students s
    JOIN users u ON s.user_id = u.id
    $sql_where
    ORDER BY s.created_at DESC";
$stmt = safeQueryPrepare($conn, $sql);

// Initialize students array
$students = [];

// Only proceed if the statement was prepared successfully
if ($stmt !== false) {
    $stmt->bind_param("i", $accommodation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
}

// Get student stats
$stmt_stats = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
    FROM students WHERE accommodation_id = ?");

if ($stmt_stats) {
    $stmt_stats->bind_param("i", $accommodation_id);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
}

$pageTitle = "Manage Students";
require_once '../includes/components/header.php';
require_once '../includes/components/navigation.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Students</h2>
            <a href="create-code.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Generate Onboarding Code
            </a>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <h5>Total Students</h5>
                        <h2><?= $stats['total'] ?? 0 ?></h2>
                    </div>
                    <div class="col-md-3 text-center">
                        <h5>Active Students</h5>
                        <h2 class="text-success"><?= $stats['active'] ?? 0 ?></h2>
                    </div>
                    <div class="col-md-3 text-center">
                        <h5>Pending Students</h5>
                        <h2 class="text-warning"><?= $stats['pending'] ?? 0 ?></h2>
                    </div>
                    <div class="col-md-3 text-center">
                        <h5>Inactive Students</h5>
                        <h2 class="text-danger"><?= $stats['inactive'] ?? 0 ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?= $filter == 'all' ? 'active' : '' ?>" href="?filter=all">All Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter == 'active' ? 'active' : '' ?>" href="?filter=active">Active</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter == 'pending' ? 'active' : '' ?>" href="?filter=pending">Pending</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filter == 'inactive' ? 'active' : '' ?>" href="?filter=inactive">Inactive</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <?php if (count($students) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Preferred</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= $student['first_name'] ?> <?= $student['last_name'] ?></td>
                                        <td><?= $student['email'] ?></td>
                                        <td><?= $student['phone_number'] ?></td>
                                        <td><?= $student['preferred_communication'] ?></td>
                                        <td>
                                            <?php if ($student['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($student['status'] == 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="actionDropdown<?= $student['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="actionDropdown<?= $student['id'] ?>">
                                                    <li><a class="dropdown-item" href="student-details.php?id=<?= $student['id'] ?>">View Details</a></li>
                                                    <li><a class="dropdown-item" href="send-voucher.php?id=<?= $student['id'] ?>">Send Voucher</a></li>
                                                    <?php if ($student['status'] != 'active'): ?>
                                                        <li><a class="dropdown-item" href="?action=activate&id=<?= $student['id'] ?>">Activate</a></li>
                                                    <?php endif; ?>
                                                    <?php if ($student['status'] != 'inactive'): ?>
                                                        <li><a class="dropdown-item" href="?action=deactivate&id=<?= $student['id'] ?>">Deactivate</a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="?action=delete&id=<?= $student['id'] ?>" onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">Delete</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No Students Found</h5>
                        
                        <?php if (isset($_GET['status']) && $_GET['status'] == 'active'): ?>
                            <p class="text-muted">You don't have any active students at the moment.</p>
                        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'pending'): ?>
                            <p class="text-muted">There are no pending student onboarding requests.</p>
                        <?php else: ?>
                            <p class="text-muted">Once students complete the onboarding process, they'll appear here.</p>
                        <?php endif; ?>
                        
                        <a href="codes.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Create Onboarding Code
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php require_once '../includes/components/footer.php'; ?>
