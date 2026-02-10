<?php
/**
 * Component for displaying flash messages and errors
 * 
 * @param string $error Error message to display
 * @param string $success Success message to display
 */

// Check for flash messages
$flashMessage = null;
$flashType = null;

if (isset($_SESSION['flash'])) {
    $flashMessage = $_SESSION['flash']['message'];
    $flashType = $_SESSION['flash']['type'];
    unset($_SESSION['flash']);
}

// Local error/success messages take precedence
$error = $error ?? null;
$success = $success ?? null;
?>

<?php if ($flashMessage): ?>
    <div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
        <?= $flashMessage ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
