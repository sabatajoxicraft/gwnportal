<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require owner login
requireOwnerLogin();

$owner_id = $_SESSION['user_id'];
$conn = getDbConnection();

// Get all accommodations for this owner - Updated query to use only existing columns
$stmt = safeQueryPrepare($conn, "SELECT a.id, a.name, a.created_at,
                                                                 (SELECT COUNT(DISTINCT ua.user_id)
                                                                    FROM user_accommodation ua
                                                                    JOIN users um ON ua.user_id = um.id
                                                                    JOIN roles rm ON um.role_id = rm.id
                                                                    WHERE ua.accommodation_id = a.id AND rm.name = 'manager') AS manager_count,
                                                                 (SELECT COUNT(*)
                                                                    FROM students s
                                                                    WHERE s.accommodation_id = a.id) AS student_count
                                                                FROM accommodations a 
                                                                WHERE a.owner_id = ? 
                                                                ORDER BY a.name ASC");

if ($stmt === false) {
    $error = "Unable to load accommodations. Please try again later.";
} else {
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $accommodations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = "My Accommodations";
$activePage = "accommodations"; // Matches the nav item ID for highlighting
require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Accommodations</h2>
        <a href="create-accommodation.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Accommodation
        </a>
    </div>

    <?php if (!empty($accommodations)): ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th class="text-center">Managers</th>
                                <th class="text-center">Students</th>
                                <th>Created Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accommodations as $accommodation): ?>
                                <tr>
                                    <td class="align-middle">
                                        <strong><?= htmlspecialchars($accommodation['name']) ?></strong>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-primary rounded-pill"><?= $accommodation['manager_count'] ?></span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-success rounded-pill"><?= $accommodation['student_count'] ?></span>
                                    </td>
                                    <td class="align-middle">
                                        <?= date('M j, Y', strtotime($accommodation['created_at'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <a href="view-accommodation.php?id=<?= $accommodation['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit-accommodation.php?id=<?= $accommodation['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="managers.php?accommodation_id=<?= $accommodation['id'] ?>" class="btn btn-sm btn-outline-info" title="Manage Staff">
                                                <i class="bi bi-people"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#managersModal<?= $accommodation['id'] ?>" title="View Managers">
                                                <i class="bi bi-person-lines-fill"></i>
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
        
        <!-- Manager Modals - One for each accommodation -->
        <?php foreach ($accommodations as $accommodation): ?>
            <div class="modal fade" id="managersModal<?= $accommodation['id'] ?>" tabindex="-1" aria-labelledby="managersModalLabel<?= $accommodation['id'] ?>" aria-hidden="true">
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
                                                        <form method="post" action="managers.php" onsubmit="return confirm('Are you sure you want to unassign this manager?');" class="d-inline">
                                                            <input type="hidden" name="manager_id" value="<?= $manager['id'] ?>">
                                                            <input type="hidden" name="accommodation_id" value="<?= $accommodation['id'] ?>">
                                                            <button type="submit" name="unassign_manager" class="btn btn-sm btn-outline-danger" title="Unassign">
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
                            <a href="managers.php?accommodation_id=<?= $accommodation['id'] ?>" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Manage Staff
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-building display-1 text-muted mb-3"></i>
                <h3>No Accommodations Found</h3>
                <p class="text-muted mb-4">You haven't added any accommodations yet.</p>
                <a href="create-accommodation.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Add Your First Accommodation
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/components/footer.php'; ?>
