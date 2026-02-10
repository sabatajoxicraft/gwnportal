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

if (!$user) {
    redirect('users.php', 'User not found.', 'danger');
}

$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $roleId = $_POST['role_id'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($username) || empty($email) || empty($firstName) || empty($lastName) || empty($roleId) || empty($status)) {
        $error = 'All fields are required.';
    } else {
        $stmt = safeQueryPrepare($conn, "UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role_id = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssisi", $username, $email, $firstName, $lastName, $roleId, $status, $userId);

        if ($stmt->execute()) {
            $success = true;
            $user = getUserDetails($userId); // Refresh user details
        } else {
            $error = 'Error updating user: ' . $conn->error;
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
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="role_id" class="form-label">Role</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="1" <?= $user['role_id'] == 1 ? 'selected' : '' ?>>Admin</option>
                                <option value="2" <?= $user['role_id'] == 2 ? 'selected' : '' ?>>Owner</option>
                                <option value="3" <?= $user['role_id'] == 3 ? 'selected' : '' ?>>Manager</option>
                                <option value="4" <?= $user['role_id'] == 4 ? 'selected' : '' ?>>Student</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="pending" <?= $user['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
