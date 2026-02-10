/* 
 * Admin Dashboard JavaScript
 * Handles interactive elements on the admin dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin dashboard loaded');
    
    // Initialize any charts or data visualization here
    
    // Example event listeners for dashboard actions
    const quickActions = document.querySelectorAll('.list-group-item-action');
    quickActions.forEach(action => {
        action.addEventListener('click', function(e) {
            // You could add confirmation dialogs for sensitive actions
            if (this.getAttribute('href') === 'system-backup.php') {
                if (!confirm('Are you sure you want to create a system backup?')) {
                    e.preventDefault();
                }
            }
        });
    });
});
