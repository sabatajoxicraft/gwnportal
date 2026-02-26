<?php
/**
 * Auth Header Component
 * Used on: Login, registration, password reset, authentication pages
 * 
 * Features: Minimal, distraction-free, no navigation, focus on auth actions
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default page variables
$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? 'd-flex flex-column min-vh-100 bg-light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts - Nunito -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/custom.css" rel="stylesheet">
    
    <!-- Page specific CSS -->
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
    
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/img/favicon.ico">
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Minimal Header - Brand Only -->
    <div class="bg-white border-bottom shadow-sm py-3">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between">
                <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none">
                    <h4 class="mb-0">
                        <i class="bi bi-wifi text-primary me-2"></i>
                        <span class="text-dark"><?= APP_NAME ?></span>
                    </h4>
                </a>
                <a href="<?= BASE_URL ?>/help.php" class="text-muted text-decoration-none">
                    <i class="bi bi-question-circle me-1"></i>Need help?
                </a>
            </div>
        </div>
    </div>
    
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
