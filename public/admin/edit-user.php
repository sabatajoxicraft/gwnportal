<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$pageTitle = "Edit User";

// Ensure database connection is available
$conn = getDbConnection();

// Check if the user is logged in and has admin privileges
requireAdminLogin();

$userId = $_GET['id'] ?? null;

if (!$userId || !is_numeric($userId)) {
    redirect('users.php', 'Invalid user ID.', 'danger');
}

$user = getUserDetails($userId);
$user = is_array($user) ? array_change_key_case($user, CASE_LOWER) : $user;

if (!$user) {
    redirect('users.php', 'User not found.', 'danger');
}

// Get student details if this is a student (role_id = 4)
$student = null;
if ($user['role_id'] == 4) {
    $studentStmt = safeQueryPrepare($conn, "SELECT s.*, a.name as accommodation_name FROM students s LEFT JOIN accommodations a ON s.accommodation_id = a.id WHERE s.user_id = ?");
    $studentStmt->bind_param("i", $userId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc();
}

$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $email = $_POST['email'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $idNumber = $_POST['id_number'] ?? '';
    $phoneNumber = $_POST['phone_number'] ?? '';
    $whatsappNumber = $_POST['whatsapp_number'] ?? '';
    $preferredComm = $_POST['preferred_communication'] ?? 'SMS';
    $roleId = $_POST['role_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $roomNumber = $_POST['room_number'] ?? '';

    if (empty($email) || empty($firstName) || empty($lastName) || empty($roleId) || empty($status)) {
        $error = 'Email, name, role, and status are required.';
    } else {
        // Check for duplicate email (excluding current user)
        $dupStmt = safeQueryPrepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
        $dupStmt->bind_param("si", $email, $userId);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            $error = 'This email is already in use by another user.';
        } elseif ($student && !empty($roomNumber)) {
            // Update student room number if editing a student
            $roomStmt = safeQueryPrepare($conn, "UPDATE students SET room_number = ? WHERE user_id = ?");
            $roomStmt->bind_param("si", $roomNumber, $userId);
            $roomStmt->execute();
        }
        
        if (empty($error)) {
            $stmt = safeQueryPrepare($conn, "UPDATE users SET email = ?, first_name = ?, last_name = ?, id_number = ?, phone_number = ?, whatsapp_number = ?, preferred_communication = ?, role_id = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssssisi", $email, $firstName, $lastName, $idNumber, $phoneNumber, $whatsappNumber, $preferredComm, $roleId, $status, $userId);

            if ($stmt->execute()) {
                $success = true;
                logActivity($conn, $_SESSION['user_id'], 'edit_user', "Updated user (ID {$userId})", $_SERVER['REMOTE_ADDR']);
                $user = getUserDetails($userId);
                $user = is_array($user) ? array_change_key_case($user, CASE_LOWER) : $user;
                if ($student) {
                    $studentStmt = safeQueryPrepare($conn, "SELECT s.*, a.name as accommodation_name FROM students s LEFT JOIN accommodations a ON s.accommodation_id = a.id WHERE s.user_id = ?");
                    $studentStmt->bind_param("i", $userId);
                    $studentStmt->execute();
                    $student = $studentStmt->get_result()->fetch_assoc();
                }
            } else {
                $error = 'Error updating user: ' . $conn->error;
            }
        }
    }
}

require_once '../../includes/components/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit User</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>User updated successfully!
                        </div>
                    <?php elseif (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?php echo csrfField(); ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username <small class="text-muted">(read-only)</small></label>
                                <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <small class="text-muted">Username cannot be changed. Contact support if needed.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="id_number" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="id_number" name="id_number" value="<?= htmlspecialchars($user['id_number'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                <small class="text-muted">Must be unique</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                <input type="tel" class="form-control" id="whatsapp_number" name="whatsapp_number" value="<?= htmlspecialchars($user['whatsapp_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="preferred_communication" class="form-label">Preferred Communication</label>
                                <select class="form-select" id="preferred_communication" name="preferred_communication">
                                    <option value="SMS" <?= ($user['preferred_communication'] ?? 'SMS') == 'SMS' ? 'selected' : '' ?>>SMS</option>
                                    <option value="WhatsApp" <?= ($user['preferred_communication'] ?? 'SMS') == 'WhatsApp' ? 'selected' : '' ?>>WhatsApp</option>
                                </select>
                            </div>
                        </div>

                        <?php if ($student): ?>
                            <hr class="my-4">
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Student Information</strong>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="accommodation" class="form-label">Accommodation</label>
                                    <input type="text" class="form-control" id="accommodation" value="<?= htmlspecialchars($student['accommodation_name'] ?? 'Not assigned') ?>" disabled>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="room_number" class="form-label">Room Number</label>
                                    <input type="text" class="form-control" id="room_number" name="room_number" value="<?= htmlspecialchars($student['room_number'] ?? '') ?>">
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role_id" name="role_id" required>
                                    <option value="1" <?= $user['role_id'] == 1 ? 'selected' : '' ?>>Admin</option>
                                    <option value="2" <?= $user['role_id'] == 2 ? 'selected' : '' ?>>Owner</option>
                                    <option value="3" <?= $user['role_id'] == 3 ? 'selected' : '' ?>>Manager</option>
                                    <option value="4" <?= $user['role_id'] == 4 ? 'selected' : '' ?>>Student</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="pending" <?= $user['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-grid">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">Update User</button>
                                <a href="view-user.php?id=<?= $userId ?>" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
