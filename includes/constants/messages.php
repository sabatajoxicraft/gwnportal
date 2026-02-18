<?php
/**
 * Centralized Message Strings for GWN Portal
 * Eliminates hardcoded error/success/warning messages throughout the application
 * 
 * Usage: Replace all hardcoded strings with getMessage() function
 * Example: redirect('/', getMessage('error', 'unauthorized_access'), 'danger');
 */

// Error messages (for danger alerts)
const ERROR_MESSAGES = [
    'invalid_session' => 'Session expired. Please login again.',
    'unauthorized_access' => 'You do not have permission to access this resource.',
    'invalid_user_id' => 'Invalid user ID provided.',
    'accommodation_not_found' => 'Accommodation not found or you do not have access.',
    'student_not_found' => 'Student not found.',
    'code_expired' => 'Invitation code has expired.',
    'code_invalid' => 'Invalid invitation code.',
    'code_used' => 'Invitation code has already been used.',
    'device_not_found' => 'Device not found.',
    'invalid_mac_address' => 'Invalid MAC address format.',
    'duplicate_device' => 'This device is already registered.',
    'invalid_email' => 'Invalid email address format.',
    'invalid_id_number' => 'Invalid South African ID number format.',
    'invalid_phone' => 'Invalid phone number format.',
    'database_error' => 'Database error occurred. Please try again later.',
    'invalid_password' => 'Password does not meet requirements.',
    'passwords_not_match' => 'Passwords do not match.',
    'user_exists' => 'User account already exists.',
    'username_taken' => 'Username is already taken.',
    'email_taken' => 'Email address is already in use.',
    'id_number_taken' => 'ID number is already registered.',
    'missing_fields' => 'Please fill in all required fields.',
    'invalid_credentials' => 'Invalid username or password.',
    'account_disabled' => 'Your account has been disabled.',
    'account_pending' => 'Your account is pending activation.',
];

// Success messages (for success alerts)
const SUCCESS_MESSAGES = [
    'user_created' => 'User account created successfully.',
    'user_updated' => 'User details updated successfully.',
    'user_deleted' => 'User account deleted successfully.',
    'password_changed' => 'Password changed successfully.',
    'password_reset' => 'Password reset link sent to your email.',
    'accommodation_created' => 'Accommodation created successfully.',
    'accommodation_updated' => 'Accommodation updated successfully.',
    'accommodation_deleted' => 'Accommodation deleted successfully.',
    'student_assigned' => 'Student assigned to accommodation successfully.',
    'student_removed' => 'Student removed from accommodation successfully.',
    'code_generated' => 'Invitation code generated and sent successfully.',
    'code_revoked' => 'Invitation code revoked.',
    'device_registered' => 'Device registered successfully.',
    'device_updated' => 'Device updated successfully.',
    'device_blocked' => 'Device blocked successfully.',
    'device_unblocked' => 'Device unblocked successfully.',
    'device_removed' => 'Device removed successfully.',
    'manager_assigned' => 'Manager assigned to accommodation successfully.',
    'manager_removed' => 'Manager removed from accommodation successfully.',
    'profile_updated' => 'Profile updated successfully.',
    'preferences_saved' => 'Preferences saved successfully.',
    'voucher_sent' => 'Voucher sent successfully.',
];

// Warning messages (for warning alerts)
const WARNING_MESSAGES = [
    'no_data' => 'No data found.',
    'no_accommodations' => 'You have no accommodations assigned.',
    'no_students' => 'No students found.',
    'no_devices' => 'No devices registered.',
    'no_codes' => 'No invitation codes available.',
    'inactive_account' => 'Your account is inactive.',
    'first_login' => 'Please change your password on first login.',
    'password_expires_soon' => 'Your password will expire soon. Please change it.',
    'session_expires_soon' => 'Your session will expire in 5 minutes.',
];

// Info messages (for info alerts)
const INFO_MESSAGES = [
    'verify_email' => 'Please check your email to verify your account.',
    'email_sent' => 'Email has been sent successfully.',
    'loading' => 'Loading, please wait...',
    'processing' => 'Processing your request...',
    'test_mode' => 'Application is running in test mode.',
];

/**
 * Get a message by type and key
 * 
 * @param string $type Message type ('error', 'success', 'warning', 'info')
 * @param string $key Message key
 * @param string $default Default message if key not found
 * @return string The message, or $default if not found
 */
function getMessage($type, $key, $default = '') {
    $type = strtoupper($type);
    $constant = $type . '_MESSAGES';
    
    if (!defined($constant)) {
        return $default;
    }
    
    $messages = constant($constant);
    
    return $messages[$key] ?? $default;
}

/**
 * Get all messages of a specific type
 * 
 * @param string $type Message type ('error', 'success', 'warning', 'info')
 * @return array All messages of that type
 */
function getMessagesByType($type) {
    $type = strtoupper($type);
    $constant = $type . '_MESSAGES';
    
    if (!defined($constant)) {
        return [];
    }
    
    return constant($constant);
}

/**
 * Check if error message key exists
 * 
 * @param string $key Error message key
 * @return bool True if exists, false otherwise
 */
function hasErrorMessage($key) {
    return isset(ERROR_MESSAGES[$key]);
}

/**
 * Check if success message key exists
 * 
 * @param string $key Success message key
 * @return bool True if exists, false otherwise
 */
function hasSuccessMessage($key) {
    return isset(SUCCESS_MESSAGES[$key]);
}

?>
