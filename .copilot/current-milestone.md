# Current Milestone: M2 - Bug Fixes & Enhancements 🔄

**Phase:** M2 (Ongoing Maintenance)  
**Started:** 2026-02-13  
**Status:** 🔄 IN PROGRESS

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

### 📊 Stats

- **Files Modified:** 20+
- **Schema Issues Fixed:** 13
- **Undefined Variables Fixed:** 10+
- **New Features:** 1 (Credential Recovery)
- **Critical Errors Resolved:** All

## M2 Achievements (Current Session - 2026-03-05)

### ✅ Activity Log Display Improvements (M2-T5)

- Implemented human-readable action names: `auth_login_success` → **Login Success**
- Added category prefix stripping: `auth_`, `device_`, `voucher_`, `student_`, `accommodation_`, `permission_`
- Added snake_case to Title Case conversion
- Implemented JSON details parsing for readability
- Details with `reason` field now display friendly text instead of raw JSON
- Key-value pairs formatted as readable labels, skipping redundant fields (ip_address, success)
- File: `public/admin/view-user.php` (Activity Log section, +28 lines)
- Commit: `c8b632d`

### ✅ Activity Log Timestamp Fix (M2-T6)

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

## Previous Milestones

- **M1 (Core Infrastructure):** ✅ Complete - Security hardening, RBAC, validation
- **M0.5 (Scaffold Validation):** ✅ Complete - CI GREEN, configs locked
- **M0 (PRD Definition):** ✅ Complete - Approved 2026-02-10
