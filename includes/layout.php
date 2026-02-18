<?php
/**
 * Main Layout Template
 * This file provides a consistent layout wrapper for all pages
 * 
 * Usage in pages:
 * 1. Set variables: $pageTitle, $activePage, $content (content or callback)
 * 2. Include this file at the end of your page logic
 * 3. All HTML will be rendered with consistent header/footer/nav
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure config and functions are loaded (without reloading)
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';
}

// Ensure accommodation handler runs for managers and owners
if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['manager', 'owner']) && !isset($_SESSION['accommodation_handler_ran'])) {
    require_once __DIR__ . '/accommodation-handler.php';
    $_SESSION['accommodation_handler_ran'] = true;
}

// Default page variables
$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
$pageContent = $pageContent ?? '';
$extraCss = $extraCss ?? '';
$extraJs = $extraJs ?? '';

// Get user role for conditional styling
$userRole = $_SESSION['user_role'] ?? '';
$navbarClass = '';

// Set navbar class based on user role
if (isLoggedIn()) {
    switch ($userRole) {
        case 'admin':
            $navbarClass = 'admin-navbar';
            break;
        case 'owner':
            $navbarClass = 'owner-navbar';
            break;
        case 'manager':
            $navbarClass = 'manager-navbar';
            break;
        case 'student':
            $navbarClass = 'student-navbar';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts - Nunito -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/custom.css" rel="stylesheet">
    <!-- Page specific CSS -->
    <?php if (!empty($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <?php require_once __DIR__ . '/components/navigation.php'; ?>
    
    <!-- Flash Messages -->
    <div class="container mt-4">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        <?php 
        // If pageContent is a callback, execute it to get content
        if (is_callable($pageContent)) {
            echo $pageContent();
        } else {
            echo $pageContent;
        }
        ?>
    </div>

    <!-- Footer -->
    <?php require_once __DIR__ . '/components/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Page specific JS -->
    <?php if (!empty($extraJs)): ?>
        <?= $extraJs ?>
    <?php endif; ?>
</body>
</html>
