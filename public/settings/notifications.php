<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/components/header.php';

requireLogin();

$userId = $_SESSION['user_id'];
$success = null;
$error = null;
$conn = getDbConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notify_device_requests = isset($_POST['notify_device_requests']) ? 1 : 0;
    $notify_device_status = isset($_POST['notify_device_status']) ? 1 : 0;
    $notify_vouchers = isset($_POST['notify_vouchers']) ? 1 : 0;
    $notify_new_students = isset($_POST['notify_new_students']) ? 1 : 0;
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;

    $stmt = safeQueryPrepare($conn, "INSERT INTO user_preferences (user_id, notify_device_requests, notify_device_status, notify_vouchers, notify_new_students, email_notifications) 
                                     VALUES (?, ?, ?, ?, ?, ?)
                                     ON DUPLICATE KEY UPDATE 
                                     notify_device_requests = VALUES(notify_device_requests),
                                     notify_device_status = VALUES(notify_device_status),
                                     notify_vouchers = VALUES(notify_vouchers),
                                     notify_new_students = VALUES(notify_new_students),
                                     email_notifications = VALUES(email_notifications)");
    
    $stmt->bind_param("iiiiii", $userId, $notify_device_requests, $notify_device_status, $notify_vouchers, $notify_new_students, $email_notifications);
    
    if ($stmt->execute()) {
        $success = "Preferences updated successfully.";
    } else {
        $error = "Failed to update preferences.";
    }
}

// Fetch current preferences
$stmt = safeQueryPrepare($conn, "SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$prefs = $result->fetch_assoc();

// Set defaults if no preferences exist
if (!$prefs) {
    $prefs = [
        'notify_device_requests' => 1,
        'notify_device_status' => 1,
        'notify_vouchers' => 1,
        'notify_new_students' => 1,
        'email_notifications' => 0
    ];
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Notification Settings</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <h5 class="mb-3">Email Notifications</h5>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?= $prefs['email_notifications'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="email_notifications">Receive email notifications</label>
                        </div>

                        <hr>

                        <h5 class="mb-3">In-App Notifications</h5>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="notify_device_status" name="notify_device_status" <?= $prefs['notify_device_status'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_device_status">Device Status Updates (Approvals/Rejections)</label>
                        </div>

                        <?php if (hasAnyRole(['manager', 'admin', 'owner'])): ?>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="notify_device_requests" name="notify_device_requests" <?= $prefs['notify_device_requests'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_device_requests">New Device Requests</label>
                            </div>
                        <?php endif; ?>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="notify_vouchers" name="notify_vouchers" <?= $prefs['notify_vouchers'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notify_vouchers">Voucher Received</label>
                        </div>

                        <?php if (hasAnyRole(['manager', 'admin', 'owner'])): ?>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="notify_new_students" name="notify_new_students" <?= $prefs['notify_new_students'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notify_new_students">New Student Registrations</label>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary mt-3">Save Preferences</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/components/footer.php'; ?>
