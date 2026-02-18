<?php
/**
 * Role Constants for GWN Portal
 * Centralized role definitions to eliminate hardcoded role strings/IDs
 * 
 * Usage: Replace hardcoded '1', '2', '3', '4' and 'admin', 'owner', etc. with these constants
 * Example: if ($userRole === ROLE_ADMIN) instead of if ($userRole === 'admin')
 */

// Role name constants (for string comparisons)
const ROLE_ADMIN = 'admin';
const ROLE_OWNER = 'owner';
const ROLE_MANAGER = 'manager';
const ROLE_STUDENT = 'student';

// Role ID mapping (for database queries)
const ROLE_IDS = [
    'admin' => 1,
    'owner' => 2,
    'manager' => 3,
    'student' => 4,
];

// Inverse mapping (ID to name)
const ROLE_NAMES = [
    1 => 'admin',
    2 => 'owner',
    3 => 'manager',
    4 => 'student',
];

// Role hierarchy (higher number = lower privilege)
const ROLE_HIERARCHY = [
    'admin' => 1,
    'owner' => 2,
    'manager' => 3,
    'student' => 4,
];

// Readable role descriptions
const ROLE_DESCRIPTIONS = [
    'admin' => 'System Administrator',
    'owner' => 'Accommodation Owner',
    'manager' => 'Accommodation Manager',
    'student' => 'Student User',
];

/**
 * Get role ID from role name
 * 
 * @param string $roleName Role name (e.g., 'admin', 'owner')
 * @return int|null Role ID, or null if not found
 */
function getRoleId($roleName) {
    return ROLE_IDS[$roleName] ?? null;
}

/**
 * Get role name from role ID
 * 
 * @param int $roleId Role ID (1, 2, 3, or 4)
 * @return string|null Role name, or null if not found
 */
function getRoleName($roleId) {
    return ROLE_NAMES[$roleId] ?? null;
}

/**
 * Get all role names
 * 
 * @return array Array of role names
 */
function getAllRoleNames() {
    return [ROLE_ADMIN, ROLE_OWNER, ROLE_MANAGER, ROLE_STUDENT];
}

/**
 * Get all role IDs
 * 
 * @return array Array of role IDs
 */
function getAllRoleIds() {
    return [1, 2, 3, 4];
}

/**
 * Check if a role ID is valid
 * 
 * @param int $roleId Role ID to check
 * @return bool True if valid, false otherwise
 */
function isValidRoleId($roleId) {
    return isset(ROLE_NAMES[$roleId]);
}

/**
 * Check if a role name is valid
 * 
 * @param string $roleName Role name to check
 * @return bool True if valid, false otherwise
 */
function isValidRoleName($roleName) {
    return isset(ROLE_IDS[$roleName]);
}

/**
 * Compare role hierarchy (check if $roleA has higher privilege than $roleB)
 * Used for permission checks like "Is this user more privileged than that user?"
 * 
 * @param string $roleA First role name
 * @param string $roleB Second role name
 * @return bool True if roleA has higher privilege (lower hierarchy number), false otherwise
 */
function isRoleHigherPrivilege($roleA, $roleB) {
    $hierarchyA = ROLE_HIERARCHY[$roleA] ?? null;
    $hierarchyB = ROLE_HIERARCHY[$roleB] ?? null;
    
    if ($hierarchyA === null || $hierarchyB === null) {
        return false;
    }
    
    return $hierarchyA < $hierarchyB;
}

/**
 * Get role display name (human-readable)
 * 
 * @param string $roleName Role name
 * @return string Role display name
 */
function getRoleDisplayName($roleName) {
    return ROLE_DESCRIPTIONS[$roleName] ?? 'Unknown Role';
}

?>
