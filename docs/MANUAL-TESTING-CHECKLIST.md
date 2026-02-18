# Manual Testing Checklist - EPIC 5

> Complete manual testing checklist for all user flows and features.
> Use this to verify functionality before each release.

---

## Test Environment Setup

- [ ] Database: Use `gwn_wifi_system` with test data loaded from `db/fixtures/test-data.sql`
- [ ] Web Server: Running on `http://localhost` or configured `APP_URL`
- [ ] Session: Browser cookies enabled
- [ ] Error Reporting: Development mode enabled to see errors
- [ ] Database Logging: Verify `error_logs` and `activity_logs` tables exist

---

## Authentication & Session Management

### Login Flow

- [ ] Navigate to `/public/login.php`
- [ ] Test: Valid credentials (find in test-data.sql)
  - [ ] Username/password login successful
  - [ ] Session variables set correctly (`user_id`, `user_role`)
  - [ ] Redirected to appropriate dashboard
- [ ] Test: Invalid credentials
  - [ ] Error message displayed
  - [ ] User not authenticated
- [ ] Test: Missing credentials
  - [ ] Validation error shown
  - [ ] User not authenticated
- [ ] Test: Account locked after 5 failed attempts
  - [ ] Login blocked temporarily
  - [ ] Error message shown

### Logout Flow

- [ ] Click logout button
- [ ] Session destroyed
- [ ] Redirected to login page
- [ ] Cannot access protected pages without re-login

### Session Security

- [ ] Session timeout after 30 minutes of inactivity
- [ ] Session timeout warning appears
- [ ] Session regeneration on privilege escalation (if any)
- [ ] CSRF token validation on all POST forms
- [ ] Security headers present (X-Frame-Options, CSP, etc.)

---

## User Management (Admin)

### Create User

- [ ] Navigate to user management
- [ ] Fill in all required fields
- [ ] Test: Valid user creation
  - [ ] User added to database
  - [ ] Unique username enforced
  - [ ] Unique email enforced
  - [ ] Role assigned correctly
- [ ] Test: Invalid data
  - [ ] Email validation fails on invalid format
  - [ ] Password strength validated (minimum 8 chars, uppercase, number, symbol)
  - [ ] SA ID format validated (13 digits, Luhn check)
  - [ ] Phone number format validated
  - [ ] Appropriate error messages shown

### View Users

- [ ] List all users displayed
- [ ] Pagination works (if applicable)
- [ ] Search by username/email works
- [ ] Filter by role works
- [ ] Sort by name, email, created date works

### Edit User

- [ ] Update user details
- [ ] Change user role
- [ ] Mark as inactive
- [ ] Verify changes saved to database
- [ ] Activity log entry created

### Delete User

- [ ] Delete confirmation dialog shown
- [ ] User deleted from database
- [ ] Associated data handled properly
- [ ] Activity log entry created

---

## Accommodation Management

### Create Accommodation (Owner)

- [ ] Navigate to accommodation creation
- [ ] Fill in name
- [ ] Test: Valid accommodation created
  - [ ] Accommodation added to database
  - [ ] Owner assigned correctly
- [ ] Test: Duplicate accommodation names allowed (within same owner)

### View Accommodations

- [ ] List accommodations based on role
  - [ ] Admin: sees all
  - [ ] Owner: sees own only
  - [ ] Manager: sees assigned only
  - [ ] Student: sees none

### Edit Accommodation

- [ ] Update accommodation name
- [ ] Changes saved to database
- [ ] Only owner/admin can edit

### Manage Managers

- [ ] Add manager to accommodation
  - [ ] User selection dropdown works
  - [ ] Manager assigned
  - [ ] Activity log entry created
- [ ] Remove manager
  - [ ] Confirmation shown
  - [ ] Manager removed
  - [ ] Activity log entry created
- [ ] View all managers
  - [ ] List managers for accommodation
  - [ ] All current managers shown

---

## Student Management

### Student Registration (via Code)

- [ ] Onboarding code generated (by manager)
- [ ] Student receives code
- [ ] Student logs in to portal
- [ ] Enters code on registration page
- [ ] Test: Valid code
  - [ ] Code validates
  - [ ] Student assigned to accommodation
  - [ ] Student can select room number
  - [ ] Confirmation sent
  - [ ] Code marked as used
- [ ] Test: Invalid code
  - [ ] Error message shown
  - [ ] Student not registered
- [ ] Test: Expired code
  - [ ] Code rejected
  - [ ] Error about expired code shown

### View Students (Manager/Owner)

- [ ] List all students in accommodation
- [ ] Filter by status (active, pending, inactive)
- [ ] Search by name/email
- [ ] Sort by registration date, room number

### Edit Student

- [ ] Update room assignment
- [ ] Change status (active/inactive)
- [ ] Verify changes saved
- [ ] Activity log entry created

### Deactivate Student

- [ ] Click deactivate button
- [ ] Confirmation shown
- [ ] Student status changed to inactive
- [ ] Student can no longer access WiFi (depends on WiFi system)
- [ ] Activity log entry created

---

## Device Management

### Register Device (Student)

- [ ] Navigate to device registration
- [ ] Select device type (Laptop, Phone, Tablet, etc.)
- [ ] Enter MAC address
- [ ] Test: Valid MAC address
  - [ ] Format accepted (AA:BB:CC:DD:EE:FF, aabbccddeeff, etc.)
  - [ ] Device added to database
  - [ ] Confirmation shown
- [ ] Test: Invalid MAC address
  - [ ] Validation error shown
  - [ ] Device not registered
- [ ] Test: Duplicate MAC address
  - [ ] Error shown if already registered
  - [ ] Cannot register same device twice
- [ ] Activity log entry created

### View Devices (Student)

- [ ] List all student's devices
- [ ] Device type shown
- [ ] MAC address shown
- [ ] Registration date shown

### Delete Device

- [ ] Click delete button
- [ ] Confirmation shown
- [ ] Device removed from database
- [ ] Activity log entry created

---

## Voucher Management (if applicable)

### View Vouchers

- [ ] Current month vouchers shown
- [ ] Previous months accessible
- [ ] Voucher code shown
- [ ] Status shown (sent, failed, pending)

### Send Vouchers

- [ ] Bulk send vouchers to all active students
- [ ] Email/SMS option available
- [ ] Delivery method selected (SMS/WhatsApp)
- [ ] Progress shown
- [ ] Confirmation when complete
- [ ] Activity log entry created

### Revoke Voucher

- [ ] Mark voucher as revoked
- [ ] Confirmation shown
- [ ] Activity log entry created

---

## Code Management (Manager)

### Generate Codes

- [ ] Navigate to code generation
- [ ] Select accommodation
- [ ] Select role (Student or Manager)
- [ ] Set expiration days
- [ ] Test: Generate single code
  - [ ] Code generated in format XXXX-XXXX-XXXX-XX
  - [ ] Added to database
  - [ ] Unique code verified
- [ ] Test: Generate multiple codes
  - [ ] Batch generation works
  - [ ] All codes unique

### View Codes

- [ ] List all codes for accommodation
- [ ] Filter by status (unused, used, expired)
- [ ] Show expiration date
- [ ] Show usage date
- [ ] Show who used code (if used)

### Revoke Code

- [ ] Mark code as expired
- [ ] Code cannot be used after revocation
- [ ] Activity log entry created

### Code Cleanup

- [ ] Expired codes older than retention period deleted
- [ ] Used codes retained for history
- [ ] Activity log cleaned per retention policy

---

## Dashboard & Reporting

### Admin Dashboard

- [ ] Total users count displayed
- [ ] Total accommodations count
- [ ] Total students count
- [ ] Recent activity log shown
- [ ] Error summary shown (if any)
- [ ] Active sessions shown

### Manager Dashboard

- [ ] Accommodation information displayed
- [ ] Student count for accommodation
- [ ] Active devices count
- [ ] Recent activity log filtered to accommodation
- [ ] Codes status summary

### Owner Dashboard

- [ ] All owned accommodations listed
- [ ] Manager assignments shown
- [ ] Student counts
- [ ] Recent activity for all accommodations

### Student Dashboard

- [ ] Personal information displayed
- [ ] Room assignment shown
- [ ] Accommodation details shown
- [ ] Registered devices listed
- [ ] Current month voucher shown (if applicable)

---

## Forms & Validation

### All Forms

- [ ] Required fields marked with \*
- [ ] Validation errors shown before form submission
- [ ] Error messages are clear and actionable
- [ ] HTML5 validation attributes work (email, required, pattern, etc.)
- [ ] Form maintains field values after validation error

### Email Fields

- [ ] Accept valid emails only
- [ ] Reject invalid formats
- [ ] Unique email enforcement (where applicable)

### Phone Fields

- [ ] Accept South African format (+27XXXXXXXXX)
- [ ] Accept other formats with proper validation
- [ ] Store consistently in database

### Password Fields

- [ ] Minimum 8 characters
- [ ] Require uppercase letter
- [ ] Require number
- [ ] Require special character
- [ ] Confirm password field
- [ ] Password strength indicator (optional)

### File Uploads

- [ ] File type validation
- [ ] File size validation
- [ ] Uploaded to secure directory
- [ ] Only authorized roles can upload

---

## Activity Logging

### Log Entry Creation

- [ ] User action creates log entry
- [ ] User ID captured
- [ ] Action type recorded
- [ ] Entity type recorded (user, device, student, etc.)
- [ ] Entity ID recorded
- [ ] Timestamp recorded correctly
- [ ] IP address captured

### Activity Log Viewing

- [ ] Admin can view all activity logs
- [ ] Owner/Manager can view accommodation activity
- [ ] Student can view own activity only
- [ ] Pagination works
- [ ] Sorting by date works
- [ ] Filter by action type works

### Sensitive Data

- [ ] Passwords never logged
- [ ] Tokens never logged
- [ ] Personal data masked in logs (SA IDs, phone numbers)

---

## Error Handling & Recovery

### Database Errors

- [ ] Connection error shows friendly message
- [ ] Error logged to database
- [ ] Admin can view error in admin panel
- [ ] Error notification sent (if configured)

### Validation Errors

- [ ] Validation errors prevent data save
- [ ] Clear error messages shown to user
- [ ] Form data preserved for correction

### Missing Data

- [ ] 404 error for missing pages
- [ ] Proper error message shown
- [ ] Redirect option provided

### Permission Errors

- [ ] 403 error for unauthorized access
- [ ] User redirected with message
- [ ] Attempt logged

### Session Errors

- [ ] Expired session redirects to login
- [ ] Flash message shown
- [ ] Original page remembered (optional)

---

## Performance & Load

### Load Testing

- [ ] Multiple users accessing simultaneously (5+ users)
- [ ] Page load times reasonable
- [ ] Database queries optimized
- [ ] No N+1 query problems

### Pagination

- [ ] Large lists paginate correctly
- [ ] Page navigation works
- [ ] Item counts accurate

### Search & Filter Performance

- [ ] Search returns results quickly
- [ ] Filters apply without lag
- [ ] Database indexes being used

---

## Security

### Input Validation

- [ ] All user inputs validated
- [ ] No SQL injection possible (parameterized queries)
- [ ] No XSS possible (output escaped/sanitized)
- [ ] CSRF tokens present on all forms

### Authentication

- [ ] Passwords hashed (bcrypt/argon2)
- [ ] Sessions use secure cookies
- [ ] Session IDs regenerated on login
- [ ] HTTPS enforced (in production)

### Authorization

- [ ] Users can only access their own data
- [ ] Role-based access control enforced
- [ ] Admin functions only accessible to admins
- [ ] Manager functions only for assigned accommodations

### Data Protection

- [ ] Sensitive data in database (passwords, tokens)
- [ ] Personal data encrypted at rest (optional)
- [ ] Logs don't contain sensitive data
- [ ] Backups secure and encrypted

---

## Browser Compatibility

- [ ] Chrome/Chromium
  - [ ] Latest version
  - [ ] Latest - 1 version
- [ ] Firefox
  - [ ] Latest version
  - [ ] Latest - 1 version
- [ ] Safari
  - [ ] Latest version
- [ ] Edge
  - [ ] Latest version
- [ ] Mobile browsers
  - [ ] Chrome mobile
  - [ ] Safari iOS

---

## Accessibility

- [ ] Keyboard navigation works
- [ ] Tab order logical
- [ ] Form labels associated with inputs
- [ ] Color not sole indicator
- [ ] ARIA labels present where needed
- [ ] Images have alt text

---

## Responsive Design

- [ ] Desktop (1920px+) - layout correct
- [ ] Laptop (1024px) - layout correct
- [ ] Tablet (768px) - layout correct
- [ ] Mobile (375px) - layout correct
- [ ] Touch targets adequate (44x44px minimum)
- [ ] Text readable on small screens

---

## Regression Testing

### After Each Change

- [ ] Affected feature works
- [ ] Related features still work
- [ ] No console errors
- [ ] No database errors
- [ ] Activity logs created
- [ ] Performance acceptable

---

## Sign-Off

- [ ] All tests completed: **\_\_\_\_** (Date)
- [ ] Tester Name: **********\_\_\_\_**********
- [ ] Issues Found: **\_\_\_\_** (Number)
- [ ] Ready for Release: YES / NO

---

**Use this checklist for manual QA before each release. Document any issues found and verify fixes before retesting.**
