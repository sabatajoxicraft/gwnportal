<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login and owner role
if (!isLoggedIn() || $_SESSION['user_role'] !== 'owner') {
    redirect(BASE_URL . '/login.php', 'Please login as an owner to access this page', 'warning');
}

$error = '';
$success = '';
$userId = $_SESSION['user_id'] ?? 0;
$conn = getDbConnection();

// Check if owner already has accommodations (shouldn't reach here if properly setup, but just in case)
$stmtCheck = safeQueryPrepare($conn, "SELECT COUNT(*) as count FROM accommodations WHERE owner_id = ?");
$stmtCheck->bind_param("i", $userId);
$stmtCheck->execute();
$checkResult = $stmtCheck->get_result()->fetch_assoc();

if ($checkResult['count'] > 0) {
    // Already has accommodations, redirect to dashboard
    redirect(BASE_URL . '/dashboard.php');
}

// Handle accommodation creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_accommodation'])) {
    requireCsrfToken();
    $accommodation_name = trim($_POST['accommodation_name'] ?? '');
    
    if (empty($accommodation_name)) {
        $error = 'Please enter an accommodation name.';
    } else if (strlen($accommodation_name) < 3) {
        $error = 'Accommodation name must be at least 3 characters.';
    } else if (strlen($accommodation_name) > 100) {
        $error = 'Accommodation name cannot exceed 100 characters.';
    } else {
        try {
            // Create accommodation
            $stmt = safeQueryPrepare($conn, "INSERT INTO accommodations (name, owner_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->bind_param("si", $accommodation_name, $userId);
            
            if ($stmt->execute()) {
                $accommodationId = $conn->insert_id;
                
                // Log activity
                logActivity($conn, $userId, "Accommodation Created", "Created accommodation: " . $accommodation_name);
                
                // Redirect to dashboard
                redirect(BASE_URL . '/dashboard.php', 'Accommodation created successfully!', 'success');
            } else {
                $error = "Failed to create accommodation: " . $conn->error;
            }
        } catch (Exception $e) {
            $error = "Error creating accommodation: " . $e->getMessage();
            error_log("Accommodation creation error: " . $e->getMessage());
        }
    }
}

$pageTitle = "Owner Setup";
require_once '../includes/components/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-building me-2"></i>Welcome, Accommodation Owner</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <p class="mb-0">
                            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Owner') ?></strong>
                        </p>
                        <p class="mb-0 mt-2">
                            Before you can access your dashboard and manage accommodations, you need to create at least one accommodation.
                        </p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <?php echo csrfField(); ?>
                        <div class="mb-4">
                            <h5>Create Your First Accommodation</h5>
                            <p class="text-muted">Enter the name of your accommodation (e.g., "De Beers Diamond Lodge").</p>
                        </div>

                        <div class="mb-3">
                            <label for="accommodation_name" class="form-label">Accommodation Name *</label>
                            <input type="text" class="form-control form-control-lg" 
                                id="accommodation_name" name="accommodation_name" 
                                placeholder="e.g., Student Residences, Guesthouse"
                                value="<?= htmlspecialchars($_POST['accommodation_name'] ?? '') ?>"
                                minlength="3" maxlength="100"
                                autofocus required>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                3-100 characters. You can add more accommodations after setup.
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="create_accommodation" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-circle me-2"></i>Create Accommodation
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title">What happens next?</h6>
                            <ol class="small mb-0 ps-3">
                                <li>Create your first accommodation</li>
                                <li>Access your dashboard to manage settings</li>
                                <li>Generate onboarding codes for managers and students</li>
                                <li>Share codes with your team</li>
                            </ol>
                        </div>
                    </div>

                    <div class="mt-4 text-center">
                        <a href="<?= BASE_URL ?>/logout.php" class="text-muted small">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
