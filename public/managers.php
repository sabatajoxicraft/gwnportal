<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require owner login
requireOwnerLogin();

$owner_id = $_SESSION['user_id'];
$conn = getDbConnection();

// Handle unassign manager action
if (isset($_POST['unassign_manager']) && isset($_POST['manager_id']) && isset($_POST['accommodation_id'])) {
    requireCsrfToken();
    $manager_id = (int)$_POST['manager_id'];
    $accommodation_id_unassign = (int)$_POST['accommodation_id'];

    // Verify the assignment belongs to one of the owner's accommodations
    $stmt = safeQueryPrepare($conn, 
        "SELECT ua.user_id FROM user_accommodation ua
         JOIN accommodations a ON ua.accommodation_id = a.id
         JOIN users u ON ua.user_id = u.id
         JOIN roles r ON u.role_id = r.id
         WHERE ua.user_id = ? AND ua.accommodation_id = ? AND a.owner_id = ? AND r.name = 'manager'");

    if ($stmt !== false) {
        $stmt->bind_param("iii", $manager_id, $accommodation_id_unassign, $owner_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Delete the manager assignment for this accommodation
            $delete_stmt = safeQueryPrepare($conn, "DELETE FROM user_accommodation WHERE user_id = ? AND accommodation_id = ?");
            if ($delete_stmt !== false) {
                $delete_stmt->bind_param("ii", $manager_id, $accommodation_id_unassign);
                if ($delete_stmt->execute()) {
                    $success = "Manager successfully unassigned from accommodation.";

                    // Log activity
                    logActivity($conn, $_SESSION['user_id'], 'Manager Unassigned', 'Manager ID: ' . $manager_id . ' was unassigned from accommodation ' . $accommodation_id_unassign);
                } else {
                    $error = "Failed to unassign manager. Please try again.";
                }
            } else {
                $error = "Database error. Please try again later.";
            }
        } else {
            $error = "Invalid manager or you don't have permission to modify this manager.";
        }
    } else {
        $error = "Database error. Please try again later.";
    }
}

// Check if a specific accommodation is requested
$accommodation_id = isset($_GET['accommodation_id']) ? (int)$_GET['accommodation_id'] : 0;
$specific_accommodation = false;
$accommodation = null;

// If accommodation ID is provided, verify it belongs to this owner
if ($accommodation_id > 0) {
    $stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations WHERE id = ? AND owner_id = ?");
    if ($stmt !== false) {
        $stmt->bind_param("ii", $accommodation_id, $owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $accommodation = $result->fetch_assoc();
            $specific_accommodation = true;
        } else {
            // Invalid accommodation ID or doesn't belong to owner
            redirect(BASE_URL . '/accommodations.php', 'Accommodation not found or you do not have permission to manage it.', 'danger');
        }
    }
}

// Handle adding a new manager (only when viewing a specific accommodation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $specific_accommodation && isset($_POST['add_manager'])) {
    requireCsrfToken();
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($user_id <= 0) {
        $error = 'Please select a valid user.';
    } else {
        // Check if user exists and is not already a manager for this accommodation
        $check_stmt = safeQueryPrepare($conn, "SELECT u.id FROM users u 
                              LEFT JOIN user_accommodation ua ON u.id = ua.user_id AND ua.accommodation_id = ?
                              WHERE u.id = ? AND u.role_id = 3 AND ua.user_id IS NULL");
        if ($check_stmt !== false) {
            $check_stmt->bind_param("ii", $accommodation_id, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                // Do another check to see if they're already a manager
                $already_manager = safeQueryPrepare($conn, "SELECT 1 FROM user_accommodation WHERE user_id = ? AND accommodation_id = ?");
                if ($already_manager !== false) {
                    $already_manager->bind_param("ii", $user_id, $accommodation_id);
                    $already_manager->execute();
                    if ($already_manager->get_result()->num_rows > 0) {
                        $error = 'This user is already a manager for this accommodation.';
                    } else {
                        $error = 'User not found or does not have manager privileges.';
                    }
                }
            } else {
                // Add manager to accommodation
                $insert_stmt = safeQueryPrepare($conn, "INSERT INTO user_accommodation (user_id, accommodation_id) VALUES (?, ?)");
                if ($insert_stmt !== false) {
                    $insert_stmt->bind_param("ii", $user_id, $accommodation_id);
                    if ($insert_stmt->execute()) {
                        redirect(BASE_URL . '/managers.php?accommodation_id=' . $accommodation_id, 'Manager added successfully!', 'success');
                    } else {
                        $error = 'Failed to add manager. Please try again.';
                    }
                }
            }
        }
    }
}

// Handle creating a new manager account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_manager'])) {
    requireCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $assign_to_accommodation = isset($_POST['assign_to_accommodation']) ? (int)$_POST['assign_to_accommodation'] : 0;
    
    // Validate inputs
    if (empty($username)) {
        $error = 'Username is required.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email is required.';
    } elseif (empty($first_name)) {
        $error = 'First name is required.';
    } elseif (empty($last_name)) {
        $error = 'Last name is required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username or email already exists
        $check_stmt = safeQueryPrepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
        if ($check_stmt !== false) {
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = 'Username or email already exists.';
            } else {
                // Create the new manager account (role_id = 3 for manager)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = safeQueryPrepare($conn, 
                    "INSERT INTO users (username, email, password, first_name, last_name, role_id, status) 
                     VALUES (?, ?, ?, ?, ?, 3, 'active')");
                
                if ($insert_stmt !== false) {
                    $insert_stmt->bind_param("sssss", $username, $email, $hashed_password, $first_name, $last_name);
                    
                    if ($insert_stmt->execute()) {
                        $new_user_id = $insert_stmt->insert_id;
                        $success = 'Manager account created successfully!';
                        
                        // If an accommodation was selected, assign the new manager to it
                        if ($assign_to_accommodation > 0) {
                            // Verify accommodation belongs to owner
                            $verify_acc = safeQueryPrepare($conn, "SELECT id FROM accommodations WHERE id = ? AND owner_id = ?");
                            if ($verify_acc !== false) {
                                $verify_acc->bind_param("ii", $assign_to_accommodation, $owner_id);
                                $verify_acc->execute();
                                if ($verify_acc->get_result()->num_rows === 1) {
                                    // Add manager to accommodation
                                    $assign_stmt = safeQueryPrepare($conn, 
                                        "INSERT INTO user_accommodation (user_id, accommodation_id) VALUES (?, ?)" );
                                    if ($assign_stmt !== false) {
                                        $assign_stmt->bind_param("ii", $new_user_id, $assign_to_accommodation);
                                        if ($assign_stmt->execute()) {
                                            $success .= ' Manager has been assigned to the selected accommodation.';
                                            
                                            // Redirect to accommodation-specific managers page
                                            redirect(BASE_URL . '/managers.php?accommodation_id=' . $assign_to_accommodation, $success, 'success');
                                        }
                                    }
                                }
                            }
                        }
                        
                        // If we didn't redirect above, redirect to main managers page
                        redirect(BASE_URL . '/managers.php', $success, 'success');
                    } else {
                        $error = 'Failed to create manager account. Please try again.';
                    }
                }
            }
        }
    }
}

// Handle manager status update
if ($specific_accommodation && isset($_GET['action']) && ($_GET['action'] === 'activate' || $_GET['action'] === 'deactivate') && isset($_GET['manager_id'])) {
    $manager_id = (int)$_GET['manager_id'];
    $new_status = ($_GET['action'] === 'activate') ? 'active' : 'inactive';

    // Update manager (user) status only if assigned to this owner's accommodation
    $update_stmt = safeQueryPrepare($conn, "UPDATE users u
        JOIN user_accommodation ua ON u.id = ua.user_id
        JOIN accommodations a ON ua.accommodation_id = a.id
        SET u.status = ?
        WHERE u.id = ? AND ua.accommodation_id = ? AND a.owner_id = ?");
    if ($update_stmt !== false) {
        $update_stmt->bind_param("siii", $new_status, $manager_id, $accommodation_id, $owner_id);
        if ($update_stmt->execute()) {
            redirect(BASE_URL . '/managers.php?accommodation_id=' . $accommodation_id, 'Manager status updated successfully!', 'success');
        } else {
            $error = 'Failed to update manager status. Please try again.';
        }
    }
}

// Different query depending on whether we're viewing all managers or managers for a specific accommodation
if ($specific_accommodation) {
    // Get all managers for this specific accommodation
    $managers_query = "SELECT u.id AS manager_id, u.username, u.first_name, u.last_name, u.email, u.status AS manager_status,
                      ua.accommodation_id
                      FROM users u 
                      JOIN user_accommodation ua ON u.id = ua.user_id
                      WHERE ua.accommodation_id = ? AND u.role_id = 3
                      ORDER BY u.first_name, u.last_name";
    $stmt_managers = safeQueryPrepare($conn, $managers_query);
    if ($stmt_managers !== false) {
        $stmt_managers->bind_param("i", $accommodation_id);
    }
} else {
    // Get all managers across all accommodations owned by this owner
    $managers_query = "SELECT u.id AS manager_id, u.username, u.first_name, u.last_name, u.email, u.status AS manager_status,
                      a.id as accommodation_id, a.name as accommodation_name
                      FROM users u 
                      JOIN user_accommodation ua ON u.id = ua.user_id
                      JOIN accommodations a ON ua.accommodation_id = a.id
                      WHERE a.owner_id = ? AND u.role_id = 3
                      ORDER BY a.name, u.first_name, u.last_name";
    $stmt_managers = safeQueryPrepare($conn, $managers_query);
    if ($stmt_managers !== false) {
        $stmt_managers->bind_param("i", $owner_id);
    }
}

// Get managers based on the prepared query
$managers = [];
if ($stmt_managers !== false) {
    $stmt_managers->execute();
    $managers = $stmt_managers->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get all accommodations for dropdown when adding managers
$accommodations = [];
if (!$specific_accommodation) {
    $stmt_accommodations = safeQueryPrepare($conn, "SELECT id, name FROM accommodations WHERE owner_id = ? ORDER BY name");
    if ($stmt_accommodations !== false) {
        $stmt_accommodations->bind_param("i", $owner_id);
        $stmt_accommodations->execute();
        $accommodations = $stmt_accommodations->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Get available users with manager role who aren't already managers for this accommodation
$available_managers = [];
if ($specific_accommodation) {
    $available_managers_query = "SELECT u.id, u.username, u.first_name, u.last_name, u.email
                               FROM users u
                               LEFT JOIN user_accommodation ua ON u.id = ua.user_id AND ua.accommodation_id = ?
                               WHERE u.role_id = 3 AND ua.user_id IS NULL
                               ORDER BY u.first_name, u.last_name";
    $stmt_available = safeQueryPrepare($conn, $available_managers_query);
    if ($stmt_available !== false) {
        $stmt_available->bind_param("i", $accommodation_id);
        $stmt_available->execute();
        $available_managers = $stmt_available->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Modify the pageTitle section to reflect the new feature
$pageTitle = $specific_accommodation ? "Managers for " . htmlspecialchars($accommodation['name']) : "Manager Accounts";
$activePage = "managers";
require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $pageTitle ?></h2>
        <div>
            <?php if ($specific_accommodation): ?>
                <a href="managers.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-list"></i> All Managers
                </a>
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addManagerModal">
                    <i class="bi bi-person-plus"></i> Assign Manager
                </button>
            <?php else: ?>
                <a href="accommodations.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-buildings"></i> Accommodations
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createManagerModal">
                <i class="bi bi-person-plus-fill"></i> Create New Manager
            </button>
        </div>
    </div>

    <?php if (!$specific_accommodation): ?>
        <!-- Show manager statistics card -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-bg-primary">
                    <div class="card-body">
                        <?php
                            // Count all manager users for this owner (was counting all managers before)
                            $stmt = safeQueryPrepare($conn,
                                "SELECT COUNT(DISTINCT ua.user_id) AS total
                                 FROM user_accommodation ua
                                 JOIN accommodations a ON ua.accommodation_id = a.id
                                 JOIN users u ON ua.user_id = u.id
                                 WHERE a.owner_id = ? AND u.role_id = 3"
                            );
                            $total_managers = 0;
                            if ($stmt !== false) {
                                $stmt->bind_param("i", $owner_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($row = $result->fetch_assoc()) {
                                    $total_managers = $row['total'];
                                }
                            }
                        ?>
                        <h5 class="card-title">My Managers</h5>
                        <p class="display-4"><?= $total_managers ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">My Manager Accounts</h5>
                    </div>
                    <div class="card-body">
                        <?php
                            // Get all manager users that are assigned to this owner's accommodations
                            $managers_list = [];
                            $stmt = safeQueryPrepare($conn, 
                                "SELECT DISTINCT u.id, u.username, u.email, u.first_name, u.last_name, u.status, u.created_at 
                                FROM users u 
                                JOIN user_accommodation ua ON u.id = ua.user_id
                                JOIN accommodations a ON ua.accommodation_id = a.id
                                WHERE u.role_id = 3 AND a.owner_id = ? 
                                ORDER BY u.created_at DESC");
                            if ($stmt !== false) {
                                $stmt->bind_param("i", $owner_id);
                                $stmt->execute();
                                $managers_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            }
                        ?>
                        
                        <?php if (!empty($managers_list)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Assignments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($managers_list as $manager): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?></td>
                                                <td><?= htmlspecialchars($manager['username']) ?></td>
                                                <td><?= htmlspecialchars($manager['email']) ?></td>
                                                <td>
                                                    <span class="badge <?= $manager['status'] === 'active' ? 'bg-success' : 'bg-warning' ?>">
                                                        <?= ucfirst($manager['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($manager['created_at'])) ?></td>
                                                <td>
                                                    <?php
                                                        // Count assignments for this manager
                                                        $count_stmt = safeQueryPrepare($conn, 
                                                            "SELECT COUNT(*) as count FROM user_accommodation ua
                                                             JOIN accommodations a ON ua.accommodation_id = a.id
                                                             WHERE ua.user_id = ? AND a.owner_id = ?");
                                                        $count = 0;
                                                        if ($count_stmt !== false) {
                                                            $count_stmt->bind_param("ii", $manager['id'], $owner_id);
                                                            $count_stmt->execute();
                                                            $result = $count_stmt->get_result();
                                                            if ($row = $result->fetch_assoc()) {
                                                                $count = $row['count'];
                                                            }
                                                        }
                                                    ?>
                                                    <span class="badge bg-info"><?= $count ?> accommodation<?= $count !== 1 ? 's' : '' ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p><i class="bi bi-info-circle"></i> You haven't created any manager accounts yet.</p>
                                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createManagerModal">
                                    Create Your First Manager
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Display all managers grouped by accommodation -->
        <?php if (!empty($managers)): ?>
            <?php 
            // Group managers by accommodation
            $managers_by_accommodation = [];
            foreach ($managers as $manager) {
                $accommodation_id = $manager['accommodation_id'];
                if (!isset($managers_by_accommodation[$accommodation_id])) {
                    $managers_by_accommodation[$accommodation_id] = [
                        'name' => $manager['accommodation_name'],
                        'managers' => []
                    ];
                }
                $managers_by_accommodation[$accommodation_id]['managers'][] = $manager;
            }
            ?>
            
            <?php foreach ($managers_by_accommodation as $acc_id => $acc_data): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= htmlspecialchars($acc_data['name']) ?></h5>
                        <a href="managers.php?accommodation_id=<?= $acc_id ?>" class="btn btn-sm btn-outline-primary">
                            Manage
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($acc_data['managers'] as $manager): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?></td>
                                            <td><?= htmlspecialchars($manager['username']) ?></td>
                                            <td><?= htmlspecialchars($manager['email']) ?></td>
                                            <td>
                                                <span class="badge <?= $manager['manager_status'] === 'active' ? 'bg-success' : 'bg-warning' ?>">
                                                    <?= ucfirst($manager['manager_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('Are you sure you want to unassign this manager?');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="manager_id" value="<?= $manager['manager_id'] ?>">
                                                    <input type="hidden" name="accommodation_id" value="<?= $manager['accommodation_id'] ?>">
                                                    <button type="submit" name="unassign_manager" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-person-x me-1"></i> Unassign
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <p><i class="bi bi-info-circle"></i> You don't have any managers assigned yet.</p>
                <?php if (!empty($accommodations)): ?>
                    <p>Choose an accommodation to add managers:</p>
                    <div class="list-group mt-3">
                        <?php foreach ($accommodations as $acc): ?>
                            <a href="managers.php?accommodation_id=<?= $acc['id'] ?>" class="list-group-item list-group-item-action">
                                <?= htmlspecialchars($acc['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>You need to <a href="create-accommodation.php">create an accommodation</a> first.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Display managers for a specific accommodation -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Managers for <?= htmlspecialchars($accommodation['name']) ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($managers)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($managers as $manager): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?></td>
                                        <td><?= htmlspecialchars($manager['username']) ?></td>
                                        <td><?= htmlspecialchars($manager['email']) ?></td>
                                        <td>
                                            <span class="badge <?= $manager['manager_status'] === 'active' ? 'bg-success' : 'bg-warning' ?>">
                                                <?= ucfirst($manager['manager_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($manager['manager_status'] === 'active'): ?>
                                                <a href="?accommodation_id=<?= $accommodation_id ?>&action=deactivate&manager_id=<?= $manager['manager_id'] ?>" class="btn btn-sm btn-warning">Deactivate</a>
                                            <?php else: ?>
                                                <a href="?accommodation_id=<?= $accommodation_id ?>&action=activate&manager_id=<?= $manager['manager_id'] ?>" class="btn btn-sm btn-success">Activate</a>
                                            <?php endif; ?>
                                            <form method="post" onsubmit="return confirm('Are you sure you want to unassign this manager?');" style="display:inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="manager_id" value="<?= $manager['manager_id'] ?>">
                                                <input type="hidden" name="accommodation_id" value="<?= $accommodation_id ?>">
                                                <button type="submit" name="unassign_manager" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-person-x me-1"></i> Unassign
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p><i class="bi bi-info-circle"></i> No managers assigned to this accommodation yet.</p>
                        <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addManagerModal">
                            <i class="bi bi-person-plus"></i> Add Manager
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Manager Modal -->
<?php if ($specific_accommodation): ?>
<div class="modal fade" id="addManagerModal" tabindex="-1" aria-labelledby="addManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <?php echo csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addManagerModalLabel">Add Manager to <?= htmlspecialchars($accommodation['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($available_managers)): ?>
                        <div class="alert alert-info">
                            <p><i class="bi bi-info-circle"></i> No available users with manager role found.</p>
                            <p>All manager users have already been assigned to this accommodation or there are no users with manager role.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select Manager</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">-- Select a manager --</option>
                                <?php foreach ($available_managers as $manager): ?>
                                    <option value="<?= $manager['id'] ?>">
                                        <?= htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']) ?> 
                                        (<?= htmlspecialchars($manager['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select a user with manager role to assign to this accommodation.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($available_managers)): ?>
                        <button type="submit" name="add_manager" class="btn btn-primary">Add Manager</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Create New Manager Modal -->
<div class="modal fade" id="createManagerModal" tabindex="-1" aria-labelledby="createManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="">
            <?php echo csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="createManagerModalLabel">Create New Manager Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password_confirm" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="assign_manager" onchange="toggleAccommodationSelect()">
                            <label class="form-check-label" for="assign_manager">
                                Assign to accommodation immediately
                            </label>
                        </div>
                    </div>
                    
                    <div id="accommodation_select_container" style="display: none;">
                        <div class="mb-3">
                            <label for="assign_to_accommodation" class="form-label">Select Accommodation</label>
                            <select class="form-select" id="assign_to_accommodation" name="assign_to_accommodation">
                                <option value="">-- Select an accommodation --</option>
                                <?php foreach ($accommodations as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_manager" class="btn btn-success">Create Manager</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAccommodationSelect() {
    const isChecked = document.getElementById('assign_manager').checked;
    const container = document.getElementById('accommodation_select_container');
    container.style.display = isChecked ? 'block' : 'none';
    
    if (!isChecked) {
        document.getElementById('assign_to_accommodation').value = '';
    }
}
</script>

<?php require_once '../includes/components/footer.php'; ?>