# Service Validation & Integration Guide - EPIC 5

> Complete validation framework for testing service interactions and ensuring system integrity.

---

## Overview

This guide provides methods to validate that all services integrate properly and that the application functions as expected. Use this before deployment.

---

## Service Interaction Map

```
┌─────────────────────────────────────────────────────────────┐
│                     Public Pages (.php)                    │
└──────────────────────┬──────────────────────────────────────┘
                       │
        ┌──────────────┴──────────────┐
        │                             │
┌───────▼───────┐          ┌──────────▼────────┐
│  Utilities    │          │  Services        │
│               │          │                  │
│ - Response    │◄─────────┤ - UserService    │
│ - FormHelper  │          │ - DeviceService  │
│ - ErrorHandler│          │ - StudentService │
│ - Permission  │          │ - CodeService    │
│ - Request Log │          │ - etc.           │
│ - Dashboard   │          └──────────────────┘
└───────────────┘                │
                                 │
        ┌────────────────────────┴────────────────┐
        │                                         │
┌───────▼──────────┐                   ┌─────────▼────────┐
│ Helper Services  │                   │  Database       │
│                  │                   │                  │
│ - QueryService   │                   │ - activity_logs  │
│ - FormValidator  │                   │ - error_logs     │
│ - ActivityLogger │                   │ - _migrations    │
│ - ErrorLogger    │                   │ - (User tables)  │
└──────────────────┘                   └──────────────────┘
```

---

## Pre-Deployment Validation Checklist

### Environment Setup

- [ ] `.env` file configured with correct database
- [ ] Database `gwn_wifi_system` created
- [ ] All migrations applied (`2024_01_20_add_logging_infrastructure.sql`)
- [ ] Test data loaded (`db/fixtures/test-data.sql`)
- [ ] Web server running and accessible
- [ ] Error logging enabled

### Service Availability

- [ ] `includes/services/` directory contains all 8 services:
  - [ ] `UserService.php`
  - [ ] `AccommodationService.php`
  - [ ] `CodeService.php`
  - [ ] `StudentService.php`
  - [ ] `DeviceManagementService.php`
  - [ ] `ActivityLogger.php`
  - [ ] `QueryService.php`
  - [ ] `MigrationService.php`
  - [ ] `RequestLogger.php`
  - [ ] `DatabaseErrorLogger.php`

- [ ] `includes/utilities/` directory contains all utilities:
  - [ ] `Response.php`
  - [ ] `FormHelper.php`
  - [ ] `ErrorHandler.php`
  - [ ] `PermissionHelper.php`
  - [ ] `ActivityDashboardWidget.php`

### Database Tables

Run this SQL to verify all tables exist:

```sql
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'gwn_wifi_system'
ORDER BY TABLE_NAME;
```

Expected tables:

- [ ] accommodations
- [ ] activity_logs
- [ ] error_logs
- [ ] \_migrations
- [ ] accommodation_managers
- [ ] onboarding_codes
- [ ] roles
- [ ] students
- [ ] user_devices
- [ ] user_accommodation
- [ ] users
- [ ] voucher_logs
- [ ] notifications

---

## Integration Test Scenarios

### Scenario 1: Complete User Registration Flow

**Objective:** Verify complete flow from code generation to student registration.

**Steps:**

1. **Generate Code** (as Manager)

   ```php
   $code = CodeService::generateCode(
       $conn,
       $managerId,
       $accommodationId,
       ROLE_STUDENT,
       7
   );
   ```

   - [ ] Code created in database
   - [ ] Code is unique (not duplicate)
   - [ ] Expiration date set (NOW + 7 days)
   - [ ] ActivityLogger::logCodeGeneration() called
   - [ ] Response shows success

2. **Student Registration** (as Student)

   ```php
   CodeService::validateAndUseCode($conn, $code['code'], $studentId);
   StudentService::registerStudent(
       $conn,
       $studentId,
       $accommodationId,
       '201'
   );
   ```

   - [ ] Code validates
   - [ ] Code marked as used
   - [ ] Student created with accommodation
   - [ ] Room assignment saved
   - [ ] ActivityLogger::logStudentAction() called
   - [ ] Status set to active

3. **Verify in Database**
   ```sql
   SELECT * FROM students WHERE user_id = $studentId;
   SELECT * FROM onboarding_codes WHERE code = '$codeValue';
   SELECT * FROM activity_logs WHERE user_id = $studentId;
   ```

   - [ ] Student record exists
   - [ ] Code shows status='used'
   - [ ] Code shows used_by=$studentId
   - [ ] Activity logs show enrollment

**Expected Result:** Student successfully registered with code marked as used.

---

### Scenario 2: Device Registration & Validation

**Objective:** Verify device registration with MAC validation.

**Steps:**

1. **Register Device** (as Student)

   ```php
   $device = DeviceManagementService::registerDevice(
       $conn,
       $studentId,
       'Laptop',
       'AA:BB:CC:DD:EE:FF'
   );
   ```

   - [ ] Device created
   - [ ] MAC address normalized
   - [ ] FormValidator used for validation
   - [ ] ActivityLogger::logDeviceAction() called
   - [ ] Response shows success

2. **Verify MAC Handling**

   ```php
   // Test different MAC formats
   'AA:BB:CC:DD:EE:FF'  // With colons
   'aabbccddeeff'        // No separators
   'AA-BB-CC-DD-EE-FF'  // With dashes
   ```

   - [ ] All formats normalize to `AA:BB:CC:DD:EE:FF`
   - [ ] Duplicates rejected
   - [ ] Invalid MACs rejected

3. **Retrieve Device**
   ```php
   $retrieved = DeviceManagementService::getDevice($conn, $deviceId);
   $byMac = DeviceManagementService::getDeviceByMac($conn, 'AA:BB:CC:DD:EE:FF');
   ```

   - [ ] Device retrieved by ID
   - [ ] Device retrieved by MAC
   - [ ] All device details correct

**Expected Result:** Device registered with MAC normalized and retrievable by both ID and MAC.

---

### Scenario 3: Accommodation Manager Assignment

**Objective:** Verify manager assignment and permission checking.

**Steps:**

1. **Assign Manager**

   ```php
   AccommodationService::assignManager($conn, $managerId, $accommodationId);
   ```

   - [ ] Record created in `accommodation_managers`
   - [ ] ActivityLogger::logAccommodationAction() called
   - [ ] Response shows success

2. **Verify Permission Checks**

   ```php
   $canManage = PermissionHelper::managesAccommodation($managerId, $accommodationId);
   $canEdit = PermissionHelper::canEditAccommodation($managerId, $accommodationId);
   ```

   - [ ] Manager correctly identified as managing accommodation
   - [ ] Owner has edit permission (not just manage)
   - [ ] Other managers cannot manage different accommodation

3. **Verify Access in Queries**
   ```php
   $accommodations = QueryService::getUserAccommodations($conn, $managerId, ROLE_MANAGER);
   ```

   - [ ] Manager sees their assigned accommodations
   - [ ] Only their accommodations returned

**Expected Result:** Manager assigned and verified with proper permission checks.

---

### Scenario 4: Error Logging & Database Recovery

**Objective:** Verify error logging works and errors are trackable.

**Steps:**

1. **Trigger Database Error**

   ```php
   // Intentional error
   $result = UserService::createUser($conn, ['invalid' => 'data']);
   ```

   - [ ] Error handling catches error
   - [ ] ErrorHandler::logError() called
   - [ ] Error logged to database
   - [ ] Error log entry created

2. **Verify Error Logged**

   ```sql
   SELECT * FROM error_logs ORDER BY created_at DESC LIMIT 1;
   ```

   - [ ] Error type recorded
   - [ ] Severity level correct
   - [ ] Message descriptive
   - [ ] Stack trace (if critical)
   - [ ] User ID recorded
   - [ ] URL recorded

3. **Verify Error Recovery**
   ```php
   $unresolved = DatabaseErrorLogger::getRecentErrors(10);
   DatabaseErrorLogger::markResolved($errorId, $adminId, 'Fixed validation');
   ```

   - [ ] Admin can view unresolved errors
   - [ ] Can mark as resolved with notes
   - [ ] Resolved flag updated

**Expected Result:** Errors logged to database and accessible for debugging.

---

### Scenario 5: Complete User Workflow

**Objective:** Verify complete workflow from user creation through device registration.

**Steps:**

1. **Admin Creates User** → UserService::createUser()
   - [ ] User created with hashed password
   - [ ] Role assigned
   - [ ] Status set to pending

2. **User Logs In** → UserService::authenticate()
   - [ ] Session created
   - [ ] User variables set
   - [ ] RequestLogger logs login

3. **User Registers Device** → DeviceManagementService::registerDevice()
   - [ ] Device registered to user
   - [ ] MAC validated and normalized
   - [ ] ActivityLogger logs device registration

4. **Manager Views User Devices** → QueryService + PermissionHelper
   - [ ] Permission checked
   - [ ] User devices returned
   - [ ] Only user's devices shown

5. **Admin Views Activity Log** → ActivityLogger
   - [ ] All actions logged
   - [ ] Timeline correct
   - [ ] User IDs and actions match

**Expected Result:** Complete workflow successfully completed with all logging.

---

## Service Dependency Validation

### UserService Dependencies

- [ ] Uses: QueryService, ActivityLogger, FormValidator, ErrorHandler
- [ ] Provides: User CRUD, authentication, password management
- [ ] Called by: All services and pages requiring user data

### AccommodationService Dependencies

- [ ] Uses: QueryService, ActivityLogger, ErrorHandler
- [ ] Provides: Accommodation CRUD, manager assignment
- [ ] Called by: Pages requiring accommodation operations

### CodeService Dependencies

- [ ] Uses: ActivityLogger, ErrorHandler, Database connection
- [ ] Provides: Code generation, validation, usage tracking
- [ ] Called by: Student registration workflow

### StudentService Dependencies

- [ ] Uses: QueryService, ActivityLogger, ErrorHandler
- [ ] Provides: Student registration and management
- [ ] Called by: Onboarding and student management pages

### DeviceManagementService Dependencies

- [ ] Uses: FormValidator, ActivityLogger, ErrorHandler
- [ ] Provides: Device registration and lifecycle
- [ ] Called by: Device management pages

### ActivityLogger Dependencies

- [ ] Uses: Database connection, ErrorHandler
- [ ] Provides: Comprehensive audit logging
- [ ] Called by: All services and pages

### PermissionHelper Dependencies

- [ ] Uses: QueryService (for ownership checks)
- [ ] Provides: Permission checking and enforcement
- [ ] Called by: All pages and services

---

## Performance Validation

### Query Performance

Test query response times:

```php
$start = microtime(true);

// Test: Get accommodation details with manager list
$accom = QueryService::getAccommodationDetails($conn, 1);
$managers = AccommodationService::getManagers($conn, 1);
$students = QueryService::getAccommodationStudents($conn, 1);

$time = microtime(true) - $start;
echo "Time: " . ($time * 1000) . "ms";  // Should be < 100ms
```

**Targets:**

- [ ] Single record lookup: < 10ms
- [ ] Relationship queries: < 50ms
- [ ] List queries with pagination: < 100ms
- [ ] Search queries: < 500ms

### Logging Performance

Test logging overhead:

```php
$start = microtime(true);

ActivityLogger::logAction($userId, 'test', [], '127.0.0.1');
RequestLogger::logPageView($userId, 'test.php');

$time = microtime(true) - $start;
echo "Logging time: " . ($time * 1000) . "ms";  // Should be < 50ms
```

**Target:** Logging should add < 50ms overhead per request

### Memory Usage

Monitor memory consumption:

```php
echo memory_get_usage() / 1024 . "KB";      // Current usage
echo memory_get_peak_usage() / 1024 . "KB"; // Peak usage
```

**Target:** Peak usage < 50MB per request

---

## Load Testing

### Setup

Install load testing tool:

```bash
# Using Apache Bench
# ab -n 100 -c 10 http://localhost/public/dashboard.php
```

### Test Scenarios

1. **Login Page** (not authenticated)

   ```bash
   ab -n 50 -c 5 http://localhost/public/login.php
   ```

   - [ ] No errors
   - [ ] Average response time < 200ms

2. **Dashboard** (authenticated)

   ```bash
   ab -n 50 -c 5 -C "PHPSESSID=<session>" http://localhost/public/dashboard.php
   ```

   - [ ] No errors
   - [ ] Average response time < 300ms

3. **API Endpoint** (data-heavy)
   ```bash
   ab -n 50 -c 5 -C "PHPSESSID=<session>" http://localhost/api/accommodations/1/students
   ```

   - [ ] No errors
   - [ ] Average response time < 200ms

---

## Regression Testing

After each service update:

1. **Unit Tests**

   ```bash
   cd /var/www/html
   php tests/ServiceTestSuite.php
   ```

   - [ ] All tests pass
   - [ ] No new failures

2. **Interactive Testing**
   - [ ] User login/logout
   - [ ] Create accommodation
   - [ ] Assign manager
   - [ ] Register device
   - [ ] View activity logs

3. **Database Checks**
   ```sql
   -- Verify data integrity
   SELECT COUNT(*) FROM accommodation_managers WHERE manager_id IS NULL;  -- Should be 0
   SELECT COUNT(*) FROM students WHERE user_id IS NULL;  -- Should be 0
   SELECT COUNT(*) FROM activity_logs WHERE action IS NULL;  -- Should be 0
   ```

---

## Validation Reporting

Document all validation results:

| Test                       | Result | Issues | Notes |
| -------------------------- | ------ | ------ | ----- |
| Service Availability       | ✓/✗    |        |       |
| Database Tables            | ✓/✗    |        |       |
| Scenario 1: Registration   | ✓/✗    |        |       |
| Scenario 2: Device Reg     | ✓/✗    |        |       |
| Scenario 3: Manager Assign | ✓/✗    |        |       |
| Scenario 4: Error Logging  | ✓/✗    |        |       |
| Scenario 5: Full Workflow  | ✓/✗    |        |       |
| Query Performance          | ✓/✗    |        |       |
| Load Testing               | ✓/✗    |        |       |

---

## Sign-Off

- [ ] All validations completed: **DATE: ****\_\_******
- [ ] Issues found: ******\_\_****** (count)
- [ ] All critical issues resolved: **YES / NO**
- [ ] Ready for deployment: **YES / NO**
- [ ] Validator Name: **************\_**************
- [ ] Sign-off: **************\_************** **DATE: ****\_\_******

---

**Complete this guide before deploying to production.**
