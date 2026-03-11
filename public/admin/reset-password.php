<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin role
requireRole('admin');

// Get database connection
$conn = getDbConnection();

// Get POST data
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$reset_type = $_POST['reset_type'] ?? 'generate';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$send_email = isset($_POST['send_email']) && $_POST['send_email'] == 'on';

// Validate CSRF token
try {
    requireCsrfToken();
} catch (Exception $e) {
    redirect(BASE_URL . '/admin/users.php', 'Security validation failed', 'danger');
}

// Validate user ID
if ($user_id <= 0) {
    redirect(BASE_URL . '/admin/users.php', 'Invalid user ID', 'danger');
}

// Check if user exists
$user_stmt = safeQueryPrepare($conn, "SELECT id, username, email, first_name, last_name FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    redirect(BASE_URL . '/admin/view-user.php?id=' . $user_id, 'User not found', 'danger');
}

$error = '';
$password_to_set = '';

// Handle password generation or validation
if ($reset_type === 'generate') {
    // Generate a random password
    $password_to_set = generateRandomPassword(12);
} elseif ($reset_type === 'specify') {
    // Validate provided passwords
    if (empty($new_password) || empty($confirm_password)) {
        redirect(BASE_URL . '/admin/view-user.php?id=' . $user_id, 'Password fields are required', 'danger');
    }
    
    if ($new_password !== $confirm_password) {
        redirect(BASE_URL . '/admin/view-user.php?id=' . $user_id, 'Passwords do not match', 'danger');
    }
    
    if (strlen($new_password) < 8) {
        redirect(BASE_URL . '/admin/view-user.php?id=' . $user_id, 'Password must be at least 8 characters', 'danger');
    }
    
    $password_to_set = $new_password;
} else {
    redirect(BASE_URL . '/admin/view-user.php?id=' . $user_id, 'Invalid reset type', 'danger');
}

// Hash the password
$password_hash = createPasswordHash($password_to_set);

// Update the user's password in the database
$update_stmt = safeQueryPrepare($conn, "UPDATE users SET password = ? WHERE id = ?");
$update_stmt->bind_param("si", $password_hash, $user_id);

if (!$update_stmt->execute()) {
    redirect(BASE_URL . '/admin/view-user.php?id=' . $user_id, 'Error updating password: ' . $conn->error, 'danger');
}

// Log the password reset action
logActivity($conn, $_SESSION['user_id'], 'Admin Password Reset', 'Reset password for user: ' . $user['username'], $_SERVER['REMOTE_ADDR']);

// Send email if requested
$email_sent = false;
if ($send_email && !empty($user['email'])) {
    $subject = 'Your Password Has Been Reset';
    
    // Build email message
    $email_body = "Dear " . htmlspecialchars($user['first_name']) . ",\n\n";
    $email_body .= "An administrator has reset your password for the GWN WiFi Portal.\n\n";
    $email_body .= "Your new login credentials:\n";
    $email_body .= "Username: " . htmlspecialchars($user['username']) . "\n";
    $email_body .= "Password: " . $password_to_set . "\n\n";
    $email_body .= "Please log in at: " . BASE_URL . "/login.php\n\n";
    $email_body .= "For security reasons, please change your password after logging in.\n\n";
    $email_body .= "Best regards,\n";
    $email_body .= "GWN WiFi Portal Administration Team";
    
    $email_sent = sendAppEmail($user['email'], $subject, $email_body, false);
}

// Build success message
$message = 'Password reset successfully';
if ($reset_type === 'generate') {
    $message .= '. Generated password: <strong>' . htmlspecialchars($password_to_set) . '</strong>';
}
if ($send_email) {
    $message .= $email_sent ? '. Email sent to user.' : '. Email could not be sent.';
}

// Redirect back to view-user page
redirect(BASE_URL . '/admin/view-user.php?id=' . $user_id, $message, 'success');
