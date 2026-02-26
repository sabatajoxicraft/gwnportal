<?php
/**
 * Accommodation Switcher Bar Component
 * 
 * Displays a horizontal button group for switching between accommodations
 * Only shows for managers and owners with multiple accommodations
 * 
 * Usage: <?php include __DIR__ . '/accommodation-switcher-bar.php'; ?>
 */

// Only show for logged-in users who are managers or owners
if (!isLoggedIn() || !in_array($_SESSION['user_role'] ?? '', ['manager', 'owner'])) {
    return;
}

$switcherAccommodations = $_SESSION['manager_accommodations'] ?? [];

// Only show if user has multiple accommodations
if (count($switcherAccommodations) <= 1) {
    return;
}

// Get current accommodation ID from session
$currentAccommodationId = null;
if ($_SESSION['user_role'] === 'manager') {
    $currentAccommodationId = $_SESSION['accommodation_id'] ?? null;
} else if ($_SESSION['user_role'] === 'owner') {
    $currentAccommodationId = $_SESSION['current_accommodation']['id'] ?? null;
}

// Get current page path for switching
$currentPage = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
if ($basePath && strpos($currentPage, $basePath) === 0) {
    $currentPage = substr($currentPage, strlen($basePath));
}
$currentPage = ltrim($currentPage, '/');
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="btn-group flex-wrap" role="group">
            <?php foreach ($switcherAccommodations as $accom): ?>
                <a href="<?= BASE_URL ?>/<?= $currentPage ?>?switch_accommodation=<?= $accom['id'] ?>" 
                   class="btn btn-outline-primary<?= ($accom['id'] == $currentAccommodationId) ? ' active' : '' ?>">
                    <?= htmlspecialchars(strtoupper($accom['name']), ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
