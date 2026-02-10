<?php
// Check if database exists before proceeding
$servername = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: 'gwn_wifi_system';

// Create connection without selecting database first
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if we can select the database
$db_exists = mysqli_select_db($conn, $dbname);

if (!$db_exists) {
    // Database doesn't exist, show setup prompt
    $pageTitle = "Setup Required";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>WiFi Management System - <?= $pageTitle ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/custom.css">
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="<?= $_SERVER['PHP_SELF'] ?>">WiFi Management System</a>
            </div>
        </nav>

        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header bg-warning text-dark">
                            <h4 class="mb-0">Database Setup Required</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <h5>Initial Setup Required</h5>
                                <p>The system database has not been set up yet. Before you can use this application, you need to run the initial setup process to create the necessary database and tables.</p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="../setup_db.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-database-gear me-2"></i>Run Database Setup
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="mt-5 py-3 bg-light fixed-bottom">
            <div class="container text-center">
                <p class="mb-0">&copy; <?= date('Y') ?> WiFi Management System. All rights reserved.</p>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// Close connection since we're done checking
$conn->close();

// Database exists, include the regular content
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set page title and active page
$pageTitle = "Home";
$activePage = "home";

// Include extra CSS for homepage animations
$extraCss = '<style>
    .feature-card {
        transition: all 0.3s ease;
    }
    .feature-card:hover {
        transform: translateY(-10px);
    }
    .feature-icon {
        font-size: 3rem;
        transition: all 0.3s ease;
    }
    .feature-card:hover .feature-icon {
        transform: scale(1.2);
    }
    .hero-section {
        background-image: var(--primary-gradient);
        color: white;
        padding: 5rem 0;
        border-radius: 0 0 50% 50% / 10%;
    }
    .animated-element {
        animation: fadeInUp 1s ease;
    }
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>';

// Include header
require_once '../includes/components/header.php';
?>

<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7 animated-element">
                <h1 class="display-4 fw-bold">Welcome to <?= APP_NAME ?></h1>
                <p class="lead fs-4">The unified platform for student accommodation WiFi management</p>
                <p class="mb-4">Manage accommodations, student onboarding, and WiFi vouchers from one place.</p>
                <?php if (!isLoggedIn()): ?>
                    <div>
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-light btn-lg me-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Login
                        </a>
                        <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-info-circle me-2"></i> Learn More
                        </a>
                    </div>
                <?php else: ?>
                    <a href="<?= getDashboardUrl() ?>" class="btn btn-light btn-lg">
                        <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                    </a>
                <?php endif; ?>
            </div>
            <div class="col-md-5 d-none d-md-block animated-element" style="animation-delay: 0.3s">
                <img src="<?= BASE_URL ?>/assets/img/wifi-illustration.svg" alt="WiFi Management" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="bg-light py-5">
    <div class="container">
        <h2 class="text-center mb-5">How It Works</h2>
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="text-center">
                    <div class="circle-icon mb-3">
                        <i class="bi bi-qr-code"></i>
                    </div>
                    <h5>Step 1</h5>
                    <p>Students receive onboarding codes from accommodation managers.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="text-center">
                    <div class="circle-icon mb-3">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <h5>Step 2</h5>
                    <p>Register your account using your onboarding code.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <div class="circle-icon mb-3">
                        <i class="bi bi-wifi"></i>
                    </div>
                    <h5>Step 3</h5>
                    <p>Receive monthly WiFi vouchers directly through SMS or WhatsApp.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Onboarding Section -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow border-0">
                <div class="card-header bg-white">
                    <h3 class="text-center mb-0">Have an Onboarding Code?</h3>
                </div>
                <div class="card-body text-center py-4">
                    <i class="bi bi-ticket-perforated display-1 text-primary mb-3"></i>
                    <h4>Quick & Easy Onboarding Process</h4>
                    <p class="mb-4">If you have received an onboarding code from your accommodation manager or owner, use it to set up your account.</p>
                    <a href="<?= BASE_URL ?>/onboard.php" class="btn btn-lg btn-primary">
                        <i class="bi bi-arrow-right-circle me-2"></i> Start Onboarding Process
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container mt-5">
    <div class="text-center mb-5 animated-element" style="animation-delay: 0.5s">
        <h2 class="display-5 fw-bold">Our Features</h2>
        <p class="lead text-muted">Everything you need to manage WiFi in student accommodations</p>
    </div>
    
    <div class="row mb-5">
        <div class="col-md-4 mb-4 animated-element" style="animation-delay: 0.7s">
            <div class="card feature-card h-100">
                <div class="card-body text-center p-4">
                    <div class="text-primary mb-3">
                        <i class="bi bi-building feature-icon"></i>
                    </div>
                    <h4 class="card-title">Accommodation Management</h4>
                    <p class="card-text">Easily manage multiple student accommodations and assign managers to oversee operations.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4 animated-element" style="animation-delay: 0.9s">
            <div class="card feature-card h-100">
                <div class="card-body text-center p-4">
                    <div class="text-success mb-3">
                        <i class="bi bi-people feature-icon"></i>
                    </div>
                    <h4 class="card-title">Student Onboarding</h4>
                    <p class="card-text">Streamlined student registration process with secure onboarding codes and verification.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4 animated-element" style="animation-delay: 1.1s">
            <div class="card feature-card h-100">
                <div class="card-body text-center p-4">
                    <div class="text-info mb-3">
                        <i class="bi bi-wifi feature-icon"></i>
                    </div>
                    <h4 class="card-title">WiFi Voucher System</h4>
                    <p class="card-text">Automated voucher generation and delivery to students via SMS, WhatsApp or email.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-md-6 animated-element" style="animation-delay: 1.3s">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h3>Already have an account?</h3>
                    <?php if (isLoggedIn()): ?>
                        <p class="card-text">You are already logged in as <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>.</p>
                        <a href="<?= getDashboardUrl() ?>" class="btn btn-primary">
                            <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <p class="card-text">Login to access your dashboard and manage your account.</p>
                        <a href="login.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 animated-element" style="animation-delay: 1.5s">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h3>Need Help?</h3>
                    <p class="card-text">Contact our support team for assistance with your account or technical issues.</p>
                    <a href="contact.php" class="btn btn-outline-primary">
                        <i class="bi bi-chat-dots me-2"></i> Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS styling for the circle icons -->
<style>
.circle-icon {
    width: 80px;
    height: 80px;
    background-color: var(--primary-color);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
}

.circle-icon i {
    font-size: 2rem;
    color: #fff;
}
</style>

<?php require_once '../includes/components/footer.php'; ?>
