<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$pageTitle = "Login";

// Ensure database connection is available
$conn = getDbConnection();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(getDashboardUrl());
}

$error = '';
$debug = []; // Debug array to track login process

// Check for login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Add debug info about server environment
    $debug['php_version'] = PHP_VERSION;
    $debug['password_hash_algo'] = defined('PASSWORD_DEFAULT') ? PASSWORD_DEFAULT : 'unknown';
    
    if (empty($username)) {
        $error = 'Please provide a username.';
    } else {
        // Check for too many failed attempts
        if (checkLoginThrottle($username)) {
            $error = 'Too many failed login attempts. Please try again later.';
        } else {
            // Query for user with this username (NO PASSWORD REQUIRED FOR TESTING)
            $stmt = safeQueryPrepare($conn, 
                "SELECT u.*, r.name as role_name 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Skip password verification - allow login with username only
                if (true) { // Always authenticate when user exists (password-less mode)
                    $debug['hash_info'] = [
                        'cost' => preg_match('/^\$2y\$(\d+)\$/', $user['password'], $matches) ? $matches[1] : 'unknown'
                    ];
                    
                    // Generate a fresh hash for comparison
                    $fresh_hash = createPasswordHash($password);
                    $debug['fresh_hash_prefix'] = substr($fresh_hash, 0, 13) . '...';
                    
                    // Try password verification with logging
                    $startTime = microtime(true);
                    // Password-less login: Always succeed when user exists
                    $debug['password_verified'] = true;
                    
                    // Check if this is a first login requiring password reset
                    $needsReset = false;
                    if (isset($user['password_reset_required']) && $user['password_reset_required'] == 1) {
                        $needsReset = true;
                        // Store in session that password reset is required
                        $_SESSION['password_reset_required'] = true;
                    }
                    
                    // Login successful - set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'];
                    $_SESSION['user_role'] = $user['role_name'];
                    
                    // Role-specific session data
                    handleRoleSpecificData($conn, $user);
                    
                    // Log the successful login
                    logActivity($conn, $user['id'], 'Login', 'User logged in successfully (password-less mode)', $_SERVER['REMOTE_ADDR']);
                    
                    // Reset failed attempts
                    resetLoginAttempts($username);
                    
                    // Redirect to dashboard
                    redirect(getDashboardUrl(), 'Login successful!', 'success');
                }
            } else {
                // No user found
                $debug['user_found'] = false;
                recordFailedLogin($username);
                $error = 'Invalid username or password.';
            }
        }
    }
}

// Helper function to handle role-specific data
function handleRoleSpecificData($conn, $user) {
    switch ($user['role_name']) {
        case 'manager':
            // Get manager's accommodation assignment (pick the first one if multiple)
            $m_stmt = safeQueryPrepare($conn, "SELECT ua.accommodation_id, a.name
                                               FROM user_accommodation ua
                                               JOIN accommodations a ON ua.accommodation_id = a.id
                                               WHERE ua.user_id = ?
                                               ORDER BY a.name LIMIT 1");
            if ($m_stmt) {
                $m_stmt->bind_param("i", $user['id']);
                $m_stmt->execute();
                $manager = $m_stmt->get_result()->fetch_assoc();
            }

            if (!empty($manager)) {
                // Use accommodation_id as manager_id for downstream pages expecting this value
                $_SESSION['manager_id'] = (int)$manager['accommodation_id'];
                $_SESSION['accommodation_id'] = (int)$manager['accommodation_id'];
                $_SESSION['manager_name'] = $user['first_name'];
                $_SESSION['accommodation_name'] = $manager['name'];
            }
            break;
            
        case 'student':
            // Get student details
            $s_stmt = safeQueryPrepare($conn, "SELECT * FROM students WHERE user_id = ?");
            $s_stmt->bind_param("i", $user['id']);
            $s_stmt->execute();
            $student = $s_stmt->get_result()->fetch_assoc();
            
            if ($student) {
                $_SESSION['student_id'] = $student['id'];
            }
            break;
            
        case 'owner':
            // Additional owner data if needed
            $_SESSION['owner_id'] = $user['id'];
            break;
    }
}

// Set active page for navigation
$activePage = "login";

// Add extra CSS for login page
$extraCss = '<style>
    body {
        background-image: var(--primary-gradient);
        height: 100vh;
        align-items: center;
    }
    .login-card {
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    .debug-info {
        font-size: 0.8rem;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 10px;
        margin-top: 15px;
    }
</style>';

require_once '../includes/components/header.php';
?>

<div class="animated-bg">
    <div class="bg-bubble"></div>
    <div class="bg-bubble"></div>
    <div class="bg-bubble"></div>
    <div class="bg-bubble"></div>
</div>

<div class="container login-container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card login-card shadow-lg mt-5">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h3 class="mb-0"><i class="bi bi-wifi me-2"></i> <?= APP_NAME ?></h3>
                    <p class="mb-0 mt-2">Sign in to your account</p>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <?php echo csrfField(); ?>
                        <div class="mb-4">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                            <small class="text-muted d-block mt-2">Testing Mode: Password not required</small>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary py-2">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!empty($debug) && isset($_GET['debug'])): ?>
                    <div class="debug-info mt-4">
                        <h6>Debug Information:</h6>
                        <div class="d-flex justify-content-end mb-2">
                            <a href="?debug=1&reset_test=1" class="btn btn-sm btn-outline-secondary">Create Test User</a>
                        </div>
                        <pre><?= json_encode($debug, JSON_PRETTY_PRINT) ?></pre>
                        
                        <div class="mt-3">
                            <h6>Password Troubleshooting:</h6>
                            <p class="small text-muted">If you're having issues logging in:</p>
                            <ol class="small">
                                <li>Try <a href="?debug=1&reset_test=1">creating a test user</a> and logging in with username "testuser" and password "test123"</li>
                                <li>Check if there are whitespace issues with your password</li>
                                <li>Ensure caps lock is not enabled</li>
                                <li>Try resetting your password if possible</li>
                            </ol>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center py-3">
                    <p class="mb-0">Need help? <a href="contact.php" class="text-primary fw-bold">Contact Support</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
</body>
</html>
