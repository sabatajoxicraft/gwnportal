# M2: Feature Development & Enhancements

**Status:** Ready to Begin  
**Gate:** Core Features Complete + CI GREEN

---

## Overview
With security hardening complete (M1), M2 focuses on implementing core features and user experience enhancements for the gwn-portal system.

---

## Feature Priorities

### M2-T1: Enhanced Dashboard Analytics 📊
**Priority:** P1 - HIGH  
**Scope:** Admin & Manager dashboards

**Requirements:**
- [ ] Admin dashboard: System-wide statistics
  - Total students, active devices, accommodations
  - Recent activity feed (last 10 actions)
  - Quick action buttons (Add Student, Add Manager, View Logs)
  - Chart: Students by accommodation (bar/pie chart)
- [ ] Manager dashboard: Accommodation-specific stats
  - Students in their accommodation
  - Device capacity (used/total)
  - Recent vouchers generated
  - Quick actions (Generate Voucher, Add Student)
- [ ] Use Chart.js or similar for visualizations

**Files to Create/Modify:**
- `public/admin/dashboard.php` - enhance with charts
- `public/manager/dashboard.php` - enhance with stats
- `public/assets/js/charts.js` - chart initialization

---

### M2-T2: Student Self-Service Portal 👤
**Priority:** P1 - HIGH  
**Scope:** Student dashboard and profile

**Requirements:**
- [ ] Student dashboard page
  - View personal information
  - List assigned devices (MAC addresses)
  - View voucher history (date, expiry, status)
  - Request new device authorization button
- [ ] Profile management
  - Update contact information (email, phone)
  - Change password
  - View account details
- [ ] Device request form
  - Submit MAC address for approval
  - Manager receives notification

**Files to Create:**
- `public/student/dashboard.php`
- `public/student/profile.php`
- `public/student/devices.php`
- `public/student/request-device.php`

---

### M2-T3: Enhanced Voucher Management 🎟️
**Priority:** P1 - HIGH  
**Scope:** Manager voucher tools

**Requirements:**
- [ ] Bulk voucher generation
  - Select multiple students from list
  - Generate vouchers for all selected
  - Show progress indicator
- [ ] Voucher history page
  - Filterable table (date range, student, status)
  - Export to CSV
  - View voucher details (QR code, expiry)
- [ ] Revoke voucher functionality
  - Mark voucher as revoked
  - Log action in activity_log

**Files to Create/Modify:**
- `public/manager/vouchers.php` - add bulk generation
- `public/manager/voucher-history.php` - new page
- `public/manager/voucher-details.php` - view single voucher

---

### M2-T4: Notification System 📧
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

### M2-T5: Advanced Search & Filters 🔍
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

### M2-T6: Responsive Mobile Optimization 📱
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

### M2-T7: Reporting & Export System 📈
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

### M2-T8: Onboarding Code Management 🔑
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
