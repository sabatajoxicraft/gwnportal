<?php
/**
 * Permission Helper Class - Centralized Permission Checking
 * 
 * Standardizes permission checks throughout the application. All permission
 * checks should use this class instead of direct role comparisons.
 * 
 * Usage:
 * - PermissionHelper::canEditAccommodation($userId, $accommodationId)
 * - PermissionHelper::requireRole('MANAGER')
 * - PermissionHelper::requirePermission('edit_codes')
 */

class PermissionHelper {

    /**
     * Check if user has admin role
     * 
     * @param int $userId User ID (optional, uses current user if not specified)
     * @return bool
     */
    public static function isAdmin($userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $userRole = $userId === ($_SESSION['user_id'] ?? null) ? ($_SESSION['user_role'] ?? null) : self::getUserRole($userId);
        
        return $userRole === ROLE_ADMIN;
    }

    /**
     * Check if user has owner role
     * 
     * @param int $userId User ID (optional, uses current user if not specified)
     * @return bool
     */
    public static function isOwner($userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $userRole = $userId === ($_SESSION['user_id'] ?? null) ? ($_SESSION['user_role'] ?? null) : self::getUserRole($userId);
        
        return $userRole === ROLE_OWNER;
    }

    /**
     * Check if user has manager role
     * 
     * @param int $userId User ID (optional, uses current user if not specified)
     * @return bool
     */
    public static function isManager($userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $userRole = $userId === ($_SESSION['user_id'] ?? null) ? ($_SESSION['user_role'] ?? null) : self::getUserRole($userId);
        
        return $userRole === ROLE_MANAGER;
    }

    /**
     * Check if user has student role
     * 
     * @param int $userId User ID (optional, uses current user if not specified)
     * @return bool
     */
    public static function isStudent($userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $userRole = $userId === ($_SESSION['user_id'] ?? null) ? ($_SESSION['user_role'] ?? null) : self::getUserRole($userId);
        
        return $userRole === ROLE_STUDENT;
    }

    /**
     * Check if user has specific role
     * 
     * @param string|int $role Role constant or ID
     * @param int $userId User ID (optional, uses current user if not specified)
     * @return bool
     */
    public static function hasRole($role, $userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $userRole = $userId === ($_SESSION['user_id'] ?? null) ? ($_SESSION['user_role'] ?? null) : self::getUserRole($userId);
        
        // Convert role name to ID if needed
        if (is_string($role)) {
            $role = getRoleId($role);
        }

        return $userRole === $role;
    }

    /**
     * Check if user has higher privilege than specified role
     * 
     * @param string|int $minRole Minimum role required
     * @param int $userId User ID (optional, uses current user if not specified)
     * @return bool
     */
    public static function hasPrivilege($minRole, $userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $userRole = $userId === ($_SESSION['user_id'] ?? null) ? ($_SESSION['user_role'] ?? null) : self::getUserRole($userId);
        
        // Convert role name to ID if needed
        if (is_string($minRole)) {
            $minRole = getRoleId($minRole);
        }

        return isRoleHigherPrivilege($userRole, $minRole);
    }

    /**
     * Check if user owns accommodation
     * 
     * @param int $userId User ID
     * @param int $accommodationId Accommodation ID
     * @return bool
     */
    public static function ownsAccommodation($userId, $accommodationId) {
        global $conn;
        
        if (!$conn) {
            return false;
        }

        $stmt = $conn->prepare("SELECT owner_id FROM accommodations WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $accommodationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $accommodation = $result->fetch_assoc();
        $stmt->close();

        return $accommodation && $accommodation['owner_id'] === $userId;
    }

    /**
     * Check if user manages accommodation
     * 
     * @param int $userId User ID
     * @param int $accommodationId Accommodation ID
     * @return bool
     */
    public static function managesAccommodation($userId, $accommodationId) {
        global $conn;
        
        if (!$conn) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM accommodation_managers 
            WHERE manager_id = ? AND accommodation_id = ?
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ii", $userId, $accommodationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['count'] > 0;
    }

    /**
     * Check if user can manage accommodation (owns or manages)
     * 
     * @param int $userId User ID
     * @param int $accommodationId Accommodation ID
     * @return bool
     */
    public static function canManageAccommodation($userId, $accommodationId) {
        return self::isAdmin($userId) || 
               self::ownsAccommodation($userId, $accommodationId) || 
               self::managesAccommodation($userId, $accommodationId);
    }

    /**
     * Check if user can edit accommodation
     * 
     * @param int $userId User ID
     * @param int $accommodationId Accommodation ID
     * @return bool
     */
    public static function canEditAccommodation($userId, $accommodationId) {
        return self::isAdmin($userId) || self::ownsAccommodation($userId, $accommodationId);
    }

    /**
     * Check if user can view codes for accommodation
     * 
     * @param int $userId User ID
     * @param int $accommodationId Accommodation ID
     * @return bool
     */
    public static function canViewCodes($userId, $accommodationId) {
        return self::canManageAccommodation($userId, $accommodationId);
    }

    /**
     * Check if user can create codes for accommodation
     * 
     * @param int $userId User ID
     * @param int $accommodationId Accommodation ID
     * @return bool
     */
    public static function canCreateCodes($userId, $accommodationId) {
        return self::canManageAccommodation($userId, $accommodationId);
    }

    /**
     * Check if user can view accommodation students
     * 
     * @param int $userId User ID
     * @param int $accommodationId Accommodation ID
     * @return bool
     */
    public static function canViewStudents($userId, $accommodationId) {
        return self::canManageAccommodation($userId, $accommodationId);
    }

    /**
     * Check if user can edit student (in accommodation)
     * 
     * @param int $userId User ID
     * @param int $studentUserId Student user ID
     * @return bool
     */
    public static function canEditStudent($userId, $studentUserId) {
        global $conn;
        
        if (self::isAdmin($userId)) {
            return true;
        }

        // Get student's accommodation
        $stmt = $conn->prepare("
            SELECT s.accommodation_id 
            FROM students s 
            WHERE s.user_id = ? LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $studentUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        if (!$student) {
            return false;
        }

        return self::canManageAccommodation($userId, $student['accommodation_id']);
    }

    /**
     * Get current user's role
     * 
     * @return string|null Role ID or null if not logged in
     */
    public static function getCurrentUserRole() {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Get user's role from database
     * 
     * @param int $userId User ID
     * @return int|null Role ID or null if user not found
     */
    public static function getUserRole($userId) {
        global $conn;
        
        if (!$conn || !$userId) {
            return null;
        }

        $stmt = $conn->prepare("SELECT role_id FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        return $user ? $user['role_id'] : null;
    }

    /**
     * Require user to be logged in
     * 
     * @return void (redirects if not logged in)
     */
    public static function requireLogin() {
        if (empty($_SESSION['user_id'])) {
            Response::redirect('/public/login.php', 'Please log in first', 'warning');
        }
    }

    /**
     * Require specific role
     * 
     * @param string|int $role Role constant or ID
     * @return void (redirects if user doesn't have role)
     */
    public static function requireRole($role) {
        self::requireLogin();

        if (!self::hasRole($role)) {
            Response::forbidden('You do not have permission to access this page');
        }
    }

    /**
     * Require one of multiple roles
     * 
     * @param array $roles Array of role constants or IDs
     * @return void (redirects if user doesn't have any role)
     */
    public static function requireAnyRole($roles) {
        self::requireLogin();

        $hasRole = false;
        foreach ($roles as $role) {
            if (self::hasRole($role)) {
                $hasRole = true;
                break;
            }
        }

        if (!$hasRole) {
            Response::forbidden('You do not have permission to access this page');
        }
    }

    /**
     * Require minimum privilege level
     * 
     * @param string|int $minRole Minimum role required
     * @return void (redirects if user doesn't have privilege)
     */
    public static function requirePrivilege($minRole) {
        self::requireLogin();

        if (!self::hasPrivilege($minRole)) {
            Response::forbidden('You do not have permission to access this page');
        }
    }

    /**
     * Log permission check
     * 
     * @param string $action Action being checked
     * @param bool $allowed Whether permission was granted
     * @param string $details Additional details
     * @return void
     */
    public static function logPermissionCheck($action, $allowed, $details = '') {
        $logData = [
            'action' => $action,
            'allowed' => $allowed,
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_role' => $_SESSION['user_role'] ?? null,
            'details' => $details
        ];

        ActivityLogger::logPermissionChange($_SESSION['user_id'] ?? null, null, $action, $logData);
    }

}

?>
