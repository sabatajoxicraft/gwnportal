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
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $preferred_communication = $_POST['preferred_communication'] ?? 'SMS';
    $status = $_POST['status'] ?? 'active';
    $role_id = (int)($_POST['role_id'] ?? 0);
    $generate_password = isset($_POST['generate_password']);
    $password = $generate_password ? generateRandomPassword() : trim($_POST['password'] ?? '');
    $send_credentials = isset($_POST['send_credentials']);
    
    // Validate input
    if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || $role_id <= 0) {
        $error = 'Username, first name, last name, email, and role are required fields';
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
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Create the user
                $stmt = safeQueryPrepare($conn, "INSERT INTO users (username, password, email, first_name, last_name, 
                                              phone_number, whatsapp_number, preferred_communication, role_id, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("sssssssssi", $username, $hashed_password, $email, $first_name, $last_name, 
                                  $phone_number, $whatsapp_number, $preferred_communication, $role_id, $status);
                
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    
                    // Send credentials if requested
                    if ($send_credentials && !empty($email)) {
                        $role_name = '';
                        $role_query = safeQueryPrepare($conn, "SELECT name FROM roles WHERE id = ?");
                        $role_query->bind_param("i", $role_id);
                        $role_query->execute();
                        $role_result = $role_query->get_result();
                        if ($role_result->num_rows > 0) {
                            $role_name = ucfirst($role_result->fetch_assoc()['name']);
                        }
                        
                        $subject = "Your " . APP_NAME . " Account";
                        $message = "Hello $first_name $last_name,\n\n";
                        $message .= "An account has been created for you on " . APP_NAME . ".\n\n";
                        $message .= "Your login credentials are:\n";
                        $message .= "Username: $username\n";
                        $message .= "Password: $password\n";
                        $message .= "Role: $role_name\n\n";
                        $message .= "You can login at " . BASE_URL . "/login.php\n\n";
                        $message .= "We recommend changing your password after your first login.\n\n";
                        $message .= "Regards,\n" . APP_NAME . " Admin";
                        
                        $headers = "From: noreply@" . $_SERVER['SERVER_NAME'] . "\r\n";
                        
                        if (mail($email, $subject, $message, $headers)) {
                            $success = "User created successfully and credentials sent to $email";
                        } else {
                            $success = "User created successfully but failed to send credentials email";
                        }
                    } else {
                        $success = "User created successfully";
                        if ($generate_password) {
                            $success .= ". Generated password: <strong>$password</strong>";
                        }
                    }
                    
                    // Reset form or redirect
                    if (isset($_POST['create_and_return'])) {
                        redirect(BASE_URL . '/admin/users.php', $success, 'success');
                    }
                } else {
                    $error = 'Failed to create user: ' . $conn->error;
                }
            }
        }
    }
}

// Get all roles for select dropdown
$conn = getDbConnection();
$roles_stmt = safeQueryPrepare($conn, "SELECT * FROM roles ORDER BY name");
$roles_stmt->execute();
$roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "Create User";
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
            <li class="breadcrumb-item active">Create User</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Create New User</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php echo csrfField(); ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username *</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= $_POST['username'] ?? '' ?>" required>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?= $_POST['first_name'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?= $_POST['last_name'] ?? '' ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= $_POST['email'] ?? '' ?>" required>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="phone_number" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?= $_POST['phone_number'] ?? '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                        <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number" value="<?= $_POST['whatsapp_number'] ?? '' ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="preferred_communication" class="form-label">Preferred Communication</label>
                        <select class="form-select" id="preferred_communication" name="preferred_communication">
                            <option value="SMS" <?= ($_POST['preferred_communication'] ?? '') === 'SMS' ? 'selected' : '' ?>>SMS</option>
                            <option value="WhatsApp" <?= ($_POST['preferred_communication'] ?? '') === 'WhatsApp' ? 'selected' : '' ?>>WhatsApp</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="pending" <?= ($_POST['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="role_id" class="form-label">Role *</label>
                        <select class="form-select" id="role_id" name="role_id" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>" <?= ($_POST['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                    <?= ucfirst($role['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="generate_password" name="generate_password" <?= isset($_POST['generate_password']) ? 'checked' : '' ?> onchange="togglePasswordField()">
                        <label class="form-check-label" for="generate_password">
                            Generate random password
                        </label>
                    </div>
                </div>
                
                <div id="password_field" class="mb-3" <?= isset($_POST['generate_password']) ? 'style="display: none;"' : '' ?>>
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" minlength="8" <?= isset($_POST['generate_password']) ? '' : 'required' ?>>
                    <div class="form-text">Password must be at least 8 characters long</div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="send_credentials" name="send_credentials" <?= isset($_POST['send_credentials']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="send_credentials">
                            Send login credentials via email
                        </label>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
                    <div>
                        <button type="submit" name="create_and_return" class="btn btn-secondary me-2">Create & Return to List</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </div>
            </form>
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

// Ensure the state is correct on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePasswordField();
});
</script>

<?php require_once '../../includes/components/footer.php'; ?>
