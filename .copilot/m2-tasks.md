# M2: Feature Development & Enhancements

**Status:** In Progress  
**Started:** 2026-02-10  
**Gate:** Core Features Complete + CI GREEN

> **This file is the planned backlog for M2.** Each task here carries a canonical ID (M2-T1…).
> Completed work and supplemental maintenance fixes that fall outside this backlog are recorded
> separately in [`current-milestone.md`](current-milestone.md) and do not consume IDs from this list.

---

## Overview
With security hardening complete (M1), M2 focuses on implementing core features and user experience enhancements for the gwn-portal system.

---

## Feature Priorities

### M2-T1: Enhanced Dashboard Analytics 📊
**Priority:** P1 - HIGH  
**Scope:** Admin & Manager dashboards
**Status:** ✅ COMPLETE

**Requirements:**
- [x] Admin dashboard: System-wide statistics
  - Total students, active devices, accommodations
  - Recent activity feed (last 5 actions)
  - Quick action buttons (Add Student, Add Manager, View Logs)
  - Chart: Students by accommodation (bar chart with Chart.js v4.4.0)
- [x] Manager dashboard: Accommodation-specific stats
  - Students in their accommodation
  - Device capacity (used/total with progress bar)
  - Recent vouchers generated (last 5 with date/month)
  - Student status distribution chart (pie chart)
- [x] Use Chart.js v4.4.0 for visualizations

**Files Created/Modified:**
- `public/admin/dashboard.php` - enhanced with charts and device stats
- `public/dashboard.php` - enhanced manager section with status chart, device capacity, and recent vouchers
- `public/assets/js/charts.js` - chart initialization with role-based gradients (NEW)

**Implementation Details:**
- Chart.js v4.4.0 from jsdelivr CDN
- Role-based gradient colors: Admin (purple), Manager (blue/green)
- Responsive charts with proper legends and tooltips
- Used existing helper functions and prepared statements
- No N+1 query problems (used JOINs where needed)

---

### M2-T2: Student Self-Service Portal 👤
**Priority:** P1 - HIGH  
**Scope:** Student dashboard and profile
**Status:** ✅ COMPLETE

**Requirements:**
- [x] Student dashboard page
  - View personal information
  - List assigned devices (MAC addresses)
  - View voucher history (date, expiry, status)
  - Request new device authorization button
- [x] Profile management
  - Update contact information (email, phone)
  - Change password
  - View account details
- [x] Device request form
  - Submit MAC address for approval
  - Manager receives notification

**Files Created:**
- `public/student/dashboard.php` - Main student portal with personal info, device summary, and voucher history
- `public/student/profile.php` - Profile management with contact info and password change
- `public/student/devices.php` - View all registered devices with status badges
- `public/student/request-device.php` - Request device authorization form with MAC address validation

**Implementation Details:**
- Uses `requireRole('student')` for access control
- CSRF protection on all forms
- MAC address validation with multiple format support (XX:XX:XX:XX:XX:XX, XX-XX-XX-XX-XX-XX, XXXXXXXXXXXX)
- Student gradient colors from custom.css
- Activity logging for all actions
- Empty states with helpful CTAs
- Responsive design with Bootstrap 5 components
- Added student navigation items in navigation.php

---

### M2-T3: Enhanced Voucher Management 🎟️
**Priority:** P1 - HIGH  
**Scope:** Manager voucher tools
**Status:** ✅ COMPLETE

**Requirements:**
- [x] Bulk voucher generation
  - Select multiple students from list
  - Generate vouchers for all selected
  - Show progress indicator
- [x] Voucher history page
  - Filterable table (date range, student, status)
  - Export to CSV
  - View voucher details (QR code, expiry)
- [x] Revoke voucher functionality
  - Mark voucher as revoked
  - Log action in activity_log

**Files Created/Modified:**
- `public/manager/vouchers.php` - NEW: Bulk selection interface with checkbox management
- `public/manager/voucher-history.php` - NEW: Filterable history with sorting and pagination
- `public/manager/voucher-details.php` - NEW: Single voucher view with QR code and timeline
- `public/manager/revoke-voucher.php` - NEW: Revoke endpoint with CSRF protection
- `public/manager/export-vouchers.php` - NEW: CSV export with all filters
- `includes/functions.php` - Added `revokeVoucher()` function
- `includes/components/navigation.php` - Added "Voucher History" link for managers
- `db/migrations/add_voucher_revoke_fields.sql` - Schema changes for revoke functionality
- `db/migrations/apply_voucher_migration.php` - PHP migration script

**Implementation Details:**
- Bulk selection with Select All/Deselect All functionality
- Communication method override (SMS/WhatsApp or respect preference)
- Progress indicator during bulk generation
- Comprehensive filters: date range, student search, status, month
- Sortable columns with ASC/DESC toggle
- Pagination (50 records per page)
- CSV export respects all filters
- QR code generation using api.qrserver.com
- Status timeline visualization
- Revoke functionality with reason logging
- is_active flag for soft deletion
- Activity logging for all voucher actions

**Database Schema Changes:**
```sql
ALTER TABLE voucher_logs 
ADD COLUMN revoked_at TIMESTAMP NULL,
ADD COLUMN revoked_by INT NULL,
ADD COLUMN revoke_reason TEXT,
ADD COLUMN is_active BOOLEAN DEFAULT 1,
ADD FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL;
```

**Migration Required:**
Run `php db/migrations/apply_voucher_migration.php` to apply schema changes.

---

### M2-T4: Student Credential Recovery 🔐
**Priority:** P1 - HIGH
**Scope:** Student password reset & recovery
**Status:** ✅ COMPLETE

**Requirements:**
- [x] Forgot Password page
  - Email input for verification
  - Security question/answer verification (if implemented) or link generation
- [x] Password Reset flow
  - Secure token generation
  - Email delivery (simulated/actual)
  - Password strength validation
- [x] Update login page with "Forgot Password" link

**Files Created/Modified:**
- `public/forgot-password.php` - Request reset link
- `public/reset-password.php` - Handle token and new password
- `includes/mail_functions.php` - Email delivery logic
- `public/login.php` - Added reset link

---

### M2-T5: Notification System 📧
**Priority:** P2 - MEDIUM  
**Scope:** In-app notifications  
**Status:** ✅ COMPLETE

**Requirements:**
- [x] Notification display component
  - Bell icon in header with badge count
  - Dropdown showing recent notifications
  - Mark as read functionality
- [x] Notification types:
  - Device request pending approval (to managers)
  - Device approved/rejected (to students)
  - Voucher generated (to students)
  - New student added (to managers)
- [x] Notification preferences page
  - Enable/disable notification types
  - Email vs. in-app settings

**Files Created/Modified:**
- `includes/components/notifications.php` - bell-icon dropdown component with badge count and mark-as-read
- `public/notifications.php` - full notification list page
- `public/settings/notifications.php` - per-type preferences backed by `user_preferences`
- `includes/functions.php` - `sendNotificationEmail()` and notification helper functions
- `includes/components/navigation.php` - bell icon wired into nav; dropdown discoverability improvements
- `db/schema.sql` - schema alignment for notifications and user_preferences tables
- New additive migration for notification system (safe cleanup + additions)
- `tests/ServiceTestSuite_docker.php` - Docker auth fallback: uses `DB_ROOT_PASSWORD` env var or Docker default

**Implementation Details:**
- CSRF hardening applied to notification-settings form and mark-read API endpoint
- `user_preferences` table used for per-user opt-in/opt-out of notification types
- Opt-in email copies sent via `sendNotificationEmail()`
- Event coverage: device request, device approval/rejection, voucher generated, new student registration
- Navigation updated for improved dropdown discoverability

**Validation Notes:**
- Targeted lint and migration checks passed
- Full Docker service suite: ✅ **51/51 passed** after post-M2-T5 follow-up fixes (test bootstrap, service `id` aliases, schema `profile_photo`, bind-type corrections, `isManager()` query, MAC normalizer, `PermissionHelper` role logic)
- CI/CD pipeline: local validation green; CI not separately re-run this session

---

### M2-T6: Advanced Search & Filters 🔍
**Priority:** P2 - MEDIUM  
**Scope:** Admin & Manager pages  
**Status:** ✅ COMPLETE

**Requirements:**
- [x] Student search & filter
  - Search by name, email, student ID, id_number
  - Filter by accommodation (admin surface)
  - Filter by device status (`all`, `has_devices`, `needs_approval`)
  - Sort by `newest`, `oldest`, `name_asc`, `name_desc`
  - 50-per-page pagination
- [x] Activity log filtering
  - Existing date range, user, and action-type filters preserved
  - Export filtered results to CSV (`public/admin/export-activity-log.php`)
- [ ] AJAX-based search (deferred — not in scope for this iteration)

**Files Created/Modified:**
- `public/students.php` — manager student surface: search, device-status filter, sort, pagination (tabs/actions/accommodation switcher preserved)
- `public/admin/students.php` — NEW admin student page: accommodation filter, search, device-status filter, sort, pagination, admin actions
- `includes/services/QueryService.php` — NEW shared student list/count query logic reused by both surfaces
- `public/admin/export-activity-log.php` — NEW CSV export; all active filters from `activity-log.php` preserved over full filtered dataset
- `db/schema.sql` — `user_devices` aligned: `linked_via` and related device-management columns now in base schema for fresh-environment compatibility

> **Note:** `public/manager/students.php` does not exist; the real manager student surface is `public/students.php`.

**Validation:**
- Targeted PHP syntax checks: ✅ passed
- Full Docker service suite: ✅ **51/51 passed** (`docker compose run --rm --no-deps gwn-app php tests\ServiceTestSuite_docker.php`)

---

### M2-T7: Responsive Mobile Optimization 📱
**Priority:** P2 - MEDIUM  
**Scope:** All pages  
**Status:** ✅ COMPLETE

**Requirements:**
- [x] Audit all pages for mobile responsiveness
- [x] Fix table overflow issues — Bootstrap horizontal scroll restored; `.scrollable-table` supports both axes
- [x] Optimize forms for mobile — filter forms stack/collapse on small screens; touch-target sizing improved
- [x] Mobile navigation — authenticated navbar and hamburger dropdown improved; dropdown escape is opt-in

**Files Modified:**
- `public/assets/css/custom.css` — shared responsive/mobile shell: `.table-responsive` reverted to Bootstrap horizontal-scroll; dropdown escape opt-in; `.scrollable-table` dual-axis support
- `public/students.php` — mobile filter form, responsive stats row, table wrapper
- `public/admin/students.php` — mobile filter form, responsive card header, table wrapper
- `public/admin/users.php` — mobile action buttons, responsive card header, table wrapper
- `public/admin/activity-log.php` — mobile filter form, scrollable log table
- `public/manager/network-clients.php` — mobile filter form, responsive stats, scrollable table
- `public/student-details.php` — mobile action buttons, responsive card layout
- `public/admin/settings.php` — responsive form sections, mobile-friendly inputs

> `public/dashboard.php` inspected but unchanged — it is a router only.

**Validation:**
- Targeted PHP syntax checks: ✅ passed
- Full Docker service suite: ✅ **51/51 passed** (`docker compose run --rm --no-deps gwn-app php tests\ServiceTestSuite_docker.php`)

---

### M2-T8: Reporting & Export System 📈
**Priority:** P2 - MEDIUM  
**Scope:** Admin & Owner dashboards
**Status:** ✅ COMPLETE

**Requirements:**
- [x] Report generation page (admin only) — implemented by extending the existing `public/admin/reports.php`
- [x] Available reports:
  - Monthly voucher usage report
  - Student enrollment by accommodation
  - Device authorization summary
  - Manager activity report
  - System audit log (filtered)
- [x] Export formats: CSV implemented; PDF remains optional/deferred (no new dependency added)
- [x] Date range selection

**Files Created/Modified:**
- `public/admin/reports.php` — existing admin reports surface extended to 8 report types with conditional filters and column-driven rendering
- `public/admin/export-reports.php` — NEW server-side CSV export endpoint preserving active filters and streaming the full filtered dataset
- `includes/services/ReportService.php` — NEW shared report query/metadata service reused by the page and export endpoint
- `db/schema.sql` — `voucher_logs` aligned with voucher lifecycle columns used by reporting and existing voucher/device flows

> **Implementation note:** `public/admin/reports.php` already existed, so M2-T8 shipped by extending that surface instead of creating a separate `report-generator.php` page or `includes/report-helpers.php`.

**Implementation Details:**
- 5 new M2-T8 reports were added while preserving the 3 legacy report types (`user_activity`, `accommodation_usage`, `onboarding_codes`)
- Monthly voucher usage now counts first use via `first_used_at` and revocations via voucher lifecycle fields instead of relying on an invalid `status = 'used'` value
- Device authorization summary now reports authorization paths (`linked_via = manual|auto|request`) rather than device-type buckets
- System audit reporting reuses `ActivityLogHelper::localDateRangeToUtc()` and existing action/detail formatting helpers
- CSV export uses UTF-8 BOM and streams the full filtered dataset; the HTML audit table remains capped at 2,000 rows

**Validation:**
- Targeted PHP syntax checks: ✅ passed (`includes/services/ReportService.php`, `public/admin/reports.php`, `public/admin/export-reports.php`)
- Full Docker service suite: ✅ **51/51 passed** (`docker compose run --rm --no-deps gwn-app php tests\ServiceTestSuite_docker.php`) after resetting only the dedicated `gwn_wifi_system_test` database to clear stale test data

---

### M2-T9: Onboarding Code Management 🔑
**Priority:** P3 - LOW  
**Scope:** Admin & Manager tools

**Requirements:**
- [ ] Generate onboarding codes for new students
- [ ] Codes are single-use, expire after 7 days
- [ ] Student uses code to self-register
- [ ] Code links to specific accommodation
- [ ] Admin/Manager can view code status (used, expired, active)

**Files to Create:**
- `public/admin/onboarding-codes.php` - manage codes
- `public/register.php` - student self-registration (optional)
- Enhance `includes/functions.php` with code helpers

---

## Testing Requirements

### Functional Testing
- [ ] All new pages accessible by correct roles
- [ ] Forms submit correctly with CSRF protection
- [ ] Data validation working on all inputs
- [ ] Role-based permissions enforced
- [ ] Charts and visualizations render correctly

### UI/UX Testing
- [ ] Mobile responsive (iPhone SE, Android)
- [ ] Browser compatibility (Chrome, Firefox, Safari, Edge)
- [ ] Accessibility (keyboard navigation, screen readers)
- [ ] Loading states for async operations
- [ ] Error messages user-friendly and actionable

### Performance Testing
- [ ] Page load time < 2 seconds
- [ ] Database queries optimized (use EXPLAIN)
- [ ] Large datasets paginated (limit 50 per page)
- [ ] No N+1 query problems

---

## Documentation Updates
- [ ] Update architecture.md with new features
- [ ] Create user guides (admin, manager, student)
- [ ] Update README with feature list
- [ ] Document API endpoints (if any)

---

## M2 Completion Criteria
- [ ] All P1 features implemented and tested
- [ ] All P2 features implemented (or deferred to M3)
- [ ] Mobile responsive verified on 3+ devices
- [ ] All functional tests pass
- [ ] Documentation updated
- [ ] CI/CD pipeline GREEN

**Gate Status:** 🟡 In Progress
