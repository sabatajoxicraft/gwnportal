<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Get accommodation ID from URL
$accommodation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($accommodation_id <= 0) {
    redirect(BASE_URL . '/admin/accommodations.php', 'Invalid accommodation ID', 'danger');
}

$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $owner_id = (int)($_POST['owner_id'] ?? 0);
    
    if (empty($name) || $owner_id <= 0) {
        $error = 'Name and owner are required fields.';
    } else {
        $stmt = safeQueryPrepare($conn, "UPDATE accommodations SET name = ?, owner_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $owner_id, $accommodation_id);
        
        if ($stmt->execute()) {
            $success = 'Accommodation updated successfully.';
        } else {
            $error = 'Failed to update accommodation: ' . $conn->error;
        }
    }
}

// Get accommodation details
$stmt = safeQueryPrepare($conn, "SELECT * FROM accommodations WHERE id = ?");
$stmt->bind_param("i", $accommodation_id);
$stmt->execute();
$accommodation = $stmt->get_result()->fetch_assoc();

if (!$accommodation) {
    redirect(BASE_URL . '/admin/accommodations.php', 'Accommodation not found', 'danger');
}

// Get owners (users with owner role)
$owners_stmt = safeQueryPrepare($conn, "SELECT u.id, u.first_name, u.last_name, u.email 
                                    FROM users u 
                                    JOIN roles r ON u.role_id = r.id 
                                    WHERE r.name = 'owner' AND u.status = 'active'
                                    ORDER BY u.first_name, u.last_name");
$owners_stmt->execute();
$owners = $owners_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "Edit Accommodation";
$activePage = "accommodations";

// Include header
require_once '../../includes/components/header.php';

// Include navigation
require_once '../../includes/components/navigation.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/accommodations.php">Accommodations</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Edit Accommodation</h5>
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
                    <label for="name" class="form-label">Accommodation Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($accommodation['name']) ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="owner_id" class="form-label">Owner</label>
                    <select class="form-select" id="owner_id" name="owner_id" required>
                        <option value="">-- Select Owner --</option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= $owner['id'] ?>" <?= $owner['id'] == $accommodation['owner_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name'] . ' (' . $owner['email'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="<?= BASE_URL ?>/admin/accommodations.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Accommodation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
