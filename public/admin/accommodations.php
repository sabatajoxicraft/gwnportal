<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Handle accommodation actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $accommodation_id = $_POST['accommodation_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    $conn = getDbConnection();
    
    if ($action === 'delete' && $accommodation_id > 0) {
        // Check if the accommodation has students or managers
        $check_stmt = safeQueryPrepare($conn, "SELECT 
            (SELECT COUNT(*) FROM user_accommodation WHERE accommodation_id = ?) AS associated_count");
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

// Get all accommodations with owner details
$conn = getDbConnection();
$sql = "SELECT a.*, u.first_name, u.last_name, 
        (SELECT COUNT(*) FROM user_accommodation WHERE accommodation_id = a.id) AS user_count
        FROM accommodations a 
        JOIN users u ON a.owner_id = u.id 
        ORDER BY a.name ASC";

$stmt = safeQueryPrepare($conn, $sql);
$accommodations = [];

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $accommodations = $result->fetch_all(MYSQLI_ASSOC);
}

// Set page title
$pageTitle = "Manage Accommodations";
$activePage = "accommodations";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
?>

<div class="container mt-4">
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Accommodations</h2>
        <a href="create-accommodation.php" class="btn btn-primary">
            <i class="bi bi-building-add"></i> Add New Accommodation
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Accommodation List</h5>
        </div>
        <div class="card-body p-0">
            <?php if (count($accommodations) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Owner</th>
                                <th>Users</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accommodations as $accommodation): ?>
                                <tr>
                                    <td><?= htmlspecialchars($accommodation['name']) ?></td>
                                    <td><?= htmlspecialchars($accommodation['first_name'] . ' ' . $accommodation['last_name']) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $accommodation['user_count'] ?></span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($accommodation['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view-accommodation.php?id=<?= $accommodation['id'] ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit-accommodation.php?id=<?= $accommodation['id'] ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($accommodation['user_count'] == 0): ?>
                                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $accommodation['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-danger" disabled title="Cannot delete accommodations with users">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
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
                                                            <input type="hidden" name="accommodation_id" value="<?= $accommodation['id'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3">No accommodations found</p>
                    <a href="create-accommodation.php" class="btn btn-primary">
                        <i class="bi bi-building-add"></i> Add New Accommodation
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
