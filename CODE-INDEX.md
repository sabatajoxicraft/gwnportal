# CODE-INDEX.md - GWN Portal Service Layer Reference

> Complete API reference for all services, utilities, and helper classes in the GWN Portal application.

---

## Table of Contents

1. [Constants & Configuration](#constants--configuration)
2. [Utility Services](#utility-services)
3. [Business Logic Services](#business-logic-services)
4. [Helper Classes](#helper-classes)
5. [Usage Patterns](#usage-patterns)

---

## Constants & Configuration

### Roles (`includes/constants/roles.php`)

**Role Constants:**

- `ROLE_ADMIN` (1) - System administrator
- `ROLE_OWNER` (2) - Accommodation owner
- `ROLE_MANAGER` (3) - Accommodation manager
- `ROLE_STUDENT` (4) - WiFi student user

**Helper Functions:**

- `getRoleId($roleName)` - Get numeric ID from role name
- `getRoleName($roleId)` - Get role name from numeric ID
- `getRoleDisplayName($roleId)` - Get human-readable role name
- `isRoleHigherPrivilege($roleId, $compareToId)` - Compare role privilege levels

**Example:**

```php
$roleId = getRoleId('MANAGER');  // Returns 3
$name = getRoleName(2);           // Returns 'OWNER'
```

### Messages (`includes/constants/messages.php`)

**Message Categories:**

- `ERROR_MESSAGES` - Error messages array
- `SUCCESS_MESSAGES` - Success messages array
- `WARNING_MESSAGES` - Warning messages array
- `INFO_MESSAGES` - Information messages array

**Helper Function:**

- `getMessage($type, $key, $default = '')` - Get localized message

**Example:**

```php
$msg = getMessage('error', 'user_not_found', 'User was not found');
$msg = getMessage('success', 'account_created', 'Account created successfully');
```

---

## Utility Services

### Response Utility (`includes/utilities/Response.php`)

Standardizes HTTP response formats for API endpoints and AJAX.

**Methods:**

- `Response::json($data, $statusCode, $headers)` - Send JSON response
- `Response::success($message, $data, $statusCode)` - Success response
- `Response::error($message, $statusCode, $errors)` - Error response
- `Response::validationError($errors, $message)` - Validation error response
- `Response::unauthorized($message)` - 401 response
- `Response::forbidden($message)` - 403 response
- `Response::notFound($message)` - 404 response
- `Response::conflict($message, $data)` - 409 conflict response
- `Response::serverError($message)` - 500 response
- `Response::paginated($data, $total, $page, $perPage)` - Paginated response
- `Response::download($filePath, $filename)` - Download file
- `Response::redirect($url, $message, $messageType)` - Redirect with session message

**HTTP Status Constants:**

- `Response::HTTP_OK` (200)
- `Response::HTTP_CREATED` (201)
- `Response::HTTP_BAD_REQUEST` (400)
- `Response::HTTP_UNAUTHORIZED` (401)
- `Response::HTTP_FORBIDDEN` (403)
- `Response::HTTP_NOT_FOUND` (404)
- `Response::HTTP_CONFLICT` (409)
- `Response::HTTP_INTERNAL_ERROR` (500)

**Example:**

```php
// Success
Response::success('User created', ['user_id' => 123], Response::HTTP_CREATED);

// Validation error
Response::validationError(['email' => 'Invalid email']);

// Redirect
Response::redirect('/dashboard', 'Login successful', 'success');
```

### Form Helper (`includes/utilities/FormHelper.php`)

Simplifies form processing: value retrieval, field rendering, error display.

**Initialization:**

- `FormHelper::init($source)` - Initialize with POST/GET data

**Value Management:**

- `FormHelper::getValue($field, $default)` - Get field value
- `FormHelper::setValue($field, $value)` - Set field value
- `FormHelper::old($field, $default)` - Get old input from session

**Error Management:**

- `FormHelper::setError($field, $message)` - Set single field error
- `FormHelper::setErrors($errors)` - Set multiple errors
- `FormHelper::getError($field)` - Get field error message
- `FormHelper::hasError($field)` - Check if field has error
- `FormHelper::hasErrors()` - Check if form has any errors
- `FormHelper::getErrors()` - Get all errors
- `FormHelper::clearErrors()` - Clear all errors

**Field Rendering:**

- `FormHelper::renderInput($name, $label, $options)` - Text input field
- `FormHelper::renderTextarea($name, $label, $options)` - Textarea field
- `FormHelper::renderSelect($name, $options, $label, $attributes)` - Select dropdown
- `FormHelper::renderCheckbox($name, $label, $value, $checked)` - Checkbox field
- `FormHelper::renderErrors()` - Render error alert block

**Other:**

- `FormHelper::storeOldInput($input)` - Store old input for redirect repopulation

**Example:**

```php
// Initialize
FormHelper::init($_POST);

// Validate
if (empty(FormHelper::getValue('email'))) {
    FormHelper::setError('email', 'Email is required');
}

// Render
echo FormHelper::renderInput('email', 'Email Address', ['required' => true]);

// Display errors
if (FormHelper::hasErrors()) {
    echo FormHelper::renderErrors();
}
```

### Error Handler (`includes/utilities/ErrorHandler.php`)

Centralizes error handling and logging throughout the application.

**Initialization:**

- `ErrorHandler::init($production, $logPath)` - Initialize error handling

**Logging Methods:**

- `ErrorHandler::logError($type, $message, $context)` - Log custom error
- `ErrorHandler::logDatabaseError($operation, $error, $query)` - Log DB error
- `ErrorHandler::logValidationError($context, $errors)` - Log validation error
- `ErrorHandler::logEvent($event, $details)` - Log application event
- `ErrorHandler::handlePhpError($level, $message, $file, $line)` - Handle PHP errors
- `ErrorHandler::handleException($exception)` - Handle exceptions

**Retrieval Methods:**

- `ErrorHandler::getErrors()` - Get all logged errors
- `ErrorHandler::getLastError()` - Get last error
- `ErrorHandler::getErrorsByType($type)` - Filter errors by type
- `ErrorHandler::clear()` - Clear error log

**Example:**

```php
// Initialize in config
ErrorHandler::init(false);  // Development mode

// Log error
ErrorHandler::logError('Database', 'Connection failed', ['host' => 'localhost']);

// Retrieve errors
$lastError = ErrorHandler::getLastError();
$dbErrors = ErrorHandler::getErrorsByType('Database Error');
```

### Permission Helper (`includes/utilities/PermissionHelper.php`)

Standardizes permission checks throughout the application.

**Role Checks:**

- `PermissionHelper::isAdmin($userId)` - Check if admin
- `PermissionHelper::isOwner($userId)` - Check if owner
- `PermissionHelper::isManager($userId)` - Check if manager
- `PermissionHelper::isStudent($userId)` - Check if student
- `PermissionHelper::hasRole($role, $userId)` - Check specific role
- `PermissionHelper::hasPrivilege($minRole, $userId)` - Check privilege level

**Resource Permissions:**

- `PermissionHelper::ownsAccommodation($userId, $accommodationId)` - Check ownership
- `PermissionHelper::managesAccommodation($userId, $accommodationId)` - Check management
- `PermissionHelper::canManageAccommodation($userId, $accommodationId)` - Check admin/owner/manager
- `PermissionHelper::canEditAccommodation($userId, $accommodationId)` - Check admin/owner
- `PermissionHelper::canViewCodes($userId, $accommodationId)` - Check code view permission
- `PermissionHelper::canCreateCodes($userId, $accommodationId)` - Check code create permission
- `PermissionHelper::canViewStudents($userId, $accommodationId)` - Check student view permission
- `PermissionHelper::canEditStudent($userId, $studentUserId)` - Check student edit permission

**Enforcement Methods:**

- `PermissionHelper::requireLogin()` - Require logged-in user
- `PermissionHelper::requireRole($role)` - Require specific role
- `PermissionHelper::requireAnyRole($roles)` - Require one of multiple roles
- `PermissionHelper::requirePrivilege($minRole)` - Require minimum privilege

**Other:**

- `PermissionHelper::getCurrentUserRole()` - Get current user's role
- `PermissionHelper::getUserRole($userId)` - Get user's role from DB
- `PermissionHelper::logPermissionCheck($action, $allowed, $details)` - Log permission check

**Example:**

```php
// Check permission
if (!PermissionHelper::canEditAccommodation($userId, $accommodationId)) {
    Response::forbidden('You cannot edit this accommodation');
}

// Require role
PermissionHelper::requireRole(ROLE_ADMIN);

// Check ownership
if (PermissionHelper::ownsAccommodation($_SESSION['user_id'], $_POST['accommodation_id'])) {
    // Allow edit
}
```

---

## Business Logic Services

### Query Service (`includes/services/QueryService.php`)

Consolidates repeated database query patterns.

**Methods:**

- `QueryService::getAccommodationDetails($conn, $accommodationId)` - Get accommodation with owner info
- `QueryService::getUserWithRole($conn, $userId)` - Get user with role name
- `QueryService::getUserByUsername($conn, $username)` - Get user by username
- `QueryService::getUserAccommodations($conn, $userId, $userRole)` - Get user's accommodations (role-aware)
- `QueryService::getAccommodationStudents($conn, $accommodationId, $filter)` - Get accommodation's students
- `QueryService::getStudentInfo($conn, $studentUserId)` - Get student details
- `QueryService::searchUsers($conn, $criteria, $limit, $offset)` - Search users
- `QueryService::countSearchResults($conn, $criteria)` - Count search results
- `QueryService::getOnboardingCode($conn, $code)` - Get code with details

**Example:**

```php
$accommodation = QueryService::getAccommodationDetails($conn, 1);
$students = QueryService::getAccommodationStudents($conn, 1, ['status' => 'active']);
```

### Activity Logger (`includes/services/ActivityLogger.php`)

Comprehensive audit logging for all user actions.

**Methods:**

- `ActivityLogger::logAction($userId, $action, $details, $ipAddress)` - Generic action log
- `ActivityLogger::logPageVisit($userId, $page, $details)` - Page visit log
- `ActivityLogger::logDeviceAction($userId, $action, $deviceId, $details)` - Device action
- `ActivityLogger::logVoucherAction($userId, $action, $voucherId, $details)` - Voucher action
- `ActivityLogger::logStudentAction($userId, $studentId, $action, $details)` - Student action
- `ActivityLogger::logAccommodationAction($userId, $accommodationId, $action, $details)` - Accommodation action
- `ActivityLogger::logPermissionChange($userId, $targetUserId, $action, $details)` - Permission change
- `ActivityLogger::logAuthEvent($userId, $action, $details)` - Authentication event
- `ActivityLogger::getActivityLog($userId, $limit, $offset)` - Get user's activity
- `ActivityLogger::getAccommodationActivityLog($accommodationId, $limit, $offset)` - Get accommodation activity
- `ActivityLogger::getAllActivityLogs($filter, $limit, $offset)` - Get all activities
- `ActivityLogger::clearOldLogs($daysToKeep)` - Clear old logs for retention

**Example:**

```php
ActivityLogger::logDeviceAction($_SESSION['user_id'], 'registered', $deviceId, [
    'mac_address' => $macAddress,
    'ip' => $_SERVER['REMOTE_ADDR']
]);

$logs = ActivityLogger::getActivityLog($_SESSION['user_id'], 10, 0);
```

### Form Validator (`includes/services/FormValidator.php`)

Centralized validation rules for forms and user inputs.

**Validators:**

- `FormValidator::validateEmail($email)` - Email format validation
- `FormValidator::validateSouthAfricanId($idNumber)` - SA ID with Luhn check
- `FormValidator::validatePhoneNumber($phone)` - Phone number format (+27XXXXXXXXX)
- `FormValidator::validateMacAddress($mac)` - MAC address format
- `FormValidator::normalizeMacAddress($mac)` - Normalize MAC to AA:BB:CC:DD:EE:FF
- `FormValidator::validateUsername($username)` - Username format
- `FormValidator::validatePassword($password)` - Password strength requirements
- `FormValidator::validateUserForm($data, $isUpdate)` - Full user form validation
- `FormValidator::validateAccommodationForm($data)` - Accommodation form validation
- `FormValidator::validateStudentAssignmentForm($data)` - Student assignment validation

**Error Management:**

- `FormValidator::getErrors()` - Get all validation errors
- `FormValidator::getError($field)` - Get specific field error
- `FormValidator::hasError($field)` - Check if field has error
- `FormValidator::addError($field, $message)` - Add error manually
- `FormValidator::clearErrors()` - Clear all errors

**Example:**

```php
$validator = new FormValidator();
if (!$validator->validateEmail($email)) {
    $validator->addError('email', 'Invalid email format');
}

if ($validator->hasErrors()) {
    Response::validationError($validator->getErrors());
}
```

### User Service (`includes/services/UserService.php`)

User management: CRUD operations, authentication, password management.

**Methods:**

- `UserService::createUser($conn, $userData)` - Create new user
- `UserService::updateUser($conn, $userId, $updateData)` - Update user details
- `UserService::getUser($conn, $userId)` - Get user by ID
- `UserService::deleteUser($conn, $userId)` - Delete user
- `UserService::authenticate($conn, $username, $password)` - Login authentication
- `UserService::changePassword($conn, $userId, $newPassword, $requireReset)` - Change password
- `UserService::verifyPassword($plainPassword, $hashedPassword)` - Verify password
- `UserService::setStatus($conn, $userId, $status)` - Set user status
- `UserService::requirePasswordReset($conn, $userId, $required)` - Force password reset
- `UserService::usernameExists($conn, $username, $excludeUserId)` - Check username availability
- `UserService::emailExists($conn, $email, $excludeUserId)` - Check email availability

**Example:**

```php
// Create user
$user = UserService::createUser($conn, [
    'username' => 'john@example.com',
    'email' => 'john@example.com',
    'password' => 'SecurePassword123!',
    'role_id' => ROLE_STUDENT
]);

// Authenticate
$user = UserService::authenticate($conn, 'john@example.com', 'SecurePassword123!');
```

### Accommodation Service (`includes/services/AccommodationService.php`)

Accommodation management: CRUD operations, manager assignment.

**Methods:**

- `AccommodationService::createAccommodation($conn, $ownerId, $name)` - Create accommodation
- `AccommodationService::updateAccommodation($conn, $accommodationId, $updateData)` - Update details
- `AccommodationService::deleteAccommodation($conn, $accommodationId)` - Delete accommodation
- `AccommodationService::getAccommodation($conn, $accommodationId)` - Get accommodation
- `AccommodationService::assignManager($conn, $managerId, $accommodationId)` - Add manager
- `AccommodationService::removeManager($conn, $managerId, $accommodationId)` - Remove manager
- `AccommodationService::getManagers($conn, $accommodationId)` - Get all managers
- `AccommodationService::isOwner($conn, $accommodationId, $ownerId)` - Check ownership
- `AccommodationService::isManager($conn, $accommodationId, $managerId)` - Check management
- `AccommodationService::getStudentAccommodation($conn, $studentUserId)` - Get student's accommodation

**Example:**

```php
// Create accommodation
$accommodation = AccommodationService::createAccommodation($conn, $ownerId, 'Main Building');

// Assign manager
AccommodationService::assignManager($conn, $managerId, $accommodationId);
```

### Code Service (`includes/services/CodeService.php`)

Invitation code lifecycle: generation, validation, usage tracking.

**Methods:**

- `CodeService::generateCode($conn, $createdBy, $accommodationId, $roleId, $expirationDays)` - Generate code
- `CodeService::validateAndUseCode($conn, $code, $userId)` - Validate and mark used
- `CodeService::validateCode($conn, $code)` - Validate without using
- `CodeService::revokeCode($conn, $codeId)` - Revoke code
- `CodeService::getAccommodationCodes($conn, $accommodationId, $status)` - Get codes for accommodation
- `CodeService::cleanupExpiredCodes($conn, $daysOld)` - Delete old codes

**Example:**

```php
// Generate code
$code = CodeService::generateCode($conn, $_SESSION['user_id'], 1, ROLE_STUDENT, 7);

// Use code
if (CodeService::validateAndUseCode($conn, $codeValue, $studentId)) {
    // Code was valid and used
}
```

### Student Service (`includes/services/StudentService.php`)

Student management: registration, room assignment, status management.

**Methods:**

- `StudentService::registerStudent($conn, $userId, $accommodationId, $roomNumber)` - Register student
- `StudentService::getStudentRecord($conn, $userId)` - Get student details
- `StudentService::updateRoomAssignment($conn, $userId, $newRoomNumber)` - Change room
- `StudentService::setStatus($conn, $userId, $newStatus)` - Change status
- `StudentService::activateStudent($conn, $userId)` - Activate (sets status to active)
- `StudentService::deactivateStudent($conn, $userId)` - Deactivate (sets status to inactive)
- `StudentService::getStudentWithDetails($conn, $userId)` - Get full student details
- `StudentService::isStudent($conn, $userId)` - Check if user is student
- `StudentService::unregisterStudent($conn, $userId)` - Remove student registration
- `StudentService::getStudentsByStatus($conn, $accommodationId, $status)` - Get students by status

**Example:**

```php
// Register student
StudentService::registerStudent($conn, $userId, $accommodationId, '201');

// Change room
StudentService::updateRoomAssignment($conn, $userId, '301');
```

### Device Management Service (`includes/services/DeviceManagementService.php`)

WiFi device management: registration, lifecycle, MAC validation.

**Methods:**

- `DeviceManagementService::registerDevice($conn, $userId, $deviceType, $macAddress)` - Register device
- `DeviceManagementService::getDevice($conn, $deviceId)` - Get device details
- `DeviceManagementService::getDeviceByMac($conn, $macAddress)` - Get device by MAC
- `DeviceManagementService::getUserDevices($conn, $userId)` - Get user's devices
- `DeviceManagementService::updateDevice($conn, $deviceId, $updateData)` - Update device
- `DeviceManagementService::deleteDevice($conn, $deviceId)` - Delete device
- `DeviceManagementService::macAddressExists($conn, $macAddress)` - Check MAC exists
- `DeviceManagementService::deviceBelongsToUser($conn, $deviceId, $userId)` - Check ownership
- `DeviceManagementService::getDeviceCount($conn, $userId)` - Count user's devices

**Example:**

```php
// Register device
$device = DeviceManagementService::registerDevice($conn, $userId, 'Laptop', 'AA:BB:CC:DD:EE:FF');

// Check if device exists
if (DeviceManagementService::macAddressExists($conn, 'AA:BB:CC:DD:EE:FF')) {
    // Device already registered
}
```

### Migration Service (`includes/services/MigrationService.php`)

Track and manage database schema migrations.

**Methods:**

- `MigrationService::init($connection)` - Initialize migration tracking
- `MigrationService::recordMigration($migrationName, $batch)` - Record migration as applied
- `MigrationService::isMigrationApplied($migrationName)` - Check if migration was applied
- `MigrationService::markMigrationFailed($migrationName, $errorMessage)` - Mark as failed
- `MigrationService::getAppliedMigrations()` - Get all applied migrations
- `MigrationService::getMigrationStatus()` - Get status summary
- `MigrationService::getCurrentBatch()` - Get current batch number
- `MigrationService::getLastBatchMigrations()` - Get migrations in last batch
- `MigrationService::rollbackLastBatch()` - Rollback last batch
- `MigrationService::getMigrationsByStatus($status)` - Filter by status

**Example:**

```php
// Initialize
MigrationService::init($conn);

// Record migration
MigrationService::recordMigration('2024_01_15_create_users_table');

// Check status
$status = MigrationService::getMigrationStatus();
```

---

## Helper Classes

### Page Template (`includes/page-template.php`)

Universal include file for consistent page setup. Replaces 5-7 scattered requires per page.

**Provides:**

- `$conn` - Database connection
- `$currentUserId` - Current user's ID
- `$currentUserRole` - Current user's role
- `$isAdmin`, `$isOwner`, `$isManager`, `$isStudent` - Role check booleans

**Usage:**

```php
<?php
require_once __DIR__ . '/../includes/page-template.php';

// Can now use $conn, $currentUserId, $currentUserRole, etc.
?>
```

---

## Usage Patterns

### Pattern 1: API Response Handling

```php
// Retrieve and validate data
$user = UserService::getUser($conn, $_POST['user_id']);
if (!$user) {
    Response::notFound('User not found');
}

// Process
$user['status'] = 'active';
UserService::updateUser($conn, $user['id'], $user);

// Return success
Response::success('User updated', $user);
```

### Pattern 2: Form Processing

```php
FormHelper::init($_POST);

// Validate
$validator = new FormValidator();
if (!$validator->validateEmail(FormHelper::getValue('email'))) {
    FormHelper::setError('email', 'Invalid email');
}

if (FormHelper::hasErrors()) {
    FormHelper::storeOldInput($_POST);
    Response::redirect('/edit-user', 'Please fix the errors', 'warning');
}

// Process
UserService::updateUser($conn, $_POST['user_id'], $_POST);
Activity Logger::logAction($_SESSION['user_id'], 'user_updated', ['user_id' => $_POST['user_id']]);

// Respond
Response::success('User updated successfully');
```

### Pattern 3: Permission-Based Access

```php
PermissionHelper::requireLogin();

if (!PermissionHelper::canEditAccommodation($_SESSION['user_id'], $_GET['accommodation_id'])) {
    Response::forbidden('You cannot edit this accommodation');
}

// Safe to proceed
$accommodation = AccommodationService::getAccommodation($conn, $_GET['accommodation_id']);
```

### Pattern 4: Error Handling

```php
ErrorHandler::init(false);  // Development mode

try {
    $result = UserService::createUser($conn, $data);
    if (!$result) {
        ErrorHandler::logError('User Creation', 'Failed to create user', $data);
        Response::serverError('Failed to create user');
    }
} catch (Exception $e) {
    ErrorHandler::handleException($e);
}
```

---

## Auto-Loading

All services and utilities are auto-loaded via PSR-4 autoloader in `includes/config.php`:

```php
$obj = new UserService();       // Auto-loaded from includes/services/
$helper = new FormHelper();     // Auto-loaded from includes/utilities/
```

---

## Environment Configuration

All sensitive configuration is in `.env` file. See `.env.example` for full list:

- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` - Database configuration
- `APP_NAME`, `APP_URL` - Application settings
- `CODE_EXPIRY_DAYS`, `CODE_LENGTH` - Onboarding settings
- `TWILIO_*` - SMS/WhatsApp configuration
- `GWN_*` - GWN API configuration

---

## Related Documentation

- [Database Schema](docs/database.md)
- [Security Documentation](docs/security.md)
- [Architecture Guide](docs/structure.md)
- [API Endpoints](public/api/)

---

**Generated:** 2024 | Last Updated: 2024
