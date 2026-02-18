<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/services/QueryService.php';
require_once '../includes/services/AccommodationService.php';
require_once '../includes/services/ActivityLogger.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login and manager role
if (!isLoggedIn() || $_SESSION['user_role'] !== 'manager') {
    redirect(BASE_URL . '/login.php', 'Please login as a manager to access this page', 'warning');
}

$error = '';
$success = '';
$userId = $_SESSION['user_id'] ?? 0;
$conn = getDbConnection();

// Check if manager already has an accommodation using service
$accommodations = QueryService::getUserAccommodations($conn, $userId);
if (!empty($accommodations)) {
    // Already assigned, redirect to dashboard
    redirect(BASE_URL . '/dashboard.php');
}

// Handle onboarding code entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_manager_code'])) {
    requireCsrfToken();
    $code = trim($_POST['manager_code'] ?? '');
    
    if (empty($code)) {
        $error = 'Please enter an onboarding code.';
    } else {
        // Validate the code using service
        $codeData = QueryService::getOnboardingCode($conn, $code);
        
        // Check if code is valid and not used yet, and is for a manager role
        if ($codeData && 
            $codeData['used_by'] === null && 
            $codeData['status'] === 'unused' && 
            strtotime($codeData['expires_at']) > time() &&
            $codeData['role_name'] === 'manager') {
            
            $accommodationId = $codeData['accommodation_id'];
            
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Assign manager to accommodation using service
                if (!AccommodationService::assignManager($conn, $userId, $accommodationId)) {
                    throw new Exception("Failed to assign accommodation");
                }
                
                // Update onboarding code as used
                $codeStmt = safeQueryPrepare($conn, "UPDATE onboarding_codes SET status = 'used', used_by = ?, used_at = NOW() WHERE id = ?");
                $codeStmt->bind_param("ii", $userId, $codeData['id']);
                
                if (!$codeStmt->execute()) {
                    throw new Exception("Failed to update code: " . $conn->error);
                }
                $codeStmt->close();
                
                // Commit transaction
                $conn->commit();
                
                // Update session
                $_SESSION['accommodation_id'] = $accommodationId;
                
                // Log activity using service
                ActivityLogger::logAction($userId, 'manager_assignment', 
                    [
                        'accommodation_id' => $accommodationId,
                        'accommodation_name' => $codeData['accommodation_name'],
                        'onboarding_code' => $code
                    ]
                );
                
                // Redirect to dashboard
                redirect(BASE_URL . '/dashboard.php', 'Successfully assigned to accommodation!', 'success');
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error: " . $e->getMessage();
                error_log("Manager setup error: " . $e->getMessage());
            }
        } else {
            $error = 'Invalid or expired manager onboarding code. Please contact your administrator.';
        }
    }
}

$pageTitle = "Manager Setup";
require_once '../includes/components/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Account Setup Required</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <p class="mb-0">
                            <strong>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Manager') ?>!</strong>
                        </p>
                        <p class="mb-0 mt-2">
                            Before you can access your dashboard, you need to be assigned to an accommodation.
                        </p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Enter Your Manager Onboarding Code</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Your administrator should have provided you with a manager onboarding code. Enter it below to be assigned to your accommodation.</p>
                            
                            <form method="post" action="">
                                <?php echo csrfField(); ?>
                                <div class="mb-3">
                                    <label for="manager_code" class="form-label">Manager Onboarding Code</label>
                                    <input type="text" class="form-control form-control-lg text-center text-uppercase" 
                                        id="manager_code" name="manager_code" 
                                        placeholder="e.g., ABCD-EFGH-IJKL"
                                        value="<?= htmlspecialchars($_POST['manager_code'] ?? '') ?>"
                                        autofocus required>
                                    <div class="form-text">This code was generated by your accommodation owner or admin.</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="validate_manager_code" class="btn btn-primary btn-lg">
                                        <i class="bi bi-check-circle me-2"></i>Validate Code
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Don't have a code?</h5>
                            <p class="card-text">
                                Contact your accommodation owner or administrator to request a manager onboarding code for your accommodation.
                            </p>
                            <p class="card-text text-muted small mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Your code will specify which accommodation you'll manage.
                            </p>
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
