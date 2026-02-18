<?php
/**
 * Notifications Dropdown Component
 * Displays a bell icon with notification badge and dropdown
 */

// Only show notifications for logged-in users
if (!isLoggedIn()) {
    return;
}

$userId = $_SESSION['user_id'] ?? 0;

// Get unread count and recent notifications
$unreadCount = getUnreadNotificationCount($userId);
$recentNotifications = getRecentNotifications($userId, 10);
?>

<!-- Notifications Dropdown -->
<li class="nav-item dropdown">
    <a class="nav-link position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell fs-5"></i>
        <?php if ($unreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notification-badge">
                <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                <span class="visually-hidden">unread notifications</span>
            </span>
        <?php endif; ?>
    </a>
    <div class="dropdown-menu dropdown-menu-end notifications-dropdown" aria-labelledby="notificationsDropdown">
        <div class="dropdown-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">Notifications</span>
            <?php if ($unreadCount > 0): ?>
                <a href="#" class="text-decoration-none small" id="mark-all-read">Mark all as read</a>
            <?php endif; ?>
        </div>
        <div class="dropdown-divider"></div>
        
        <div class="notifications-list" style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
            <?php if (count($recentNotifications) > 0): ?>
                <?php foreach ($recentNotifications as $notification): ?>
                    <a href="#" 
                       class="dropdown-item notification-item <?= $notification['is_read'] ? '' : 'unread' ?>" 
                       data-notification-id="<?= (int)$notification['id'] ?>"
                       data-category="<?= htmlspecialchars((string)($notification['category'] ?? '')) ?>"
                       data-related-id="<?= htmlspecialchars((string)($notification['related_id'] ?? '')) ?>">
                        <div class="d-flex">
                            <div class="notification-icon me-3">
                                <?php
                                // Icon based on type
                                $iconClass = 'bi-info-circle text-info';
                                switch ($notification['type']) {
                                    case 'success':
                                        $iconClass = 'bi-check-circle text-success';
                                        break;
                                    case 'warning':
                                        $iconClass = 'bi-exclamation-triangle text-warning';
                                        break;
                                    case 'danger':
                                        $iconClass = 'bi-x-circle text-danger';
                                        break;
                                }
                                ?>
                                <i class="bi <?= $iconClass ?> fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="small text-muted"><?= htmlEscape($notification['message']) ?></div>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-clock"></i> <?= timeAgo($notification['created_at']) ?>
                                </div>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <div class="notification-unread-dot">
                                    <span class="badge bg-primary rounded-circle" style="width: 8px; height: 8px; padding: 0;"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div class="dropdown-divider"></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="dropdown-item-text text-center py-4 text-muted">
                    <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>
                    <div>No notifications</div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($recentNotifications) > 0): ?>
            <div class="dropdown-divider"></div>
            <div class="dropdown-footer text-center">
                <a href="<?= BASE_URL ?>/notifications.php" class="dropdown-item text-center text-primary">
                    View all notifications
                </a>
            </div>
        <?php endif; ?>
    </div>
</li>

<style>
.notifications-dropdown {
    width: 380px;
    max-width: 90vw;
    padding: 0;
    overflow-x: hidden;
}

.notifications-dropdown .dropdown-header {
    padding: 1rem;
    background-color: #f8f9fa;
}

.notifications-dropdown .dropdown-footer {
    background-color: #f8f9fa;
}

.notification-item {
    padding: 0.75rem 1rem;
    border-left: 3px solid transparent;
    transition: all 0.2s;
    white-space: normal;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #e7f3ff;
    border-left-color: #0d6efd;
}

.notification-icon {
    flex-shrink: 0;
}

.notification-unread-dot {
    flex-shrink: 0;
    margin-left: 0.5rem;
}

#notification-badge {
    font-size: 0.65rem;
    padding: 0.25em 0.4em;
}
</style>

<script>
window.GWN_BASE_URL = <?= json_encode((string)BASE_URL, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
