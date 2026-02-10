<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default page variables
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_NAME ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons - Updated to latest version -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts - Nunito -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/custom.css" rel="stylesheet">
    <!-- Page specific CSS -->
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body>
    <?php 
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

    <!-- Include navigation component -->
    <?php require_once INCLUDES_PATH . '/components/navigation.php'; ?>
    
    <!-- Flash Messages Display -->
    <?php if (isset($_SESSION['flash'])): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show" role="alert">
            <?= $_SESSION['flash']['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    
    <!-- Page content will follow; footer will close body/html -->
