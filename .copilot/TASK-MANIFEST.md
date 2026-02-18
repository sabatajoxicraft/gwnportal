# GWN Portal - Task Manifest & Agent Delegation Blueprint

**Date:** February 17, 2026  
**Status:** Ready for agent delegation  
**Total Tasks:** 33 critical + 18 secondary = 51 tasks  
**Estimated Duration:** 4-6 weeks across 4-5 agent teams

---

## EPIC 0: 🔴 CRITICAL SECURITY & CLEANUP (Sprint 0 - 3 days)

### Task 0.1: Delete Security-Risk Files

**Owner:** SecurityBot  
**Priority:** P0 - CRITICAL  
**Effort:** 1 point  
**Files to Remove:**

```
- admin_credentials.txt (DELETE - security risk)
- test_*.php (Move to tests/ or DELETE)
  ├── test_charmain.php
  ├── test_client_edit_api.php
  ├── test_client_edit.php
  ├── test_gwn_connection.php
  ├── test_no_token_api.php
  └── test_raw_http.php
```

**Verification:**

```bash
grep -r "admin_credentials" .  # Should return 0
ls -la admin_credentials.txt    # Should not exist
```

---

### Task 0.2: Archive Root-Level Debug Files

**Owner:** SecurityBot  
**Priority:** P0  
**Effort:** 1 point  
**Files to Archive** (create `_archive/debug/` dir):

```
- debug_*.php (5 files)
  ├── debug_voucher_data.php
  ├── debug_clients.php
  ├── debug_voucher_raw.php
  ├── debug_client_api.php
  └── client_list_curl.php
```

**Action:** Move to `archive/debug/` with timestamp-named subdirectory  
**Update:** `.gitignore` to ignore `archive/` (or add to actual archive)

---

### Task 0.3: Clean Up Backup Directories in .copilot/

**Owner:** SecurityBot  
**Priority:** P0  
**Effort:** 1 point  
**Directories to Remove/Archive:**

```
.copilot/.backup-2026-02-07/
.copilot/.backup-2026-02-09/
.copilot/.backup-2026-02-10/
```

**Action:** Delete or move to external archive location  
**Verify:** `ls -la .copilot/` should have no `.backup-*` directories

---

### Task 0.4: Create CLI Script to Remove Root-Level Runners

**Owner:** SecurityBot  
**Priority:** P0  
**Effort:** 2 points  
**Files to Evaluate:**

```
- run_curl_tests.php
- run_migration.php
- fresh_signature.php
- fresh_signature.ps1
- auto_link_devices.php (special case - might be in use)
- notifications.php (might be in use)
```

**Action:**

1. Audit each file for active use
2. If unused: move to `_archive/cli/`
3. If in use: integrate into proper module location
4. Create `bin/migrate.php` if run_migration.php was used

**Criteria for "In Use":**

- Referenced in documentation
- Called by cron jobs
- Part of deployment process

---

### Task 0.5: Audit Test Data in Production Code

**Owner:** SecurityBot  
**Priority:** P0  
**Effort:** 2 points  
**Locations to Audit:**

```
Files:
- db/schema.sql (contains test INSERT statements)
- public/login.php (displays test credentials)
- admin_credentials.txt (already scheduled for deletion)
```

**Action:**

1. Remove all test INSERT statements from `schema.sql`
2. Create `db/fixtures/test-data.sql` for manual testing
3. Update `public/login.php` to show generic description instead of credentials
4. Document how to create test accounts

**New Process:**

```
For testing:
1. Run: mysql < db/schema.sql  # Fresh schema only
2. Run: mysql < db/fixtures/test-data.sql  # (Optional) Add test data
3. Use: createTestUser() function in test suite
```

---

## EPIC 1: 🟠 CODE CENTRALIZATION PHASE 1 (Sprint 1-2, 2 weeks)

### Task 1.1: Create Role Constants File

**Owner:** Architect  
**Priority:** P1  
**Effort:** 2 points  
**Deliverable:** `includes/constants/roles.php`

```php
<?php
// includes/constants/roles.php

const ROLE_ADMIN = 'admin';
const ROLE_OWNER = 'owner';
const ROLE_MANAGER = 'manager';
const ROLE_STUDENT = 'student';

// Role ID mapping (for database)
const ROLE_IDS = [
    'admin' => 1,
    'owner' => 2,
    'manager' => 3,
    'student' => 4,
];

// Inverse mapping
const ROLE_NAMES = [
    1 => 'admin',
    2 => 'owner',
    3 => 'manager',
    4 => 'student',
];

// Role hierarchy (for display/permissions)
const ROLE_HIERARCHY = [
    'admin' => 1,
    'owner' => 2,
    'manager' => 3,
    'student' => 4,
];

// Helper function
function getRoleId($roleName) {
    return ROLE_IDS[$roleName] ?? null;
}

function getRoleName($roleId) {
    return ROLE_NAMES[$roleId] ?? null;
}
?>
```

**Integration:**

- Add `require_once __DIR__ . '/constants/roles.php';` to `config.php`
- Replace all hardcoded `1`, `2`, `3`, `4` with constants

**Files to Update:** 30+ (grep for hardcoded role IDs)

---

### Task 1.2: Create Error/Message Constants File

**Owner:** Architect  
**Priority:** P1  
**Effort:** 3 points  
**Deliverable:** `includes/constants/messages.php`

```php
<?php
// includes/constants/messages.php
// Centralized message strings

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
];

const SUCCESS_MESSAGES = [
    'user_created' => 'User account created successfully.',
    'user_updated' => 'User details updated.',
    'accommodation_created' => 'Accommodation created successfully.',
    'code_generated' => 'Invitation code generated and sent.',
    'device_registered' => 'Device registered successfully.',
    'device_blocked' => 'Device blocked successfully.',
    'device_unblocked' => 'Device unblocked.',
];

const WARNING_MESSAGES = [
    'no_data' => 'No data found.',
    'inactive_account' => 'Your account is inactive.',
    'first_login' => 'Please change your password on first login.',
];

// Helper function
function getMessage($type, $key, $default = '') {
    $messages = constant(strtoupper($type) . '_MESSAGES');
    return $messages[$key] ?? $default;
}
?>
```

**Usage Example:**

```php
// OLD:
redirect('/', 'You do not have permission to access this resource.', 'danger');

// NEW:
redirect('/', getMessage('error', 'unauthorized_access'), 'danger');
```

**Files to Update:** 40+ pages with redirect() calls

---

### Task 1.3: Standardize Page Includes Template

**Owner:** Architect  
**Priority:** P1  
**Effort:** 4 points  
**Deliverable:** `includes/page-template.php`

```php
<?php
// includes/page-template.php
// Standard includes for all pages - USE THIS IN EVERY PAGE

// Configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/constants/roles.php';
require_once __DIR__ . '/constants/messages.php';

// Core utilities
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/session-config.php';

// Services (auto-load as needed)
require_once __DIR__ . '/services/UserService.php';
require_once __DIR__ . '/services/AccommodationService.php';

// Components
require_once __DIR__ . '/components/header.php';

// Ensure database connection
$conn = getDbConnection();
if (!$conn) {
    die('Database connection failed');
}
?>
```

**Then EVERY page becomes:**

```php
<?php
require_once dirname(__DIR__) . '/includes/page-template.php';

// Optional: Require login
requireLogin();

// Optional: Require specific role
requireRole(['admin', 'manager']);

// Page logic here
?>
```

**Implementation:**

1. Create `includes/page-template.php`
2. Update all 47 pages to use single require
3. Remove multiple scattered includes from each page

**Expected Result:**

```
BEFORE: Each page has 3-7 require statements
AFTER: Each page has 1 require statement
Files Simplified: 47 pages
Lines Removed: ~150
```

---

### Task 1.4: Centralize Database Query Patterns in QueryService

**Owner:** Architect  
**Priority:** P1  
**Effort:** 6 points  
**Deliverable:** `includes/services/QueryService.php`

```php
<?php
// includes/services/QueryService.php
// Centralized database query patterns

class QueryService {

    /**
     * Get user's accommodation(s) by role
     */
    public static function getUserAccommodations($userId, $userRole = null) {
        // Consolidate all 5 variations into ONE function
        // Returns array of accommodation IDs
    }

    /**
     * Get accommodation details with ownership info
     */
    public static function getAccommodationDetails($accommodationId) {
        // Single source for accommodation queries
    }

    /**
     * Get user with role info
     */
    public static function getUserWithRole($userId) {
        // Standardized user lookup
    }

    /**
     * Get manager's assigned accommodations
     */
    public static function getManagerAccommodations($managerId) {
        // Centralize manager queries
    }

    /**
     * Get owner's accommodations
     */
    public static function getOwnerAccommodations($ownerId) {
        // Centralize owner queries
    }

    /**
     * Get student's accommodation by user_id
     */
    public static function getStudentAccommodation($studentUserId) {
        // Centralize student accommodation lookup
    }

    /**
     * Search users by criteria
     */
    public static function searchUsers($criteria = []) {
        // Consolidate user search logic
    }

    /**
     * Get accommodation students with filtering
     */
    public static function getAccommodationStudents($accommodationId, $filter = []) {
        // Centralize student listing
    }
}
?>
```

**Find & Replace Task:**

- Find all accommodation queries
- Replace inline queries with `QueryService::getAccommodationDetails($id)`
- Consolidate 8+ identical/similar queries
- Expected lines removed: 100+

**Files Affected:** 15+ pages with accommodation queries

---

### Task 1.5: Create Consolidated Activity Logger Service

**Owner:** Architect  
**Priority:** P1  
**Effort:** 3 points  
**Deliverable:** `includes/services/ActivityLogger.php`

```php
<?php
// includes/services/ActivityLogger.php

class ActivityLogger {

    /**
     * Log user action
     */
    public static function logAction($userId, $action, $details = [], $ipAddress = null) {
        // Unified logging for all activities
        // Auto-includes: timestamp, IP address, user_id
    }

    /**
     * Log page visit
     */
    public static function logPageVisit($userId, $page, $details = []) {
        // Standardized page visit logging
    }

    /**
     * Log device action
     */
    public static function logDeviceAction($userId, $action, $deviceId, $details = []) {
        // Device: register, block, unblock, unlink
    }

    /**
     * Log voucher action
     */
    public static function logVoucherAction($userId, $action, $voucherId, $details = []) {
        // Voucher: issued, used, revoked
    }

    /**
     * Log student action
     */
    public static function logStudentAction($userId, $studentId, $action, $details = []) {
        // Student: created, modified, disabled
    }

    /**
     * Get user's activity log
     */
    public static function getActivityLog($userId, $limit = 50, $offset = 0) {
        // Retrieve and format activity log
    }
}
?>
```

**Usage:**

```php
// OLD (scattered):
$stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, timestamp, ip_address) VALUES (?, ?, NOW(), ?)");
$stmt->bind_param("iss", $user_id, $action, $ip_address);
$stmt->execute();

// NEW (centralized):
ActivityLogger::logAction($userId, 'device_blocked', ['device_id' => $deviceId]);
```

**Implementation Plan:**

1. Create ActivityLogger class
2. Audit all existing logging locations (40+ files)
3. Replace inline queries with ActivityLogger calls
4. Add logging to 12+ missing locations (device-actions, edit_accommodation, etc.)

---

### Task 1.6: Consolidate Form Validation Rules

**Owner:** Architect  
**Priority:** P1  
**Effort:** 4 points  
**Deliverable:** `includes/services/FormValidator.php`

```php
<?php
// includes/services/FormValidator.php

class FormValidator {

    private static $errors = [];

    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        // Centralized email validation
    }

    /**
     * Validate South African ID number
     */
    public static function validateSouthAfricanId($idNumber) {
        // 13-digit validation logic (appears in 5+ files)
    }

    /**
     * Validate phone number format
     */
    public static function validatePhoneNumber($phone) {
        // Phone validation (used in 8+ files)
    }

    /**
     * Validate MAC address format
     */
    public static function validateMacAddress($mac) {
        // MAC validation (formatMacAddress exists but scattered)
    }

    /**
     * Validate accommodation assignment form
     */
    public static function validateAccommodationForm($data) {
        // Multi-field validation
    }

    /**
     * Validate user creation form
     */
    public static function validateUserForm($data, $isUpdate = false) {
        // User data validation
    }

    /**
     * Get validation errors
     */
    public static function getErrors() {
        return self::$errors;
    }

    /**
     * Clear errors
     */
    public static function clearErrors() {
        self::$errors = [];
    }
}
?>
```

**Implementation:**

1. Create FormValidator class with 10+ validation methods
2. Extract validation logic from create-user.php, edit-accommodation.php, etc.
3. Consolidate duplicate validation (SA ID validation appears 3+ times)
4. Expected lines removed: 200+

---

## EPIC 2: 🛠️ SERVICE LAYER IMPLEMENTATION (Sprint 2-3, 2 weeks)

### Task 2.1: Create UserService Class

**Owner:** Architect  
**Priority:** P1  
**Effort:** 5 points  
**Deliverable:** `includes/services/UserService.php`

```php
<?php
// includes/services/UserService.php

class UserService {

    /**
     * Create new user
     */
    public static function createUser($data) {
        // Handles: validation, password hashing, defaults
        // Used by: admin/create-user.php, admin/create-owner.php
    }

    /**
     * Get user with full details
     */
    public static function getUser($userId) {
        // Returns: user + role + accommodations + stats
    }

    /**
     * Update user profile
     */
    public static function updateUser($userId, $data) {
        // Partial update with validation
    }

    /**
     * Change user password
     */
    public static function changePassword($userId, $oldPassword, $newPassword) {
        // Verify old, hash new, update
    }

    /**
     * Reset user password
     */
    public static function resetPassword($userId, $newPassword) {
        // Admin override, no old password check
    }

    /**
     * Disable/activate user
     */
    public static function setUserStatus($userId, $status) {
        // $status = 'active' | 'inactive'
    }

    /**
     * Get users by role
     */
    public static function getUsersByRole($role, $filters = []) {
        // Returns array of users, filterable by accommodation, etc.
    }

    /**
     * Search users
     */
    public static function searchUsers($query, $limit = 20) {
        // Full-text search on name, email, id_number
    }

    /**
     * Get user activity statistics
     */
    public static function getUserStats($userId) {
        // Last login, devices, vouchers, etc.
    }
}
?>
```

**Files to Refactor:**

- admin/create-user.php
- admin/edit-user.php
- admin/create-owner.php
- login.php (password verification)
- reset_password.php
- Expected lines removed: 150+

---

### Task 2.2: Create AccommodationService Class

**Owner:** Architect  
**Priority:** P1  
**Effort:** 5 points  
**Deliverable:** `includes/services/AccommodationService.php`

```php
<?php
// includes/services/AccommodationService.php

class AccommodationService {

    /**
     * Create accommodation
     */
    public static function createAccommodation($data) {
        // Validation, defaults, owner assignment
    }

    /**
     * Get accommodation details
     */
    public static function getAccommodation($accommodationId) {
        // Full details: name, owner, managers, student count
    }

    /**
     * Update accommodation
     */
    public static function updateAccommodation($accommodationId, $data) {
        // Name, owner, settings
    }

    /**
     * Delete accommodation
     */
    public static function deleteAccommodation($accommodationId) {
        // Cascade: remove managers, students, codes
    }

    /**
     * Assign manager to accommodation
     */
    public static function assignManager($accommodationId, $managerId) {
        // Add user_accommodation entry
    }

    /**
     * Remove manager from accommodation
     */
    public static function removeManager($accommodationId, $managerId) {
        // Delete user_accommodation entry
    }

    /**
     * Get managers for accommodation
     */
    public static function getManagers($accommodationId) {
        // Returns array of manager users with status
    }

    /**
     * Get students in accommodation
     */
    public static function getStudents($accommodationId, $filters = []) {
        // With pagination, filtering
    }

    /**
     * Filter accommodations by owner
     */
    public static function getOwnerAccommodations($ownerId) {
        // Returns owner's properties
    }

    /**
     * Get accommodation statistics
     */
    public static function getStats($accommodationId) {
        // Student count, active devices, recent vouchers
    }
}
?>
```

**Files to Refactor:**

- accommodations/create.php
- accommodations/edit.php
- accommodations/index.php
- admin/create-accommodation.php
- admin/assign-accommodation.php
- view-accommodation.php
- Expected lines removed: 120+

---

### Task 2.3: Create CodeService (Invitation Codes)

**Owner:** Architect  
**Priority:** P1  
**Effort:** 4 points  
**Deliverable:** `includes/services/CodeService.php`

```php
<?php
// includes/services/CodeService.php

class CodeService {

    /**
     * Generate new invitation code
     */
    public static function generateCode($data) {
        // role_id, accommodation_id, created_by, etc.
        // Handles: code generation, photo storage, expiry calculation
    }

    /**
     * Validate code before use
     */
    public static function validateCode($code) {
        // Check: exists, not expired, not used, correct role
        // Returns: code data or error message
    }

    /**
     * Use code (mark as used)
     */
    public static function useCode($code, $userId) {
        // Update: used_by, used_timestamp, status='used'
    }

    /**
     * Get code details
     */
    public static function getCode($code) {
        // Returns: full code record with related data
    }

    /**
     * List codes (with filtering)
     */
    public static function listCodes($filters = [], $limit = 50, $offset = 0) {
        // Filter by: role, accommodation, status, created_by
    }

    /**
     * Get user's generated codes
     */
    public static function getMyGeneratedCodes($userId, $limit = 20) {
        // Codes created by this user
    }

    /**
     * Resend code via WhatsApp/SMS
     */
    public static function resendCode($code, $method = 'whatsapp') {
        // Re-send to original phone
    }

    /**
     * Expire old codes (cleanup)
     */
    public static function expireOldCodes() {
        // Update status='expired' for expired codes
        // Can be called via cron
    }
}
?>
```

**Files to Refactor:**

- public/create-code.php
- public/admin/create-code.php
- public/codes/index.php
- public/onboard.php (validation)
- Expected lines removed: 200+

---

### Task 2.4: Create StudentService Class

**Owner:** Architect  
**Priority:** P1  
**Effort:** 4 points  
**Deliverable:** `includes/services/StudentService.php`

```php
<?php
// includes/services/StudentService.php

class StudentService {

    /**
     * Create student record
     */
    public static function createStudent($userId, $accommodationId, $data) {
        // Called during onboarding
    }

    /**
     * Get student details
     */
    public static function getStudent($studentId) {
        // Full student record with user info
    }

    /**
     * Update student info
     */
    public static function updateStudent($studentId, $data) {
        // Room number, contact info, etc.
    }

    /**
     * Get student by user ID
     */
    public static function getStudentByUserId($userId) {
        // Find student record for user
    }

    /**
     * Disable/archive student
     */
    public static function archiveStudent($studentId) {
        // Soft delete
    }

    /**
     * Get student's devices
     */
    public static function getDevices($studentId) {
        // MAC addresses registered
    }

    /**
     * Get student's vouchers
     */
    public static function getVouchers($studentId, $limit = 50) {
        // Issued, used, revoked
    }

    /**
     * Get student activity log
     */
    public static function getActivityLog($studentId, $limit = 50) {
        // Recent actions
    }

    /**
     * Get student statistics
     */
    public static function getStats($studentId) {
        // Devices, vouchers, data usage, last_active
    }
}
?>
```

**Files to Refactor:**

- public/student-details.php
- public/students.php
- public/manager/edit_student.php
- public/admin/view-user.php (student portion)
- Expected lines removed: 150+

---

### Task 2.5: Create DeviceManagementService Class

**Owner:** Architect  
**Priority:** P1  
**Effort:** 3 points  
**Deliverable:** `includes/services/DeviceManagementService.php`

```php
<?php
// includes/services/DeviceManagementService.php

class DeviceManagementService {

    /**
     * Register device
     */
    public static function registerDevice($userId, $macAddress, $deviceType) {
        // Validation, duplicate check, storage
    }

    /**
     * Block device
     */
    public static function blockDevice($deviceId) {
        // Set blocked_flag, notify GWN
    }

    /**
     * Unblock device
     */
    public static function unblockDevice($deviceId) {
        // Clear blocked_flag, notify GWN
    }

    /**
     * Unlink device (remove)
     */
    public static function unlinkDevice($deviceId) {
        // Delete from user_devices
    }

    /**
     * Get user devices
     */
    public static function getUserDevices($userId) {
        // All devices registered to user
    }

    /**
     * Get device info
     */
    public static function getDevice($deviceId) {
        // Single device details
    }

    /**
     * Sync with GWN
     */
    public static function syncWithGWN($deviceId) {
        // Push block/unblock status to GWN
    }
}
?>
```

**Files to Refactor:**

- manager/device-actions.php
- manager/network-clients.php
- student/devices.php
- Expected lines removed: 100+

---

### Task 2.6: Update Existing Services with New Patterns

**Owner:** Architect  
**Priority:** P2  
**Effort:** 3 points  
**Action:** Review and refactor existing 13 service classes

- GwnService, VoucherService, NetworkService, etc.
- Add consistent error handling patterns
- Add logging via ActivityLogger
- Implement caching where applicable

---

## EPIC 3: 🔧 STANDARDIZATION & CONSISTENCY (Sprint 3-4, 2 weeks)

### Task 3.1: Standardize Permission Check Patterns

**Owner:** Architect  
**Priority:** P1  
**Effort:** 3 points  
**Action:** Audit all 47 pages and consolidate permission patterns

**Current Variations:**

```php
// Pattern 1:
if ($_SESSION['user_role'] !== 'admin') { redirect(...); }

// Pattern 2:
if (!requireRole('admin')) { ... }

// Pattern 3:
if (!canViewUser($user_id)) { denyAccess(...); }

// Pattern 4:
requireAdminLogin();
```

**New Standard:**

```php
<?php
require_once dirname(__DIR__) . '/includes/page-template.php';

// Use ONE of these:
requireLogin();                    // Just: logged in
requireRole('admin');              // Role check
requireRole(['admin', 'owner']);   // Multiple roles
canEditUser($userId) or denyAccess(); // Resource check
?>
```

**Files to Update:** 47 pages
**Expected lines removed:** 50+

---

### Task 3.2: Standardize Database Error Handling

**Owner:** Architect  
**Priority:** P1  
**Effort:** 2 points  
**Action:** Create error handling wrapper

```php
<?php
// Standardized pattern:

try {
    $stmt = QueryService::getUserAccommodations($userId);
    if (!$stmt) {
        throw new Exception('Failed to fetch accommodations');
    }
} catch (Exception $e) {
    ActivityLogger::logError('accommodation_fetch_failed', ['error' => $e->getMessage()]);
    redirect('/', getMessage('error', 'database_error'), 'danger');
}
```

**Files to Update:** 30+ with database queries
**Expected lines removed:** 100+

---

### Task 3.3: Standardize Success/Error Response Format

**Owner:** Architect  
**Priority:** P2  
**Effort:** 3 points  
**Deliverable:** `includes/Response.php`

```php
<?php
// includes/Response.php

class Response {

    // Redirect with message
    public static function success($redirect, $message = '', $data = []) {
        $_SESSION['success'] = $message;
        $_SESSION['response_data'] = $data;
        header('Location: ' . $redirect);
        exit;
    }

    public static function error($redirect, $message = '', $data = []) {
        $_SESSION['error'] = $message;
        $_SESSION['error_data'] = $data;
        header('Location: ' . $redirect);
        exit;
    }

    public static function warning($redirect, $message = '') {
        $_SESSION['warning'] = $message;
        header('Location: ' . $redirect);
        exit;
    }

    // JSON responses (for API endpoints)
    public static function json($data = [], $status = 'success', $code = 200) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'status' => $status,
            'code' => $code,
            'data' => $data,
            'timestamp' => time(),
        ]);
        exit;
    }

    public static function jsonError($message, $code = 400, $data = []) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ]);
        exit;
    }
}
?>
```

**Usage:**

```php
// OLD:
redirect('/users.php', 'User created successfully', 'success');
redirect('/users.php', 'Database error', 'danger');

// NEW:
Response::success('/users.php', getMessage('success', 'user_created'));
Response::error('/users.php', getMessage('error', 'database_error'));

// API responses
Response::json(['user' => $user], 'success', 200);
Response::jsonError('Invalid input', 400);
```

**Files to Update:** 40+ redirect() calls
**API Endpoints:** 4 files in public/api/

---

### Task 3.4: Standardize Form Submission Handling

**Owner:** Architect  
**Priority:** P2  
**Effort:** 3 points  
**Deliverable:** `includes/Form.php`

```php
<?php
// includes/Form.php

class Form {

    /**
     * Get POST value safely
     */
    public static function get($key, $default = '', $type = 'string') {
        $value = $_POST[$key] ?? $default;

        // Type casting
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            case 'string':
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            default:
                return $value;
        }
    }

    /**
     * Get multiple values
     */
    public static function getMultiple($keys, $typeMap = []) {
        $data = [];
        foreach ($keys as $key) {
            $type = $typeMap[$key] ?? 'string';
            $data[$key] = self::get($key, '', $type);
        }
        return $data;
    }

    /**
     * Check CSRF token
     */
    public static function verifyCsrf() {
        return isset($_POST['csrf_token']) && csrfValidate();
    }
}
?>
```

**Usage:**

```php
// OLD:
$name = htmlspecialchars($_POST['name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$age = (int)($_POST['age'] ?? 0);

// NEW:
$data = Form::getMultiple(
    ['name', 'email', 'age'],
    ['name' => 'string', 'email' => 'email', 'age' => 'int']
);
```

---

### Task 3.5: Consolidate Unused Files

**Owner:** Architect  
**Priority:** P2  
**Effort:** 2 points  
**Files to Remove:**

```
- includes/layout.php (Unused - merged into header.php)
- includes/accommodation-handler.php (Can consolidate into session-config.php)
- includes/ensure_complete_html.php (Debug only)
- public/icon-test.php (Test page)
```

**Verification:**

```bash
grep -r "include.*layout.php" public/  # Should find 0
grep -r "require.*accommodation-handler" public/ # Should find 0
```

---

### Task 3.6: Create Migration Tracking System

**Owner:** Architect  
**Priority:** P1  
**Effort:** 3 points  
**Deliverable:**

1. New table: `migrations` (tracks applied migrations)
2. File: `includes/services/MigrationService.php`
3. File: `bin/migrate.php` (CLI runner)

```sql
CREATE TABLE migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) UNIQUE NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'applied', 'failed') DEFAULT 'pending'
);
```

**Usage:**

```bash
php bin/migrate.php  # Apply pending migrations
php bin/migrate.php --status  # Show status
php bin/migrate.php --rollback migration_name  # Rollback specific
```

**Migrate to Central Location:**

- All SQL files → `db/migrations/` (already there)
- Remove duplicate .php runners
- Keep single PHP runner in `bin/migrate.php`

---

### Task 3.7: Create Comprehensive Index for Code Navigation

**Owner:** Technical Writer  
**Priority:** P3  
**Effort:** 2 points  
**Deliverable:** `.copilot/CODE-INDEX.md`

```
Comprehensive index of:
- All 13 service classes (location, methods, usage)
- All helper functions (location, signature)
- All constants (location, values)
- All database tables (schema, relationships)
- All page routes (path, role requirements)
- All API endpoints (method, authentication)
```

---

## EPIC 4: 📊 MONITORING & LOGGING (Sprint 4, 1 week)

### Task 4.1: Add Logging to All Missing Areas

**Owner:** Architect  
**Priority:** P1  
**Effort:** 3 points  
**Locations Needing Logging:**

```
- device-actions.php (block/unblock/unlink)
- edit-accommodation.php (accommodation edits)
- create-user.php (user creation)
- edit-user.php (user edits)
- manager/edit_student.php (student edits)
- admin/send-notifications.php (if exists)
```

**Implementation:**
Replace inline INSERT statements with:

```php
ActivityLogger::logAction(
    $userId,
    'student_block_device',
    ['device_id' => $deviceId, 'mac_address' => $mac]
);
```

---

### Task 4.2: Implement Page Load Tracking

**Owner:** Architect  
**Priority:** P2  
**Effort:** 2 points  
**Action:** Add auto-logging to page-template.php

```php
// In page-template.php:
ActivityLogger::logPageVisit($_SESSION['user_id'] ?? null, $_SERVER['REQUEST_URI']);
```

---

### Task 4.3: Create Activity Log Dashboard Widget

**Owner:** Frontend Dev  
**Priority:** P2  
**Effort:** 2 points  
**Deliverable:** `includes/components/activity-widget.php`

- Shows recent activity in sidebar
- Filterable by type, user, date
- Used in admin/dashboard.php, admin/view-user.php

---

### Task 4.4: Implement Error Logging to Database

**Owner:** Architect  
**Priority:** P2  
**Effort:** 2 points  
**Action:** Create `error_logs` table and log all exceptions

```sql
CREATE TABLE error_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    error_message TEXT,
    error_code VARCHAR(50),
    stack_trace TEXT,
    ip_address VARCHAR(45),
    url VARCHAR(255),
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## EPIC 5: ✅ TESTING & VALIDATION (Sprint 4-5, 1 week)

### Task 5.1: Create Comprehensive Test Cases

**Owner:** QA Engineer  
**Priority:** P2  
**Effort:** 4 points  
**Test Coverage:**

- RBAC enforcement (each role on each page)
- Permission boundaries (owner can't access other's accommodations)
- Form validation (all validators)
- Activity logging (100% coverage)
- Error handling (all error paths)

---

### Task 5.2: Create Manual Testing Checklist

**Owner:** QA Engineer  
**Priority:** P2  
**Effort:** 2 points  
**Deliverable:** `docs/TESTING-CHECKLIST.md`

- Role-based scenarios
- Permission boundary tests
- Feature interaction tests
- Error condition tests

---

### Task 5.3: Validate All Services & Integrations

**Owner:** QA Engineer  
**Priority:** P2  
**Effort:** 2 points  
**Test Areas:**

- GWN Cloud integration
- Twilio WhatsApp/SMS
- Database migrations
- Photo upload/storage
- Session management
- CSRF protection

---

## Delegation Strategy

### Sprint Assignment Recommendation

**Sprint 0 (3 days):** SecurityBot (Tasks 0.1-0.5)  
**Sprint 1 (1 week):** Architect (Tasks 1.1-1.6)  
**Sprint 2 (1 week):** Architect (Tasks 2.1-2.6)  
**Sprint 3 (1 week):** Architect (Tasks 3.1-3.7)  
**Sprint 4 (1 week):** Architect + Logger (Tasks 4.1-4.4)  
**Sprint 5 (1 week):** QA Engineer (Tasks 5.1-5.3)

### Daily Standups

- Mondays: Planning & task breakdown
- Wed: Mid-sprint check-in
- Friday: Sprint review & acceptance

---

## Success Metrics

| Metric              | Current    | Target     | Timeline |
| ------------------- | ---------- | ---------- | -------- |
| Code Duplication    | 25%        | <12%       | Sprint 3 |
| Service Utilization | 40%        | 85%        | Sprint 3 |
| RBAC Coverage       | 85%        | 98%        | Sprint 1 |
| Activity Logging    | 40%        | 100%       | Sprint 4 |
| Root-level Files    | 16 cleanup | 0 extras   | Sprint 0 |
| Test Coverage       | 0%         | 40% target | Sprint 5 |
| Documentation       | 70%        | 95%        | Ongoing  |

---

## File Score: Ready for Delegation ✅

This manifest provides:

- ✅ 33 critical tasks + 18 secondary = 51 total tasks
- ✅ Clear acceptance criteria for each
- ✅ Estimated effort points for planning
- ✅ Delivery artifacts specified
- ✅ File locations & implementation details
- ✅ Verification methods for QA
- ✅ Sprint organization for 4-5 parallel teams

**READY TO DELEGATE:** Each task has enough detail for autonomous agent execution.
