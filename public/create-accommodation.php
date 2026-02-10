<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require owner login
requireOwnerLogin();

$owner_id = $_SESSION['user_id'];
$conn = getDbConnection();

$error = '';
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        $error = 'Please provide an accommodation name.';
    } else {
        // Insert new accommodation with only existing columns
        $stmt = safeQueryPrepare($conn, "INSERT INTO accommodations (name, owner_id) VALUES (?, ?)");
        
        if ($stmt === false) {
            $error = "Database error. Please try again later.";
        } else {
            $stmt->bind_param("si", $name, $owner_id);
            
            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                
                // Log activity
                logActivity($conn, $owner_id, 'create_accommodation', "Created new accommodation: $name");
                
                // Redirect to accommodation list
                redirect(BASE_URL . '/owner/accommodations.php', 'Accommodation created successfully!', 'success');
            } else {
                $error = 'Failed to create accommodation. Please try again.';
            }
        }
    }
}

$pageTitle = "Add New Accommodation";
$activePage = "accommodations";
require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4 class="mb-0">Add New Accommodation</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label for="name" class="form-label">Accommodation Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= $_POST['name'] ?? '' ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="accommodations.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Accommodation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
