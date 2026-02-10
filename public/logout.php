<?php
/**
 * Logout Handler
 * 
 * Securely destroys user session and clears all cookies
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Log user activity if logged in
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'] ?? 0;
    logActivity($conn, $userId, 'Logout', 'User logged out', $_SERVER['REMOTE_ADDR']);
}

// Clear all session variables
$_SESSION = array();

// Delete session cookie with proper parameters
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with message
redirect(BASE_URL . '/login.php', 'You have been logged out successfully.', 'info');
?>
