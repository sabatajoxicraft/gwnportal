<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require proper role - admin, owner, or manager
$userRole = $_SESSION['user_role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if (!in_array($userRole, ['admin', 'owner', 'manager'])) {
    redirect(BASE_URL . '/dashboard.php', 'You do not have permission to create codes', 'danger');
}

$error = '';
$success = '';
$generated_codes = [];

// Define which roles each user type can create codes for
$allowedRoles = [];
if ($userRole === 'admin') {
    // Admin can create codes for Owner, Manager, Student
    $allowedRoles = ['owner', 'manager', 'student'];
} elseif ($userRole === 'owner') {
    // Owner can create codes for Manager, Student
    $allowedRoles = ['manager', 'student'];
} elseif ($userRole === 'manager') {
    // Manager can only create codes for Student
    $allowedRoles = ['student'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $accommodation_id = (int)($_POST['accommodation_id'] ?? 0);
    $role_id = (int)($_POST['role_id'] ?? 0);
    $count = (int)($_POST['count'] ?? 1);
    $expiry_days = (int)($_POST['expiry_days'] ?? CODE_EXPIRY_DAYS);
    
    $conn = getDbConnection();
    
    // Verify the role being created is allowed for this user
    $roleNameStmt = safeQueryPrepare($conn, "SELECT name FROM roles WHERE id = ?");
    $roleNameStmt->bind_param("i", $role_id);
    $roleNameStmt->execute();
    $roleNameResult = $roleNameStmt->get_result()->fetch_assoc();
    $selectedRoleName = $roleNameResult['name'] ?? '';
    
    if (!in_array($selectedRoleName, $allowedRoles)) {
        $error = "You don't have permission to create codes for the {$selectedRoleName} role.";
    } else if ($accommodation_id <= 0) {
        $error = 'Please select an accommodation';
    } elseif ($role_id <= 0) {
        $error = 'Please select a role for the code';
    } elseif ($count <= 0 || $count > 50) {
        $error = 'Please enter a valid number of codes (1-50)';
    } else {
        $conn = getDbConnection();
        
        // Calculate expiry date if specified
        $expires_at = null;
        if ($expiry_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
        }
        
        // Generate codes
        for ($i = 0; $i < $count; $i++) {
            $code = generateUniqueCode();
            $created_by = $_SESSION['user_id'];
            
            $stmt = safeQueryPrepare($conn, "INSERT INTO onboarding_codes 
                                         (code, created_by, accommodation_id, role_id, status, expires_at) 
                                         VALUES (?, ?, ?, ?, 'unused', ?)");
            $stmt->bind_param("siiis", $code, $created_by, $accommodation_id, $role_id, $expires_at);
            
            if ($stmt->execute()) {
                $generated_codes[] = $code;
            } else {
                $error = 'Failed to generate codes: ' . $conn->error;
                break;
            }
        }
        
        if (empty($error)) {
            $count_generated = count($generated_codes);
            $success = "$count_generated " . ($count_generated === 1 ? 'code' : 'codes') . " generated successfully.";
        }
    }
}

// Get accommodations for select dropdown based on user role
$conn = getDbConnection();
$accommodations = [];

if ($userRole === 'admin') {
    // Admin can see all accommodations
    $accom_stmt = safeQueryPrepare($conn, "SELECT a.id, a.name, u.first_name, u.last_name 
                                      FROM accommodations a 
                                      JOIN users u ON a.owner_id = u.id 
                                      ORDER BY a.name");
    $accom_stmt->execute();
} elseif ($userRole === 'owner') {
    // Owner can only see their own accommodations
    $accom_stmt = safeQueryPrepare($conn, "SELECT a.id, a.name, u.first_name, u.last_name 
                                      FROM accommodations a 
                                      JOIN users u ON a.owner_id = u.id 
                                      WHERE a.owner_id = ?
                                      ORDER BY a.name");
    $accom_stmt->bind_param("i", $userId);
    $accom_stmt->execute();
} elseif ($userRole === 'manager') {
    // Manager can only create codes for their assigned accommodation(s)
    $accom_stmt = safeQueryPrepare($conn, "SELECT DISTINCT a.id, a.name, u.first_name, u.last_name 
                                      FROM accommodations a
                                      JOIN users u ON a.owner_id = u.id
                                      JOIN user_accommodation ua ON a.id = ua.accommodation_id
                                      WHERE ua.user_id = ?
                                      ORDER BY a.name");
    $accom_stmt->bind_param("i", $userId);
    $accom_stmt->execute();
}
$accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get roles for select dropdown - only show roles the user can create
$rolesQuery = "SELECT id, name FROM roles WHERE name IN ('" . implode("','", $allowedRoles) . "') ORDER BY 
    CASE name 
        WHEN 'owner' THEN 1 
        WHEN 'manager' THEN 2 
        WHEN 'student' THEN 3 
    END";
$roles_stmt = safeQueryPrepare($conn, $rolesQuery);
$roles_stmt->execute();
$roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = "Generate Onboarding Codes";
$activePage = "codes";

// Include header
require_once '../../includes/components/header.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/codes.php">Onboarding Codes</a></li>
            <li class="breadcrumb-item active">Generate Codes</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Generate Onboarding Codes</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success && empty($generated_codes)): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label for="accommodation_id" class="form-label">Accommodation *</label>
                            <select class="form-select" id="accommodation_id" name="accommodation_id" required>
                                <option value="">-- Select Accommodation --</option>
                                <?php foreach ($accommodations as $accommodation): ?>
                                    <option value="<?= $accommodation['id'] ?>" 
                                    <?php
                                        if ($_SESSION['user_role'] === 'owner' && count($accommodations) === 1) {
                                            echo 'selected';
                                        } elseif (($_POST['accommodation_id'] ?? '') == $accommodation['id']) {
                                            echo 'selected';
                                        }
                                    ?>>
                                        <?= htmlspecialchars($accommodation['name']) ?> 
                                        (Owner: <?= htmlspecialchars($accommodation['first_name'] . ' ' . $accommodation['last_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role_id" class="form-label">Role *</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= ($_POST['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                        <?= ucfirst($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select the role for users registering with this code.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="count" class="form-label">Number of Codes</label>
                            <input type="number" class="form-control" id="count" name="count" value="<?= $_POST['count'] ?? 1 ?>" min="1" max="50" required>
                            <div class="form-text">How many unique codes to generate (maximum 50 at once).</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="expiry_days" class="form-label">Expiry (Days)</label>
                            <input type="number" class="form-control" id="expiry_days" name="expiry_days" value="<?= $_POST['expiry_days'] ?? CODE_EXPIRY_DAYS ?>" min="0">
                            <div class="form-text">Number of days until code expires. Enter 0 for no expiration.</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>/admin/codes.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Generate Codes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <?php if (!empty($generated_codes)): ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Generated Codes (<?= count($generated_codes) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <strong><?= $success ?></strong>
                            <p class="mb-0 mt-2">These codes can be shared with users immediately.</p>
                        </div>
                        
                        <div class="list-group mb-3">
                            <?php foreach ($generated_codes as $code): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <code class="me-3"><?= $code ?></code>
                                    <button type="button" class="btn btn-sm btn-outline-primary copy-code" data-code="<?= $code ?>">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success" id="copy-all-codes">
                                <i class="bi bi-clipboard-check"></i> Copy All Codes
                            </button>
                            <a href="<?= BASE_URL ?>/admin/codes.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Codes List
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <i class="bi bi-ticket-perforated text-muted" style="font-size: 4rem;"></i>
                            <h5 class="mt-3">Generated Codes Will Appear Here</h5>
                            <p class="text-muted">Fill out the form to generate onboarding codes.</p>
                            
                            <div class="mt-4">
                                <h6>About Onboarding Codes</h6>
                                <ul class="text-start">
                                    <li>Codes are unique identifiers for user registration</li>
                                    <li>Each code can be used only once</li>
                                    <li>Codes can be set to expire after a number of days</li>
                                    <li>Different roles (manager/student) need different codes</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copy individual code to clipboard
    document.querySelectorAll('.copy-code').forEach(button => {
        button.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            navigator.clipboard.writeText(code).then(() => {
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check"></i> Copied!';
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 2000);
            });
        });
    });
    
    // Copy all codes to clipboard
    const copyAllBtn = document.getElementById('copy-all-codes');
    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function() {
            const codes = [];
            document.querySelectorAll('.list-group-item code').forEach(codeElement => {
                codes.push(codeElement.textContent);
            });
            
            navigator.clipboard.writeText(codes.join('\n')).then(() => {
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check"></i> All Codes Copied!';
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 2000);
            });
        });
    }
});
</script>

<?php
// Helper function to generate a random code
function generateUniqueCode($length = 8) {
    // Exclude potentially confusing characters (0, O, 1, I)
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

require_once '../../includes/components/footer.php';
?>
