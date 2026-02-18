<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('admin');

$conn = getDbConnection();
$accommodation_id = (int)($_GET['id'] ?? 0);

if ($accommodation_id <= 0) {
    redirect(BASE_URL . '/accommodations/', 'Invalid accommodation ID', 'danger');
}

// Get accommodation details
$stmt = $conn->prepare("SELECT * FROM accommodations WHERE id = ?");
$stmt->bind_param("i", $accommodation_id);
$stmt->execute();
$accommodation = $stmt->get_result()->fetch_assoc();

if (!$accommodation) {
    redirect(BASE_URL . '/accommodations/', 'Accommodation not found', 'danger');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        redirect(BASE_URL . "/admin/assign-users.php?id={$accommodation_id}", 'Invalid user', 'danger');
    }

    if ($action === 'assign') {
        // Check if already assigned
        $check = $conn->prepare("SELECT 1 FROM user_accommodation WHERE user_id = ? AND accommodation_id = ?");
        $check->bind_param("ii", $user_id, $accommodation_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            redirect(BASE_URL . "/admin/assign-users.php?id={$accommodation_id}", 'User is already assigned to this accommodation.', 'warning');
        }

        $ins = $conn->prepare("INSERT INTO user_accommodation (user_id, accommodation_id) VALUES (?, ?)");
        $ins->bind_param("ii", $user_id, $accommodation_id);
        if ($ins->execute()) {
            // If student, also update students.accommodation_id
            $roleCheck = $conn->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
            $roleCheck->bind_param("i", $user_id);
            $roleCheck->execute();
            $roleRow = $roleCheck->get_result()->fetch_assoc();
            if ($roleRow && $roleRow['name'] === 'student') {
                $conn->prepare("UPDATE students SET accommodation_id = ? WHERE user_id = ?")->execute([$accommodation_id, $user_id]);
            }
            logActivity($conn, $_SESSION['user_id'], 'assign_user', "Assigned user ID {$user_id} to accommodation '{$accommodation['name']}' (ID {$accommodation_id})", $_SERVER['REMOTE_ADDR']);
            redirect(BASE_URL . "/admin/assign-users.php?id={$accommodation_id}", 'User assigned successfully.', 'success');
        } else {
            redirect(BASE_URL . "/admin/assign-users.php?id={$accommodation_id}", 'Failed to assign user.', 'danger');
        }

    } elseif ($action === 'remove') {
        $del = $conn->prepare("DELETE FROM user_accommodation WHERE user_id = ? AND accommodation_id = ?");
        $del->bind_param("ii", $user_id, $accommodation_id);
        if ($del->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'remove_user_assignment', "Removed user ID {$user_id} from accommodation '{$accommodation['name']}' (ID {$accommodation_id})", $_SERVER['REMOTE_ADDR']);
            redirect(BASE_URL . "/admin/assign-users.php?id={$accommodation_id}", 'User removed from accommodation.', 'success');
        } else {
            redirect(BASE_URL . "/admin/assign-users.php?id={$accommodation_id}", 'Failed to remove user.', 'danger');
        }
    }
}

// Get currently assigned users
$assigned = $conn->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.username, r.name as role_name
     FROM user_accommodation ua
     JOIN users u ON ua.user_id = u.id
     JOIN roles r ON u.role_id = r.id
     WHERE ua.accommodation_id = ?
     ORDER BY r.name, u.first_name");
$assigned->bind_param("i", $accommodation_id);
$assigned->execute();
$assignedUsers = $assigned->get_result()->fetch_all(MYSQLI_ASSOC);
$assignedIds = array_column($assignedUsers, 'id');

// Get unassigned managers and students available to assign
$available = $conn->query(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.username, r.name as role_name
     FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE r.name IN ('manager', 'student') AND u.status = 'active'
     ORDER BY r.name, u.first_name");
$availableUsers = $available->fetch_all(MYSQLI_ASSOC);
// Filter out already assigned
$availableUsers = array_filter($availableUsers, fn($u) => !in_array($u['id'], $assignedIds));

$pageTitle = "Assign Users - " . $accommodation['name'];
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php displayFlashMessage(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-person-plus me-2"></i>Assign Users</h2>
        <a href="view-accommodation.php?id=<?= $accommodation_id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="alert alert-info mb-4">
        <i class="bi bi-building me-2"></i>
        Accommodation: <strong><?= htmlspecialchars($accommodation['name']) ?></strong>
    </div>

    <div class="row">
        <!-- Currently Assigned -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Assigned Users (<?= count($assignedUsers) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($assignedUsers) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($assignedUsers as $u): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                        <span class="badge bg-<?= $u['role_name'] === 'manager' ? 'primary' : 'success' ?> ms-2"><?= ucfirst($u['role_name']) ?></span>
                                    </div>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this user from the accommodation?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No users assigned yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Available to Assign -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Available Users</h5>
                </div>
                <div class="card-body">
                    <?php if (count($availableUsers) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($availableUsers as $u): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                        <span class="badge bg-<?= $u['role_name'] === 'manager' ? 'primary' : 'success' ?> ms-2"><?= ucfirst($u['role_name']) ?></span>
                                    </div>
                                    <form method="post" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="assign">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Assign">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">All active managers and students are already assigned.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
