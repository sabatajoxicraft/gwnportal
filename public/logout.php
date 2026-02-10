<?php
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

// If session is using cookies, remove that cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with message
redirect(BASE_URL . '/login.php', 'You have been logged out successfully.', 'info');
?>
