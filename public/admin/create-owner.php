<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $generate_password = isset($_POST['generate_password']);
    $password = $generate_password ? generateRandomPassword() : trim($_POST['password'] ?? '');
    $send_credentials = isset($_POST['send_credentials']);
    
    // Validate input
    if (empty($username) || empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'All required fields must be completed';
    } elseif (!$generate_password && (empty($password) || strlen($password) < 8)) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $conn = getDbConnection();
        
        // Check if username already exists
        $stmt = safeQueryPrepare($conn, "SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username already exists. Please choose a different username.';
        } else {
            // Check if email already exists
            $stmt = safeQueryPrepare($conn, "SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email address is already in use. Please use a different email.';
            } else {
                // Get owner role ID
                $role_stmt = safeQueryPrepare($conn, "SELECT id FROM roles WHERE name = 'owner'");
                $role_stmt->execute();
                $role_result = $role_stmt->get_result();
                
                if ($role_result->num_rows === 0) {
                    $error = 'Owner role not found in the database';
                } else {
                    $role_id = $role_result->fetch_assoc()['id'];
                    
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Create the owner
                    $stmt = safeQueryPrepare($conn, "INSERT INTO users (username, password, email, first_name, last_name, 
                                                           phone_number, role_id, status) 
                                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                    
                    $stmt->bind_param("ssssssi", $username, $hashed_password, $email, $first_name, $last_name, 
                                       $phone_number, $role_id);
                    
                    if ($stmt->execute()) {
                        $owner_id = $stmt->insert_id;
                        
                        // Send credentials to the owner if requested
                        if ($send_credentials && !empty($email)) {
                            $subject = "Your " . APP_NAME . " Account Credentials";
                            $message = "Hello $first_name $last_name,\n\n";
                            $message .= "An account has been created for you on " . APP_NAME . ".\n\n";
                            $message .= "Your login credentials are:\n";
                            $message .= "Username: $username\n";
                            $message .= "Password: $password\n\n";
                            $message .= "Please login at " . BASE_URL . "/login.php\n\n";
                            $message .= "We recommend changing your password after your first login.\n\n";
                            $message .= "Regards,\n" . APP_NAME . " Admin";
                            
                            $headers = "From: noreply@" . $_SERVER['SERVER_NAME'] . "\r\n";
                            
                            if (mail($email, $subject, $message, $headers)) {
                                $success = "Owner account created successfully and credentials sent to $email";
                            } else {
                                $success = "Owner account created successfully but failed to send credentials email";
                            }
                        } else {
                            $success = "Owner account created successfully";
                            if ($generate_password) {
                                $success .= ". Generated password: <strong>$password</strong>";
                            }
                        }
                    } else {
                        $error = 'Failed to create owner account: ' . $conn->error;
                    }
                }
            }
        }
    }
}

// Set page title
$pageTitle = "Create Owner Account";
$activePage = "users";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/users.php">Users</a></li>
            <li class="breadcrumb-item active">Create Owner</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Create New Owner Account</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <div class="mb-4">
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary me-2">Back to Users</a>
                    <a href="<?= BASE_URL ?>/admin/create-owner.php" class="btn btn-primary">Create Another Owner</a>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <?php echo csrfField(); ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="generate_password" name="generate_password" onchange="togglePasswordField()">
                            <label class="form-check-label" for="generate_password">
                                Generate random password
                            </label>
                        </div>
                    </div>
                    
                    <div id="password_field" class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                        <div class="form-text">Password must be at least 8 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="send_credentials" name="send_credentials">
                            <label class="form-check-label" for="send_credentials">
                                Send login credentials via email
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Owner Account</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function togglePasswordField() {
    const generatePassword = document.getElementById('generate_password');
    const passwordField = document.getElementById('password_field');
    const passwordInput = document.getElementById('password');
    
    if (generatePassword.checked) {
        passwordField.style.display = 'none';
        passwordInput.required = false;
    } else {
        passwordField.style.display = 'block';
        passwordInput.required = true;
    }
}
</script>

<?php require_once '../../includes/components/footer.php'; ?>
