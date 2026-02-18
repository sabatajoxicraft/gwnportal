<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$pageTitle = "Request Device Authorization";
$activePage = "student-devices";

// Ensure the user is logged in as student
requireRole('student');

$userId = $_SESSION['user_id'] ?? 0;
$studentId = $_SESSION['student_id'] ?? 0;

$conn = getDbConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Invalid security token. Please try again.";
        header("Location: request-device.php");
        exit;
    }
    
    $deviceName = trim($_POST['device_name'] ?? '');
    $macAddress = trim($_POST['mac_address'] ?? '');
    
    $errors = [];
    
    // Validate device name
    if (empty($deviceName)) {
        $errors[] = "Device name is required.";
    } elseif (strlen($deviceName) > 100) {
        $errors[] = "Device name must not exceed 100 characters.";
    }
    
    // Validate MAC address format
    if (empty($macAddress)) {
        $errors[] = "MAC address is required.";
    } else {
        // Normalize MAC address (remove spaces, convert to uppercase)
        $macAddress = strtoupper(str_replace([' ', '.'], '', $macAddress));
        
        // Convert different formats to colon-separated
        if (strpos($macAddress, '-') !== false) {
            $macAddress = str_replace('-', ':', $macAddress);
        } elseif (strlen($macAddress) === 12 && strpos($macAddress, ':') === false) {
            // Convert XXXXXXXXXXXX to XX:XX:XX:XX:XX:XX
            $macAddress = implode(':', str_split($macAddress, 2));
        }
        
        // Validate MAC address format (XX:XX:XX:XX:XX:XX)
        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $macAddress)) {
            $errors[] = "Invalid MAC address format. Expected format: XX:XX:XX:XX:XX:XX or XX-XX-XX-XX-XX-XX";
        } else {
            // Check if MAC address already exists in the system
            $stmt = safeQueryPrepare($conn, "SELECT id, user_id FROM user_devices WHERE mac_address = ? LIMIT 1");
            $stmt->bind_param("s", $macAddress);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                if ((int)$existing['user_id'] === (int)$userId) {
                    $errors[] = "This MAC address is already registered for your account.";
                } else {
                    $errors[] = "This MAC address is already registered to another account.";
                }
            }
        }
    }
    
    // If validation passes, insert the device request
    if (empty($errors)) {
        $stmt = safeQueryPrepare($conn, "INSERT INTO user_devices (user_id, device_type, mac_address, linked_via) 
                                  VALUES (?, ?, ?, 'request')", false);
        if (!$stmt) {
            $stmt = safeQueryPrepare($conn, "INSERT INTO user_devices (user_id, device_type, mac_address) 
                                      VALUES (?, ?, ?)");
        }
        if (!$stmt) {
            $_SESSION['error_message'] = "Device registration is temporarily unavailable. Please contact support.";
            header("Location: request-device.php");
            exit;
        }
        $stmt->bind_param("iss", $userId, $deviceName, $macAddress);
        
        if ($stmt->execute()) {
            $deviceId = $stmt->insert_id;
            
            // Log activity
            logActivity($conn, $userId, 'Device Request', "Student requested device authorization: $deviceName ($macAddress)");
            
            // Notify admins
            $stmt_admin = safeQueryPrepare($conn, "SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'admin')");
            $stmt_admin->execute();
            $res_admin = $stmt_admin->get_result();
            while ($admin = $res_admin->fetch_assoc()) {
                createNotification($admin['id'], "New device request from " . $_SESSION['username'] . ": $deviceName", 'device_request', $userId);
            }

            $_SESSION['success_message'] = "Device authorization request submitted successfully! An administrator will review your request shortly.";
            header("Location: devices.php");
            exit;
        } else {
            $errors[] = "Failed to submit device request. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
    }
}

include '../../includes/components/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Request Device Authorization</h1>
                <a href="devices.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Devices
                </a>
            </div>
            
            <?php include '../../includes/components/messages.php'; ?>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-student text-white">
                            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Device Request</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="request-device.php" id="deviceRequestForm">
                                <?= csrfField() ?>
                                
                                <div class="mb-3">
                                    <label for="device_name" class="form-label">Device Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="device_name" name="device_name" 
                                           required maxlength="100" 
                                           placeholder="e.g., My Laptop, iPhone 12, Samsung Galaxy"
                                           value="<?= htmlEscape($_POST['device_name'] ?? '') ?>">
                                    <small class="form-text text-muted">
                                        Give your device a recognizable name (max 100 characters)
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mac_address" class="form-label">MAC Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace" id="mac_address" name="mac_address" 
                                           required 
                                           placeholder="XX:XX:XX:XX:XX:XX or XX-XX-XX-XX-XX-XX"
                                           pattern="([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})|([0-9A-Fa-f]{12})"
                                           value="<?= htmlEscape($_POST['mac_address'] ?? '') ?>">
                                    <small class="form-text text-muted">
                                        Enter your device's MAC address (see instructions below)
                                    </small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Your request will be reviewed by an administrator. 
                                    Approval typically takes 1-2 business days.
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                                    </button>
                                    <a href="devices.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <!-- MAC Address Help Card -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>How to Find MAC Address</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="text-primary"><i class="fab fa-windows me-2"></i>Windows</h6>
                            <ol>
                                <li>Open <strong>Command Prompt</strong> (search for "cmd")</li>
                                <li>Type: <code>ipconfig /all</code></li>
                                <li>Look for <strong>"Physical Address"</strong> under your network adapter</li>
                                <li>Example: <code>1A-2B-3C-4D-5E-6F</code></li>
                            </ol>
                            
                            <hr>
                            
                            <h6 class="text-primary"><i class="fab fa-apple me-2"></i>macOS</h6>
                            <ol>
                                <li>Open <strong>System Preferences</strong></li>
                                <li>Click <strong>Network</strong></li>
                                <li>Select your connection → Click <strong>Advanced</strong></li>
                                <li>Go to <strong>Hardware</strong> tab</li>
                                <li>MAC Address is shown at the top</li>
                            </ol>
                            
                            <hr>
                            
                            <h6 class="text-primary"><i class="fab fa-android me-2"></i>Android</h6>
                            <ol>
                                <li>Open <strong>Settings</strong></li>
                                <li>Go to <strong>About Phone</strong></li>
                                <li>Tap <strong>Status</strong></li>
                                <li>Look for <strong>Wi-Fi MAC address</strong></li>
                            </ol>
                            
                            <hr>
                            
                            <h6 class="text-primary"><i class="fab fa-apple me-2"></i>iPhone/iPad</h6>
                            <ol>
                                <li>Open <strong>Settings</strong></li>
                                <li>Tap <strong>General</strong></li>
                                <li>Tap <strong>About</strong></li>
                                <li>Look for <strong>Wi-Fi Address</strong></li>
                            </ol>
                        </div>
                    </div>
                    
                    <!-- Format Examples Card -->
                    <div class="card mt-3">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-check-circle me-2"></i>Accepted Formats</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">All of these formats are valid:</p>
                            <ul class="list-unstyled mb-0">
                                <li><code>1A:2B:3C:4D:5E:6F</code> ✓</li>
                                <li><code>1A-2B-3C-4D-5E-6F</code> ✓</li>
                                <li><code>1a:2b:3c:4d:5e:6f</code> ✓</li>
                                <li><code>1A2B3C4D5E6F</code> ✓</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/components/footer.php'; ?>
