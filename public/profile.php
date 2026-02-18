<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Ensure database connection is available
$conn = getDbConnection();

$pageTitle = "My Profile";
$activePage = "profile";

// Require login
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php', 'Please login to access your profile', 'warning');
}

$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['user_role'] ?? '';

// Get user details
$stmt = safeQueryPrepare($conn, 
    "SELECT u.*, r.name as role_name 
     FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    // Check which form was submitted
    if (isset($_POST['update_profile'])) {
        // Basic profile update
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = trim($_POST['phone_number'] ?? '');
        $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');
        $preferredCommunication = $_POST['preferred_communication'] ?? 'WhatsApp';
        
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $error = 'All fields are required';
        } else {
            $stmt = safeQueryPrepare($conn, "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, whatsapp_number = ?, preferred_communication = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $firstName, $lastName, $email, $phone, $whatsappNumber, $preferredCommunication, $userId);
            
            if ($stmt->execute()) {
                // Update session name
                $_SESSION['user_name'] = $firstName;
                $success = 'Profile updated successfully';
                
                // Refresh user data
                $stmt = safeQueryPrepare($conn, 
                    "SELECT u.*, r.name as role_name 
                     FROM users u
                     JOIN roles r ON u.role_id = r.id
                     WHERE u.id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update profile';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters long';
        } else {
            // Verify current password
            if (password_verify($currentPassword, $user['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = safeQueryPrepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    $success = 'Password changed successfully';
                } else {
                    $error = 'Failed to change password';
                }
            } else {
                $error = 'Current password is incorrect';
            }
        }
    }
}

require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h2>My Profile</h2>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    Account Summary
                </div>
                <div class="card-body text-center">
                    <div class="avatar-placeholder mb-3">
                        <i class="bi bi-person-circle display-1"></i>
                    </div>
                    <h5><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                    <span class="badge <?= getRoleBadgeClass($user['role_name']) ?> mb-3"><?= ucfirst($user['role_name']) ?></span>
                    <p class="text-muted mb-1"><?= htmlspecialchars($user['email']) ?></p>
                    <p class="text-muted mb-1">Username: <?= htmlspecialchars($user['username']) ?></p>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Account Status:</span>
                        <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-warning' ?>"><?= ucfirst($user['status']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Joined:</span>
                        <span><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Last Updated:</span>
                        <span><?= date('M j, Y', strtotime($user['updated_at'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    Update Profile
                </div>
                <div class="card-body">
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="update_profile" value="1">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number" 
                                value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" placeholder="+27...">
                        </div>
                        <div class="mb-3">
                            <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                            <input type="text" class="form-control" id="whatsapp_number" name="whatsapp_number" 
                                value="<?= htmlspecialchars($user['whatsapp_number'] ?? '') ?>" placeholder="+27...">
                            <small class="form-text text-muted" id="whatsapp_hint" style="display:none;">WhatsApp number is required when WhatsApp is your preferred method.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preferred Communication Method</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="preferred_communication" 
                                        id="pref_whatsapp" value="WhatsApp" <?= ($user['preferred_communication'] ?? 'WhatsApp') === 'WhatsApp' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pref_whatsapp">WhatsApp</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="preferred_communication" 
                                        id="pref_sms" value="SMS" <?= ($user['preferred_communication'] ?? '') === 'SMS' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pref_sms">SMS</label>
                                </div>
                            </div>
                        </div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var waField = document.getElementById('whatsapp_number');
                            var phoneField = document.getElementById('phone_number');
                            if (phoneField && waField && !waField.value && phoneField.value) {
                                waField.value = phoneField.value;
                            }
                            phoneField.addEventListener('change', function() {
                                if (!waField.value) waField.value = phoneField.value;
                            });
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
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <div class="form-text">Username cannot be changed</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Change Password
                </div>
                <div class="card-body">
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once '../includes/components/footer.php'; 
?>
