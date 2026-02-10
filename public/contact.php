<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

$pageTitle = "Contact Us";
$activePage = "contact";

// Process contact form submission
$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    // Form validation
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // In a real application, you would send an email or save to database here
        
        // Log the contact request
        $conn = getDbConnection();
        logActivity($conn, 0, 'contact_form', "Contact form submitted by $name ($email): $subject");
        
        // Show success message
        $success = true;
    }
}

require_once '../includes/components/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">Contact Us</h2>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <h5 class="alert-heading">Thank you for contacting us!</h5>
                            <p>We have received your message and will respond as soon as possible.</p>
                        </div>
                        <div class="text-center mt-4">
                            <a href="<?= BASE_URL ?>" class="btn btn-primary">Return to Home</a>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <p class="lead">Have questions about our WiFi services? Get in touch with our team!</p>
                        
                        <form method="post" action="">
                            <?php echo csrfField(); ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= $_POST['name'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= $_POST['email'] ?? '' ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?= $_POST['subject'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="5" required><?= $_POST['message'] ?? '' ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-4 shadow">
                <div class="card-header bg-info text-white">
                    <h3 class="mb-0">Contact Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="bi bi-envelope-fill me-2"></i> Email</h5>
                            <p><a href="mailto:support@wifimanager.com">support@wifimanager.com</a></p>
                            
                            <h5><i class="bi bi-telephone-fill me-2"></i> Phone</h5>
                            <p>+27 12 345 6789</p>
                            
                            <h5><i class="bi bi-whatsapp me-2"></i> WhatsApp</h5>
                            <p>+27 98 765 4321</p>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="bi bi-building me-2"></i> Office Address</h5>
                            <p>
                                123 Main Street<br>
                                Pretoria<br>
                                South Africa<br>
                                0001
                            </p>
                            
                            <h5><i class="bi bi-clock-fill me-2"></i> Office Hours</h5>
                            <p>
                                Monday - Friday: 8:00 AM - 5:00 PM<br>
                                Saturday: 9:00 AM - 1:00 PM<br>
                                Sunday: Closed
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-3 text-center">
                <a href="<?= BASE_URL ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/components/footer.php'; ?>
