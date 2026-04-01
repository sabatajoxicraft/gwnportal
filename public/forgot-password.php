<?php
/**
 * Forgot Password – Self-Service Password Reset Request
 *
 * Public page: accepts an email address and dispatches a one-time reset link.
 * Always shows a generic success response regardless of whether the address
 * is registered in the system, to prevent user enumeration.
 */

include '../includes/page-template.php';

$pageTitle = 'Forgot Password';

// Redirect authenticated users to their dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token; on failure reload the page silently.
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: forgot-password.php');
        exit;
    }

    $email     = trim($_POST['email'] ?? '');
    $requestIp = $_SERVER['REMOTE_ADDR'] ?? '';

    // Attempt to issue a reset token.  Returns a plaintext token for known
    // active users, null for unknown/inactive/throttled, false on DB error.
    $tokenPlain = PasswordResetService::requestReset($conn, $email, $requestIp);

    if ($tokenPlain !== false && $tokenPlain !== null) {
        $resetUrl = ABSOLUTE_APP_URL . '/reset-password-confirm.php?token=' . urlencode($tokenPlain);
        $appName  = defined('APP_NAME') ? APP_NAME : 'GWN Portal';
        $subject  = $appName . ' – Password Reset Request';
        $body     = "Hello,\n\n"
                  . "We received a request to reset the password for your {$appName} account.\n\n"
                  . "Click the link below to set a new password. This link is valid for 1 hour "
                  . "and can only be used once.\n\n"
                  . "{$resetUrl}\n\n"
                  . "If you did not request a password reset, you can safely ignore this email — "
                  . "your password will not change.\n\n"
                  . "— The {$appName} Team";

        sendAppEmail($email, $subject, $body, false);
    }

    // Always log at this point so the execution path does not differ between
    // known and unknown addresses (prevents account-existence timing inference).
    // The hint is derived from the submitted input, not from a DB lookup result.
    ActivityLogger::logAuthEvent(null, 'password_reset_requested', [
        'ip_address' => $requestIp,
        'email_hint' => substr($email, 0, 3) . str_repeat('*', max(0, strlen($email) - 3)),
    ]);

    // Always show the generic success page – never disclose account existence.
    $submitted = true;
}

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
                <?php if ($submitted): ?>
                    <!-- Generic success – shown for all POST submissions -->
                    <div class="text-center">
                        <i class="bi bi-envelope-check text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 fw-bold">Check Your Email</h5>
                        <p class="text-muted">
                            If an account with that email address exists, we've sent a
                            password reset link. Please check your inbox and spam folder.
                        </p>
                        <p class="small text-muted mb-4">The link expires in 1 hour.</p>
                        <a href="login.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Reset request form -->
                    <h5 class="mb-1 fw-bold">Forgot your password?</h5>
                    <p class="text-muted mb-4 small">
                        Enter your email address and we'll send you a reset link.
                    </p>

                    <form method="POST" action="" novalidate>
                        <?= csrfField(); ?>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-envelope text-muted"></i>
                                </span>
                                <input
                                    type="email"
                                    class="form-control border-start-0"
                                    id="email"
                                    name="email"
                                    required
                                    autofocus
                                    placeholder="Enter your email address"
                                >
                            </div>
                        </div>

                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold py-2">
                                <i class="bi bi-send me-2"></i>Send Reset Link
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
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
