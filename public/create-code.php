<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require manager or owner login
requireRole(['manager', 'owner']);

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? '';
$conn = getDbConnection();

$error = '';
$success = '';
$generated_codes = [];

// Handle different role types
$role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

// For owners creating manager codes
if ($user_role === 'owner' && $role_id === 2) { // Assuming role_id 2 is manager
    $pageTitle = "Create Manager Invitation";
    $code_role_id = 2; // Manager role
    
    // Get owner's accommodations
    $accom_stmt = safeQueryPrepare($conn, "SELECT id, name FROM accommodations WHERE owner_id = ? ORDER BY name");
    $accom_stmt->bind_param("i", $user_id);
    $accom_stmt->execute();
    $accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} 
// For managers creating student codes
else if ($user_role === 'manager') {
    $pageTitle = "Create Student Invitation";
    $code_role_id = 4; // Student role - adjust based on your actual role IDs
    
    // Get current accommodation from session
    $current_accommodation_id = $_SESSION['accommodation_id'] ?? 0;
    
    // Get manager's accommodations using user_accommodation table
    $accom_stmt = safeQueryPrepare($conn, "SELECT a.id, a.name 
                                      FROM accommodations a
                                      JOIN user_accommodation ua ON a.id = ua.accommodation_id 
                                      WHERE ua.user_id = ? ORDER BY a.name");
    $accom_stmt->bind_param("i", $user_id);
    $accom_stmt->execute();
    $accommodations = $accom_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // If no accommodation selected, use the first one
    if (!$current_accommodation_id && count($accommodations) > 0) {
        $current_accommodation_id = $accommodations[0]['id'];
        $_SESSION['accommodation_id'] = $current_accommodation_id;
    }
    
    // Pre-select current accommodation
    $single_accommodation = null;
    foreach ($accommodations as $accom) {
        if ($accom['id'] == $current_accommodation_id) {
            $single_accommodation = $accom;
            break;
        }
    }
} else {
    // Invalid access
    redirect(BASE_URL . '/dashboard.php', 'Invalid access attempt', 'danger');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    // If manager has only one accommodation, use that ID directly
    if ($user_role === 'manager' && $single_accommodation) {
        $accommodation_id = $single_accommodation['id'];
    } else {
        $accommodation_id = (int)($_POST['accommodation_id'] ?? 0);
    }
    $count = (int)($_POST['count'] ?? 1);
    $expiry_days = (int)($_POST['expiry_days'] ?? CODE_EXPIRY_DAYS);
    $send_method = $_POST['send_method'] ?? 'none';
    $recipient = trim($_POST['recipient'] ?? '');
    
    // Get student name if provided (only for student codes)
    $student_first_name = trim($_POST['student_first_name'] ?? '');
    $student_last_name = trim($_POST['student_last_name'] ?? '');
    
    // Handle profile photo upload
    $profile_photo_path = null;
    if (!empty($_POST['photo_data'])) {
        $photo_data = $_POST['photo_data'];
        
        // Validate base64 image data
        if (preg_match('/^data:image\/(\w+);base64,/', $photo_data, $type)) {
            $photo_data = substr($photo_data, strpos($photo_data, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif
            
            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                $error = 'Invalid image type';
            } else {
                $photo_data = base64_decode($photo_data);
                
                if ($photo_data === false) {
                    $error = 'Base64 decode failed';
                } else {
                    // Create uploads directory if it doesn't exist
                    $upload_dir = PUBLIC_PATH . '/uploads/profile_photos';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $filename = 'profile_' . uniqid() . '_' . time() . '.' . $type;
                    $filepath = $upload_dir . '/' . $filename;
                    
                    // Save the file
                    if (file_put_contents($filepath, $photo_data)) {
                        $profile_photo_path = 'uploads/profile_photos/' . $filename;
                    } else {
                        $error = 'Failed to save profile photo';
                    }
                }
            }
        }
    }

    // Validate input (only if no photo error)
    if (empty($error)) {
        if ($accommodation_id <= 0) {
            $error = 'Please select an accommodation';
        } else if ($count <= 0 || $count > 50) {
            $error = 'Please enter a valid number of codes (1-50)';
        } else if ($send_method !== 'none' && empty($recipient)) {
            $error = 'Please enter recipient information';
        } else if ($code_role_id === 4 && empty($student_first_name)) {
            $error = 'Please enter student first name for identification';
        } else {
            // Calculate expiry date if specified
            $expires_at = null;
            if ($expiry_days > 0) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
            }
            
            // Generate code
            $code = generateUniqueCode();
            
            // Insert code with profile photo, phone number, and send method
            $stmt = safeQueryPrepare($conn, "INSERT INTO onboarding_codes 
                                       (code, created_by, accommodation_id, role_id, status, expires_at, profile_photo, student_first_name, student_last_name, phone_number, send_method) 
                                       VALUES (?, ?, ?, ?, 'unused', ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiissssss", $code, $user_id, $accommodation_id, $code_role_id, $expires_at, $profile_photo_path, $student_first_name, $student_last_name, $recipient, $send_method);
            
            if ($stmt->execute()) {
                $generated_codes[] = $code;
                $success = "Invitation code generated successfully.";
                
                if ($profile_photo_path) {
                    $success .= " Student photo saved for verification.";
                }
                
                // Handle sending of code
                switch ($send_method) {
                    case 'email':
                        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                            $subject = "Your " . APP_NAME . " Invitation";
                            $message = "Hello,\n\nYou have been invited to join " . APP_NAME . ".\n\n";
                            $message .= "Your invitation code is: " . $code . "\n\n";
                            $message .= "Please visit " . BASE_URL . "/register.php to create your account.\n\n";
                            $message .= "This code will expire on " . ($expires_at ? date('F j, Y', strtotime($expires_at)) : 'never') . ".\n\n";
                            $message .= "Regards,\n" . APP_NAME . " Team";
                            
                            if (mail($recipient, $subject, $message)) {
                                $success .= " The code has been sent to " . $recipient;
                            } else {
                                $error = "Failed to send email. The code was generated but you'll need to share it manually.";
                            }
                        } else {
                            $error = "Invalid email address. The code was generated but you'll need to share it manually.";
                        }
                        break;
                        
                    case 'sms':
                        // Send via Twilio SMS
                        $sms_message = "Your invitation code for " . APP_NAME . " is: " . $code . "\n\n";
                        $sms_message .= "Please visit " . BASE_URL . "/onboard.php to create your account.\n\n";
                        $sms_message .= "This code will expire on " . ($expires_at ? date('F j, Y', strtotime($expires_at)) : 'never') . ".";
                        
                        if (sendSMS($recipient, $sms_message)) {
                            $success .= " The code has been sent via SMS to " . $recipient;
                        } else {
                            $error = "Failed to send SMS. The code was generated but you'll need to share it manually.";
                        }
                        break;
                        
                    case 'whatsapp':
                        // Send via Twilio WhatsApp
                        $whatsapp_message = "Your invitation code for " . APP_NAME . " is: *" . $code . "*\n\n";
                        $whatsapp_message .= "Please visit " . BASE_URL . " to create your account.\n\n";
                        $whatsapp_message .= "This code will expire on " . ($expires_at ? date('F j, Y', strtotime($expires_at)) : 'never') . ".";
                        
                        if (sendWhatsApp($recipient, $whatsapp_message)) {
                            $success .= " The code has been sent via WhatsApp to " . $recipient;
                        } else {
                            $error = "Failed to send WhatsApp message. The code was generated but you'll need to share it manually.";
                        }
                        break;
                }
            } else {
                $error = 'Failed to generate code: ' . $conn->error;
            }
        }
    }
}

require_once '../includes/components/header.php';
?>

<div class="container mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success && empty($generated_codes)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
            <?php if ($user_role === 'owner'): ?>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/managers.php">Managers</a></li>
                <li class="breadcrumb-item active">Invite Manager</li>
            <?php else: ?>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/codes.php">Invitation Codes</a></li>
                <li class="breadcrumb-item active">Create Code</li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?= ($user_role === 'owner') ? 'Invite New Manager' : 'Generate Invitation Code' ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <?php echo csrfField(); ?>
                        <?php if ($user_role === 'manager' && $single_accommodation): ?>
                            <!-- For managers with only one accommodation -->
                            <div class="mb-3">
                                <label class="form-label">Accommodation</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($single_accommodation['name']) ?>" disabled>
                                <input type="hidden" name="accommodation_id" value="<?= $single_accommodation['id'] ?>">
                                <div class="form-text">You are managing this accommodation.</div>
                            </div>
                        <?php else: ?>
                            <!-- For owners or managers with multiple accommodations -->
                            <div class="mb-3">
                                <label for="accommodation_id" class="form-label">Accommodation *</label>
                                <select class="form-select" id="accommodation_id" name="accommodation_id" required>
                                    <option value="">-- Select Accommodation --</option>
                                    <?php foreach ($accommodations as $accommodation): ?>
                                        <option value="<?= $accommodation['id'] ?>" <?= ($_POST['accommodation_id'] ?? '') == $accommodation['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($accommodation['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'owner'): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <span>You are creating an invitation code for a new manager. They will use this code to register their account.</span>
                            </div>
                        <?php elseif ($user_role === 'manager' && $code_role_id === 4): ?>
                            <!-- Student Identification Section -->
                            <div class="card mb-3 border-primary">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Student Identification</h6>
                                </div>
                                <div class="card-body">
                                    <p class="small text-muted mb-3">
                                        Enter student details and capture their photo for verification purposes.
                                    </p>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="student_first_name" class="form-label">Student First Name *</label>
                                            <input type="text" class="form-control" id="student_first_name" name="student_first_name" 
                                                   value="<?= htmlspecialchars($_POST['student_first_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="student_last_name" class="form-label">Student Last Name</label>
                                            <input type="text" class="form-control" id="student_last_name" name="student_last_name" 
                                                   value="<?= htmlspecialchars($_POST['student_last_name'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Student Photo (Optional)</label>
                                        <div class="text-center mb-3">
                                            <video id="camera" width="100%" height="auto" autoplay style="display:none; max-width: 400px; border-radius: 8px; border: 2px solid #dee2e6;"></video>
                                            <canvas id="canvas" style="display:none;"></canvas>
                                            <div id="photo-preview" style="display:none; position: relative;">
                                                <img id="captured-photo" alt="Captured photo" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 2px solid #198754;" />
                                                <button type="button" class="btn btn-sm btn-danger" id="retake-photo" style="position: absolute; top: 10px; right: 10px;">
                                                    <i class="bi bi-x-circle me-1"></i> Retake
                                                </button>
                                            </div>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-outline-primary" id="start-camera">
                                                <i class="bi bi-camera me-2"></i>Open Camera
                                            </button>
                                            <button type="button" class="btn btn-success" id="capture-photo" style="display:none;">
                                                <i class="bi bi-camera-fill me-2"></i>Capture Photo
                                            </button>
                                        </div>
                                        <input type="hidden" name="photo_data" id="photo_data">
                                        <div class="form-text mt-2">
                                            <i class="bi bi-info-circle me-1"></i>Take a photo of the student for visual identification and verification.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="expiry_days" class="form-label">Code Expires After (Days)</label>
                            <input type="number" class="form-control" id="expiry_days" name="expiry_days" value="<?= $_POST['expiry_days'] ?? CODE_EXPIRY_DAYS ?>" min="1">
                            <div class="form-text">How many days until this invitation expires.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Share Invitation Code</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_method" id="send_none" value="none" checked>
                                <label class="form-check-label" for="send_none">
                                    Don't send - I'll share it myself
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_method" id="send_email" value="email">
                                <label class="form-check-label" for="send_email">
                                    Send via Email
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_method" id="send_sms" value="sms">
                                <label class="form-check-label" for="send_sms">
                                    Send via SMS
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_method" id="send_whatsapp" value="whatsapp">
                                <label class="form-check-label" for="send_whatsapp">
                                    Send via WhatsApp
                                </label>
                            </div>
                        </div>
                        
                        <div id="recipient_field" class="mb-3 d-none">
                            <label for="recipient" class="form-label">Recipient</label>
                            <input type="text" class="form-control" id="recipient" name="recipient" placeholder="Email, phone number, etc.">
                            <div class="form-text" id="recipient_help">Enter the recipient's information.</div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?= ($user_role === 'owner') ? BASE_URL . '/managers.php' : BASE_URL . '/codes.php' ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <?= ($user_role === 'owner') ? 'Create Manager Invitation' : 'Generate Code' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <?php if (!empty($generated_codes)): ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Invitation Code Generated</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <?= $success ?>
                        </div>
                        
                        <div class="p-3 border rounded mb-3 text-center">
                            <h4 class="mb-3">Invitation Code</h4>
                            <div class="display-6 font-monospace mb-3"><?= $generated_codes[0] ?></div>
                            <button type="button" class="btn btn-primary copy-code" data-code="<?= $generated_codes[0] ?>">
                                <i class="bi bi-clipboard"></i> Copy Code
                            </button>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Make sure to share this code with the intended recipient. They will need it to create their account.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="<?= ($user_role === 'owner') ? BASE_URL . '/managers.php' : BASE_URL . '/codes.php' ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to <?= ($user_role === 'owner') ? 'Managers' : 'Codes' ?> List
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">How It Works</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="bi bi-ticket-perforated text-primary" style="font-size: 3rem;"></i>
                        </div>
                        
                        <?php if ($user_role === 'owner'): ?>
                            <h5>Inviting a New Manager</h5>
                            <ol>
                                <li>Generate an invitation code for the manager</li>
                                <li>Share the code with them via email, SMS, WhatsApp, or in person</li>
                                <li>The manager uses the code to create their account</li>
                                <li>They will automatically be assigned to the selected accommodation</li>
                            </ol>
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle me-2"></i>
                                Managers will have access to create student codes and manage their assigned accommodation.
                            </div>
                        <?php else: ?>
                            <h5>Creating Student Invitation Codes</h5>
                            <ol>
                                <li>Generate an invitation code for a student</li>
                                <li>Share the code with them via email, SMS, WhatsApp, or in person</li>
                                <li>The student uses the code to create their account</li>
                                <li>They will automatically be assigned to the selected accommodation</li>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle showing/hiding the recipient field
    const sendMethodRadios = document.querySelectorAll('input[name="send_method"]');
    const recipientField = document.getElementById('recipient_field');
    const recipientHelp = document.getElementById('recipient_help');
    const recipientInput = document.getElementById('recipient');
    
    sendMethodRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'none') {
                recipientField.classList.add('d-none');
                recipientInput.required = false;
            } else {
                recipientField.classList.remove('d-none');
                recipientInput.required = true;
                
                // Update placeholder and help text
                switch(this.value) {
                    case 'email':
                        recipientInput.placeholder = 'example@email.com';
                        recipientHelp.textContent = 'Enter the recipient\'s email address.';
                        break;
                    case 'sms':
                        recipientInput.placeholder = '+123456789';
                        recipientHelp.textContent = 'Enter the recipient\'s phone number with country code.';
                        break;
                    case 'whatsapp':
                        recipientInput.placeholder = '+123456789';
                        recipientHelp.textContent = 'Enter the recipient\'s WhatsApp number with country code.';
                        break;
                }
            }
        });
    });
    
    // Copy code to clipboard
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
    
    // Camera capture functionality
    const startCameraBtn = document.getElementById('start-camera');
    const capturePhotoBtn = document.getElementById('capture-photo');
    const retakePhotoBtn = document.getElementById('retake-photo');
    const video = document.getElementById('camera');
    const canvas = document.getElementById('canvas');
    const photoPreview = document.getElementById('photo-preview');
    const capturedPhoto = document.getElementById('captured-photo');
    const photoDataInput = document.getElementById('photo_data');
    let stream = null;
    
    if (startCameraBtn) {
        startCameraBtn.addEventListener('click', async function() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    } 
                });
                video.srcObject = stream;
                video.style.display = 'block';
                capturePhotoBtn.style.display = 'block';
                startCameraBtn.style.display = 'none';
                photoPreview.style.display = 'none';
            } catch (err) {
                alert('Error accessing camera: ' + err.message);
                console.error('Camera error:', err);
            }
        });
        
        capturePhotoBtn.addEventListener('click', function() {
            // Set canvas dimensions to match video
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Draw video frame to canvas
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Convert to base64
            const photoData = canvas.toDataURL('image/jpeg', 0.9);
            photoDataInput.value = photoData;
            
            // Show preview
            capturedPhoto.src = photoData;
            photoPreview.style.display = 'block';
            
            // Hide video and capture button
            video.style.display = 'none';
            capturePhotoBtn.style.display = 'none';
            
            // Stop camera stream
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        });
        
        retakePhotoBtn.addEventListener('click', function() {
            photoDataInput.value = '';
            capturedPhoto.src = '';
            photoPreview.style.display = 'none';
            startCameraBtn.style.display = 'block';
        });
    }
});
</script>

<?php 
require_once '../includes/components/footer.php'; 
?>
</body>
</html>
