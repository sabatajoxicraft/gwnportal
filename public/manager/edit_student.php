<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Ensure database connection is available
$conn = getDbConnection();

// Require login and manager role
if (!isLoggedIn() || $_SESSION['user_role'] !== 'manager') {
    redirect(BASE_URL . '/login.php', 'Please login as a manager to access this page', 'warning');
}

// Initialize variables
$pageTitle = "Edit Student Details";
$activePage = "students";
$success = false;
$error = null;
$student = [];
$manager_id = $_SESSION['user_id'] ?? 0;
$student_id = intval($_GET['id'] ?? 0);

// Validate that student exists and belongs to manager's accommodation
$stmt = safeQueryPrepare($conn, "SELECT u.*, s.room_number, s.accommodation_id, s.id as student_id, 
                                a.name as accommodation_name
                              FROM users u 
                              JOIN students s ON u.id = s.user_id
                              JOIN accommodations a ON s.accommodation_id = a.id
                              JOIN user_accommodation ua ON ua.accommodation_id = a.id
                              WHERE s.id = ? AND ua.user_id = ? AND u.role_id = 4");

if ($stmt === false) {
    $error = "System error when preparing statement.";
} else {
    $stmt->bind_param("ii", $student_id, $manager_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        redirect(BASE_URL . '/manager/students.php', 'Student not found or you do not have permission to edit this student', 'danger');
    }
    
    $student = $result->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    // Get form data
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');
    $preferred_communication = $_POST['preferred_communication'] ?? 'SMS';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    
    // Basic validation
    $errors = [];
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    if (!empty($id_number)) {
        if (strlen($id_number) !== 13 || !ctype_digit($id_number)) {
            $errors[] = "ID Number must be 13 digits";
        } else {
            // Check if ID number is already in use by another user
            $check_id = safeQueryPrepare($conn, "SELECT id FROM users WHERE id_number = ? AND id != ?");
            if ($check_id !== false) {
                $check_id->bind_param("si", $id_number, $student['id']);
                $check_id->execute();
                if ($check_id->get_result()->num_rows > 0) {
                    $errors[] = "This ID Number is already registered";
                }
            }
        }
    }
    
    // Check if username already exists (if changed)
    if ($username !== $student['username']) {
        $check_username = safeQueryPrepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
        if ($check_username !== false) {
            $check_username->bind_param("si", $username, $student['id']);
            $check_username->execute();
            if ($check_username->get_result()->num_rows > 0) {
                $errors[] = "This username is already taken";
            }
        }
    }
    
    // If no errors, update the student details
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Set the session variable to indicate this is a manager editing a student
            $conn->query("SET @current_user_is_manager_of_student = TRUE");
            
            // Update user table with all fields including username and ID if changed
            $stmt_update = safeQueryPrepare($conn, 
                "UPDATE users SET 
                 email = ?, 
                 username = ?, 
                 id_number = ?, 
                 phone_number = ?, 
                 whatsapp_number = ?,
                 preferred_communication = ?,
                 first_name = ?,
                 last_name = ?
                 WHERE id = ?");
                 
            if ($stmt_update !== false) {
                $stmt_update->bind_param("ssssssssi", 
                    $email, 
                    $username, 
                    $id_number, 
                    $phone_number, 
                    $whatsapp_number,
                    $preferred_communication,
                    $first_name,
                    $last_name,
                    $student['id']
                );
                
                if (!$stmt_update->execute()) {
                    throw new Exception("Failed to update student details: " . $conn->error);
                }
            } else {
                throw new Exception("Failed to prepare update statement");
            }
            
            // Update student table room number
            $stmt_student_update = safeQueryPrepare($conn, "UPDATE students SET room_number = ? WHERE user_id = ?");
            if ($stmt_student_update !== false) {
                $stmt_student_update->bind_param("si", $room_number, $student['id']);
                if (!$stmt_student_update->execute()) {
                    throw new Exception("Failed to update room number: " . $conn->error);
                }
            }
            
            // Reset the session variable
            $conn->query("SET @current_user_is_manager_of_student = FALSE");
            
            // Log activity
            logActivity($conn, $manager_id, "Student Updated", 
                "Manager updated student {$student['first_name']} {$student['last_name']} (#{$student_id})");
            
            // Commit transaction
            $conn->commit();
            
            $success = true;
            
            // Refresh student data
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/manager/students.php">Students</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Student</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Edit Student Details</h4>
                    <span class="badge bg-info"><?= htmlspecialchars($student['accommodation_name'] ?? '') ?></span>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Student details have been updated successfully!
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $student_id) ?>">
                        <?php echo csrfField(); ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                    value="<?= htmlspecialchars($student['first_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                    value="<?= htmlspecialchars($student['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                value="<?= htmlspecialchars($student['username'] ?? '') ?>" required>
                            <small class="text-muted">As a manager, you can change the student's username</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_number" class="form-label">ID Number</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" 
                                value="<?= htmlspecialchars($student['id_number'] ?? '') ?>"
                                placeholder="13-digit South African ID number">
                            <small class="text-muted">As a manager, you can change or set the student's ID number</small>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                    value="<?= htmlspecialchars($student['phone_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                <input type="tel" class="form-control" id="whatsapp_number" name="whatsapp_number" 
                                    value="<?= htmlspecialchars($student['whatsapp_number'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="room_number" name="room_number" 
                                value="<?= htmlspecialchars($student['room_number'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Preferred Communication</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="preferred_communication" id="comm_sms" value="SMS"
                                    <?= ($student['preferred_communication'] ?? 'SMS') === 'SMS' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="comm_sms">
                                    SMS
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="preferred_communication" id="comm_whatsapp" value="WhatsApp"
                                    <?= ($student['preferred_communication'] ?? '') === 'WhatsApp' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="comm_whatsapp">
                                    WhatsApp
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>/manager/students.php" class="btn btn-outline-secondary">Back to Students</a>
                            <button type="submit" class="btn btn-primary">Update Student</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
