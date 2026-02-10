<?php
/**
 * Unified Create Accommodation Page
 * Consolidates public/create-accommodation.php and public/admin/create-accommodation.php
 * 
 * Supports both admin and owner roles:
 * - Admin: Can select any owner for the accommodation
 * - Owner: Automatically sets self as owner
 */
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require owner or admin role
requireRole(['owner', 'admin']);

$conn = getDbConnection();
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$currentUserId = $_SESSION['user_id'];

$error = '';
$owners = [];

// For admin, get owners list
if ($isAdmin) {
    $owners_stmt = safeQueryPrepare($conn, 
        "SELECT u.id, u.first_name, u.last_name, u.email 
         FROM users u 
         JOIN roles r ON u.role_id = r.id 
         WHERE r.name = 'owner' AND u.status = 'active'
         ORDER BY u.first_name, u.last_name");
    if ($owners_stmt) {
        $owners_stmt->execute();
        $owners = $owners_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $name = trim($_POST['name'] ?? '');
    
    // Admin can select owner, Owner uses self
    if ($isAdmin) {
        $owner_id = (int)($_POST['owner_id'] ?? 0);
        // Validate owner exists
        $check = safeQueryPrepare($conn, 
            "SELECT id FROM users u JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ? AND r.name = 'owner'");
        if ($check) {
            $check->bind_param("i", $owner_id);
            $check->execute();
            if (!$check->get_result()->num_rows) {
                $error = 'Invalid owner selected.';
            }
        }
    } else {
        $owner_id = $currentUserId;
    }
    
    // Validate name required
    if (empty($name)) {
        $error = 'Please provide an accommodation name.';
    }
    
    // Check for duplicate name (admin only check)
    if (empty($error) && $isAdmin) {
        $check_stmt = safeQueryPrepare($conn, "SELECT id FROM accommodations WHERE name = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("s", $name);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = 'An accommodation with this name already exists.';
            }
        }
    }
    
    // Insert accommodation
    if (empty($error)) {
        $stmt = safeQueryPrepare($conn, 
            "INSERT INTO accommodations (name, owner_id) VALUES (?, ?)");
        
        if ($stmt === false) {
            $error = "Database error. Please try again later.";
        } else {
            $stmt->bind_param("si", $name, $owner_id);
            
            if ($stmt->execute()) {
                $accom_id = $conn->insert_id;
                
                // Log activity
                logActivity($conn, $currentUserId, 'create_accommodation', "Created new accommodation: $name");
                
                // Redirect based on role
                if ($isAdmin) {
                    redirect(BASE_URL . '/view-accommodation.php?id=' . $accom_id, 
                        'Accommodation created successfully.', 'success');
                } else {
                    redirect(BASE_URL . '/accommodations/', 
                        'Accommodation created successfully!', 'success');
                }
            } else {
                $error = 'Failed to create accommodation. Please try again.';
            }
        }
    }
}

// Set page variables
$pageTitle = $isAdmin ? "Create Accommodation" : "Add New Accommodation";
$activePage = "accommodations";

// Include header
require_once '../../includes/components/header.php';

// Include navigation for admin
if ($isAdmin) {
    require_once '../../includes/components/navigation.php';
}
?>

<div class="container mt-4">
    <?php if ($isAdmin): ?>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/accommodations/">Accommodations</a></li>
            <li class="breadcrumb-item active">Create</li>
        </ol>
    </nav>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h<?= $isAdmin ? '5' : '4' ?> class="mb-0"><?= $isAdmin ? 'Create New' : 'Add New' ?> Accommodation</h<?= $isAdmin ? '5' : '4' ?>>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($isAdmin && count($owners) === 0): ?>
                        <div class="alert alert-warning">
                            <h5>No owners available</h5>
                            <p>You need to create at least one owner account before you can create an accommodation.</p>
                            <a href="<?= BASE_URL ?>/admin/create-owner.php" class="btn btn-primary">Create Owner Account</a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="">
                            <?php echo csrfField(); ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Accommodation Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                            </div>
                            
                            <?php if ($isAdmin): ?>
                            <div class="mb-3">
                                <label for="owner_id" class="form-label">Owner *</label>
                                <select class="form-select" id="owner_id" name="owner_id" required>
                                    <option value="">-- Select Owner --</option>
                                    <?php foreach ($owners as $owner): ?>
                                        <option value="<?= $owner['id'] ?>" <?= (($_POST['owner_id'] ?? '') == $owner['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name'] . ' (' . $owner['email'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>/accommodations/" class="btn btn-<?= $isAdmin ? 'secondary' : 'outline-secondary' ?>">Cancel</a>
                                <button type="submit" class="btn btn-primary">Create Accommodation</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
