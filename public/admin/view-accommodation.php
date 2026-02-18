<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Get accommodation ID from URL
$accommodation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($accommodation_id <= 0) {
    redirect(BASE_URL . '/admin/accommodations.php', 'Invalid accommodation ID', 'danger');
}

// Get accommodation details
$conn = getDbConnection();
$stmt = safeQueryPrepare($conn, "SELECT a.*, u.first_name, u.last_name, u.email 
                                FROM accommodations a 
                                JOIN users u ON a.owner_id = u.id 
                                WHERE a.id = ?");
$stmt->bind_param("i", $accommodation_id);
$stmt->execute();
$accommodation = $stmt->get_result()->fetch_assoc();

if (!$accommodation) {
    redirect(BASE_URL . '/admin/accommodations.php', 'Accommodation not found', 'danger');
}

// Get associated users (managers and students)
$users_stmt = safeQueryPrepare($conn, "SELECT u.*, r.name as role_name 
                                    FROM users u 
                                    JOIN roles r ON u.role_id = r.id
                                    JOIN user_accommodation ua ON u.id = ua.user_id
                                    WHERE ua.accommodation_id = ?
                                    ORDER BY r.name, u.first_name, u.last_name");
$users_stmt->bind_param("i", $accommodation_id);
$users_stmt->execute();
$users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "View Accommodation";
$activePage = "accommodations";

// Include header
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php require_once '../../includes/components/messages.php'; ?>
    
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/accommodations.php">Accommodations</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($accommodation['name']) ?></li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= htmlspecialchars($accommodation['name']) ?></h2>
        <div>
            <a href="edit-accommodation.php?id=<?= $accommodation_id ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <a href="assign-users.php?id=<?= $accommodation_id ?>" class="btn btn-success">
                <i class="bi bi-person-plus"></i> Assign Users
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Accommodation Details</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th>Name:</th>
                            <td><?= htmlspecialchars($accommodation['name']) ?></td>
                        </tr>
                        <tr>
                            <th>Owner:</th>
                            <td>
                                <a href="view-user.php?id=<?= $accommodation['owner_id'] ?>">
                                    <?= htmlspecialchars($accommodation['first_name'] . ' ' . $accommodation['last_name']) ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Owner Email:</th>
                            <td><?= htmlspecialchars($accommodation['email']) ?></td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td><?= date('F j, Y', strtotime($accommodation['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td><?= date('F j, Y', strtotime($accommodation['updated_at'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h3>
                                <?php
                                $manager_count = 0;
                                $student_count = 0;
                                foreach ($users as $user) {
                                    if ($user['role_name'] === 'manager') $manager_count++;
                                    if ($user['role_name'] === 'student') $student_count++;
                                }
                                echo $manager_count;
                                ?>
                            </h3>
                            <p class="text-muted">Managers</p>
                        </div>
                        <div class="col-6 mb-3">
                            <h3><?= $student_count ?></h3>
                            <p class="text-muted">Students</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Associated Users</h5>
        </div>
        <div class="card-body p-0">
            <?php if (count($users) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                    <td>
                                        <span class="badge <?= $user['role_name'] === 'manager' ? 'bg-primary' : 'bg-success' ?>">
                                            <?= ucfirst($user['role_name']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($user['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view-user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p>No users are associated with this accommodation yet.</p>
                    <a href="assign-users.php?id=<?= $accommodation_id ?>" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Assign Users
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
