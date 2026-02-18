# M2: Feature Development & Enhancements

**Status:** In Progress  
**Started:** 2026-02-10  
**Gate:** Core Features Complete + CI GREEN

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

**Requirements:**
- [ ] Notification display component
  - Bell icon in header with badge count
  - Dropdown showing recent notifications
  - Mark as read functionality
- [ ] Notification types:
  - Device request pending approval (to managers)
  - Device approved/rejected (to students)
  - Voucher generated (to students)
  - New student added (to managers)
- [ ] Notification preferences page
  - Enable/disable notification types
  - Email vs. in-app settings

**Files to Create:**
- `includes/components/notifications.php` - dropdown component
- `public/notifications.php` - full notification list
- `public/settings/notifications.php` - preferences
- Enhance `includes/functions.php` with notification helpers

---

### M2-T6: Advanced Search & Filters 🔍
**Priority:** P2 - MEDIUM  
**Scope:** Admin & Manager pages

**Requirements:**
- [ ] Student search & filter
  - Search by name, email, student ID
  - Filter by accommodation
  - Filter by device status (has devices, needs approval)
  - Sort by name, date added
- [ ] Activity log filtering
  - Filter by date range
  - Filter by user
  - Filter by action type
  - Export filtered results to CSV
- [ ] AJAX-based search (optional enhancement)

**Files to Modify:**
- `public/admin/students.php` - add search/filter
- `public/manager/students.php` - add search/filter
- `public/admin/activity-log.php` - add filtering

---

### M2-T7: Responsive Mobile Optimization 📱
**Priority:** P2 - MEDIUM  
**Scope:** All pages

**Requirements:**
- [ ] Audit all pages for mobile responsiveness
- [ ] Fix table overflow issues
  - Use responsive tables (Bootstrap classes)
  - Add horizontal scroll where needed
- [ ] Optimize forms for mobile
  - Stack form fields vertically on small screens
  - Larger touch targets (buttons, inputs)
- [ ] Mobile navigation
  - Collapsible sidebar or hamburger menu
  - Touch-friendly dropdowns

**Files to Audit:**
- All `public/**/*.php` pages
- `includes/components/header.php` - mobile nav
- `public/assets/css/custom.css` - mobile styles

---

### M2-T8: Reporting & Export System 📈
**Priority:** P2 - MEDIUM  
**Scope:** Admin & Owner dashboards

**Requirements:**
- [ ] Report generation page (admin only)
- [ ] Available reports:
  - Monthly voucher usage report
  - Student enrollment by accommodation
  - Device authorization summary
  - Manager activity report
  - System audit log (filtered)
- [ ] Export formats: CSV, PDF (optional)
- [ ] Date range selection

**Files to Create:**
- `public/admin/reports.php` - report selection
- `public/admin/report-generator.php` - generate report
- `includes/report-helpers.php` - report generation functions

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

**Gate Status:** 🔴 Not Started
