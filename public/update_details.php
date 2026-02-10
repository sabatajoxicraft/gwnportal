<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure database connection is available
$conn = getDbConnection();

// Require login (redirects to login page if not logged in)
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php', 'Please login to access this page', 'warning');
}

// Get user role and ensure student access
$userRole = $_SESSION['user_role'] ?? '';
if ($userRole != 'student') {
    redirect(BASE_URL . '/dashboard.php', 'Access denied. This page is for students only.', 'danger');
}

// Set page title
$pageTitle = "Update Contact Details";
$activePage = "update_details";

// Initialize variables
$success = false;
$error = null;
$user = [];

// Get user ID - use user_id instead of student_id
$userId = $_SESSION['user_id'] ?? 0;

// Get student details
$stmt = safeQueryPrepare($conn, "SELECT * FROM users WHERE id = ?");
if ($stmt === false) {
    $error = "Unable to retrieve your details. Please try again later.";
} else {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Get additional student information if needed
    $stmt_student = safeQueryPrepare($conn, "SELECT * FROM students WHERE user_id = ?");
    if ($stmt_student !== false) {
        $stmt_student->bind_param("i", $userId);
        $stmt_student->execute();
        $student = $stmt_student->get_result()->fetch_assoc();
        
        // Merge student info into user array if records found
        if ($student) {
            $user = array_merge($user, $student);
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    // Get form data
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $whatsapp = trim($_POST['whatsapp_number'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $preferred_communication = $_POST['preferred_communication'] ?? 'SMS';
    
    // Basic validation
    $errors = [];
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // Validate ID number if provided and not already set
    if (!empty($id_number) && empty($user['id_number'])) {
        if (strlen($id_number) !== 13 || !ctype_digit($id_number)) {
            $errors[] = "ID Number must be 13 digits";
        } else {
            // Check if ID number is already in use
            $check_id = safeQueryPrepare($conn, "SELECT id FROM users WHERE id_number = ? AND id != ?");
            if ($check_id !== false) {
                $check_id->bind_param("si", $id_number, $userId);
                $check_id->execute();
                if ($check_id->get_result()->num_rows > 0) {
                    $errors[] = "This ID Number is already registered";
                }
            }
        }
    }
    
    // If no errors, update the user details
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update user table
            $update_fields = "email = ?, phone_number = ?, whatsapp_number = ?, preferred_communication = ?";
            $params = [$email, $phone, $whatsapp, $preferred_communication];
            $types = "ssss";
            
            // Only update ID number if it's not already set
            if (!empty($id_number) && empty($user['id_number'])) {
                $update_fields .= ", id_number = ?";
                $params[] = $id_number;
                $types .= "s";
            }
            
            $params[] = $userId;
            $types .= "i";
            
            $stmt_update = safeQueryPrepare($conn, "UPDATE users SET $update_fields WHERE id = ?");
            if ($stmt_update !== false) {
                $stmt_update->bind_param($types, ...$params);
                if (!$stmt_update->execute()) {
                    throw new Exception("Failed to update user details");
                }
                
                // Update student table with room number
                $stmt_student_update = safeQueryPrepare($conn, "UPDATE students SET room_number = ? WHERE user_id = ?");
                if ($stmt_student_update !== false) {
                    $stmt_student_update->bind_param("si", $room_number, $userId);
                    if (!$stmt_student_update->execute()) {
                        throw new Exception("Failed to update room number");
                    }
                }
                
                // Log activity
                logActivity($conn, $userId, "Profile Updated", "User updated their contact details");
                
                // Commit transaction
                $conn->commit();
                
                $success = true;
                // Update session variables if needed
                $_SESSION['user_email'] = $email;
                
                // Refresh user data
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                
                // Refresh student data
                if ($stmt_student !== false) {
                    $stmt_student->execute();
                    $student = $stmt_student->get_result()->fetch_assoc();
                    if ($student) {
                        $user = array_merge($user, $student);
                    }
                }
            } else {
                throw new Exception("System error when preparing update statement");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Update Contact Details</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Your details have been updated successfully!
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly>
                            <small class="text-muted">Username can only be changed by your accommodation manager</small>
                        </div>

                        <div class="mb-3">
                            <label for="id_number" class="form-label">ID Number</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" 
                                value="<?= htmlspecialchars($user['id_number'] ?? '') ?>" readonly>
                            <small class="text-muted">ID Number can only be changed by your accommodation manager</small>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                    value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                <input type="tel" class="form-control" id="whatsapp_number" name="whatsapp_number" 
                                    value="<?= htmlspecialchars($user['whatsapp_number'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="room_number" name="room_number" 
                                value="<?= htmlspecialchars($user['room_number'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Preferred Communication</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="preferred_communication" id="comm_sms" value="SMS"
                                    <?= ($user['preferred_communication'] ?? 'SMS') === 'SMS' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="comm_sms">
                                    SMS
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="preferred_communication" id="comm_whatsapp" value="WhatsApp"
                                    <?= ($user['preferred_communication'] ?? '') === 'WhatsApp' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="comm_whatsapp">
                                    WhatsApp
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Update Details</button>
                            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
