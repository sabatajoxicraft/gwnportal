<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Require manager login
requireManagerLogin();

$accommodation_id = $_SESSION['accommodation_id'] ?? $_SESSION['manager_id'] ?? 0;
$conn = getDbConnection();

// Get student ID from query string
$student_id = $_GET['id'] ?? 0;

// Verify student belongs to this manager and fetch user details including username
$stmt = $conn->prepare("SELECT s.id, s.status, s.user_id, u.first_name, u.last_name, u.email, u.phone_number, u.whatsapp_number, u.preferred_communication, u.username
                        FROM students s
                        JOIN users u ON s.user_id = u.id
                        WHERE s.id = ? AND s.accommodation_id = ?");
$stmt->bind_param("ii", $student_id, $accommodation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect(BASE_URL . '/students.php', 'Student not found or does not belong to your accommodation.', 'danger');
}

$student = $result->fetch_assoc();

// Block resending credentials to archived students
if ($student['status'] === 'archived') {
    redirect(BASE_URL . '/student-details.php?id=' . $student_id, 'Cannot resend credentials to an archived student. Restore the student first.', 'danger');
}

// Generate a secure temporary password
$temp_password = bin2hex(random_bytes(8));

// Hash the temporary password
$hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

// Update user password and set password_reset_required flag
$stmt_update = $conn->prepare("UPDATE users SET password = ?, password_reset_required = 1 WHERE id = ?");
$stmt_update->bind_param("si", $hashed_password, $student['user_id']);

if (!$stmt_update->execute()) {
    redirect(BASE_URL . '/student-details.php?id=' . $student_id, 'Failed to generate new credentials. Please try again.', 'danger');
}

// Send credentials via SMS template
$sent = false;
$method = 'SMS';
$sendNumber = !empty($student['phone_number']) ? $student['phone_number'] : ($student['whatsapp_number'] ?? '');

if (!empty($sendNumber)) {
    $sent = sendCredentialsMessage($sendNumber, $student['first_name'], $student['username'], $temp_password);
} else {
    // Fallback to email
    $message = "Hello {$student['first_name']},\n\nHere are your login details for the WiFi Portal:\n\nUsername: {$student['username']}\nTemporary Password: {$temp_password}\n\nPlease login and change your password immediately.\n\n- WiFi Management Team";
    $from_email = defined('SYSTEM_EMAIL') ? SYSTEM_EMAIL : 'noreply@kimwifi.co.za';
    $sent = mail(
        $student['email'],
        'WiFi Portal - Login Credentials',
        $message,
        'From: ' . $from_email
    );
    $method = 'Email';
}

if ($sent) {
    // Log the activity
    logActivity($conn, $_SESSION['user_id'], 'resend_credentials', "Resent login credentials to student ID {$student_id} via {$method}", $_SERVER['REMOTE_ADDR']);
    
    redirect(BASE_URL . '/student-details.php?id=' . $student_id, "Login credentials have been sent successfully to {$student['first_name']} {$student['last_name']} via {$method}.", 'success');
} else {
    redirect(BASE_URL . '/student-details.php?id=' . $student_id, 'Failed to send login credentials. Please try again.', 'danger');
}
