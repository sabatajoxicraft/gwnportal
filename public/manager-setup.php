<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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

// Check if manager already has an accommodation (shouldn't reach here if properly setup, but just in case)
$stmtCheck = safeQueryPrepare($conn, "SELECT COUNT(*) as count FROM user_accommodation WHERE user_id = ?");
$stmtCheck->bind_param("i", $userId);
$stmtCheck->execute();
$checkResult = $stmtCheck->get_result()->fetch_assoc();

if ($checkResult['count'] > 0) {
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
        // Validate the code
        $stmt = safeQueryPrepare($conn, 
            "SELECT oc.*, a.name as accommodation_name, r.name as role_name
             FROM onboarding_codes oc
             LEFT JOIN accommodations a ON oc.accommodation_id = a.id
             LEFT JOIN roles r ON oc.role_id = r.id
             WHERE oc.code = ? AND oc.status = 'unused' AND oc.expires_at > NOW() AND r.name = 'manager'");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $codeData = $result->fetch_assoc();
            $accommodationId = $codeData['accommodation_id'];
            
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Assign manager to accommodation
                $assignStmt = safeQueryPrepare($conn, "INSERT INTO user_accommodation (user_id, accommodation_id) VALUES (?, ?)");
                $assignStmt->bind_param("ii", $userId, $accommodationId);
                
                if (!$assignStmt->execute()) {
                    throw new Exception("Failed to assign accommodation: " . $conn->error);
                }
                
                // Update onboarding code as used
                $codeStmt = safeQueryPrepare($conn, "UPDATE onboarding_codes SET status = 'used', used_by = ?, used_at = NOW() WHERE code = ?");
                $codeStmt->bind_param("is", $userId, $code);
                
                if (!$codeStmt->execute()) {
                    throw new Exception("Failed to update code: " . $conn->error);
                }
                
                // Commit transaction
                $conn->commit();
                
                // Update session
                $_SESSION['accommodation_id'] = $accommodationId;
                
                // Log activity
                logActivity($conn, $userId, "Manager Assignment", "Assigned to accommodation using onboarding code");
                
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
