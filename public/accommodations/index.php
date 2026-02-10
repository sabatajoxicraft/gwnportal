<?php
/**
 * Unified Accommodations List Page
 * Consolidates public/accommodations.php and public/admin/accommodations.php
 * 
 * Supports both admin and owner roles:
 * - Admin: Sees all accommodations with owner info and delete capability
 * - Owner: Sees only owned accommodations with manager management
 */
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require owner or admin role
requireRole(['owner', 'admin']);

$conn = getDbConnection();
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$currentUserId = $_SESSION['user_id'];

$message = '';
$message_type = '';
$accommodations = [];
$error = null;

// Handle accommodation actions (admin only - delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $isAdmin) {
    requireCsrfToken();
    $accommodation_id = $_POST['accommodation_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && $accommodation_id > 0) {
        // Check if the accommodation has students or managers
        $check_stmt = safeQueryPrepare($conn, 
            "SELECT (SELECT COUNT(*) FROM user_accommodation WHERE accommodation_id = ?) AS associated_count");
        $check_stmt->bind_param("i", $accommodation_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if ($check_result['associated_count'] > 0) {
            $message = "Cannot delete accommodation because it has associated users.";
            $message_type = "danger";
        } else {
            // Delete the accommodation
            $stmt = safeQueryPrepare($conn, "DELETE FROM accommodations WHERE id = ?");
            $stmt->bind_param("i", $accommodation_id);
            
            if ($stmt->execute()) {
                $message = "Accommodation deleted successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to delete accommodation.";
                $message_type = "danger";
            }
        }
    }
}

// Build query based on role
if ($isAdmin) {
    // Admin sees all accommodations with owner details
    $sql = "SELECT a.*, u.first_name, u.last_name, 
            (SELECT COUNT(*) FROM user_accommodation WHERE accommodation_id = a.id) AS user_count
            FROM accommodations a 
            JOIN users u ON a.owner_id = u.id 
            ORDER BY a.name ASC";
    $stmt = safeQueryPrepare($conn, $sql);
    if ($stmt) {
        $stmt->execute();
        $accommodations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} else {
    // Owner sees only their accommodations with counts
    $sql = "SELECT a.id, a.name, a.created_at,
            (SELECT COUNT(DISTINCT ua.user_id)
               FROM user_accommodation ua
               JOIN users um ON ua.user_id = um.id
               JOIN roles rm ON um.role_id = rm.id
               WHERE ua.accommodation_id = a.id AND rm.name = 'manager') AS manager_count,
            (SELECT COUNT(*) FROM students s WHERE s.accommodation_id = a.id) AS student_count
            FROM accommodations a 
            WHERE a.owner_id = ? 
            ORDER BY a.name ASC";
    
    $stmt = safeQueryPrepare($conn, $sql);
    if ($stmt === false) {
        $error = "Unable to load accommodations. Please try again later.";
    } else {
        $stmt->bind_param("i", $currentUserId);
        $stmt->execute();
        $accommodations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Set page variables
$pageTitle = $isAdmin ? "Manage Accommodations" : "My Accommodations";
$activePage = "accommodations";

// Include header
require_once '../../includes/components/header.php';

// Include navigation for admin
if ($isAdmin) {
    require_once '../../includes/components/navigation.php';
}
?>

<div class="container mt-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $isAdmin ? 'Manage Accommodations' : 'My Accommodations' ?></h2>
        <a href="<?= BASE_URL ?>/accommodations/create.php" class="btn btn-primary">
            <i class="bi bi-<?= $isAdmin ? 'building-add' : 'plus-circle' ?>"></i> Add New Accommodation
        </a>
    </div>

    <?php if (!empty($accommodations)): ?>
        <div class="card">
            <?php if ($isAdmin): ?>
            <div class="card-header">
                <h5 class="mb-0">Accommodation List</h5>
            </div>
            <?php endif; ?>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover <?= $isAdmin ? '' : 'table-striped' ?> mb-0">
                        <thead class="<?= $isAdmin ? '' : 'table-light' ?>">
                            <tr>
                                <th>Name</th>
                                <?php if ($isAdmin): ?>
                                <th>Owner</th>
                                <th>Users</th>
                                <?php else: ?>
                                <th class="text-center">Managers</th>
                                <th class="text-center">Students</th>
                                <?php endif; ?>
                                <th>Created<?= $isAdmin ? '' : ' Date' ?></th>
                                <th class="<?= $isAdmin ? '' : 'text-end' ?>">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accommodations as $accommodation): ?>
                                <tr>
                                    <td<?= $isAdmin ? '' : ' class="align-middle"' ?>>
                                        <?php if (!$isAdmin): ?><strong><?php endif; ?>
                                        <?= htmlspecialchars($accommodation['name']) ?>
                                        <?php if (!$isAdmin): ?></strong><?php endif; ?>
                                    </td>
                                    <?php if ($isAdmin): ?>
                                    <td><?= htmlspecialchars($accommodation['first_name'] . ' ' . $accommodation['last_name']) ?></td>
                                    <td><span class="badge bg-info"><?= $accommodation['user_count'] ?></span></td>
                                    <?php else: ?>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-primary rounded-pill"><?= $accommodation['manager_count'] ?></span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-success rounded-pill"><?= $accommodation['student_count'] ?></span>
                                    </td>
                                    <?php endif; ?>
                                    <td<?= $isAdmin ? '' : ' class="align-middle"' ?>>
                                        <?= date('M j, Y', strtotime($accommodation['created_at'])) ?>
                                    </td>
                                    <td class="<?= $isAdmin ? '' : 'text-end' ?>">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= BASE_URL ?>/view-accommodation.php?id=<?= $accommodation['id'] ?>" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/edit-accommodation.php?id=<?= $accommodation['id'] ?>" 
                                               class="btn btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($isAdmin): ?>
                                                <?php if ($accommodation['user_count'] == 0): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?= $accommodation['id'] ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-danger" disabled 
                                                            title="Cannot delete accommodations with users">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/managers.php?accommodation_id=<?= $accommodation['id'] ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Manage Staff">
                                                    <i class="bi bi-people"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#managersModal<?= $accommodation['id'] ?>" 
                                                        title="View Managers">
                                                    <i class="bi bi-person-lines-fill"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($isAdmin && $accommodation['user_count'] == 0): ?>
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?= $accommodation['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Deletion</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete the accommodation "<?= htmlspecialchars($accommodation['name']) ?>"?</p>
                                                        <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="post" action="">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="accommodation_id" value="<?= $accommodation['id'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if (!$isAdmin): ?>
        <!-- Manager Modals - One for each accommodation (Owner view only) -->
        <?php foreach ($accommodations as $accommodation): ?>
            <div class="modal fade" id="managersModal<?= $accommodation['id'] ?>" tabindex="-1" 
                 aria-labelledby="managersModalLabel<?= $accommodation['id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="managersModalLabel<?= $accommodation['id'] ?>">
                                Managers for <?= htmlspecialchars($accommodation['name']) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php 
                                // Fetch managers for this accommodation
                                $stmt_managers = safeQueryPrepare($conn, 
                                    "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.status as manager_status
                                     FROM users u
                                     JOIN roles r ON u.role_id = r.id
                                     JOIN user_accommodation ua ON u.id = ua.user_id
                                     WHERE ua.accommodation_id = ? AND r.name = 'manager'
                                     ORDER BY u.first_name, u.last_name"); 
                                
                                $managers = [];
                                if ($stmt_managers !== false) {
                                    $stmt_managers->bind_param("i", $accommodation['id']);
                                    $stmt_managers->execute();
                                    $managers = $stmt_managers->get_result()->fetch_all(MYSQLI_ASSOC);
                                }
                            ?>
                            
                            <?php if (!empty($managers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Username</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($managers as $manager): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?></td>
                                                    <td><?= htmlspecialchars($manager['email']) ?></td>
                                                    <td><?= htmlspecialchars($manager['username']) ?></td>
                                                    <td class="text-center">
                                                        <span class="badge <?= $manager['manager_status'] === 'active' ? 'bg-success' : 'bg-warning' ?>">
                                                            <?= ucfirst($manager['manager_status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <form method="post" action="<?= BASE_URL ?>/managers.php" 
                                                              onsubmit="return confirm('Are you sure you want to unassign this manager?');" 
                                                              class="d-inline">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="manager_id" value="<?= $manager['id'] ?>">
                                                            <input type="hidden" name="accommodation_id" value="<?= $accommodation['id'] ?>">
                                                            <button type="submit" name="unassign_manager" 
                                                                    class="btn btn-sm btn-outline-danger" title="Unassign">
                                                                <i class="bi bi-person-x"></i> Unassign
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <p class="mb-0"><i class="bi bi-info-circle"></i> No managers assigned to this accommodation yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <a href="<?= BASE_URL ?>/managers.php?accommodation_id=<?= $accommodation['id'] ?>" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Manage Staff
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="card">
            <div class="<?= $isAdmin ? 'card-body text-center py-5' : 'card-body text-center py-5' ?>">
                <i class="bi bi-building <?= $isAdmin ? '' : 'display-1' ?> text-muted <?= $isAdmin ? '' : 'mb-3' ?>" 
                   <?= $isAdmin ? 'style="font-size: 3rem;"' : '' ?>></i>
                <?php if ($isAdmin): ?>
                    <p class="mt-3">No accommodations found</p>
                <?php else: ?>
                    <h3>No Accommodations Found</h3>
                    <p class="text-muted mb-4">You haven't added any accommodations yet.</p>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/accommodations/create.php" class="btn btn-primary <?= $isAdmin ? '' : 'mt-3' ?>">
                    <i class="bi bi-<?= $isAdmin ? 'building-add' : 'plus-circle me-1' ?>"></i> 
                    Add <?= $isAdmin ? 'New' : 'Your First' ?> Accommodation
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/components/footer.php'; ?>