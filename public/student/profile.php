<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$pageTitle = "My Profile";
$activePage = "student-profile";

// Ensure the user is logged in as student
requireRole('student');

$userId = $_SESSION['user_id'] ?? 0;
$studentId = $_SESSION['student_id'] ?? 0;

$conn = getDbConnection();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid security token. Please try again.";
        header("Location: profile.php");
        exit;
    }
    
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp = trim($_POST['whatsapp_number'] ?? '');
    $preferredCommunication = $_POST['preferred_communication'] ?? 'WhatsApp';
    
    $errors = [];
    
    // Validate email
    if (!empty($email) && !validateEmail($email)) {
        $errors[] = "Invalid email format.";
    }
    
    // Validate phone
    if (!empty($phone) && !validatePhone($phone)) {
        $errors[] = "Invalid phone number format.";
    }
    
    // Validate WhatsApp
    if (!empty($whatsapp) && !validatePhone($whatsapp)) {
        $errors[] = "Invalid WhatsApp number format.";
    }
    
    if (empty($errors)) {
        // Update user record (not students table) - phone, email, whatsapp, preferred_communication are in users table
        $stmt = safeQueryPrepare($conn, "UPDATE users SET email = ?, phone_number = ?, whatsapp_number = ?, preferred_communication = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $email, $phone, $whatsapp, $preferredCommunication, $userId);
        
        if ($stmt->execute()) {
            // Log activity
            logActivity($conn, $userId, 'Profile Update', "Student updated profile information");
            
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit;
        } else {
            $errors[] = "Failed to update profile. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid security token. Please try again.";
        header("Location: profile.php");
        exit;
    }
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate inputs
    if (empty($currentPassword)) {
        $errors[] = "Current password is required.";
    }
    
    if (empty($newPassword)) {
        $errors[] = "New password is required.";
    } elseif (strlen($newPassword) < 8) {
        $errors[] = "New password must be at least 8 characters long.";
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match.";
    }
    
    if (empty($errors)) {
        // Verify current password
        $stmt = safeQueryPrepare($conn, "SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect.";
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = safeQueryPrepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                // Log activity
                logActivity($conn, $userId, 'Password Change', "Student changed password");
                
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: profile.php");
                exit;
            } else {
                $errors[] = "Failed to change password. Please try again.";
            }
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
    }
}

// Get student information
$stmt = safeQueryPrepare($conn, "SELECT s.*, u.username, u.email as user_email, u.phone_number, u.whatsapp_number, u.preferred_communication,
                                 CONCAT(u.first_name, ' ', u.last_name) as full_name,
                                 a.name as accommodation_name
                          FROM students s
                          INNER JOIN users u ON s.user_id = u.id
                          LEFT JOIN accommodations a ON s.accommodation_id = a.id
                          WHERE s.id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

include '../../includes/components/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>My Profile</h1>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
            
            <?php include '../../includes/components/messages.php'; ?>
            
            <div class="row">
                <!-- Profile Information Form -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-student text-white">
                            <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <!-- Read-only Information -->
                            <div class="mb-4">
                                <h6 class="text-muted mb-3">Account Details (Read-only)</h6>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Student Number:</strong></label>
                                    <p class="form-control-plaintext"><?= htmlEscape($student['username']) ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Full Name:</strong></label>
                                    <p class="form-control-plaintext"><?= htmlEscape($student['full_name']) ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Accommodation:</strong></label>
                                    <p class="form-control-plaintext"><?= htmlEscape($student['accommodation_name'] ?? 'Not assigned') ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Status:</strong></label>
                                    <p class="form-control-plaintext">
                                        <span class="badge bg-<?= $student['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= htmlEscape(ucfirst($student['status'])) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Editable Information -->
                            <h6 class="text-muted mb-3">Contact Information</h6>
                            <form method="POST" action="profile.php">
                                <?= csrfField() ?>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlEscape($student['email'] ?? $student['user_email']) ?>">
                                    <small class="form-text text-muted">Used for voucher delivery and notifications</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlEscape($student['phone_number'] ?? '') ?>" 
                                           placeholder="+27123456789">
                                    <small class="form-text text-muted">Format: +27123456789</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                    <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number" 
                                           value="<?= htmlEscape($student['whatsapp_number'] ?? '') ?>" 
                                           placeholder="+27123456789">
                                    <small class="form-text text-muted">Used for WhatsApp voucher delivery</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Preferred Communication Method</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="preferred_communication" 
                                                id="pref_whatsapp" value="WhatsApp" <?= ($student['preferred_communication'] ?? 'WhatsApp') === 'WhatsApp' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pref_whatsapp">WhatsApp</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="preferred_communication" 
                                                id="pref_sms" value="SMS" <?= ($student['preferred_communication'] ?? '') === 'SMS' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pref_sms">SMS</label>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted" id="whatsapp_hint" style="display:none;">WhatsApp number is required when WhatsApp is your preferred method.</small>
                                </div>
                                
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var waField = document.getElementById('whatsapp_number');
                                    function toggleHint() {
                                        var hint = document.getElementById('whatsapp_hint');
                                        var isWa = document.getElementById('pref_whatsapp').checked;
                                        hint.style.display = (isWa && !waField.value) ? 'block' : 'none';
                                    }
                                    document.querySelectorAll('input[name="preferred_communication"]').forEach(function(r) {
                                        r.addEventListener('change', toggleHint);
                                    });
                                    waField.addEventListener('input', toggleHint);
                                    toggleHint();
                                });
                                </script>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Change Password Form -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="profile.php">
                                <?= csrfField() ?>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" required minlength="8">
                                    <small class="form-text text-muted">Minimum 8 characters</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required minlength="8">
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Security Tips -->
                    <div class="card mt-3">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Password Security Tips</h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Use at least 8 characters</li>
                                <li>Mix uppercase and lowercase letters</li>
                                <li>Include numbers and special characters</li>
                                <li>Don't use easily guessable information</li>
                                <li>Don't share your password with anyone</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/components/footer.php'; ?>
