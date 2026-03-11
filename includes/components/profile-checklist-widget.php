<?php
/**
 * Profile Completion Checklist Widget
 * 
 * Dismissible widget showing profile setup progress
 * Reappears on login if dismissed AND completion < 100%
 * 
 * Usage:
 * require_once __DIR__ . '/../services/ProfileChecklistService.php';
 * include __DIR__ . '/../components/profile-checklist-widget.php';
 */

// Only show if user is logged in
if (!isset($_SESSION['user_id'])) {
    return;
}

// Auto-check tasks on page load
ProfileChecklistService::autoCheckTasks($conn, $_SESSION['user_id']);

// Check if widget should be displayed
$percentage = ProfileChecklistService::getCompletionPercentage($conn, $_SESSION['user_id']);
$isDismissed = ProfileChecklistService::isWidgetDismissed($conn, $_SESSION['user_id']);

// Show widget if: NOT dismissed OR completion < 100%
if ($isDismissed && $percentage >= 100) {
    return; // Fully complete and dismissed, don't show
}

$incompleteItems = ProfileChecklistService::getIncompleteItems($conn, $_SESSION['user_id']);
$incompleteCount = count(array_filter($incompleteItems, function($item) { return !$item['optional']; }));

// Don't show if 100% complete (all required tasks done)
if ($percentage >= 100) {
    return;
}
?>

<div class="card mb-4 border-primary shadow-sm profile-checklist-widget" id="profileChecklistWidget">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">
                <i class="fas fa-tasks me-2"></i>Complete Your Profile Setup
            </h5>
        </div>
        <button type="button" class="btn btn-sm btn-light" onclick="dismissChecklistWidget()" title="Dismiss">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold">Progress: <?= number_format($percentage, 0) ?>% Complete</span>
                <span class="text-muted small"><?= $incompleteCount ?> task<?= $incompleteCount != 1 ? 's' : '' ?> remaining</span>
            </div>
            <div class="progress" style="height: 25px;">
                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                     role="progressbar" 
                     style="width: <?= $percentage ?>%;" 
                     aria-valuenow="<?= $percentage ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                    <?= number_format($percentage, 0) ?>%
                </div>
            </div>
        </div>

        <?php if (!empty($incompleteItems)): ?>
            <div class="checklist-items">
                <h6 class="fw-bold mb-3">Tasks to Complete:</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach ($incompleteItems as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-circle-notch text-warning me-2"></i>
                                <?= htmlspecialchars($item['label']) ?>
                                <?php if ($item['optional']): ?>
                                    <span class="badge bg-secondary ms-2">Optional</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['link']): ?>
                                <a href="<?= htmlspecialchars($item['link']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-arrow-right me-1"></i> Go
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="mt-3 text-center">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Complete your profile to unlock all features.
                <a href="/public/profile.php" class="text-decoration-none">View full checklist</a>
            </small>
        </div>
    </div>
</div>

<script>
function dismissChecklistWidget() {
    // Send AJAX request to dismiss widget
    fetch('/public/api/dismiss-checklist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ dismiss: true })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hide widget with smooth animation
            const widget = document.getElementById('profileChecklistWidget');
            if (widget) {
                widget.style.transition = 'opacity 0.3s ease-out';
                widget.style.opacity = '0';
                setTimeout(() => {
                    widget.remove();
                }, 300);
            }
        } else {
            console.error('Failed to dismiss checklist widget');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>

<style>
.profile-checklist-widget {
    animation: slideInDown 0.5s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.checklist-items ul {
    max-height: 300px;
    overflow-y: auto;
}

.checklist-items .list-group-item {
    border-left: none;
    border-right: none;
    padding: 0.75rem 0;
}

.checklist-items .list-group-item:first-child {
    border-top: none;
}
</style>
