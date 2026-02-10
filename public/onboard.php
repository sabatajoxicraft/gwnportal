<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$pageTitle = "Account Onboarding";
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Initialize session variables for onboarding if not already set
if (!isset($_SESSION['onboarding'])) {
    $_SESSION['onboarding'] = [
        'code' => '',
        'user_role' => '',
        'role_id' => '',
        'entity_id' => '', // Use a generic entity_id instead of separate fields for each role
        'accommodation_id' => '',
        'accommodation_name' => '',
        'step' => 1
    ];
}

// Get connection
$conn = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Validate onboarding code
    if (isset($_POST['validate_code'])) {
        $code = trim($_POST['code']);
        
        // Debug
        error_log("Validating code: $code");
        
        // Use a revised query that matches the actual database schema
        $stmt = safeQueryPrepare(
            $conn, 
            "SELECT oc.*, 
                    a.name as accommodation_name,
                    r.id as role_id, r.name as role_name
             FROM onboarding_codes oc
             LEFT JOIN accommodations a ON oc.accommodation_id = a.id
             LEFT JOIN roles r ON oc.role_id = r.id
             WHERE oc.code = ? AND oc.status = 'unused' AND oc.expires_at > NOW()"
        );
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $codeData = $result->fetch_assoc();
            
            // Determine role based on role_id from the onboarding code
            $userRole = strtolower($codeData['role_name'] ?? 'student');
            $roleId = $codeData['role_id'];
            $accommodationId = $codeData['accommodation_id'];
            
            // Code is valid, save to session and proceed to step 2
            $_SESSION['onboarding'] = [
                'code' => $code,
                'user_role' => $userRole,
                'role_id' => $roleId,
                'accommodation_id' => $accommodationId,
                'accommodation_name' => $codeData['accommodation_name'],
                'step' => 2,
                'code_id' => $codeData['id']
            ];
            
            // Debug
            error_log("Code valid, moving to step 2. Role: {$userRole}, Accommodation: {$accommodationId}");
            
            // Redirect to step 2
            redirect(BASE_URL . '/onboard.php?step=2');
        } else {
            $error = "Invalid or expired onboarding code. Please try again.";
            error_log("Invalid code attempted: $code");
        }
    }
    
    // Step 2: Create user account
    if (isset($_POST['create_account'])) {
        // Verify session data is available
        if (!isset($_SESSION['onboarding']) || empty($_SESSION['onboarding']['user_role']) || empty($_SESSION['onboarding']['accommodation_id'])) {
            $error = "Session expired or invalid. Please start over.";
            error_log("Missing onboarding session data at step 2: " . print_r($_SESSION['onboarding'] ?? [], true));
            redirect(BASE_URL . '/onboard.php');
        }
        
        // Get form data
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate form data
        if (empty($firstName) || empty($lastName) || empty($email) || 
            empty($password)) {
            $error = "All required fields must be completed.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            // All validation passed, create user account
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Format phone number if provided
                $formattedPhone = !empty($phone) ? formatPhoneNumber($phone) : '';
                
                // Create password hash
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Check if email already exists
                $stmt = safeQueryPrepare($conn, "SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("Email address is already registered.");
                }
                
                // Get role ID based on user_role
                $roleId = $_SESSION['onboarding']['role_id'];
                
                // Generate username
                $username = strtolower(substr($firstName, 0, 1) . $lastName) . rand(100, 999);
                
                // Debug
                error_log("Creating user with username: $username, email: $email, role_id: $roleId");
                
                // Create user
                $stmt = safeQueryPrepare($conn, "INSERT INTO users (username, password, email, first_name, last_name, phone_number, role_id, status, created_at) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->bind_param("ssssssi", $username, $passwordHash, $email, $firstName, $lastName, $formattedPhone, $roleId);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create user: " . $conn->error);
                }
                
                $userId = $conn->insert_id;
                error_log("User created with ID: $userId");
                
                // Role-specific updates
                if ($_SESSION['onboarding']['user_role'] === 'manager') {
                    // Link manager to accommodation
                    error_log("Linking manager user_id: $userId to accommodation_id: {$_SESSION['onboarding']['accommodation_id']}");
                    
                    $stmt = safeQueryPrepare($conn, "INSERT INTO user_accommodation (user_id, accommodation_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $userId, $_SESSION['onboarding']['accommodation_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to link manager to accommodation: " . $conn->error);
                    }
                } elseif ($_SESSION['onboarding']['user_role'] === 'student') {
                    error_log("Creating student record with user_id: $userId and accommodation_id: {$_SESSION['onboarding']['accommodation_id']}");
                    
                    $stmt = safeQueryPrepare($conn, "INSERT INTO students (user_id, accommodation_id, status) VALUES (?, ?, 'active')");
                    $stmt->bind_param("ii", $userId, $_SESSION['onboarding']['accommodation_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to create student record: " . $conn->error);
                    }
                }
                
                // Update onboarding code to used and mark who used it
                $stmt = safeQueryPrepare($conn, "UPDATE onboarding_codes SET status = 'used', used_by = ?, used_at = NOW() WHERE code = ?");
                $stmt->bind_param("is", $userId, $_SESSION['onboarding']['code']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update onboarding code: " . $conn->error);
                }
                
                // Commit transaction
                $conn->commit();
                error_log("Transaction committed successfully");
                
                // Set user session
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_role'] = $_SESSION['onboarding']['user_role'];
                $_SESSION['username'] = $username;
                
                if ($_SESSION['onboarding']['user_role'] === 'manager') {
                    $_SESSION['manager_id'] = $userId;
                } elseif ($_SESSION['onboarding']['user_role'] === 'student') {
                    $_SESSION['student_id'] = $userId;
                }
                
                // Log activity
                logActivity($conn, $userId, "User Onboarding", $_SESSION['onboarding']['user_role'] . " account created via onboarding code");
                
                // Set success message and redirect to final step
                $_SESSION['onboarding_success'] = true;
                $_SESSION['onboarding_role'] = $_SESSION['onboarding']['user_role'];
                
                // Clear onboarding data after setting success flags
                $_SESSION['onboarding'] = null;
                
                redirect(BASE_URL . '/onboard.php?step=3');
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error creating account: " . $e->getMessage();
                error_log("Onboarding error: " . $e->getMessage() . " - Stack trace: " . $e->getTraceAsString());
            }
        }
    }
}

// Enforce step progression
if ($step > 1 && (!isset($_SESSION['onboarding']) || $_SESSION['onboarding']['step'] < $step)) {
    // If trying to access a step without completing previous steps
    if ($step < 3) { // Allow step 3 for success page
        redirect(BASE_URL . '/onboard.php');
    }
}

// Load the appropriate step view
require_once '../includes/components/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Account Onboarding - Step <?= $step ?></h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if ($step == 1): ?>
                        <!-- Step 1: Enter Onboarding Code -->
                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                            <div class="mb-4 text-center">
                                <p>Enter the onboarding code provided to you.</p>
                            </div>
                            <div class="mb-3">
                                <label for="code" class="form-label">Onboarding Code</label>
                                <input type="text" class="form-control form-control-lg text-center" 
                                    id="code" name="code" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" 
                                    placeholder="Enter your code" required autofocus>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="validate_code" class="btn btn-primary btn-lg">Continue</button>
                            </div>
                        </form>
                    <?php elseif ($step == 2): ?>
                        <!-- Step 2: Create Account -->
                        <div class="mb-4">
                            <?php if ($_SESSION['onboarding']['user_role'] === 'manager'): ?>
                                <p>Create your manager account for <strong><?= htmlspecialchars($_SESSION['onboarding']['accommodation_name'] ?? '') ?></strong></p>
                            <?php elseif ($_SESSION['onboarding']['user_role'] === 'student'): ?>
                                <p>Create your student account for <strong><?= htmlspecialchars($_SESSION['onboarding']['accommodation_name'] ?? '') ?></strong></p>
                            <?php else: ?>
                                <p>Create your account for <strong><?= htmlspecialchars($_SESSION['onboarding']['accommodation_name'] ?? '') ?></strong></p>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . '?step=2' ?>">
                            <div class="row mb-3">
                                <div class="col">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                        value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                        value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Minimum 8 characters</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid gap-2">
                                <input type="hidden" name="step" value="2">
                                <button type="submit" name="create_account" class="btn btn-primary">Create Account</button>
                            </div>
                        </form>
                    <?php elseif ($step == 3): ?>
                        <!-- Step 3: Success -->
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="mb-3">Account Created Successfully</h3>
                            <p class="mb-4">Your <?= htmlspecialchars($_SESSION['onboarding_role'] ?? 'user') ?> account has been created. You can now access the dashboard.</p>
                            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
