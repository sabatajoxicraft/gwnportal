<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Require owner login
requireOwnerLogin();

$owner_id = $_SESSION['user_id'] ?? 0;
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

// Get all owner's accommodations first
$owner_accommodations = [];
$stmt_owner_acc = safeQueryPrepare($conn, "SELECT id, name FROM accommodations WHERE owner_id = ? ORDER BY name");
if ($stmt_owner_acc !== false) {
    $stmt_owner_acc->bind_param("i", $owner_id);
    $stmt_owner_acc->execute();
    $owner_accommodations = $stmt_owner_acc->get_result()->fetch_all(MYSQLI_ASSOC);
}

// If no accommodations, redirect to create one
if (empty($owner_accommodations)) {
    redirect(BASE_URL . '/owner-setup.php', 'Please create an accommodation first.', 'info');
}

// Store in session for switcher component
$_SESSION['manager_accommodations'] = $owner_accommodations;

// Handle accommodation switching
if (isset($_GET['switch_accommodation'])) {
    $switch_to = (int)$_GET['switch_accommodation'];
    // Verify this accommodation belongs to the owner
    $valid = false;
    foreach ($owner_accommodations as $acc) {
        if ($acc['id'] === $switch_to) {
            $valid = true;
            break;
        }
    }
    if ($valid) {
        $_SESSION['current_accommodation'] = ['id' => $switch_to];
        // Redirect to clean URL
        header('Location: ' . BASE_URL . '/managers.php');
        exit;
    }
}

// Get current accommodation from session or URL or default to first
$accommodation_id = 0;
if (isset($_GET['accommodation_id']) && $_GET['accommodation_id'] > 0) {
    $accommodation_id = (int)$_GET['accommodation_id'];
} elseif (isset($_SESSION['current_accommodation']['id'])) {
    $accommodation_id = $_SESSION['current_accommodation']['id'];
} else {
    // Default to first accommodation
    $accommodation_id = $owner_accommodations[0]['id'];
    $_SESSION['current_accommodation'] = ['id' => $accommodation_id];
}

// Verify accommodation belongs to this owner and get details
$accommodation = null;
$stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations WHERE id = ? AND owner_id = ?");
if ($stmt !== false) {
    $stmt->bind_param("ii", $accommodation_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $accommodation = $result->fetch_assoc();
    } else {
        // Invalid accommodation, redirect to first one
        $accommodation_id = $owner_accommodations[0]['id'];
        $_SESSION['current_accommodation'] = ['id' => $accommodation_id];
        redirect(BASE_URL . '/managers.php');
    }
}

// Handle adding a new manager
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manager'])) {
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
    
    // If assign_manager checkbox is checked, assign to current accommodation
    $assign_to_accommodation = isset($_POST['assign_manager']) ? $accommodation_id : 0;
    
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

                        // Send credentials email to the new manager
                        $email_subject = 'Your ' . APP_NAME . ' Account';
                        $email_body = "Hello " . $first_name . ",\n\n"
                            . "Your manager account has been created.\n\n"
                            . "Username: " . $username . "\n"
                            . "Password: " . $password . "\n"
                            . "Role: Manager\n\n"
                            . "Login at: " . ABSOLUTE_APP_URL . "/login.php\n\n"
                            . "Please change your password after your first login.";
                        $email_sent = sendAppEmail($email, $email_subject, $email_body);

                        if ($email_sent) {
                            $success = 'Manager account created successfully! Credentials have been sent to ' . $email . '.';
                            $flash_type = 'success';
                        } else {
                            $success = 'Manager account created, but the credentials email could not be sent.';
                            $flash_type = 'warning';
                        }

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
                                            redirect(BASE_URL . '/managers.php?accommodation_id=' . $assign_to_accommodation, $success, $flash_type);
                                        }
                                    }
                                }
                            }
                        }

                        // If we didn't redirect above, redirect to main managers page
                        redirect(BASE_URL . '/managers.php', $success, $flash_type);
                    } else {
                        $error = 'Failed to create manager account. Please try again.';
                    }
                }
            }
        }
    }
}

// Handle manager status update
if (isset($_GET['action']) && ($_GET['action'] === 'activate' || $_GET['action'] === 'deactivate') && isset($_GET['manager_id'])) {
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
            redirect(BASE_URL . '/managers.php', 'Manager status updated successfully!', 'success');
        } else {
            $error = 'Failed to update manager status. Please try again.';
        }
    }
}

// Get all managers for the current accommodation
$managers_query = "SELECT u.id AS manager_id, u.username, u.first_name, u.last_name, u.email, u.status AS manager_status,
                  ua.accommodation_id
                  FROM users u 
                  JOIN user_accommodation ua ON u.id = ua.user_id
                  WHERE ua.accommodation_id = ? AND u.role_id = 3
                  ORDER BY u.first_name, u.last_name";
$stmt_managers = safeQueryPrepare($conn, $managers_query);
$managers = [];
if ($stmt_managers !== false) {
    $stmt_managers->bind_param("i", $accommodation_id);
    $stmt_managers->execute();
    $managers = $stmt_managers->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get available users with manager role who aren't already managers for this accommodation
$available_managers = [];
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

// Set page title
$pageTitle = "Managers - " . htmlspecialchars($accommodation['name']);
$activePage = "managers";
require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <?php require_once '../includes/components/messages.php'; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $pageTitle ?></h2>
        <div>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addManagerModal">
                <i class="bi bi-person-plus"></i> Assign Manager
            </button>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createManagerModal">
                <i class="bi bi-person-plus-fill"></i> Create New Manager
            </button>
        </div>
    </div>

    <!-- Accommodation Switcher Bar Component -->
    <?php include __DIR__ . '/../includes/components/accommodation-switcher-bar.php'; ?>

    <!-- Managers List Card -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="bi bi-people"></i> Managers for <?= htmlspecialchars($accommodation['name']) ?>
            </h5>
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
                                        <span class="badge <?= $manager['manager_status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= ucfirst($manager['manager_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($manager['manager_status'] === 'active'): ?>
                                                <a href="?action=deactivate&manager_id=<?= $manager['manager_id'] ?>" 
                                                   class="btn btn-outline-warning" title="Deactivate">
                                                    <i class="bi bi-pause"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=activate&manager_id=<?= $manager['manager_id'] ?>" 
                                                   class="btn btn-outline-success" title="Activate">
                                                    <i class="bi bi-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <form method="post" onsubmit="return confirm('Remove this manager from the accommodation?');" style="display:inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="manager_id" value="<?= $manager['manager_id'] ?>">
                                                <input type="hidden" name="accommodation_id" value="<?= $accommodation_id ?>">
                                                <button type="submit" name="unassign_manager" class="btn btn-outline-danger btn-sm" title="Unassign">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i> No managers assigned to this accommodation yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Manager Modal -->
<div class="modal fade" id="addManagerModal" tabindex="-1" aria-labelledby="addManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <?php echo csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addManagerModalLabel">Assign Manager</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($available_managers)): ?>
                        <div class="alert alert-info">
                            <p><i class="bi bi-info-circle"></i> No available managers found.</p>
                            <p>All existing managers are already assigned to this accommodation. <strong>Create a new manager account</strong> to add more staffing.</p>
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
                            <div class="form-text">Select an available manager to assign to this accommodation.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($available_managers)): ?>
                        <button type="submit" name="add_manager" class="btn btn-primary">Assign Manager</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

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
                            <input class="form-check-input" type="checkbox" id="assign_manager" name="assign_manager" checked onchange="toggleAccommodationSelect()">
                            <label class="form-check-label" for="assign_manager">
                                Assign to current accommodation
                            </label>
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
    // Just toggle - the accommodation is automatically set from current context
}
</script>

<?php require_once '../includes/components/footer.php'; ?>