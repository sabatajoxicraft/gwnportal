<?php
// filepath: c:\xampp\htdocs\wifi\web\public\owner\edit-accommodation.php
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
$stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations WHERE id = ? AND owner_id = ?");
if ($stmt === false) {
    $error = "Database error. Please try again later.";
} else {
    $stmt->bind_param("ii", $accommodation_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if accommodation exists and belongs to this owner
    if ($result->num_rows === 0) {
        redirect(BASE_URL . '/accommodations.php', 'Accommodation not found or you do not have permission to edit it.', 'danger');
    }
    
    $accommodation = $result->fetch_assoc();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        $error = 'Please provide an accommodation name.';
    } else {
        // Update accommodation
        $update_stmt = safeQueryPrepare($conn, "UPDATE accommodations SET name = ? WHERE id = ?");
        if ($update_stmt === false) {
            $error = "Database error. Please try again later.";
        } else {
            $update_stmt->bind_param("si", $name, $accommodation_id);
            if ($update_stmt->execute()) {
                // Log activity
                logActivity($conn, $owner_id, 'edit_accommodation', "Updated accommodation: $name (ID: $accommodation_id)");
                
                redirect(BASE_URL . '/accommodations.php', 'Accommodation updated successfully!', 'success');
            } else {
                $error = 'Failed to update accommodation. Please try again.';
            }
        }
    }
}

$pageTitle = "Edit Accommodation";
$activePage = "accommodations";
require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4 class="mb-0">Edit Accommodation</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Accommodation Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($accommodation['name']) ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="accommodations.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Accommodation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>