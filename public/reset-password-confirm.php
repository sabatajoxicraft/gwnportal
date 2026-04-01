<?php
/**
 * Reset Password Confirm – Self-Service Password Reset Completion
 *
 * Public page: accepts a one-time token from the reset email, validates it,
 * and allows the user to set a new password.
 *
 * GET  ?token=<hex>  – validate the token and render the password form.
 * POST               – consume the token and apply the new password.
 *
 * Both valid-token-expired and completely-unknown-token scenarios show the
 * same generic "invalid or expired" message.
 */

include '../includes/page-template.php';

$pageTitle = 'Set New Password';

// Redirect authenticated users to their dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Prefer POST hidden-field over GET query param so the form can be re-submitted
// on validation errors without requiring the token in the URL.
$tokenPlain = trim($_POST['token'] ?? $_GET['token'] ?? '');

$error      = '';
$success    = false;
$tokenValid = false;
$tokenData  = null;
$requestIp  = $_SERVER['REMOTE_ADDR'] ?? '';

// Validate the token on every request (GET and POST alike).
if (!empty($tokenPlain)) {
    $tokenData  = PasswordResetService::validateToken($conn, $tokenPlain);
    $tokenValid = ($tokenData !== false);
}

// Log attempts with an invalid or expired token on GET so brute-force guessing
// is visible in the audit trail.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($tokenPlain) && !$tokenValid) {
    ActivityLogger::logAuthEvent(null, 'password_reset_token_invalid', [
        'ip_address' => $requestIp,
        'reason'     => 'invalid or expired token on page load',
    ]);
}

// -------------------------------------------------------------------------
// Handle form submission
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (!$tokenValid) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in both password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            if (PasswordResetService::consumeToken($conn, $tokenPlain, $newPassword)) {
                ActivityLogger::logAuthEvent((int) $tokenData['user_id'], 'password_reset_completed', [
                    'ip_address' => $requestIp,
                    'method'     => 'self_service_email_token',
                ]);
                $success = true;
            } else {
                // Token may have expired or been used in a concurrent request.
                ActivityLogger::logAuthEvent(null, 'password_reset_token_invalid', [
                    'ip_address' => $requestIp,
                    'reason'     => 'consumeToken returned false (possible race or expiry)',
                ]);
                $tokenValid = false;
                $error = 'This reset link is invalid or has expired. Please request a new one.';
            }
        }
    }
}

// -------------------------------------------------------------------------
// Page styling (mirrors login.php)
// -------------------------------------------------------------------------
$extraCss = '
<style>
    body.login-page {
        background: var(--primary-gradient);
        min-height: 100vh;
    }
    .login-page-wrapper {
        min-height: calc(100vh - 140px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 0;
    }
    .login-container {
        max-width: 450px;
        width: 100%;
        padding: 0 15px;
    }
    .login-card {
        box-shadow: 0 15px 45px rgba(0, 0, 0, 0.2);
        border: none;
        border-radius: 10px;
        overflow: hidden;
    }
    .login-card .card-header {
        padding: 2.5rem 1rem;
        background: var(--dark-gradient);
        border-bottom: none;
    }
    .login-card .card-header h3 {
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .login-card .card-header p {
        opacity: 0.95;
        margin-bottom: 0;
    }
</style>
';

$bodyClass = 'login-page';

require_once '../includes/components/header.php';
?>

<div class="login-page-wrapper">
    <div class="login-container">
        <div class="card login-card">
            <!-- Card Header -->
            <div class="card-header text-white text-center">
                <h3><i class="bi bi-wifi"></i> <?= APP_NAME ?></h3>
                <p class="small">WiFi Access Management</p>
            </div>

            <!-- Card Body -->
            <div class="card-body p-5">

                <?php if ($success): ?>
                    <!-- Success state -->
                    <div class="text-center">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 fw-bold">Password Updated</h5>
                        <p class="text-muted">
                            Your password has been reset successfully.
                            You can now log in with your new password.
                        </p>
                        <a href="login.php" class="btn btn-primary mt-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                        </a>
                    </div>

                <?php elseif (!$tokenValid && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                    <!-- Invalid / expired token on initial page load -->
                    <div class="text-center">
                        <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 fw-bold">Link Invalid or Expired</h5>
                        <p class="text-muted">
                            This password reset link is invalid or has expired.
                            Reset links are valid for 1 hour and can only be used once.
                        </p>
                        <a href="forgot-password.php" class="btn btn-primary mt-2">
                            <i class="bi bi-arrow-repeat me-2"></i>Request New Link
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Password reset form (also shown on POST validation errors) -->
                    <h5 class="mb-1 fw-bold">Set a New Password</h5>
                    <p class="text-muted mb-4 small">Choose a strong password for your account.</p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                            <i class="bi bi-exclamation-circle flex-shrink-0"></i>
                            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$tokenValid): ?>
                        <!-- Token became invalid after a failed POST (race / expiry) -->
                        <div class="text-center mt-3">
                            <a href="forgot-password.php" class="btn btn-primary">
                                <i class="bi bi-arrow-repeat me-2"></i>Request New Link
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" novalidate>
                            <?= csrfField(); ?>
                            <input type="hidden" name="token" value="<?= htmlspecialchars($tokenPlain, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-bold">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-lock text-muted"></i>
                                    </span>
                                    <input
                                        type="password"
                                        class="form-control border-start-0"
                                        id="new_password"
                                        name="new_password"
                                        required
                                        autofocus
                                        placeholder="Enter new password"
                                    >
                                </div>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-bold">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-lock-fill text-muted"></i>
                                    </span>
                                    <input
                                        type="password"
                                        class="form-control border-start-0"
                                        id="confirm_password"
                                        name="confirm_password"
                                        required
                                        placeholder="Confirm new password"
                                    >
                                </div>
                            </div>

                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold py-2">
                                    <i class="bi bi-shield-check me-2"></i>Reset Password
                                </button>
                            </div>

                            <div class="text-center">
                                <small>
                                    <a href="login.php" class="text-primary text-decoration-none">
                                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                                    </a>
                                </small>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
