<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

requireRole('admin');

$conn = getDbConnection();
$user_id = (int)($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    redirect(BASE_URL . '/admin/users.php', 'Invalid user ID', 'danger');
}

// Get user details
$stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !in_array($user['role_name'], ['manager', 'student'])) {
    redirect(BASE_URL . '/admin/users.php', 'User not found or cannot be assigned to accommodations.', 'danger');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $accommodation_id = (int)($_POST['accommodation_id'] ?? 0);

    if ($accommodation_id <= 0) {
        redirect(BASE_URL . "/admin/assign-accommodation.php?user_id={$user_id}", 'Please select an accommodation.', 'danger');
    }

    // Check if already assigned
    $check = $conn->prepare("SELECT 1 FROM user_accommodation WHERE user_id = ? AND accommodation_id = ?");
    $check->bind_param("ii", $user_id, $accommodation_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        redirect(BASE_URL . "/admin/view-user.php?id={$user_id}", 'User is already assigned to that accommodation.', 'warning');
    }

    $ins = $conn->prepare("INSERT INTO user_accommodation (user_id, accommodation_id) VALUES (?, ?)");
    $ins->bind_param("ii", $user_id, $accommodation_id);
    if ($ins->execute()) {
        // If student, also update students.accommodation_id
        if ($user['role_name'] === 'student') {
            $conn->prepare("UPDATE students SET accommodation_id = ? WHERE user_id = ?")->execute([$accommodation_id, $user_id]);
        }
        logActivity($conn, $_SESSION['user_id'], 'assign_accommodation', "Assigned {$user['first_name']} {$user['last_name']} (ID {$user_id}) to accommodation ID {$accommodation_id}", $_SERVER['REMOTE_ADDR']);
        redirect(BASE_URL . "/admin/view-user.php?id={$user_id}", 'Accommodation assigned successfully.', 'success');
    } else {
        redirect(BASE_URL . "/admin/assign-accommodation.php?user_id={$user_id}", 'Failed to assign accommodation.', 'danger');
    }
}

// Get all accommodations
$accommodations = $conn->query("SELECT id, name FROM accommodations ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get already assigned
$assigned = $conn->prepare("SELECT accommodation_id FROM user_accommodation WHERE user_id = ?");
$assigned->bind_param("i", $user_id);
$assigned->execute();
$assignedIds = array_column($assigned->get_result()->fetch_all(MYSQLI_ASSOC), 'accommodation_id');

$available = array_filter($accommodations, fn($a) => !in_array($a['id'], $assignedIds));

$pageTitle = "Assign Accommodation";
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <?php displayFlashMessage(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-building-add me-2"></i>Assign Accommodation</h2>
        <a href="view-user.php?id=<?= $user_id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Assign <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                        <span class="badge bg-<?= $user['role_name'] === 'manager' ? 'primary' : 'success' ?>"><?= ucfirst($user['role_name']) ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($available) > 0): ?>
                        <form method="post">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label for="accommodation_id" class="form-label">Select Accommodation</label>
                                <select class="form-select" id="accommodation_id" name="accommodation_id" required>
                                    <option value="">-- Choose --</option>
                                    <?php foreach ($available as $a): ?>
                                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-building-add me-2"></i>Assign
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mb-0">This user is already assigned to all available accommodations.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
