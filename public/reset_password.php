<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$pageTitle = "Reset Password";

// Ensure database connection is available
$conn = getDbConnection();

// Only allow access if logged in and password reset is required
if (!isLoggedIn()) {
    redirect('public/login.php', 'Please login first', 'warning');
}

// Check if password reset is required or explicitly requested
$resetRequired = isset($_SESSION['password_reset_required']) && $_SESSION['password_reset_required'];
$resetRequested = isset($_GET['reset']) && $_GET['reset'] == 1;

if (!$resetRequired && !$resetRequested) {
    redirect('public/dashboard.php', 'Password reset not required', 'info');
}

$error = '';
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Get current user
    $userId = $_SESSION['user_id'];
    $user = getUserDetails($userId);
    
    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!verifyPassword($currentPassword, $user['password'])) {
        $error = 'Current password is incorrect';
    } else {
        // Hash the new password
        $newHash = createPasswordHash($newPassword);
        
        // Update the password in the database
        $stmt = safeQueryPrepare($conn, "UPDATE users SET password = ?, password_reset_required = 0 WHERE id = ?");
        $stmt->bind_param("si", $newHash, $userId);
        
        if ($stmt->execute()) {
            // Remove the password reset flag from session
            unset($_SESSION['password_reset_required']);
            
            // Log the password change
            logActivity($conn, $userId, 'Password Change', 'User changed their password', $_SERVER['REMOTE_ADDR']);
            
            $success = true;
            // Redirect after a short delay
            header("refresh:2;url=" . getDashboardUrl());
        } else {
            $error = 'Error updating password: ' . $conn->error;
        }
    }
}

// Set active page for navigation
$activePage = "account";

require_once '../includes/components/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Reset Your Password</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <h5><i class="bi bi-check-circle-fill me-2"></i>Password Updated Successfully!</h5>
                            <p>Your password has been changed. You will be redirected to your dashboard.</p>
                        </div>
                    <?php else: ?>
                        <?php if ($resetRequired): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                You need to set a new password before continuing.
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Password must be at least 8 characters long</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
