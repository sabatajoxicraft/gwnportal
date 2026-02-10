<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

$error = '';
$success = '';

$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $owner_id = (int)($_POST['owner_id'] ?? 0);
    
    if (empty($name) || $owner_id <= 0) {
        $error = 'Name and owner are required fields.';
    } else {
        // Check if accommodation with same name already exists
        $check_stmt = safeQueryPrepare($conn, "SELECT id FROM accommodations WHERE name = ?");
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'An accommodation with this name already exists.';
        } else {
            // Create new accommodation
            $stmt = safeQueryPrepare($conn, "INSERT INTO accommodations (name, owner_id) VALUES (?, ?)");
            $stmt->bind_param("si", $name, $owner_id);
            
            if ($stmt->execute()) {
                $accommodation_id = $stmt->insert_id;
                redirect(BASE_URL . '/view-accommodation.php?id=' . $accommodation_id, 'Accommodation created successfully.', 'success');
            } else {
                $error = 'Failed to create accommodation: ' . $conn->error;
            }
        }
    }
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
$pageTitle = "Create Accommodation";
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
            <li class="breadcrumb-item active">Create</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Create New Accommodation</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if (count($owners) === 0): ?>
                <div class="alert alert-warning">
                    <h5>No owners available</h5>
                    <p>You need to create at least one owner account before you can create an accommodation.</p>
                    <a href="<?= BASE_URL ?>/admin/create-owner.php" class="btn btn-primary">Create Owner Account</a>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <?php echo csrfField(); ?>
                    <div class="mb-3">
                        <label for="name" class="form-label">Accommodation Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="owner_id" class="form-label">Owner</label>
                        <select class="form-select" id="owner_id" name="owner_id" required>
                            <option value="">-- Select Owner --</option>
                            <?php foreach ($owners as $owner): ?>
                                <option value="<?= $owner['id'] ?>">
                                    <?= htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name'] . ' (' . $owner['email'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/admin/accommodations.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Accommodation</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
