<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Process contact form
$message_sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } else {
        $subject    = 'WiFi System Support Request';
        $email_body = "Name: $name\nEmail: $email\n"
                    . ($phone !== '' ? "Phone: $phone\n" : '')
                    . "\nMessage:\n$message";
        if (sendAppEmail('support@joxicraft.co.za', $subject, $email_body, false, 'support')) {
            $message_sent = true;
        } else {
            $error = 'Your message could not be sent. Please try again or contact us directly.';
        }
    }
}

$pageTitle = 'Help Center';
$extraCss  = '<style>
.help-hero{background:linear-gradient(135deg,#0d6efd 0%,#0a58ca 100%);color:#fff;padding:3rem 1.5rem 2rem;}
.help-hero h1{font-weight:800;letter-spacing:-0.5px;}
.section-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.help-section{scroll-margin-top:80px;}
.toc-link{text-decoration:none;color:#495057;transition:color .15s;}
.toc-link:hover{color:#0d6efd;}
.step-num{width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;}
.illus-frame{border:1px solid #dee2e6;border-radius:8px;overflow:hidden;background:#f8f9fa;}
</style>';

// Use public chrome for guests, app chrome for authenticated users
$headerType = isLoggedIn() ? 'app' : 'public';
$footerType = $headerType;

// Determine which role-specific sections to show
$viewerRole  = isLoggedIn() ? getUserRole() : null;
$showStudent = ($viewerRole === ROLE_STUDENT);
$showManager = ($viewerRole === ROLE_MANAGER);
$showAdmin   = ($viewerRole === ROLE_ADMIN);
$showOwner   = ($viewerRole === ROLE_OWNER);  // owner has no dedicated section yet

// Dynamic hero subtitle
$heroSubtitle = match(true) {
    $showStudent => 'Step-by-step guides for your student account',
    $showManager => 'Step-by-step guides for accommodation managers',
    $showAdmin   => 'Step-by-step guides for portal administrators',
    $showOwner   => 'Help guides and contact support',
    default      => 'Help guides and frequently asked questions',
};

require_once '../includes/components/header.php';
?>

<div class="help-hero text-center">
    <h1 class="mb-2"><i class="bi bi-question-circle me-2"></i>Help Center</h1>
    <p class="lead mb-0 opacity-75"><?= htmlspecialchars($heroSubtitle) ?></p>
</div>

<div class="container py-4">
    <div class="row g-4">

        <!-- Sidebar TOC -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="sticky-top" style="top:80px;">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold border-bottom">
                        <i class="bi bi-list-ul me-2 text-primary"></i>Contents
                    </div>
                    <div class="card-body p-2">
                        <nav class="nav flex-column small">
                            <a class="toc-link py-1 px-2 rounded" href="#getting-started"><i class="bi bi-play-circle me-2 text-primary"></i>Getting Started</a>
                            <?php if ($showStudent): ?>
                            <a class="toc-link py-1 px-2 rounded" href="#student-voucher"><i class="bi bi-wifi me-2 text-info"></i>Request a Voucher</a>
                            <a class="toc-link py-1 px-2 rounded" href="#student-use-voucher"><i class="bi bi-router me-2 text-info"></i>Use Your Voucher</a>
                            <a class="toc-link py-1 px-2 rounded" href="#student-devices"><i class="bi bi-laptop me-2 text-info"></i>Register a Device</a>
                            <a class="toc-link py-1 px-2 rounded" href="#student-device-status"><i class="bi bi-activity me-2 text-info"></i>Device Status</a>
                            <?php endif; ?>
                            <?php if ($showManager): ?>
                            <a class="toc-link py-1 px-2 rounded" href="#manager-send"><i class="bi bi-send me-2" style="color:#6610f2"></i>Send Vouchers</a>
                            <a class="toc-link py-1 px-2 rounded" href="#manager-students"><i class="bi bi-people me-2" style="color:#6610f2"></i>Student Details</a>
                            <a class="toc-link py-1 px-2 rounded" href="#manager-access"><i class="bi bi-shield-lock me-2" style="color:#6610f2"></i>Block / Restore Access</a>
                            <?php endif; ?>
                            <?php if ($showAdmin): ?>
                            <a class="toc-link py-1 px-2 rounded" href="#admin-overview"><i class="bi bi-gear me-2 text-danger"></i>Admin Overview</a>
                            <?php endif; ?>
                            <a class="toc-link py-1 px-2 rounded" href="#troubleshooting"><i class="bi bi-tools me-2 text-warning"></i>Troubleshooting</a>
                            <a class="toc-link py-1 px-2 rounded" href="#contact"><i class="bi bi-envelope me-2 text-secondary"></i>Contact Support</a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-lg-9">

            <!-- Mobile TOC (collapsed) -->
            <div class="d-lg-none mb-3">
                <button class="btn btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#mobileToc">
                    <i class="bi bi-list-ul me-2"></i>Jump to section
                </button>
                <div class="collapse mt-2" id="mobileToc">
                    <div class="card card-body p-2">
                        <nav class="nav flex-column small">
                            <a class="toc-link py-1 px-2" href="#getting-started">Getting Started</a>
                            <?php if ($showStudent): ?>
                            <a class="toc-link py-1 px-2" href="#student-voucher">Request a Voucher</a>
                            <a class="toc-link py-1 px-2" href="#student-use-voucher">Use Your Voucher</a>
                            <a class="toc-link py-1 px-2" href="#student-devices">Register a Device</a>
                            <a class="toc-link py-1 px-2" href="#student-device-status">Device Status</a>
                            <?php endif; ?>
                            <?php if ($showManager): ?>
                            <a class="toc-link py-1 px-2" href="#manager-send">Send Vouchers</a>
                            <a class="toc-link py-1 px-2" href="#manager-students">Student Details</a>
                            <a class="toc-link py-1 px-2" href="#manager-access">Block / Restore Access</a>
                            <?php endif; ?>
                            <?php if ($showAdmin): ?>
                            <a class="toc-link py-1 px-2" href="#admin-overview">Admin Overview</a>
                            <?php endif; ?>
                            <a class="toc-link py-1 px-2" href="#troubleshooting">Troubleshooting</a>
                            <a class="toc-link py-1 px-2" href="#contact">Contact Support</a>
                        </nav>
                    </div>
                </div>
            </div>

            <?php if ($showOwner): ?>
            <div class="alert alert-info d-flex gap-2 mb-4">
                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                <div>The shared help sections below are available to everyone. Role-specific guidance for owners will be added in a future update.</div>
            </div>
            <?php endif; ?>

            <!-- GETTING STARTED -->
            <section id="getting-started" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon bg-primary text-white"><i class="bi bi-play-circle-fill"></i></div>
                    <h2 class="mb-0 h4">Getting Started &amp; Login</h2>
                </div>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <p class="mb-3">The GWN Portal is your central hub for WiFi access management. Here is how to sign in:</p>
                                <ol class="list-group list-group-flush list-group-numbered mb-0">
                                    <li class="list-group-item border-0 ps-0">Open <strong><?= BASE_URL ?>/login.php</strong> in your browser.</li>
                                    <li class="list-group-item border-0 ps-0">Enter your <strong>student number</strong> (or email) and your <strong>password</strong>.</li>
                                    <li class="list-group-item border-0 ps-0">Click <strong>Login</strong>. You will be taken to your dashboard.</li>
                                    <li class="list-group-item border-0 ps-0">First login? You may be asked to set a new password immediately.</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <div class="illus-frame">
                                    <img src="<?= BASE_URL ?>/assets/img/help/illustrations/login.svg" alt="Login screen illustration" class="img-fluid w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info d-flex gap-2">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <div><strong>Forgot your password?</strong> Click the <em>Forgot Password</em> link on the login page. A reset link will be emailed to you.</div>
                </div>
            </section>

            <?php if ($showStudent): ?>
            <!-- STUDENT: REQUEST A VOUCHER -->
            <section id="student-voucher" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon bg-info text-white"><i class="bi bi-wifi"></i></div>
                    <div>
                        <span class="badge bg-info text-white mb-1">Students</span>
                        <h2 class="mb-0 h4">Requesting a WiFi Voucher</h2>
                    </div>
                </div>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row align-items-center g-3">
                            <div class="col-md-7">
                                <p>A WiFi voucher is your monthly access code. Follow these steps:</p>
                                <div class="d-flex flex-column gap-3">
                                    <div class="d-flex gap-3 align-items-start">
                                        <span class="step-num bg-info text-white">1</span>
                                        <div>Log in and go to your <a href="<?= BASE_URL ?>/student/dashboard.php">Student Dashboard</a>.</div>
                                    </div>
                                    <div class="d-flex gap-3 align-items-start">
                                        <span class="step-num bg-info text-white">2</span>
                                        <div>Click <strong>Request Voucher</strong> (or go to <a href="<?= BASE_URL ?>/student/request-voucher.php">Request Voucher page</a>).</div>
                                    </div>
                                    <div class="d-flex gap-3 align-items-start">
                                        <span class="step-num bg-info text-white">3</span>
                                        <div>Verify your contact details (phone / WhatsApp) are correct, then submit.</div>
                                    </div>
                                    <div class="d-flex gap-3 align-items-start">
                                        <span class="step-num bg-success text-white">4</span>
                                        <div>Your manager reviews and approves. You will receive the code via <strong>SMS or WhatsApp</strong>.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="illus-frame">
                                    <img src="<?= BASE_URL ?>/assets/img/help/illustrations/request-voucher.svg" alt="Request voucher steps" class="img-fluid w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning d-flex gap-2">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                    <div>You can only have <strong>one active voucher</strong> at a time. A new request will only be processed once the current voucher period ends or you are manually approved again.</div>
                </div>
            </section>

            <!-- STUDENT: USE THE VOUCHER -->
            <section id="student-use-voucher" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon bg-info text-white"><i class="bi bi-router"></i></div>
                    <div>
                        <span class="badge bg-info text-white mb-1">Students</span>
                        <h2 class="mb-0 h4">Using Your Voucher to Connect</h2>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <ol class="list-group list-group-flush list-group-numbered mb-0">
                                    <li class="list-group-item border-0 ps-0">On your phone or laptop, open WiFi settings and connect to the <strong>GWN network</strong> for your building.</li>
                                    <li class="list-group-item border-0 ps-0">A captive portal (login page) will appear. If it does not, open a browser and navigate to any HTTP site.</li>
                                    <li class="list-group-item border-0 ps-0">Enter the voucher code you received via SMS or WhatsApp.</li>
                                    <li class="list-group-item border-0 ps-0">Click <strong>Connect</strong>. WiFi access is granted immediately.</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <div class="illus-frame">
                                    <img src="<?= BASE_URL ?>/assets/img/help/illustrations/use-voucher.svg" alt="Using a voucher to connect" class="img-fluid w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- STUDENT: REGISTER A DEVICE -->
            <section id="student-devices" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon bg-warning text-dark"><i class="bi bi-laptop"></i></div>
                    <div>
                        <span class="badge bg-info text-white mb-1">Students</span>
                        <h2 class="mb-0 h4">Registering a Device</h2>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row align-items-start g-3">
                            <div class="col-md-6">
                                <p>You can register up to <strong>2 devices</strong> to your account.</p>
                                <ol class="list-group list-group-flush list-group-numbered mb-2">
                                    <li class="list-group-item border-0 ps-0">Go to <a href="<?= BASE_URL ?>/student/devices.php">My Devices</a>.</li>
                                    <li class="list-group-item border-0 ps-0">Click <strong>Add Device</strong>.</li>
                                    <li class="list-group-item border-0 ps-0">Enter your device <strong>MAC address</strong> and give it a nickname (e.g. "My Laptop").</li>
                                    <li class="list-group-item border-0 ps-0">Submit. The device will appear as <em>Pending</em> until approved.</li>
                                </ol>
                                <strong class="small">How to find your MAC address:</strong>
                                <ul class="small mt-1 mb-0">
                                    <li><strong>Android:</strong> Settings &gt; About Phone &gt; Status &gt; WiFi MAC Address</li>
                                    <li><strong>iPhone:</strong> Settings &gt; General &gt; About &gt; WiFi Address</li>
                                    <li><strong>Windows:</strong> Run <code>ipconfig /all</code> &mdash; look for <em>Physical Address</em></li>
                                    <li><strong>macOS:</strong> System Settings &gt; Network &gt; Wi-Fi &gt; Details &gt; Hardware</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="illus-frame">
                                    <img src="<?= BASE_URL ?>/assets/img/help/illustrations/add-device.svg" alt="Adding a device" class="img-fluid w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- STUDENT: DEVICE STATUS -->
            <section id="student-device-status" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon bg-info text-white"><i class="bi bi-activity"></i></div>
                    <div>
                        <span class="badge bg-info text-white mb-1">Students</span>
                        <h2 class="mb-0 h4">Checking Device Status</h2>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <p>On the <a href="<?= BASE_URL ?>/student/devices.php">My Devices</a> page you will see a status badge for each device:</p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead class="table-light">
                                    <tr><th>Status</th><th>Meaning</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td><span class="badge bg-success">Active</span></td><td>Device can connect to WiFi</td><td>None required</td></tr>
                                    <tr><td><span class="badge bg-warning text-dark">Pending</span></td><td>Awaiting manager approval</td><td>Wait or contact your manager</td></tr>
                                    <tr><td><span class="badge bg-secondary">Inactive</span></td><td>Not linked to an active voucher</td><td>Request a new voucher</td></tr>
                                    <tr><td><span class="badge bg-danger">Blocked</span></td><td>Access suspended by manager</td><td>Contact your accommodation manager</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; // showStudent ?>

            <?php if ($showManager): ?>
            <!-- MANAGER: SEND VOUCHERS -->
            <section id="manager-send" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon text-white" style="background:#6610f2;"><i class="bi bi-send-fill"></i></div>
                    <div>
                        <span class="badge text-white mb-1" style="background:#6610f2;">Managers</span>
                        <h2 class="mb-0 h4">Sending Vouchers to Students</h2>
                    </div>
                </div>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row align-items-start g-3">
                            <div class="col-md-6">
                                <ol class="list-group list-group-flush list-group-numbered mb-0">
                                    <li class="list-group-item border-0 ps-0">Go to <a href="<?= BASE_URL ?>/send-voucher.php">Send Voucher</a> in the main menu.</li>
                                    <li class="list-group-item border-0 ps-0">Select the <strong>student</strong> from the dropdown list.</li>
                                    <li class="list-group-item border-0 ps-0">Choose the <strong>voucher pool</strong> (speed tier) to draw from.</li>
                                    <li class="list-group-item border-0 ps-0">Click <strong>Send Voucher</strong>. The system delivers it automatically.</li>
                                    <li class="list-group-item border-0 ps-0">Review past sends in <a href="<?= BASE_URL ?>/manager/voucher-history.php">Voucher History</a>.</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <div class="illus-frame">
                                    <img src="<?= BASE_URL ?>/assets/img/help/illustrations/manager-vouchers.svg" alt="Manager voucher panel" class="img-fluid w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info d-flex gap-2">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <div>Use <strong>Voucher History</strong> to filter by student, date range, or status. You can export the history as CSV for your records.</div>
                </div>
            </section>

            <!-- MANAGER: STUDENT DETAILS -->
            <section id="manager-students" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon text-white" style="background:#6610f2;"><i class="bi bi-person-lines-fill"></i></div>
                    <div>
                        <span class="badge text-white mb-1" style="background:#6610f2;">Managers</span>
                        <h2 class="mb-0 h4">Reviewing Student Details</h2>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <p>From the <a href="<?= BASE_URL ?>/students.php">Students</a> list, click any student row to open their detail page. There you can:</p>
                        <ul class="mb-0">
                            <li>View personal info, room number, and contact details.</li>
                            <li>See all vouchers sent to this student and their current status.</li>
                            <li>See registered devices and their approval state.</li>
                            <li>Edit contact details if the student's information has changed.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- MANAGER: BLOCK / RESTORE ACCESS -->
            <section id="manager-access" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon text-white" style="background:#6610f2;"><i class="bi bi-shield-lock-fill"></i></div>
                    <div>
                        <span class="badge text-white mb-1" style="background:#6610f2;">Managers</span>
                        <h2 class="mb-0 h4">Blocking and Restoring Access</h2>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="text-danger"><i class="bi bi-slash-circle me-2"></i>Block a Student</h6>
                                <ol class="small mb-0">
                                    <li>Open the student's detail page.</li>
                                    <li>Click <strong>Block Access</strong>.</li>
                                    <li>Confirm. The student's devices are revoked immediately.</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success"><i class="bi bi-check-circle me-2"></i>Restore a Student</h6>
                                <ol class="small mb-0">
                                    <li>Open the blocked student's detail page.</li>
                                    <li>Click <strong>Restore Access</strong>.</li>
                                    <li>Send a new voucher for the student to reconnect.</li>
                                </ol>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0 small d-flex gap-2">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                            <div>Blocking a student immediately disconnects all their registered devices. Only block when necessary and in line with your accommodation's fair-use policy.</div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; // showManager ?>

            <?php if ($showAdmin): ?>
            <!-- ADMIN OVERVIEW -->
            <section id="admin-overview" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon bg-danger text-white"><i class="bi bi-gear-fill"></i></div>
                    <div>
                        <span class="badge bg-danger mb-1">Admins</span>
                        <h2 class="mb-0 h4">Admin Overview</h2>
                    </div>
                </div>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row align-items-start g-3">
                            <div class="col-md-6">
                                <p>Admins have full access to all areas of the portal. Key areas:</p>
                                <ul class="mb-0">
                                    <li><a href="<?= BASE_URL ?>/admin/dashboard.php">Admin Dashboard</a> &mdash; system-wide stats.</li>
                                    <li><a href="<?= BASE_URL ?>/admin/users.php">Users</a> &mdash; create, edit, or deactivate any account.</li>
                                    <li><a href="<?= BASE_URL ?>/accommodations/">Accommodations</a> &mdash; manage buildings, assign managers.</li>
                                    <li><a href="<?= BASE_URL ?>/admin/activity-log.php">Activity Log</a> &mdash; full audit trail.</li>
                                    <li><a href="<?= BASE_URL ?>/admin/network-management.php">Network Management</a> &mdash; router pools and speed tiers.</li>
                                    <li><a href="<?= BASE_URL ?>/admin/reports.php">Reports</a> &mdash; usage analytics.</li>
                                    <li><a href="<?= BASE_URL ?>/admin/settings.php">Settings</a> &mdash; global portal configuration.</li>
                                    <li><a href="<?= BASE_URL ?>/codes/">Onboarding Codes</a> &mdash; QR codes for new student sign-up.</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="illus-frame">
                                    <img src="<?= BASE_URL ?>/assets/img/help/illustrations/admin-overview.svg" alt="Admin control panel" class="img-fluid w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-danger d-flex gap-2">
                    <i class="bi bi-shield-exclamation flex-shrink-0 mt-1"></i>
                    <div><strong>Admin actions are irreversible in some cases.</strong> Always review before confirming destructive actions. All changes are recorded in the Activity Log.</div>
                </div>
            </section>
            <?php endif; // showAdmin ?>

            <!-- TROUBLESHOOTING -->
            <section id="troubleshooting" class="help-section mb-5">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon bg-warning text-dark"><i class="bi bi-tools"></i></div>
                    <h2 class="mb-0 h4">Troubleshooting &amp; Common Issues</h2>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row align-items-start g-3">
                            <div class="col-md-7">
                                <div class="accordion" id="troubleAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#t1">
                                                I entered my voucher code but cannot connect
                                            </button>
                                        </h2>
                                        <div id="t1" class="accordion-collapse collapse show" data-bs-parent="#troubleAccordion">
                                            <div class="accordion-body small">
                                                <ul class="mb-0">
                                                    <li>Double-check that you typed the code exactly (no extra spaces).</li>
                                                    <li>Ensure you are connected to <em>your building's</em> GWN network.</li>
                                                    <li>Try forgetting the network and reconnecting to re-trigger the captive portal.</li>
                                                    <li>Confirm your device MAC address is registered on your account.</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t2">
                                                I did not receive my voucher via SMS / WhatsApp
                                            </button>
                                        </h2>
                                        <div id="t2" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                                            <div class="accordion-body small">
                                                <ol class="mb-0">
                                                    <li>Check that your phone number in your profile is correct.</li>
                                                    <li>Ask your manager to confirm the voucher was sent (check Voucher History).</li>
                                                    <li>SMS messages can be delayed up to 10 minutes.</li>
                                                    <li>If still not received, ask your manager to resend.</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t3">
                                                My device shows as Blocked or Inactive
                                            </button>
                                        </h2>
                                        <div id="t3" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                                            <div class="accordion-body small">
                                                <ul class="mb-0">
                                                    <li><strong>Inactive:</strong> Your current voucher may have expired. Request a new one.</li>
                                                    <li><strong>Blocked:</strong> Your manager has suspended access. Contact them to resolve the issue.</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t4">
                                                I cannot log in to the portal
                                            </button>
                                        </h2>
                                        <div id="t4" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                                            <div class="accordion-body small">
                                                <ol class="mb-0">
                                                    <li>Use the <em>Forgot Password</em> link on the login page.</li>
                                                    <li>Check that your student number is correct.</li>
                                                    <li>If your account was recently created, ask your manager to confirm it is active.</li>
                                                    <li>Still locked out? Use the contact form below.</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#t5">
                                                How do I find my MAC address? (full guide)
                                            </button>
                                        </h2>
                                        <div id="t5" class="accordion-collapse collapse" data-bs-parent="#troubleAccordion">
                                            <div class="accordion-body small">
                                                <ul class="mb-0">
                                                    <li><strong>Android:</strong> Settings &gt; About Phone &gt; Status &gt; WiFi MAC Address</li>
                                                    <li><strong>iPhone/iPad:</strong> Settings &gt; General &gt; About &gt; WiFi Address</li>
                                                    <li><strong>Windows 10/11:</strong> Settings &gt; Network &gt; WiFi &gt; Hardware Properties &gt; Physical address (MAC)</li>
                                                    <li><strong>Windows (cmd):</strong> <code>ipconfig /all</code> under Wireless LAN adapter</li>
                                                    <li><strong>macOS:</strong> System Settings &gt; Network &gt; Wi-Fi &gt; Details &gt; Hardware</li>
                                                    <li><strong>Linux:</strong> <code>ip link show</code> &mdash; look for <em>link/ether</em></li>
                                                </ul>
                                                <p class="mb-0 text-muted mt-2">Format: 6 pairs of hex digits, e.g. <code>A1:B2:C3:D4:E5:F6</code></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="illus-frame">
                                    <img src="<?= BASE_URL ?>/assets/img/help/illustrations/troubleshoot.svg" alt="Troubleshooting checklist" class="img-fluid w-100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CONTACT / SUPPORT FORM -->
            <section id="contact" class="help-section mb-4">
                <div class="d-flex align-items-center mb-3 gap-3">
                    <div class="section-icon bg-secondary text-white"><i class="bi bi-envelope-fill"></i></div>
                    <h2 class="mb-0 h4">Contact Support</h2>
                </div>

                <?php if ($message_sent): ?>
                    <div class="alert alert-success d-flex gap-2">
                        <i class="bi bi-check-circle-fill flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>Message sent!</strong> Thank you for contacting us. We will respond as soon as possible.
                            <div class="mt-2"><a href="<?= BASE_URL ?>/index.php" class="btn btn-sm btn-outline-success">Back to Home</a></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <form method="post" action="#contact" class="row g-3">
                                <?php echo csrfField(); ?>
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-2"></i>Send Message</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

        </div><!-- /col main -->
    </div><!-- /row -->
</div><!-- /container -->

<?php require_once '../includes/components/footer.php'; ?>