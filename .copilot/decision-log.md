# Decision Log

> 📋 AI AGENTS: Check here before making architectural decisions. Don't re-litigate what's already decided.

## How to Use This Log
1. Before making a significant decision, check if it's already been made
2. If making a new decision, add it here with rationale
3. If reversing a decision, document WHY and mark the old one as superseded

---

## Decisions

### [DECISION-001] M0.5 Configuration Freeze
- **Date**: 2026-02-10
- **Status**: Active
- **Category**: Tech Stack | Process
- **Decision**: Lock all working configuration versions after successful CI/CD validation
- **Context**: M0.5 GATE PASSED with CI and Docker Build workflows GREEN. Need to prevent configuration drift that could break validated setup.
- **Rationale**: These versions passed all GitHub Actions workflows. Any changes to these components must be tested in CI before merging to main branch.
- **Consequences**: 
  - All locked versions are now the baseline for this project
  - Changes require CI validation before merge
  - Breaking Change Protocol must be followed for config modifications

#### Locked Versions

| Component | Version | Lock Reason |
|-----------|---------|-------------|
| PHP | 8.2.30 | Validated in CI and Docker builds |
| MySQL | 8.0.44 | Compatible with PHP 8.2 and mysqli extension |
| Apache | 2.4 | Via php:8.2-apache Docker image |
| Bootstrap | 5.3.0 (CDN) | Working frontend styling |
| Docker Compose | v2 syntax | Commands use space (`docker compose`) not hyphen |
| GitHub Actions PHP | 8.2 | Matches production environment |
| GitHub Actions MySQL | mysql:8.0 | Service container for tests |

#### Docker Configuration (LOCKED)
- **Base image**: `php:8.2-apache`
- **Compose file**: `docker-compose.yml` (v2 syntax)
- **Services**: 
  - `gwn-db` (MySQL 8.0.44)
  - `gwn-app` (PHP 8.2-apache)
  - `phpmyadmin` (latest)

#### CI/CD Configuration (LOCKED)
- **Workflow files**: 
  - `.github/workflows/ci.yml`
  - `.github/workflows/docker.yml`
- **PHP setup**: shivammathur/setup-php@v2 with PHP 8.2
- **MySQL service**: mysql:8.0 with mysqli extension
- **PHP linting**: Error detection via grep pattern
- **Docker commands**: v2 syntax (space not hyphen)

#### Environment Variables (Template)
From `.env.example` - Structure is locked:
- **Database**: DB_HOST, DB_USER, DB_PASS, DB_NAME
- **GWN API**: DEFAULT_URL, ID, Key, Access_token, ALLOWED_DEVICES
- **Twilio**: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_PHONE_NUMBER, TWILIO_WHATSAPP_NO, message template variables
- **OneDrive**: CLIENT_ID, CLIENT_SECRET, TENANT_ID, FILE_ID, FILE_NAME, FOLDER_PATH

#### Configuration Files (DO NOT MODIFY without CI validation)
- `Dockerfile`
- `docker-compose.yml`
- `.github/workflows/ci.yml`
- `.github/workflows/docker.yml`
- `env.example` (variable structure)

#### Breaking Change Protocol
If you need to change locked configs:
1. Create feature branch from main
2. Modify configuration files
3. Push and verify **ALL** GitHub Actions workflows pass
4. Update this decision log with new versions
5. Get approval before merging to main
6. Merge only after CI is GREEN

---

### [DECISION-002] CSRF Protection Implementation
- **Date**: 2026-02-10
- **Status**: Active
- **Category**: Security
- **Decision**: Implement CSRF token validation for all forms using PHP sessions
- **Context**: Critical security vulnerability identified in M0 PRD. All forms were vulnerable to Cross-Site Request Forgery attacks.
- **Implementation**:
  - **Token Generation**: Session-based tokens using `bin2hex(random_bytes(32))`
  - **Token Storage**: `$_SESSION['csrf_token']` - regenerated per session
  - **Validation**: `hash_equals()` function for timing-attack safe comparison
  - **Response**: HTTP 403 on validation failure with user-friendly error message
  - **Form Integration**: Hidden input field via `csrfField()` helper function
- **Rationale**: 
  - Session-based tokens are simple, secure, and don't require database storage
  - Using `hash_equals()` prevents timing attacks
  - Centralized functions in `includes/csrf.php` ensure consistent implementation
- **Files Modified**:
  - `includes/csrf.php` (NEW - CSRF helper functions)
  - `includes/config.php` (added csrf.php include)
  - `public/login.php`
  - `public/onboard.php`
  - `public/profile.php`
  - `public/reset_password.php`
  - `public/send-voucher.php`
  - `public/send-vouchers.php`
  - `public/create-accommodation.php`
  - `public/edit-accommodation.php`
  - `public/create-code.php`
  - `public/manager-setup.php`
  - `public/update_details.php`
  - `public/dashboard.php`
  - `public/help.php`
  - `public/contact.php`
  - `public/owner-setup.php`
  - `public/managers.php`
  - `public/accommodations.php`
  - `public/manager/edit_student.php`
  - `public/admin/create-user.php`
  - `public/admin/create-accommodation.php`
  - `public/admin/create-owner.php`
  - `public/admin/create-code.php`
  - `public/admin/edit-user.php`
  - `public/admin/edit-accommodation.php`
  - `public/admin/settings.php`
  - `public/admin/view-user.php`
  - `public/admin/users.php`
  - `public/admin/codes.php`
  - `public/admin/accommodations.php`
  - `public/admin/system-backup.php`
- **Consequences**: 
  - All POST requests now require valid CSRF token
  - Users must have cookies/sessions enabled
  - Forms that don't submit tokens will be rejected with 403 error

---

### [DECISION-TEMPLATE] [Short Title]
- **Date**: YYYY-MM-DD
- **Status**: Active | Superseded | Reversed
- **Category**: Architecture | Tech Stack | Design | Process | Security
- **Decision**: [What was decided]
- **Context**: [Why this decision was needed]
- **Options Considered**:
  1. [Option A] - [Pros/Cons]
  2. [Option B] - [Pros/Cons]
- **Rationale**: [Why this option was chosen]
- **Consequences**: [What this means for the project]
- **Supersedes**: [Previous decision ID if applicable]

---

### [DECISION-003] Resource-Based Access Control (RBAC) Implementation
- **Date**: 2025-02-10
- **Status**: Active
- **Category**: Security | Architecture
- **Decision**: Implement fine-grained RBAC permissions in `includes/permissions.php` complementing existing role-based checks
- **Context**: CodeScout analysis revealed gaps in resource-level permission checking. While 67% of pages had role checks (isAdmin, requireManagerLogin, etc.), there was no validation for resource ownership (e.g., can this owner edit THIS accommodation?).
- **Options Considered**:
  1. **Extend functions.php** - Add permission functions to existing file
     - Pro: Single file
     - Con: File already 480+ lines, mixing concerns
  2. **New permissions.php module** - Separate file for RBAC (CHOSEN)
     - Pro: Clear separation of concerns, modular
     - Pro: Easier to test and maintain
     - Con: Additional include required
- **Implementation**:
  - **Resource Ownership Checks** (7 functions):
    - `canViewUser($user_id)` - View profile access
    - `canEditUser($user_id)` - Edit profile access
    - `canEditAccommodation($accommodation_id)` - Accommodation ownership
    - `canManageStudents($accommodation_id)` - Student management rights
    - `canEditStudent($student_id)` - Individual student editing
    - `canCreateCodes($accommodation_id)` - Onboarding code creation
    - `canViewAccommodationStudents($accommodation_id)` - Student list viewing
  - **Accommodation Access Helpers** (5 functions):
    - `getUserAccommodations($user_id)` - All accessible accommodations
    - `getManagerAccommodations($manager_id)` - Manager assignments
    - `getOwnerAccommodations($owner_id)` - Owner's properties
    - `getStudentAccommodation($student_user_id)` - Student enrollment
    - `isAccommodationOwner($accommodation_id, $owner_id)` - Ownership check
    - `isManagerOfAccommodation($accommodation_id, $manager_id)` - Assignment check
  - **Generic Permission Helpers** (5 functions):
    - `hasPermissionToResource($resource_type, $resource_id, $permission)` - Unified checker
    - `denyAccess($message, $redirect_to, $log_attempt)` - Centralized denial with logging
    - `requirePermission($resource_type, $resource_id, $permission)` - Enforce or deny
    - `isAdmin()`, `isOwner()`, `isManager()`, `isStudent()` - Role shortcuts
    - `getAccommodationWithOwner($accommodation_id)` - Convenience getter
- **Security Principles Applied**:
  - Admin bypass: Admin role always returns true (by design)
  - Prepared statements: All queries use parameterized queries
  - Integer validation: All IDs cast to (int) before use
  - Session validation: Uses `$_SESSION['user_id']` and `$_SESSION['user_role']`
  - Error handling: Returns false on errors, never throws exceptions
  - Logging: Access denials logged to activity_log table
- **Files Modified**:
  - `includes/permissions.php` (NEW - Complete RBAC system)
  - `public/edit-accommodation.php` (Uses canEditAccommodation())
  - `public/students.php` (Uses canEditStudent())
  - `public/manager/edit_student.php` (Uses canEditStudent())
- **Rationale**: 
  - Separation of concerns: Role checks (functions.php) vs resource checks (permissions.php)
  - DRY principle: Centralized permission logic prevents code duplication
  - Security in depth: Two layers of access control (role + resource)
- **Consequences**: 
  - New pages should include permissions.php and use permission functions
  - Existing pages should be migrated to use new functions over time
  - Permission denials are logged for security auditing

---

<!-- Copy template above for new decisions -->

## Decision Index by Category

### Architecture
- [DECISION-003] Resource-Based Access Control (RBAC) Implementation

### Tech Stack
- [DECISION-001] M0.5 Configuration Freeze

### Design
- (None yet)

### Process
- [DECISION-001] M0.5 Configuration Freeze (Breaking Change Protocol)

### Security
- [DECISION-002] CSRF Protection Implementation
- [DECISION-003] Resource-Based Access Control (RBAC) Implementation
