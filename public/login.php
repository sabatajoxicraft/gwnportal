<?php
/**
 * Login Page
 * Authenticates users and initiates sessions using UserService
 * 
 * Refactored to use service-oriented architecture:
 * - UserService::authenticate() for password verification
 * - ActivityLogger for login tracking
 * - Centralized error handling and logging
 */

// Include page template (provides $conn, $currentUserId, $currentUserRole)
include '../includes/page-template.php';

$pageTitle = "Login";

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$loginError = '';

/**
 * Handle login form submission
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $loginError = 'Invalid security token. Please try again.';
        ActivityLogger::logAuthEvent(null, 'login_failure', false, $_SERVER['REMOTE_ADDR'] ?? 'unknown', 'CSRF token invalid');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($username) || empty($password)) {
            $loginError = 'Please provide both username and password.';
        } else {
            // Use UserService to authenticate user
            $user = UserService::authenticate($conn, $username, $password);
            
            if ($user) {
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $loginError = 'Your account is not active. Please contact your accommodation manager.';
                    ActivityLogger::logAuthEvent(
                        null,
                        'login_failure',
                        false,
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'Account inactive: ' . $username
                    );
                } else {
                    $roleName = strtolower($user['role_name'] ?? (getRoleName($user['role_id'] ?? null) ?? ''));
                    $user['role_name'] = $roleName;

                    // Set up session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'] ?? $user['username'];
                    $_SESSION['user_role'] = $roleName ?: null;
                    $_SESSION['role'] = $roleName ?: null;
                    $_SESSION['role_id'] = $user['role_id'];
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Set up role-specific session data
                    setupRoleSpecificSession($conn, $user);
                    
                    // Log successful login
                    ActivityLogger::logAuthEvent(
                        $user['id'],
                        'login_success',
                        true,
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'User logged in successfully'
                    );
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                // Authentication failed - invalid credentials
                $loginError = 'Invalid username or password.';
                ActivityLogger::logAuthEvent(
                    null,
                    'login_failure',
                    false,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'Failed login attempt: ' . $username
                );
            }
        }
    }
}

$showLoginHelper = getenv('SHOW_LOGIN_HELPER') === '1';
$loginHelperUsers = [];

if ($showLoginHelper) {
    $stmt = safeQueryPrepare(
        $conn,
        "SELECT u.username, u.status, r.name AS role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY r.name, u.username"
    );
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $loginHelperUsers[] = $row;
        }
        $stmt->close();
    }
}

/**
 * Setup role-specific session data
 * Called after successful authentication to populate role-specific session variables
 * 
 * @param mysqli $conn Database connection
 * @param array $user User record from UserService::authenticate()
 */
function setupRoleSpecificSession($conn, $user) {
    $roleName = strtolower($user['role_name'] ?? (getRoleName($user['role_id'] ?? null) ?? ''));
    if ($roleName === 'manager') {
        // Get manager's assigned accommodations
        $accommodations = QueryService::getUserAccommodations($conn, $user['id'], 'manager');
        if (!empty($accommodations)) {
            $_SESSION['accommodation_id'] = $accommodations[0]['id'];
            $_SESSION['accommodation_name'] = $accommodations[0]['name'];
        }
    } elseif ($roleName === 'student') {
        // Get student record and set student-specific session data
        $student = StudentService::getStudentRecord($conn, $user['id']);
        if ($student) {
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['accommodation_id'] = $student['accommodation_id'] ?? null;
        }
    } elseif ($roleName === 'owner') {
        // Owner has access to all their accommodations
        $_SESSION['owner_id'] = $user['id'];
    }
}

// Styling for login page
$extraCss = '
<style>
    body.login-page {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            <h3><i class="bi bi-wifi"></i> GWN Portal</h3>
            <p class="small">WiFi Access Management</p>
        </div>
        
        <!-- Card Body -->
        <div class="card-body p-5">
            <!-- Login Error Alert -->
            <?php if (!empty($loginError)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-circle flex-shrink-0"></i>
                    <div><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="" novalidate>
                <!-- CSRF Token -->
                <?= csrfField(); ?>
                
                <!-- Username Field -->
                <div class="mb-3">
                    <label for="username" class="form-label fw-bold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                        <input 
                            type="text" 
                            class="form-control border-start-0" 
                            id="username" 
                            name="username" 
                            required 
                            autofocus 
                            placeholder="Enter your username"
                        >
                    </div>
                </div>
                
                <!-- Password Field -->
                <div class="mb-4">
                    <label for="password" class="form-label fw-bold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                        <input 
                            type="password" 
                            class="form-control border-start-0" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Enter your password"
                        >
                    </div>
                </div>
                
                <!-- Login Button -->
                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold py-2">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </div>
                
                <!-- Help Links -->
                <div class="text-center">
                    <small class="text-muted">
                        <a href="help.php" class="text-primary text-decoration-none">Need help?</a> • 
                        <a href="contact.php" class="text-primary text-decoration-none">Contact Support</a>
                    </small>
                </div>
            </form>
        </div>
        
        <!-- Card Footer - Development Notice -->
        <div class="card-footer bg-light border-top">
            <?php if ($showLoginHelper): ?>
                <div class="alert alert-warning mb-3 small">
                    <strong>Login Helper (Dev Only)</strong>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($loginHelperUsers)): ?>
                                    <tr>
                                        <td colspan="3" class="text-muted">No users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($loginHelperUsers as $helperUser): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($helperUser['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($helperUser['role_name'] ?? 'unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($helperUser['status'] ?? 'unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <div class="alert alert-info mb-0 small">
                <strong>Development Setup:</strong><br>
                Load test data with: <br>
                <code class="d-block mt-2 p-2 bg-white rounded border">mysql gwn_wifi_system &lt; db/fixtures/test-data.sql</code>
            </div>
        </div>
    </div>
</div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
