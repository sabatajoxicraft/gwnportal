# Current Milestone: M2 - Feature Development & Enhancements 🔄

**Phase:** M2 (Feature Development & Enhancements)
**Started:** 2026-02-13  
**Status:** 🔄 IN PROGRESS

> **File roles:** [`m2-tasks.md`](m2-tasks.md) is the **planned backlog** (open/upcoming work with assigned IDs M2-T1…).
> This file is the **live delivery log** — it records what was actually shipped, including supplemental
> maintenance work that does not consume a backlog task ID.

## M1 Achievements

### ✅ Authentication Hardening (M1-T1)

- CSRF protection on all forms
- Session security: 30-min timeout, regeneration, secure cookies
- Password verification restored (password-less mode removed)
- Session validation middleware
- Proper logout with session cleanup

### ✅ RBAC Permission Enforcement (M1-T2)

- All admin pages protected with role checks
- All manager pages protected with role checks
- `requireRole()` and `hasPermission()` helpers implemented
- 403 errors on unauthorized access
- Permission system verified

### ✅ Input Validation & Sanitization (M1-T5)

- `validateEmail()`, `validatePhone()`, `sanitizeInput()` helpers created
- `validateMaxLength()` and `validateRequired()` helpers
- Ready for application across all forms

### ✅ Output Escaping (M1-T6)

- `htmlEscape()` / `e()`, `jsEscape()`, `urlEscape()` helpers
- Flash messages escaped
- Login error messages escaped
- XSS prevention foundation established

### ✅ Security Headers (M1-T7)

- X-Frame-Options, X-Content-Type-Options, X-XSS-Protection
- Content-Security-Policy (basic)
- Referrer-Policy configured

### ✅ Rate Limiting (M1-T8)

- Login rate limiting active (checkLoginThrottle)
- Failed login tracking (recordFailedLogin)

### ✅ Error Handling & Logging (M1-T3)

- Standardized error handler (safeQueryPrepare)
- Security event logging (logActivity)
- Error display helpers (dev vs. production)

## Files Modified

- `includes/config.php` - session settings, security headers
- `includes/functions.php` - CSRF, validation, escaping helpers (+163 lines)
- `includes/permissions.php` - RBAC system
- `public/login.php` - password verification restored
- Multiple form pages - CSRF tokens added

## M2 Achievements (2026-02-13 to 2026-02-14)

### ✅ Login & Authentication Fixes (M2-T1)

- Fixed test credentials display on login page
- Corrected usernames: sabata/thabo → nokuthula (owner), actual manager/student names
- Fixed password setup for ALL users (not just admin/owner)
- Updated login credentials reference card with collapsible design

### ✅ Database Schema Alignment (M2-T2)

- Fixed 13+ schema mismatches across codebase
- Corrected `student_devices` → `user_devices` table references (2 files)
- Fixed `student_id` → `user_id` in user_devices table (6 files)
- Fixed `device_name` → `device_type` column naming (3 files)
- Removed non-existent columns: status, added_at, updated_at, device_capacity
- Fixed JOIN conditions in dashboard queries
- Removed accommodations.address reference
- Fixed notifications table column naming (recipient_id, read_status)
- Handled user_preferences table gracefully

### ✅ Undefined Variable Fixes (M2-T3)

- Fixed 10+ undefined variable warnings
- Added null coalescing operators (??) across all session variables
- Added isset() checks for GET/POST parameters
- Fixed $owner_id undefined in edit-accommodation.php
- Fixed session variable access in dashboard, managers, create-code, students pages

### ✅ Student Credential Recovery Feature (M2-T4)

- Added "Resend Login Details" button to student-details.php
- Created resend-credentials.php handler with full security
- Implemented secure temporary password generation (16 chars)
- Added SMS/WhatsApp/Email delivery with fallback
- Added confirmation dialogs and activity logging
- Added resend button to student list actions dropdown

### ✅ Student Credential Recovery Feature (M2-T4) — stats below

### 📊 Stats (M2-T1 through M2-T4)

- **Files Modified:** 20+
- **Schema Issues Fixed:** 13
- **Undefined Variables Fixed:** 10+
- **New Features:** 1 (Credential Recovery)
- **Critical Errors Resolved:** All

## M2 Achievements (Current Session - 2026-03-05)

### ✅ Activity Log Display Improvements (Supplemental Maintenance)

- Implemented human-readable action names: `auth_login_success` → **Login Success**
- Added category prefix stripping: `auth_`, `device_`, `voucher_`, `student_`, `accommodation_`, `permission_`
- Added snake_case to Title Case conversion
- Implemented JSON details parsing for readability
- Details with `reason` field now display friendly text instead of raw JSON
- Key-value pairs formatted as readable labels, skipping redundant fields (ip_address, success)
- File: `public/admin/view-user.php` (Activity Log section, +28 lines)
- Commit: `c8b632d`

### ✅ Activity Log Timestamp Fix (Supplemental Maintenance)

- **Problem:** Timestamps showing as "Jan 1, 1970 12:00 AM" (MySQL epoch)
- **Root Cause:** `activity_log` table had `timestamp DATETIME NOT NULL` with NO DEFAULT
- **Solution:** Added `DEFAULT CURRENT_TIMESTAMP` to all schema definitions
- **Database:** Applied direct migration to running container
- **Files Updated:**
  - `db/schema.sql` - Added DEFAULT CURRENT_TIMESTAMP
  - `db/joxicaxs_wifi.sql` - Added DEFAULT CURRENT_TIMESTAMP
  - `db/migrations/2026_03_05_fix_activity_log_timestamp.sql` - Migration script created
- Commit: `cb5a94e`
- **Result:** Activity logs now show correct dates (e.g., "March 04, 2026 01:26 PM")
- **Future-proof:** New activity logs automatically get correct timestamp on creation

### 📊 Session Stats

- **Commits:** 2
- **Files Modified:** 4
- **Bugs Fixed:** 2 (display formatting, timestamp defaults)
- **UX Improvements:** Readable activity logs with proper timestamps
- **Time:** Session

## M2 Achievements (Current Session - 2026-04-14)

### ✅ Notification System (M2-T5)

- Bell icon with unread badge wired into navigation dropdown
- Full notification list page (`public/notifications.php`)
- Per-user preferences page (`public/settings/notifications.php`) backed by `user_preferences` table
- CSRF hardening on notification-settings form and mark-read API endpoint
- Event coverage: device request, device approval/rejection, voucher generated, new student registration
- Opt-in email copies via `sendNotificationEmail()`
- Schema alignment in `db/schema.sql`; new additive migration (safe cleanup + additions)
- Docker test auth: `tests/ServiceTestSuite_docker.php` now accepts `DB_ROOT_PASSWORD` env var with Docker default fallback

**Validation:**
- Targeted lint and migration checks: ✅ passed
- Full Docker service suite: ✅ **51/51 passed** (`docker compose run --rm --no-deps gwn-app php tests\ServiceTestSuite_docker.php`)
- CI/CD pipeline: local validation green; CI not separately re-run this session

### ✅ Post-M2-T5 Follow-up Fixes (Supplemental Maintenance)

Unblocked the Docker service suite (was blocked on pre-existing test and service issues):

- **Test bootstrap fixes** (`tests/ServiceTestSuite.php`, `tests/ServiceTestSuite_docker.php`): correctly initialize test DB, include `functions.php`, supply required user fields (`first_name`, etc.), point global `$conn` at test DB.
- **Service compatibility** (`UserService`, `AccommodationService`, `DeviceManagementService`): added backward-compatible `id` aliases where result sets lacked them.
- **Schema** (`db/schema.sql`): added missing `profile_photo` column.
- **`CodeService`**: corrected bind-type string.
- **`AccommodationService::isManager()`**: fixed existence query.
- **`FormValidator::normalizeMacAddress()`**: now safely normalizes to uppercase.
- **`PermissionHelper`**: corrected role-resolution and privilege logic.

**Result:** `docker compose run --rm --no-deps gwn-app php tests\ServiceTestSuite_docker.php` → **51/51 passed**.

### 📊 Session Stats (M2-T5)

- **Commits:** TBD (in progress)
- **Files Modified:** 8+ (M2-T5) + maintenance follow-ups
- **New Features:** Notification System (in-app + email opt-in)
- **Security Improvements:** CSRF hardening on 2 endpoints
- **Schema Changes:** notifications + user_preferences alignment; `profile_photo` added
- **Test Suite:** Docker service suite unblocked — 51/51 passing

## M2 Achievements (Current Session - 2026-04-15)

### ✅ Advanced Search & Filters (M2-T6)

- **Manager student surface** (`public/students.php`): search by name, email, student ID, or id_number; device-status filter (`all`, `has_devices`, `needs_approval`); sort (`newest`, `oldest`, `name_asc`, `name_desc`); 50-per-page pagination — existing tabs, actions, and accommodation switcher preserved.
- **Admin student page** (`public/admin/students.php`): new page with accommodation filter, same search/device-status/sort/pagination, accommodation context display, and admin actions.
- **Shared query logic** extracted to `includes/services/QueryService.php` — student list and count queries reused by both manager and admin surfaces.
- **Admin activity log CSV export** (`public/admin/export-activity-log.php`): all active filters (user, action type, date range) from `public/admin/activity-log.php` preserved in the export of the full filtered dataset.
- **Schema alignment** (`db/schema.sql`): `user_devices` now includes `linked_via` and related device-management columns for fresh-environment compatibility; no migration-only drift.

**Validation:**
- Targeted PHP syntax checks: ✅ passed
- Full Docker service suite: ✅ **51/51 passed** (`docker compose run --rm --no-deps gwn-app php tests\ServiceTestSuite_docker.php`)

### 📊 Session Stats (M2-T6)

- **Files Created/Modified:** `public/students.php`, `public/admin/students.php` (new), `includes/services/QueryService.php` (new), `public/admin/export-activity-log.php` (new), `db/schema.sql`
- **New Features:** Advanced search + filters on student lists (manager & admin), admin activity-log CSV export
- **Schema Changes:** `user_devices` aligned with `linked_via` and device-management columns in base schema
- **Test Suite:** Docker service suite 51/51 passing

## M2 Achievements (Current Session - 2026-04-16)

### ✅ Responsive Mobile Optimization (M2-T7)

- **Shared CSS** (`public/assets/css/custom.css`): reverted generic `.table-responsive` to Bootstrap-style horizontal scrolling (overflow-x: auto); dropdown escape is now opt-in; `.scrollable-table` extended to support both axes for actual scrollable log areas.
- **Mobile-friendly filter forms**: stacked/collapsed filter rows on small screens across all priority surfaces.
- **Responsive stats rows**: stat cards reflow naturally on narrow viewports.
- **Improved table wrappers/scrollers**: consistent use of `.table-responsive` / `.scrollable-table` wrappers per surface.
- **Mobile action/button handling**: action-dropdown containers and button groups reconfigured for touch targets.
- **Responsive card headers**: title + action buttons wrap cleanly on small screens.
- **Search container sizing**: search inputs and filter selects sized appropriately for mobile.
- **Authenticated navbar/mobile dropdown**: nav and hamburger dropdown improvements for authenticated users.

**Pages updated:**
- `public/students.php`
- `public/admin/students.php`
- `public/admin/users.php`
- `public/admin/activity-log.php`
- `public/manager/network-clients.php`
- `public/student-details.php`
- `public/admin/settings.php`

> `public/dashboard.php` inspected but unchanged — it is a router only.

**Validation:**
- Targeted PHP syntax checks: ✅ passed
- Full Docker service suite: ✅ **51/51 passed** (`docker compose run --rm --no-deps gwn-app php tests\ServiceTestSuite_docker.php`)

### 📊 Session Stats (M2-T7)

- **Files Modified:** `public/assets/css/custom.css`, `public/students.php`, `public/admin/students.php`, `public/admin/users.php`, `public/admin/activity-log.php`, `public/manager/network-clients.php`, `public/student-details.php`, `public/admin/settings.php`
- **New Features:** Mobile-responsive tables, forms, stats rows, nav, and card headers across all priority surfaces
- **Test Suite:** Docker service suite 51/51 passing

### ✅ Reporting & Export System (M2-T8)

- **Shared reporting service** (`includes/services/ReportService.php`): centralizes report queries plus column/title/icon/filter metadata for all admin report surfaces.
- **Admin reports page** (`public/admin/reports.php`): extended instead of duplicated; now supports the 5 M2-T8 report types plus the 3 legacy report types with conditional filters and column-driven rendering.
- **CSV export endpoint** (`public/admin/export-reports.php`): server-side export preserving active filters, streaming the full filtered dataset with UTF-8 BOM for Excel compatibility.
- **Voucher lifecycle schema alignment** (`db/schema.sql`): `voucher_logs` now includes `gwn_voucher_id`, `gwn_group_id`, `first_used_at`, `first_used_mac`, `revoked_at`, `revoked_by`, `revoke_reason`, and `is_active` in the base schema for fresh-environment compatibility.
- **Report logic corrections**: monthly voucher usage now counts first use via `first_used_at` instead of an invalid `status = 'used'` assumption; device authorization summary now reports authorization paths (`manual`, `auto`, `request`) instead of device-type buckets.
- **System audit behavior**: HTML display remains capped at 2,000 rows while CSV export streams the full filtered result set; audit date filtering/formatting reuses `ActivityLogHelper`.

**Validation:**
- Targeted PHP syntax checks: ✅ passed (`includes/services/ReportService.php`, `public/admin/reports.php`, `public/admin/export-reports.php`)
- Code review follow-ups: ✅ applied (explicit 2,000-row HTML cap, accommodation filter typing cleanup)
- Full Docker service suite: ✅ **51/51 passed** (`docker compose run --rm --no-deps gwn-app php tests\ServiceTestSuite_docker.php`) after resetting only the dedicated `gwn_wifi_system_test` database to clear stale test data

### 📊 Session Stats (M2-T8)

- **Files Created/Modified:** `includes/services/ReportService.php` (new), `public/admin/export-reports.php` (new), `public/admin/reports.php`, `db/schema.sql`
- **New Features:** 5 new admin report types, shared reporting service, server-side CSV export preserving filters
- **Schema Changes:** `voucher_logs` base schema aligned with existing voucher lifecycle fields used by reporting and voucher/device flows
- **Test Suite:** Docker service suite 51/51 passing on a clean test database

## Previous Milestones

- **M1 (Core Infrastructure):** ✅ Complete - Security hardening, RBAC, validation
- **M0.5 (Scaffold Validation):** ✅ Complete - CI GREEN, configs locked
- **M0 (PRD Definition):** ✅ Complete - Approved 2026-02-10
