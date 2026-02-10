<?php
/**
 * Resource-Based Access Control (RBAC) Permissions System
 * 
 * Provides fine-grained permission checks for resources like accommodations,
 * students, and users. Complements role-based checks in functions.php.
 * 
 * @package GWN Portal
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ============================================================================
// RESOURCE OWNERSHIP CHECKS
// ============================================================================

/**
 * Check if current user can view a specific user's profile
 * 
 * @param int $user_id The user ID to view
 * @return bool True if access allowed
 */
function canViewUser($user_id) {
    if (!isLoggedIn()) return false;
    
    $user_id = (int)$user_id;
    $current_user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? '';
    
    // Admin can view all users
    if ($role === 'admin') return true;
    
    // Users can view their own profile
    if ($current_user_id === $user_id) return true;
    
    // Owners and managers can view users in their accommodations
    if ($role === 'owner' || $role === 'manager') {
        $user_accommodations = getUserAccommodations($current_user_id);
        $target_accommodations = getStudentAccommodation($user_id);
        
        if ($target_accommodations && in_array($target_accommodations, $user_accommodations)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if current user can edit a specific user's profile
 * 
 * @param int $user_id The user ID to edit
 * @return bool True if edit allowed
 */
function canEditUser($user_id) {
    if (!isLoggedIn()) return false;
    
    $user_id = (int)$user_id;
    $current_user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? '';
    
    // Admin can edit all users
    if ($role === 'admin') return true;
    
    // Users can edit their own profile
    if ($current_user_id === $user_id) return true;
    
    // Owners and managers cannot edit other users directly
    return false;
}

/**
 * Check if current user can edit an accommodation
 * 
 * @param int $accommodation_id The accommodation ID to check
 * @return bool True if edit allowed
 */
function canEditAccommodation($accommodation_id) {
    if (!isLoggedIn()) return false;
    
    $accommodation_id = (int)$accommodation_id;
    $current_user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? '';
    
    // Admin can edit all accommodations
    if ($role === 'admin') return true;
    
    // Owner must own the accommodation
    if ($role === 'owner') {
        return isAccommodationOwner($accommodation_id, $current_user_id);
    }
    
    return false;
}

/**
 * Check if current user can manage students in an accommodation
 * 
 * @param int $accommodation_id The accommodation ID to check
 * @return bool True if management allowed
 */
function canManageStudents($accommodation_id) {
    if (!isLoggedIn()) return false;
    
    $accommodation_id = (int)$accommodation_id;
    $current_user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? '';
    
    // Admin can manage all
    if ($role === 'admin') return true;
    
    // Owner can manage students in owned accommodations
    if ($role === 'owner') {
        return isAccommodationOwner($accommodation_id, $current_user_id);
    }
    
    // Manager can manage students in assigned accommodations
    if ($role === 'manager') {
        return isManagerOfAccommodation($accommodation_id, $current_user_id);
    }
    
    return false;
}

/**
 * Check if current user can edit a specific student
 * 
 * @param int $student_id The student ID (from students table) to check
 * @return bool True if edit allowed
 */
function canEditStudent($student_id) {
    if (!isLoggedIn()) return false;
    
    $student_id = (int)$student_id;
    $current_user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? '';
    
    // Admin can edit all students
    if ($role === 'admin') return true;
    
    // Get the student's accommodation
    $conn = getDbConnection();
    $stmt = safeQueryPrepare($conn, "SELECT accommodation_id, user_id FROM students WHERE id = ?");
    if (!$stmt) return false;
    
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return false;
    
    $student = $result->fetch_assoc();
    $accommodation_id = (int)$student['accommodation_id'];
    $student_user_id = (int)$student['user_id'];
    
    // Students can edit their own record
    if ($role === 'student' && $current_user_id === $student_user_id) {
        return true;
    }
    
    // Owner can edit students in owned accommodations
    if ($role === 'owner') {
        return isAccommodationOwner($accommodation_id, $current_user_id);
    }
    
    // Manager can edit students in assigned accommodations
    if ($role === 'manager') {
        return isManagerOfAccommodation($accommodation_id, $current_user_id);
    }
    
    return false;
}

/**
 * Check if current user can create onboarding codes for an accommodation
 * 
 * @param int|null $accommodation_id Specific accommodation or null for any
 * @return bool True if code creation allowed
 */
function canCreateCodes($accommodation_id = null) {
    if (!isLoggedIn()) return false;
    
    $current_user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? '';
    
    // Admin can create codes for all accommodations
    if ($role === 'admin') return true;
    
    // If no specific accommodation, check if user has any
    if ($accommodation_id === null) {
        if ($role === 'owner') {
            $accommodations = getOwnerAccommodations($current_user_id);
            return !empty($accommodations);
        }
        if ($role === 'manager') {
            $accommodations = getManagerAccommodations($current_user_id);
            return !empty($accommodations);
        }
        return false;
    }
    
    $accommodation_id = (int)$accommodation_id;
    
    // Owner can create codes for owned accommodations
    if ($role === 'owner') {
        return isAccommodationOwner($accommodation_id, $current_user_id);
    }
    
    // Manager can create codes for assigned accommodations
    if ($role === 'manager') {
        return isManagerOfAccommodation($accommodation_id, $current_user_id);
    }
    
    return false;
}

/**
 * Check if current user can view students in an accommodation
 * 
 * @param int $accommodation_id The accommodation ID to check
 * @return bool True if viewing allowed
 */
function canViewAccommodationStudents($accommodation_id) {
    if (!isLoggedIn()) return false;
    
    $accommodation_id = (int)$accommodation_id;
    $current_user_id = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'] ?? '';
    
    // Admin can view all
    if ($role === 'admin') return true;
    
    // Owner can view students in owned accommodations
    if ($role === 'owner') {
        return isAccommodationOwner($accommodation_id, $current_user_id);
    }
    
    // Manager can view students in assigned accommodations
    if ($role === 'manager') {
        return isManagerOfAccommodation($accommodation_id, $current_user_id);
    }
    
    return false;
}

// ============================================================================
// ACCOMMODATION ACCESS HELPERS
// ============================================================================

/**
 * Get all accommodations a user can access
 * 
 * @param int|null $user_id User ID or null for current user
 * @return array Array of accommodation IDs
 */
function getUserAccommodations($user_id = null) {
    $conn = getDbConnection();
    $user_id = $user_id !== null ? (int)$user_id : (int)($_SESSION['user_id'] ?? 0);
    
    if ($user_id === 0) return [];
    
    // Get user's role
    $stmt = safeQueryPrepare($conn, "SELECT r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    if (!$stmt) return [];
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return [];
    
    $role = $result->fetch_assoc()['role'];
    
    // Admin sees all accommodations
    if ($role === 'admin') {
        $stmt = safeQueryPrepare($conn, "SELECT id FROM accommodations");
        if (!$stmt) return [];
        $stmt->execute();
        $result = $stmt->get_result();
        
        $accommodations = [];
        while ($row = $result->fetch_assoc()) {
            $accommodations[] = (int)$row['id'];
        }
        return $accommodations;
    }
    
    // Owner sees owned accommodations
    if ($role === 'owner') {
        return getOwnerAccommodations($user_id);
    }
    
    // Manager sees assigned accommodations
    if ($role === 'manager') {
        return getManagerAccommodations($user_id);
    }
    
    // Student sees their enrolled accommodation
    if ($role === 'student') {
        $accommodation = getStudentAccommodation($user_id);
        return $accommodation ? [$accommodation] : [];
    }
    
    return [];
}

/**
 * Get accommodations assigned to a manager
 * 
 * @param int|null $manager_id Manager user ID or null for current user
 * @return array Array of accommodation IDs
 */
function getManagerAccommodations($manager_id = null) {
    $conn = getDbConnection();
    $manager_id = $manager_id !== null ? (int)$manager_id : (int)($_SESSION['user_id'] ?? 0);
    
    if ($manager_id === 0) return [];
    
    $stmt = safeQueryPrepare($conn, "SELECT accommodation_id FROM user_accommodation WHERE user_id = ?");
    if (!$stmt) return [];
    
    $stmt->bind_param("i", $manager_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $accommodations = [];
    while ($row = $result->fetch_assoc()) {
        $accommodations[] = (int)$row['accommodation_id'];
    }
    
    return $accommodations;
}

/**
 * Get accommodations owned by an owner
 * 
 * @param int|null $owner_id Owner user ID or null for current user
 * @return array Array of accommodation IDs
 */
function getOwnerAccommodations($owner_id = null) {
    $conn = getDbConnection();
    $owner_id = $owner_id !== null ? (int)$owner_id : (int)($_SESSION['user_id'] ?? 0);
    
    if ($owner_id === 0) return [];
    
    $stmt = safeQueryPrepare($conn, "SELECT id FROM accommodations WHERE owner_id = ?");
    if (!$stmt) return [];
    
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $accommodations = [];
    while ($row = $result->fetch_assoc()) {
        $accommodations[] = (int)$row['id'];
    }
    
    return $accommodations;
}

/**
 * Get accommodation a student is enrolled in
 * 
 * @param int|null $student_user_id Student's user ID or null for current user
 * @return int|null Accommodation ID or null if not found
 */
function getStudentAccommodation($student_user_id = null) {
    $conn = getDbConnection();
    $student_user_id = $student_user_id !== null ? (int)$student_user_id : (int)($_SESSION['user_id'] ?? 0);
    
    if ($student_user_id === 0) return null;
    
    $stmt = safeQueryPrepare($conn, "SELECT accommodation_id FROM students WHERE user_id = ?");
    if (!$stmt) return null;
    
    $stmt->bind_param("i", $student_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return null;
    
    return (int)$result->fetch_assoc()['accommodation_id'];
}

/**
 * Check if a user owns an accommodation
 * 
 * @param int $accommodation_id The accommodation ID
 * @param int|null $owner_id Owner user ID or null for current user
 * @return bool True if user owns the accommodation
 */
function isAccommodationOwner($accommodation_id, $owner_id = null) {
    $conn = getDbConnection();
    $accommodation_id = (int)$accommodation_id;
    $owner_id = $owner_id !== null ? (int)$owner_id : (int)($_SESSION['user_id'] ?? 0);
    
    if ($owner_id === 0) return false;
    
    $stmt = safeQueryPrepare($conn, "SELECT owner_id FROM accommodations WHERE id = ?");
    if (!$stmt) return false;
    
    $stmt->bind_param("i", $accommodation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return false;
    
    $actual_owner = (int)$result->fetch_assoc()['owner_id'];
    return $actual_owner === $owner_id;
}

/**
 * Check if a manager is assigned to an accommodation
 * 
 * @param int $accommodation_id The accommodation ID
 * @param int|null $manager_id Manager user ID or null for current user
 * @return bool True if manager is assigned
 */
function isManagerOfAccommodation($accommodation_id, $manager_id = null) {
    $conn = getDbConnection();
    $accommodation_id = (int)$accommodation_id;
    $manager_id = $manager_id !== null ? (int)$manager_id : (int)($_SESSION['user_id'] ?? 0);
    
    if ($manager_id === 0) return false;
    
    $stmt = safeQueryPrepare($conn, "SELECT 1 FROM user_accommodation WHERE user_id = ? AND accommodation_id = ?");
    if (!$stmt) return false;
    
    $stmt->bind_param("ii", $manager_id, $accommodation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// ============================================================================
// GENERIC PERMISSION HELPERS
// ============================================================================

/**
 * Generic permission checker for any resource type
 * 
 * @param string $resource_type Type: 'accommodation', 'student', 'user'
 * @param int $resource_id The resource ID
 * @param string $permission Permission: 'view', 'edit', 'manage', 'delete'
 * @return bool True if permission granted
 */
function hasPermissionToResource($resource_type, $resource_id, $permission = 'view') {
    if (!isLoggedIn()) return false;
    
    $resource_id = (int)$resource_id;
    
    switch ($resource_type) {
        case 'accommodation':
            if ($permission === 'edit' || $permission === 'delete') {
                return canEditAccommodation($resource_id);
            }
            return canViewAccommodationStudents($resource_id);
            
        case 'student':
            if ($permission === 'edit') {
                return canEditStudent($resource_id);
            }
            // For view, check if student is in user's accessible accommodations
            $conn = getDbConnection();
            $stmt = safeQueryPrepare($conn, "SELECT accommodation_id FROM students WHERE id = ?");
            if (!$stmt) return false;
            $stmt->bind_param("i", $resource_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) return false;
            $accommodation_id = (int)$result->fetch_assoc()['accommodation_id'];
            return canViewAccommodationStudents($accommodation_id);
            
        case 'user':
            if ($permission === 'edit') {
                return canEditUser($resource_id);
            }
            return canViewUser($resource_id);
            
        default:
            return false;
    }
}

/**
 * Centralized access denial handler
 * 
 * @param string $message Error message to display
 * @param string $redirect_to URL to redirect to
 * @param bool $log_attempt Whether to log the access attempt
 * @return void
 */
function denyAccess($message = 'Access denied', $redirect_to = null, $log_attempt = true) {
    // Log the access denial attempt
    if ($log_attempt && isLoggedIn()) {
        $conn = getDbConnection();
        $user_id = $_SESSION['user_id'];
        $action = 'access_denied';
        $details = $message . ' - URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown');
        logActivity($conn, $user_id, $action, $details);
    }
    
    // Determine redirect URL
    if ($redirect_to === null) {
        $redirect_to = isLoggedIn() ? BASE_URL . '/dashboard.php' : BASE_URL . '/login.php';
    }
    
    // Redirect with error message
    redirect($redirect_to, $message, 'danger');
    exit;
}

/**
 * Require permission to a resource or deny access
 * 
 * @param string $resource_type Type: 'accommodation', 'student', 'user'
 * @param int $resource_id The resource ID
 * @param string $permission Permission: 'view', 'edit', 'manage', 'delete'
 * @param string|null $redirect_to Custom redirect URL
 * @return void
 */
function requirePermission($resource_type, $resource_id, $permission = 'view', $redirect_to = null) {
    if (!hasPermissionToResource($resource_type, $resource_id, $permission)) {
        denyAccess("You don't have permission to $permission this $resource_type", $redirect_to);
    }
}

/**
 * Check if current user is an admin
 * 
 * @return bool True if user is admin
 */
function isAdmin() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Check if current user is an owner
 * 
 * @return bool True if user is owner
 */
function isOwner() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'owner';
}

/**
 * Check if current user is a manager
 * 
 * @return bool True if user is manager
 */
function isManager() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'manager';
}

/**
 * Check if current user is a student
 * 
 * @return bool True if user is student
 */
function isStudent() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'student';
}

/**
 * Get accommodation details with ownership info
 * 
 * @param int $accommodation_id The accommodation ID
 * @return array|null Accommodation details or null if not found
 */
function getAccommodationWithOwner($accommodation_id) {
    $conn = getDbConnection();
    $accommodation_id = (int)$accommodation_id;
    
    $stmt = safeQueryPrepare($conn, "
        SELECT a.*, u.first_name, u.last_name, u.email as owner_email
        FROM accommodations a
        JOIN users u ON a.owner_id = u.id
        WHERE a.id = ?
    ");
    if (!$stmt) return null;
    
    $stmt->bind_param("i", $accommodation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return null;
    
    return $result->fetch_assoc();
}