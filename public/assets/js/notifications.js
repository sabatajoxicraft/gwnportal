/**
 * Notifications JavaScript
 * Handles notification interactions, auto-refresh, and mark as read
 */

(function() {
    'use strict';

    // Configuration
    const REFRESH_INTERVAL = 30000; // 30 seconds
    const configuredBaseUrl = (window.GWN_BASE_URL || '').replace(/\/$/, '');
    const BASE_URL = configuredBaseUrl || window.location.origin;

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotifications);
    } else {
        initNotifications();
    }

    function initNotifications() {
        if (window.__gwnNotificationsInitialized) {
            return;
        }
        window.__gwnNotificationsInitialized = true;

        // Auto-refresh badge count
        setInterval(refreshNotificationBadge, REFRESH_INTERVAL);

        // Mark as read on click
        delegateEvent(document, 'click', '.notification-item', handleNotificationClick);

        // Mark all as read button
        delegateEvent(document, 'click', '#mark-all-read', handleMarkAllRead);

        // Prevent dropdown from closing when clicking notification items
        const notificationItems = document.querySelectorAll('.notification-item');
        notificationItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
            });
        });
    }

    /**
     * Refresh notification badge count via AJAX
     */
    function refreshNotificationBadge() {
        fetch(BASE_URL + '/api/notifications-count.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateBadge(data.count);
            }
        })
        .catch(error => {
            console.error('Error refreshing notification count:', error);
        });
    }

    /**
     * Update badge display
     */
    function updateBadge(count) {
        const badge = document.getElementById('notification-badge');
        
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 99 ? '99+' : count;
            } else {
                // Create badge if it doesn't exist
                const bellIcon = document.querySelector('#notificationsDropdown');
                if (bellIcon) {
                    const newBadge = document.createElement('span');
                    newBadge.id = 'notification-badge';
                    newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    newBadge.innerHTML = (count > 99 ? '99+' : count) + '<span class="visually-hidden">unread notifications</span>';
                    bellIcon.appendChild(newBadge);
                }
            }
        } else {
            // Remove badge if count is 0
            if (badge) {
                badge.remove();
            }
        }
    }

    /**
     * Handle notification item click - mark as read
     */
    function handleNotificationClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const item = this;
        const notificationId = item.dataset.notificationId;
        const category = item.dataset.category;
        const relatedId = item.dataset.relatedId;

        if (!notificationId) {
            return;
        }
        
        // Mark as read
        markAsRead(notificationId, function(success) {
            if (success) {
                // Update UI
                item.classList.remove('unread');
                const dot = item.querySelector('.notification-unread-dot');
                if (dot) {
                    dot.remove();
                }
                
                // Update badge count
                const currentBadge = document.getElementById('notification-badge');
                if (currentBadge) {
                    const currentCount = parseInt(currentBadge.textContent) || 0;
                    updateBadge(currentCount - 1);
                }
                
                // Navigate to related page if applicable
                navigateToRelated(category, relatedId);
            }
        });
    }

    /**
     * Handle mark all as read button
     */
    function handleMarkAllRead(e) {
        e.preventDefault();
        e.stopPropagation();
        
        fetch(BASE_URL + '/api/mark-notification-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'mark_all=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update all notification items
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    const dot = item.querySelector('.notification-unread-dot');
                    if (dot) {
                        dot.remove();
                    }
                });
                
                // Remove badge
                updateBadge(0);
                
                // Hide "mark all as read" button
                const markAllBtn = document.getElementById('mark-all-read');
                if (markAllBtn) {
                    markAllBtn.remove();
                }
            }
        })
        .catch(error => {
            console.error('Error marking all as read:', error);
        });
    }

    /**
     * Mark notification as read via AJAX
     */
    function markAsRead(notificationId, callback) {
        fetch(BASE_URL + '/api/mark-notification-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'notification_id=' + encodeURIComponent(notificationId)
        })
        .then(response => response.json())
        .then(data => {
            if (callback) {
                callback(data.success);
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
            if (callback) {
                callback(false);
            }
        });
    }

    /**
     * Navigate to related page based on category
     */
    function navigateToRelated(category, relatedId) {
        if (!category || !relatedId) {
            return;
        }

        let url = null;
        
        switch (category) {
            case 'device_request':
            case 'device_approval':
            case 'device_rejection':
                url = BASE_URL + '/student/devices.php';
                break;
            case 'voucher':
                // Could navigate to voucher history
                url = BASE_URL + '/manager/voucher-history.php';
                break;
            case 'new_student':
                url = BASE_URL + '/students.php';
                break;
        }
        
        if (url) {
            window.location.href = url;
        }
    }

    /**
     * Event delegation helper
     */
    function delegateEvent(parent, eventType, selector, handler) {
        parent.addEventListener(eventType, function(e) {
            const target = e.target.closest(selector);
            if (target) {
                handler.call(target, e);
            }
        });
    }

})();
