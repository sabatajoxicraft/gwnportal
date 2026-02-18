<?php
/**
 * GWN Portal - Standard Page Template
 * Include this file at the top of EVERY page (public/*.php files)
 * 
 * Usage: <?php require_once dirname(__DIR__) . '/includes/page-template.php'; ?>
 * 
 * This replaces multiple scattered require statements with ONE consistent include.
 * REPLACES the need for individual requires of:
 * - config.php
 * - db.php
 * - functions.php
 * - permissions.php
 * - csrf.php
 * - components/header.php
 */

// ============================================================================
// REQUIRE ALL CORE DEPENDENCIES
// ============================================================================

// Configuration and constants (MUST be first)
// Includes: session config, role constants, message constants
require_once __DIR__ . '/config.php';

// Database connection
require_once __DIR__ . '/db.php';

// Core functions library
require_once __DIR__ . '/functions.php';

// Permission checking functions
require_once __DIR__ . '/permissions.php';

// CSRF protection
require_once __DIR__ . '/csrf.php';

// ============================================================================
// VALIDATE DATABASE CONNECTION
// ============================================================================

// Ensure we have a valid database connection
$conn = getDbConnection();
if (!$conn) {
    // Log the error but don't expose details to user
    error_log('Database connection failed in page-template.php');
    die('Application error: Unable to establish database connection. Please try again later.');
}

// ============================================================================
// OPTIONAL: REQUIRE LOGIN
// ============================================================================
// Uncomment in your page to require user to be logged in
// if (!isLoggedIn()) {
//     redirect(BASE_URL . '/login.php', 'Please login to access this page.', 'warning');
// }

// ============================================================================
// OPTIONAL: REQUIRE SPECIFIC ROLE
// ============================================================================
// Uncomment in your page to restrict access to specific roles
// Requires login first!
// Example: requireRole(['admin', 'manager']);
// 
// if (!isLoggedIn()) {
//     redirect(BASE_URL . '/login.php', 'Please login first.', 'warning');
// }
// if (!requireRole(['admin', 'owner'])) {
//     redirect(BASE_URL . '/', getMessage('error', 'unauthorized_access'), 'danger');
// }

// ============================================================================
// PAGE VARIABLES (used by all pages)
// ============================================================================

// Current logged-in user info (if logged in)
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserRole = $_SESSION['role'] ?? null;
$currentUserStatus = $_SESSION['status'] ?? null;

// Page context helpers
$isLoggedIn = isLoggedIn();
$isAdmin = $currentUserRole === ROLE_ADMIN;
$isOwner = $currentUserRole === ROLE_OWNER;
$isManager = $currentUserRole === ROLE_MANAGER;
$isStudent = $currentUserRole === ROLE_STUDENT;

// ============================================================================
// RENDER PAGE HEADER (optional, can be disabled in page)
// ============================================================================
// Pages can override header by including it themselves
// For most pages, this will be rendered automatically

// Don't include header yet - let the page decide
// require_once __DIR__ . '/components/header.php';

// ============================================================================
// COMMON VARIABLES AVAILABLE IN YOUR PAGE
// ============================================================================
// $conn                - Database connection
// $currentUserId       - ID of logged-in user (or null)
// $currentUserRole     - Role of logged-in user ('admin', 'owner', 'manager', 'student')
// $isLoggedIn          - Boolean: is user logged in?
// $isAdmin, $isOwner, etc. - Boolean shorthand for role checks
// 
// Functions available:
// - getMessage('error'|'success'|'warning'|'info', $key) - Get localized messages
// - getRoleId($name) - Convert role name to ID
// - getRoleName($id) - Convert role ID to name
// - isLoggedIn() - Check if user is authenticated
// - requireLogin() - Redirect if not logged in
// - requireRole($roles) - Redirect if user is not in allowed roles
// - csrfField() - Output CSRF hidden field for forms
// - redirect($url, $message, $type) - Redirect with message alert
// 
// ============================================================================

?>
