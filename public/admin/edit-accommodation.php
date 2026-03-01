<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get accommodation ID from URL
$accommodation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php', 'Please login to access this page', 'warning');
}

if (!hasRole('admin')) {
    if (hasRole('owner')) {
        $target = BASE_URL . '/edit-accommodation.php';
        if ($accommodation_id > 0) {
            $target .= '?id=' . $accommodation_id;
        }
        redirect($target);
    }
    redirect(BASE_URL . '/index.php', 'You do not have permission to access this page', 'danger');
}

if ($accommodation_id <= 0) {
    redirect(BASE_URL . '/admin/accommodations.php', 'Invalid accommodation ID', 'danger');
}

$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $owner_id = (int)($_POST['owner_id'] ?? 0);
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $map_url = trim($_POST['map_url'] ?? '');
    $max_students = trim($_POST['max_students'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($name) || $owner_id <= 0) {
        $error = 'Name and owner are required fields.';
    } elseif (!empty($map_url) && !filter_var($map_url, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid map URL.';
    } elseif (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid contact email address.';
    } elseif (!empty($max_students) && (!ctype_digit($max_students) || (int)$max_students < 1)) {
        $error = 'Maximum students must be a positive number.';
    } else {
        $stmt = safeQueryPrepare($conn, "UPDATE accommodations SET NAME = ?, owner_id = ?, address_line1 = ?, address_line2 = ?, city = ?, province = ?, postal_code = ?, map_url = ?, max_students = NULLIF(?, ''), contact_phone = ?, contact_email = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("sissssssssssi", $name, $owner_id, $address_line1, $address_line2, $city, $province, $postal_code, $map_url, $max_students, $contact_phone, $contact_email, $notes, $accommodation_id);
        
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'edit_accommodation', "Updated accommodation '{$name}' (ID {$accommodation_id})", $_SERVER['REMOTE_ADDR']);
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
$accommodation_name = (string)($accommodation['name'] ?? $accommodation['NAME'] ?? '');
$address_line1_value = (string)($_POST['address_line1'] ?? ($accommodation['address_line1'] ?? ''));
$address_line2_value = (string)($_POST['address_line2'] ?? ($accommodation['address_line2'] ?? ''));
$city_value = (string)($_POST['city'] ?? ($accommodation['city'] ?? ''));
$province_value = (string)($_POST['province'] ?? ($accommodation['province'] ?? ''));
$postal_code_value = (string)($_POST['postal_code'] ?? ($accommodation['postal_code'] ?? ''));
$map_url_value = (string)($_POST['map_url'] ?? ($accommodation['map_url'] ?? ''));
$max_students_value = (string)($_POST['max_students'] ?? ($accommodation['max_students'] ?? ''));
$contact_phone_value = (string)($_POST['contact_phone'] ?? ($accommodation['contact_phone'] ?? ''));
$contact_email_value = (string)($_POST['contact_email'] ?? ($accommodation['contact_email'] ?? ''));
$notes_value = (string)($_POST['notes'] ?? ($accommodation['notes'] ?? ''));

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
                <?php $selected_owner_id = (int)($_POST['owner_id'] ?? ($accommodation['owner_id'] ?? 0)); ?>
                <div class="mb-3">
                    <label for="name" class="form-label">Accommodation Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($accommodation_name, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="mb-3">
                    <label for="address_line1" class="form-label">Address Line 1</label>
                    <input type="text" class="form-control" id="address_line1" name="address_line1" value="<?= htmlspecialchars($address_line1_value, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="mb-3">
                    <label for="address_line2" class="form-label">Address Line 2</label>
                    <input type="text" class="form-control" id="address_line2" name="address_line2" value="<?= htmlspecialchars($address_line2_value, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="city" class="form-label">City</label>
                        <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($city_value, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="province" class="form-label">Province/State</label>
                        <input type="text" class="form-control" id="province" name="province" value="<?= htmlspecialchars($province_value, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= htmlspecialchars($postal_code_value, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="map_url" class="form-label">Map Link</label>
                        <input type="url" class="form-control" id="map_url" name="map_url" value="<?= htmlspecialchars($map_url_value, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://maps.google.com/...">
                    </div>
                    <div class="col-md-6">
                        <label for="max_students" class="form-label">Maximum Students</label>
                        <input type="number" class="form-control" id="max_students" name="max_students" min="1" max="1000000" step="1" value="<?= htmlspecialchars($max_students_value, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-text">Set any positive number (not limited to 99).</div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="contact_phone" class="form-label">Contact Phone</label>
                        <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($contact_phone_value, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= htmlspecialchars($contact_email_value, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($notes_value, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="owner_id" class="form-label">Owner</label>
                    <select class="form-select" id="owner_id" name="owner_id" required>
                        <option value="">-- Select Owner --</option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= $owner['id'] ?>" <?= ((int)$owner['id'] === $selected_owner_id) ? 'selected' : '' ?>>
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
