<?php
// filepath: c:\xampp\htdocs\wifi\web\public\owner\view-accommodation.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require owner login
requireOwnerLogin();

$owner_id = $_SESSION['user_id'];
$conn = getDbConnection();

// Get accommodation ID from query param
$accommodation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify this accommodation belongs to the owner
$stmt = safeQueryPrepare($conn, "SELECT id, name, created_at FROM accommodations WHERE id = ? AND owner_id = ?");
if ($stmt === false) {
    $error = "Database error. Please try again later.";
} else {
    $stmt->bind_param("ii", $accommodation_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if accommodation exists and belongs to this owner
    if ($result->num_rows === 0) {
        redirect(BASE_URL . '/owner/accommodations.php', 'Accommodation not found or you do not have permission to view it.', 'danger');
    }
    
    $accommodation = $result->fetch_assoc();
}

// Get managers for this accommodation
$managers = [];
$stmt_managers = safeQueryPrepare($conn, "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.status as manager_status 
                                        FROM users u 
                                        JOIN roles r ON u.role_id = r.id
                                        JOIN user_accommodation ua ON u.id = ua.user_id
                                        WHERE ua.accommodation_id = ? AND r.name = 'manager'");
if ($stmt_managers !== false) {
    $stmt_managers->bind_param("i", $accommodation_id);
    $stmt_managers->execute();
    $managers = $stmt_managers->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get student count
$student_count = 0;
$stmt_students = safeQueryPrepare($conn, "SELECT COUNT(*) as count FROM students WHERE accommodation_id = ?");
if ($stmt_students !== false) {
    $stmt_students->bind_param("i", $accommodation_id);
    $stmt_students->execute();
    $student_count = $stmt_students->get_result()->fetch_assoc()['count'] ?? 0;
}

$pageTitle = "View Accommodation";
$activePage = "accommodations";
require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= htmlspecialchars($accommodation['name']) ?></h2>
        <a href="accommodations.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Accommodations
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Accommodation Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?= htmlspecialchars($accommodation['name']) ?></p>
                    <p><strong>Created:</strong> <?= date('M j, Y', strtotime($accommodation['created_at'])) ?></p>
                    <p>
                        <strong>Manager:</strong>
                        <?php if (!empty($managers)): ?>
                            <?= htmlspecialchars($managers[0]['first_name'] . ' ' . $managers[0]['last_name']) ?>
                        <?php else: ?>
                            <em>No manager assigned</em>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Summary</h5>
                </div>
                <div class="card-body">
                    <p><strong>Total Students:</strong> <?= $student_count ?></p>
                    <p><strong>Assigned Managers:</strong> <?= count($managers) ?></p>
                    <div class="d-grid">
                        <a href="managers.php?accommodation_id=<?= $accommodation_id ?>" class="btn btn-primary">
                            <i class="bi bi-people"></i> Manage Managers
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>