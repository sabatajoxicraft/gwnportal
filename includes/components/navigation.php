<?php
/**
 * Common navigation component for all pages
 * 
 * @param string $activePage The current active page
 */

// Force password reset if required (allow reset_password.php and logout)
if (isLoggedIn() && !empty($_SESSION['password_reset_required'])) {
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    if (!in_array($currentScript, ['reset_password.php', 'logout.php'])) {
        redirect(BASE_URL . '/reset_password.php', 'Please set a new password to continue.', 'warning');
    }
}

// Determine the active page for navigation highlighting
$activePage = $activePage ?? '';

// Get user role for conditional display
$userRole = $_SESSION['user_role'] ?? '';

// Default nav for users not logged in
$navItems = [
    'home' => [
        'url' => BASE_URL . '/index.php',
        'text' => 'Home',
        'icon' => 'bi-house'
    ],
    'login' => [
        'url' => BASE_URL . '/login.php',
        'text' => 'Login',
        'icon' => 'bi-box-arrow-in-right'
    ]
];

// Role-based navigation
if (isLoggedIn()) {
    // Common items for all logged-in users - Remove profile from main nav
    $navItems = [
        'dashboard' => [
            'url' => BASE_URL . '/dashboard.php',
            'text' => 'Dashboard',
            'icon' => 'bi-speedometer2'
        ]
        // Profile entry removed from here
    ];
    
    // Role-specific nav items
    switch ($userRole) {
        case 'admin':
            $navItems['users'] = [
                'url' => BASE_URL . '/admin/users.php',
                'text' => 'Users',
                'icon' => 'bi-people'
            ];
            $navItems['accommodations'] = [
                'url' => BASE_URL . '/accommodations/',
                'text' => 'Accommodations',
                'icon' => 'bi bi-building'
            ];
            break;
            
        case 'owner':
            $navItems['accommodations'] = [
                'url' => BASE_URL . '/accommodations/',
                'text' => 'Accommodations',
                'icon' => 'bi bi-building'
            ];
            $navItems['managers'] = [
                'url' => BASE_URL . '/managers.php',
                'text' => 'Managers',
                'icon' => 'bi-people'
            ];
            break;
            
        case 'manager':
            $navItems['students'] = [
                'url' => BASE_URL . '/students.php',
                'text' => 'Students',
                'icon' => 'bi-people'
            ];
            $navItems['voucher_history'] = [
                'url' => BASE_URL . '/manager/voucher-history.php',
                'text' => 'Vouchers',
                'icon' => 'bi-wifi'
            ];
            break;
            
        case 'student':
            unset($navItems['dashboard']);
            $navItems['student-dashboard'] = [
                'url' => BASE_URL . '/student/dashboard.php',
                'text' => 'Dashboard',
                'icon' => 'bi-speedometer2'
            ];
            $navItems['student-voucher'] = [
                'url' => BASE_URL . '/student/request-voucher.php',
                'text' => 'Request Voucher',
                'icon' => 'bi-wifi'
            ];
            $navItems['student-devices'] = [
                'url' => BASE_URL . '/student/devices.php',
                'text' => 'My Devices',
                'icon' => 'bi-laptop'
            ];
            break;
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark <?= $navbarClass ?>">
    <div class="container">
        <a class="navbar-brand" href="<?= isLoggedIn() ? BASE_URL . '/dashboard.php' : BASE_URL ?>">
            <i class="bi bi-wifi me-2"></i><?= APP_NAME ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <?php foreach ($navItems as $id => $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === $id ? 'active' : '' ?>" href="<?= $item['url'] ?>">
                            <?php if (!empty($item['icon'])): ?>
                                <i class="bi <?= $item['icon'] ?> me-1"></i>
                            <?php endif; ?>
                            <?= $item['text'] ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if (isLoggedIn()): ?>
                <ul class="navbar-nav ms-auto">
                    <?php include_once __DIR__ . '/notifications.php'; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User') ?>
                            <span class="badge <?= getRoleBadgeClass($userRole) ?> ms-1"><?= ucfirst($userRole) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php"><i class="bi bi-person me-2"></i>User Profile (<?= htmlspecialchars(ucfirst($userRole)) ?>)</a></li>
                            
                            <?php 
                            // Show accommodation items for managers and owners with multiple accommodations
                            if (in_array($userRole, ['manager', 'owner']) && isset($_SESSION['manager_accommodations']) && count($_SESSION['manager_accommodations']) > 1): 
                                $currentAccommodation = $_SESSION['current_accommodation'] ?? null;
                                $managerAccommodations = $_SESSION['manager_accommodations'] ?? [];
                                // Get current page path for switching within same page (preserve subdirectory)
                                $currentPage = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                                $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
                                if ($basePath && strpos($currentPage, $basePath) === 0) {
                                    $currentPage = substr($currentPage, strlen($basePath));
                                }
                                $currentPage = ltrim($currentPage, '/');
                            ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header"><i class="bi bi-building me-2"></i>Switch Accommodation</h6></li>
                            <?php foreach ($managerAccommodations as $accom): ?>
                                <li>
                                    <a class="dropdown-item <?= isset($currentAccommodation) && $accom['id'] == $currentAccommodation['id'] ? 'active' : '' ?>" 
                                       href="<?= BASE_URL ?>/<?= $currentPage ?>?switch_accommodation=<?= $accom['id'] ?>">
                                        <span class="d-flex justify-content-between align-items-center w-100">
                                            <?= htmlspecialchars($accom['name']) ?>
                                            <?= isset($currentAccommodation) && $accom['id'] == $currentAccommodation['id'] ? '<i class="bi bi-check-lg text-success"></i>' : '' ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            
                            <?php if ($userRole === 'admin'): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/codes/"><i class="bi bi-qr-code me-2"></i> Onboarding Codes</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/activity-log.php"><i class="bi bi-clock-history me-2"></i> Activity Log</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/network-management.php"><i class="bi bi-diagram-3 me-2"></i> Network Management</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/reports.php"><i class="bi bi-graph-up me-2"></i> Reports</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>

                            <?php if ($userRole === 'manager'): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/codes/"><i class="bi bi-qr-code me-2"></i> Onboarding Codes</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>

                            <?php if ($userRole === 'student'): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/help.php"><i class="bi bi-question-circle me-2"></i> Help</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
