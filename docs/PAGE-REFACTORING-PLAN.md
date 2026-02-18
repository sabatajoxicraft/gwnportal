# Page Refactoring Plan - Week 7

> Strategy for updating public pages to use new service-oriented architecture.

---

## Overview

Refactor existing pages (public/\*.php) to use the new services, utilities, and patterns established in EPIC 0-5, eliminating code duplication and improving maintainability.

**Objective:** Replace direct database calls and inline logic with service layer calls and centralized utilities.

---

## Refactoring Strategy

### Pattern: Old vs New

**OLD Pattern (To Replace)**

```php
<?php
include '../includes/session-config.php';
include '../includes/db.php';

// Direct database operations
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Manual permission checks
if ($_SESSION['role'] != 'admin') {
    die("Access denied");
}

// Manual validation
if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    die("Invalid email");
}

// Manual response handling
header("Location: ...");
?>
```

**NEW Pattern (To Use)**

```php
<?php
include '../includes/page-template.php';

// Service-based operations
$user = UserService::getUser($conn, $id);

// Service-based permission checks
PermissionHelper::requireRole(ROLE_ADMIN);

// Centralized validation
if (!FormValidator::validateEmail($email)) {
    FormHelper::setError('email', 'Invalid email');
}

// Standardized responses
Response::redirect('dashboard.php');
?>
```

---

## Page Categories & Priority

### Tier 1: Critical Pages (High Impact, Do First)

These pages are accessed frequently and have complex logic:

1. **login.php** ⭐ HIGHEST PRIORITY
   - Used for: User authentication
   - Current issues: Direct password verification, manual session setup
   - Refactor to: UserService::authenticate(), proper error logging
   - Files affected: Entire login workflow

2. **dashboard.php**
   - Used for: Admin/manager overview
   - Current issues: Multiple direct queries, no service calls
   - Refactor to: Use all services to gather dashboard data
   - Files affected: Activity summaries, user counts, accommodation info

3. **public/admin/** (admin management pages)
   - create-XXX.php, edit-XXX.php pages
   - Current: Direct INSERT/UPDATE statements
   - Refactor to: Use AccommodationService, UserService, etc.

4. **public/manager/** (manager pages)
   - Manager-specific views and operations
   - Refactor to: PermissionHelper for checks, services for operations

### Tier 2: Workflow Pages (Medium Impact)

5. **onboard.php** (Student onboarding)
   - Use: CodeService for code validation, StudentService for registration
   - High impact on user registration flow

6. **send-voucher(s).php** (Voucher management)
   - Use: Existing voucher logic with ActivityLogger
   - Medium complexity but critical for WiFi management

7. **students.php** (Student listing/management)
   - Use: StudentService, QueryService
   - High data volume page

### Tier 3: Standard Pages (Lower Impact)

8. **profile.php** (User profile)
   - Use: UserService, FormHelper
   - Lower complexity, fewer dependencies

9. **accommodations.php** (Accommodation listing)
   - Use: AccommodationService, QueryService
   - List page with filtering

10. **codes.php** (Code management)
    - Use: CodeService, PermissionHelper
    - Moderate complexity

### Tier 4: Support Pages (Lowest Priority)

11. **help.php**, **contact.php** - Informational pages
12. **logout.php** - Session cleanup
13. **icon-test.php** - Debug/test page

---

## Files to Refactor (Priority Order)

| #   | Page                     | Complexity | Services Needed                                | Effort |
| --- | ------------------------ | ---------- | ---------------------------------------------- | ------ |
| 1   | login.php                | High       | UserService                                    | 1h     |
| 2   | dashboard.php            | High       | All services                                   | 2h     |
| 3   | create-accommodation.php | Medium     | AccommodationService                           | 45m    |
| 4   | edit-accommodation.php   | Medium     | AccommodationService                           | 45m    |
| 5   | accommodations.php       | Medium     | AccommodationService, QueryService             | 1h     |
| 6   | onboard.php              | High       | CodeService, StudentService                    | 1.5h   |
| 7   | students.php             | Medium     | StudentService, QueryService, PermissionHelper | 1h     |
| 8   | student-details.php      | Medium     | StudentService, DeviceManagementService        | 1h     |
| 9   | manager-setup.php        | Medium     | UserService, AccommodationService              | 45m    |
| 10  | owner-setup.php          | Medium     | UserService, AccommodationService              | 45m    |
| 11  | send-voucher.php         | Medium     | Existing logic + ActivityLogger                | 45m    |
| 12  | send-vouchers.php        | Medium     | Existing logic + ActivityLogger                | 45m    |
| 13  | codes.php                | Low        | CodeService, PermissionHelper                  | 45m    |
| 14  | profile.php              | Low        | UserService, FormHelper                        | 30m    |
| 15  | reset_password.php       | Low        | UserService, FormHelper                        | 30m    |

**Total Estimated Effort:** 15-16 hours

---

## Refactoring Patterns by Page Type

### Authentication Pages

**login.php**

```php
// OLD: Direct password check
if (password_verify($password, $stored_hash)) { ... }

// NEW: Use UserService
$user = UserService::authenticate($conn, $username, $password);
if ($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    ActivityLogger::logAuthEvent($_SESSION['user_id'], 'login', true, $_SERVER['REMOTE_ADDR']);
    Response::redirect('dashboard.php');
} else {
    ActivityLogger::logAuthEvent(null, 'login', false, $_SERVER['REMOTE_ADDR']);
    FormHelper::setError('login', 'Invalid credentials');
}
?>
```

### List Pages

**students.php, accommodations.php**

```php
// OLD: Direct queries
$result = $conn->query("SELECT * FROM students WHERE accommodation_id = " . $accommodationId);

// NEW: Use services
$students = StudentService::getStudentsByStatus(
    $conn,
    $accommodationId,
    'active'
);

// Display with FormHelper
foreach ($students as $student) {
    echo FormHelper::renderStudentRow($student);
}
?>
```

### CRUD Pages

**create-accommodation.php**

```php
// OLD: Direct INSERT
$stmt = $conn->prepare("INSERT INTO accommodations ...");
$stmt->execute();

// NEW: Use AccommodationService
$accommodation = AccommodationService::createAccommodation(
    $conn,
    $_POST['name'],
    $_POST['address'],
    $_SESSION['user_id']
);

if ($accommodation) {
    ActivityLogger::logAccommodationAction(
        $_SESSION['user_id'],
        'accommodation_created',
        $accommodation['id']
    );
    Response::success(['id' => $accommodation['id']]);
} else {
    Response::error('Failed to create accommodation');
}
?>
```

### Permission-Gated Pages

**manager-setup.php**

```php
// OLD: Manual role check
if ($_SESSION['role'] != 'owner' && $_SESSION['role'] != 'admin') {
    die("Access denied");
}

// NEW: Use PermissionHelper
PermissionHelper::requireAnyRole([ROLE_OWNER, ROLE_ADMIN]);

// Optional: Permission checks for specific resources
if (!PermissionHelper::isOwner($_SESSION['user_id'], $accommodationId)) {
    PermissionHelper::unauthorized("Cannot manage this accommodation");
}
?>
```

---

## Refactoring Checklist

### For Each Page Refactoring:

#### Phase 1: Planning (5 minutes)

- [ ] Identify current database queries
- [ ] List all direct SQL statements
- [ ] Identify permission checks
- [ ] Identify validation logic
- [ ] Map to appropriate services

#### Phase 2: Implementation (varies)

- [ ] Replace direct queries with service calls
- [ ] Replace validation with FormValidator
- [ ] Replace permission checks with PermissionHelper
- [ ] Replace manual responses with Response utility
- [ ] Add ActivityLogger calls to actions
- [ ] Use FormHelper for form rendering

#### Phase 3: Testing (15 minutes)

- [ ] Test page loads without errors
- [ ] Test all user workflows
- [ ] Test permission checks (access denied scenarios)
- [ ] Test validation errors
- [ ] Check activity logs for action entries
- [ ] Verify no PHP errors in logs

#### Phase 4: Documentation (5 minutes)

- [ ] Document changes made
- [ ] List services used
- [ ] Note any special handling

---

## Refactoring Implementation Plan

### Week 7 Schedule (40 hours)

**Day 1-2: Critical Pages (8-10 hours)**

- [ ] login.php
- [ ] dashboard.php
- [ ] Test critical workflows

**Day 3-4: Admin/Setup Pages (8-10 hours)**

- [ ] create-accommodation.php
- [ ] edit-accommodation.php
- [ ] manager-setup.php
- [ ] owner-setup.php

**Day 5 AM: Workflow Pages (4-5 hours)**

- [ ] onboard.php
- [ ] students.php

**Day 5 PM: Support Pages (4-5 hours)**

- [ ] accommodations.php
- [ ] student-details.php
- [ ] send-voucher.php, send-vouchers.php
- [ ] codes.php

**Day 5-6: Remaining Pages & QA (4-5 hours)**

- [ ] profile.php
- [ ] reset_password.php
- [ ] Remaining utility pages
- [ ] Full regression testing

---

## Testing Strategy

### Unit Level

- [ ] Each refactored page works independently
- [ ] No PHP errors
- [ ] All queries return expected data
- [ ] Permissions enforced correctly

### Integration Level

- [ ] Multi-step workflows (login → dashboard → manage)
- [ ] Cross-page data consistency
- [ ] Session management works
- [ ] File uploads/downloads work

### Regression Testing

- [ ] Execute MANUAL-TESTING-CHECKLIST.md on all refactored pages
- [ ] Compare before/after behavior
- [ ] Verify no functionality lost

### Security Testing

- [ ] Execute SECURITY-AUDIT-CHECKLIST.md
- [ ] Test permission bypasses
- [ ] Test injection attempts
- [ ] Test CSRF protection

---

## Code Quality Standards (Apply to All Refactored Pages)

### Structure

```php
<?php
// 1. Includes (page-template.php provided by includes/page-template.php)
include '../includes/page-template.php';

// 2. Require role/login
PermissionHelper::requireRole(ROLE_MANAGER);

// 3. Get current user context (from page-template)
// $currentUserId, $currentUserRole already set

// 4. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate
    // Act
    // Redirect or return
}

// 5. Prepare data for display
$data = [];

// 6. Include view
include '../includes/components/page-name.php';
?>
```

### Standards

- [ ] Use page-template.php include
- [ ] Use PermissionHelper for access control
- [ ] Use services for data operations
- [ ] Use FormValidator for input validation
- [ ] Use ActivityLogger for action logging
- [ ] Use FormHelper for form rendering
- [ ] Use Response for API endpoints
- [ ] No direct database calls
- [ ] No hardcoded SQL queries
- [ ] Proper error handling with try/catch or null checks

---

## Success Metrics

| Metric           | Target | Current | Goal |
| ---------------- | ------ | ------- | ---- |
| Pages refactored | 15+    | 0       | 15   |
| Service usage %  | 85%+   | ~30%    | 85%  |
| Code duplication | <12%   | ~25%    | <8%  |
| Test pass rate   | 100%   | 0%      | 100% |
| No PHP errors    | 0      | ~5-10   | 0    |

---

## Risk Mitigation

### Backup Strategy

- [ ] Git commit before each page refactoring
- [ ] Database backup before major changes
- [ ] Ability to rollback per-page or per-day

### Testing Safety

- [ ] Test on dev environment first
- [ ] Use test data for validation
- [ ] Have manual testing checklist ready
- [ ] Keep old code as reference

### Change Management

- [ ] One page at a time
- [ ] Complete testing before next
- [ ] Document changes
- [ ] Get approval before production

---

## Next Steps

1. **Immediate:** Start with login.php (critical path)
2. **Week 7:** Complete all 15 pages
3. **Week 7-8:** Full regression testing
4. **Week 8:** Deploy to production

---

**Refactoring Lead:** ******\_\_\_******
**Approval:** ******\_\_\_******
**Start Date:** 2024-01-20
**Target Completion:** 2024-02-03
