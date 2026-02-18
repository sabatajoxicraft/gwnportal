# M2-T3 Testing Checklist

## Pre-Testing Setup

- [ ] Database migration applied successfully
  ```bash
  php db/migrations/apply_voucher_migration.php
  ```
- [ ] Verify new columns exist in voucher_logs table
- [ ] Log in as a manager account
- [ ] Ensure test accommodation has active students
- [ ] Clear browser cache if needed

---

## Test 1: Navigation & Access

### Steps:
1. Log in as manager
2. Check navigation menu

### Expected Results:
- [ ] "Voucher History" link appears in manager menu
- [ ] Link has clock-history icon
- [ ] Clicking link goes to `/manager/voucher-history.php`

### Test as Non-Manager:
- [ ] Student cannot access voucher pages (redirected)
- [ ] Admin cannot access (or has different interface)

---

## Test 2: Bulk Voucher Generation

### Test 2.1: Access Page
**URL:** `/manager/vouchers.php`

- [ ] Page loads without errors
- [ ] Student list displays
- [ ] Month selector shows current and next month
- [ ] Communication method selector displays
- [ ] Select All/Deselect All buttons present

### Test 2.2: Selection Functionality
- [ ] Click individual checkbox - student selected
- [ ] Click again - student deselected
- [ ] "Select All" button - all students checked
- [ ] "Deselect All" button - all students unchecked
- [ ] Selected count updates in real-time
- [ ] Header checkbox matches selection state (checked/indeterminate/unchecked)

### Test 2.3: Form Validation
- [ ] Submit with no students selected - error message
- [ ] Submit with no month selected - error message
- [ ] Submit with valid data - confirmation dialog appears

### Test 2.4: Generation Process
Select 2-3 students:
- [ ] Click "Generate Vouchers for Selected Students"
- [ ] Confirmation dialog appears
- [ ] Click OK
- [ ] Loading indicator displays
- [ ] Progress bar animates
- [ ] Page processes (may take 10-30 seconds)
- [ ] Success page displays
- [ ] Results table shows each student with status
- [ ] Success count is correct
- [ ] Links to history work

### Test 2.5: Communication Method Override
Test with "Send All via SMS":
- [ ] Select students with different preferences
- [ ] Choose "Send All via SMS"
- [ ] Generate vouchers
- [ ] Verify all vouchers sent via SMS (check voucher_logs)

Test with "Send All via WhatsApp":
- [ ] Repeat with WhatsApp option
- [ ] Verify all sent via WhatsApp

Test with "Respect Student Preference":
- [ ] Each student receives via their preferred method

### Test 2.6: Edge Cases
- [ ] No active students - warning message displays
- [ ] 1 student - generation works
- [ ] 50+ students - generation works, no timeout
- [ ] Student becomes inactive mid-generation - handled gracefully

---

## Test 3: Voucher History Page

### Test 3.1: Initial Load
**URL:** `/manager/voucher-history.php`

- [ ] Page loads without errors
- [ ] Voucher table displays
- [ ] Export to CSV button visible
- [ ] Send Vouchers button visible
- [ ] Filter form displays
- [ ] Results count displays

### Test 3.2: Filters
#### Date Range Filter:
- [ ] Select "From Date" only - results filtered
- [ ] Select "To Date" only - results filtered
- [ ] Select both - results within range
- [ ] Clear dates - all results shown

#### Student Search:
- [ ] Enter first name - matching results
- [ ] Enter last name - matching results
- [ ] Enter email - matching results
- [ ] Partial match works
- [ ] Clear search - all results shown

#### Status Filter:
- [ ] Select "Sent" - only sent vouchers
- [ ] Select "Failed" - only failed vouchers
- [ ] Select "Pending" - only pending vouchers
- [ ] Select "All Statuses" - all shown

#### Month Filter:
- [ ] Dropdown populated with existing months
- [ ] Select month - only that month shown
- [ ] Select "All Months" - all shown

#### Combined Filters:
- [ ] Apply multiple filters - AND logic works
- [ ] Clear Filters button resets all

### Test 3.3: Sorting
- [ ] Click "Student" header - sorts by name ASC
- [ ] Click again - sorts DESC
- [ ] Arrow indicator shows direction
- [ ] Click "Voucher Code" - sorts alphabetically
- [ ] Click "Month" - sorts by month
- [ ] Click "Status" - sorts by status
- [ ] Click "Sent Date" - sorts chronologically
- [ ] Sorting persists with filters

### Test 3.4: Pagination
Create 60+ vouchers for testing:
- [ ] First page shows 50 records
- [ ] "Next" button appears
- [ ] Click Next - page 2 displays
- [ ] "Previous" button appears
- [ ] Page numbers display correctly
- [ ] Click page number - jumps to page
- [ ] Last page shows remaining records
- [ ] Pagination respects filters

### Test 3.5: Action Buttons
- [ ] Eye icon (View Details) - goes to details page
- [ ] X icon (Revoke) - appears only for sent, active vouchers
- [ ] X icon missing for:
  - [ ] Failed vouchers
  - [ ] Pending vouchers
  - [ ] Already revoked vouchers
  - [ ] Expired vouchers (optional, depends on business logic)

### Test 3.6: Revoke Modal
- [ ] Click revoke button - modal opens
- [ ] Voucher code displayed correctly
- [ ] Reason textarea required
- [ ] Submit with empty reason - validation error
- [ ] Enter reason and submit - voucher revoked
- [ ] Success message displays
- [ ] Voucher status updates to "Revoked"
- [ ] Revoke button disappears

### Test 3.7: Icons and Badges
- [ ] SMS vouchers show chat-text icon
- [ ] WhatsApp vouchers show whatsapp icon
- [ ] Sent status - green badge
- [ ] Failed status - red badge
- [ ] Pending status - yellow badge
- [ ] Revoked status - dark badge

---

## Test 4: Voucher Details Page

### Test 4.1: Access
**URL:** `/manager/voucher-details.php?id={voucher_id}`

From history page:
- [ ] Click eye icon on a voucher
- [ ] Details page loads
- [ ] Correct voucher displayed

Direct access:
- [ ] Access with valid voucher ID - works
- [ ] Access with invalid voucher ID - error or redirect
- [ ] Access voucher from different accommodation - access denied

### Test 4.2: Voucher Information Card
- [ ] Voucher code displayed (monospace font)
- [ ] Month displayed
- [ ] Status badge correct color
- [ ] Sent via icon correct (SMS/WhatsApp)
- [ ] Sent date human-readable format
- [ ] Expiry date calculated correctly (last day of month)
- [ ] Expired indicator if past expiry
- [ ] Created date displays

### Test 4.3: QR Code Card
- [ ] QR code image loads
- [ ] Image is 250x250px
- [ ] QR code scannable with mobile device
- [ ] Download button works
- [ ] Downloaded file is PNG format
- [ ] Downloaded QR code is valid

### Test 4.4: Student Information Card
- [ ] Student name displays
- [ ] Email displays and is clickable (mailto link)
- [ ] Phone number displays
- [ ] WhatsApp number displays
- [ ] Student status badge correct

### Test 4.5: Status Timeline
For sent voucher:
- [ ] "Created" event displays
- [ ] "Sent via [method]" event displays
- [ ] Timeline line connects events
- [ ] Markers have correct colors

For revoked voucher:
- [ ] "Revoked" event displays
- [ ] Timeline shows progression

For expired voucher:
- [ ] "Expired" indicator shows

### Test 4.6: Revoked Voucher Details
Revoke a voucher first, then view details:
- [ ] Revoked alert box displays
- [ ] "Revoked At" timestamp correct
- [ ] "Revoked By" user name displays
- [ ] Revoke reason displays
- [ ] Revoke button hidden
- [ ] Status badge shows "Revoked"

### Test 4.7: Action Buttons
- [ ] "Revoke Voucher" button shows for eligible vouchers
- [ ] Button hidden for already revoked
- [ ] "Send New Voucher" button works
- [ ] "Back to History" link works
- [ ] Revoke modal functions same as history page

---

## Test 5: Revoke Functionality

### Test 5.1: Authorization
- [ ] Manager can revoke vouchers from their accommodation
- [ ] Manager cannot revoke vouchers from other accommodations
- [ ] Revoke requires reason
- [ ] Activity log entry created

### Test 5.2: Revoke Restrictions
- [ ] Can revoke: Sent voucher, is_active=1
- [ ] Cannot revoke: Failed voucher
- [ ] Cannot revoke: Pending voucher
- [ ] Cannot revoke: Already revoked voucher
- [ ] Attempting to revoke twice - error message

### Test 5.3: Revoke Process
- [ ] Click revoke button
- [ ] Modal opens
- [ ] Enter reason: "Student left accommodation"
- [ ] Click "Revoke Voucher"
- [ ] Success message displays
- [ ] Redirect to details page
- [ ] Status updated to "Revoked"
- [ ] Revoke metadata saved:
  - [ ] revoked_at timestamp
  - [ ] revoked_by user ID
  - [ ] revoke_reason text
  - [ ] is_active = 0

### Test 5.4: Activity Logging
Check activity_log table:
- [ ] Entry created with action "voucher_revoked"
- [ ] Details include voucher code and reason
- [ ] user_id matches revoker
- [ ] Timestamp accurate

---

## Test 6: CSV Export

### Test 6.1: Basic Export
From history page:
- [ ] Click "Export to CSV" button
- [ ] File downloads immediately
- [ ] Filename includes timestamp
- [ ] Format: `vouchers_export_YYYY-MM-DD_HHmmss.csv`

### Test 6.2: CSV Content
Open exported CSV:
- [ ] Headers present: Student Name, Email, Voucher Code, Month, Sent Via, Status, Sent Date, Accommodation, Active
- [ ] Data rows match filtered view
- [ ] All columns populated correctly
- [ ] UTF-8 encoding (no garbled characters)
- [ ] Opens correctly in Excel
- [ ] Commas in data properly escaped
- [ ] Line breaks in data handled

### Test 6.3: Export with Filters
Apply filters then export:
- [ ] Date range filter - only those dates in export
- [ ] Student search - only matching students
- [ ] Status filter - only that status
- [ ] Month filter - only that month
- [ ] Multiple filters - AND logic in export

### Test 6.4: Large Export
Create 100+ vouchers:
- [ ] Export completes without timeout
- [ ] All records in CSV
- [ ] No duplicate rows
- [ ] No missing rows

### Test 6.5: Status Representation
- [ ] Sent vouchers: Status = "Sent"
- [ ] Failed vouchers: Status = "Failed"
- [ ] Pending vouchers: Status = "Pending"
- [ ] Revoked vouchers: Status = "Revoked"
- [ ] Active column: "Yes" or "No"

---

## Test 7: Security & Authorization

### Test 7.1: CSRF Protection
- [ ] All forms have CSRF token
- [ ] Submit without token - rejected
- [ ] Submit with invalid token - rejected
- [ ] Submit with valid token - accepted

### Test 7.2: SQL Injection Prevention
Try SQL injection in:
- [ ] Student search field: `' OR '1'='1`
- [ ] Date fields: `'; DROP TABLE voucher_logs; --`
- [ ] Month filter: `<script>alert('xss')</script>`
- [ ] All inputs sanitized

### Test 7.3: XSS Prevention
Create voucher with malicious data:
- [ ] Student name with `<script>alert('xss')</script>`
- [ ] View in history - script not executed
- [ ] View in details - script not executed
- [ ] Export to CSV - script as plain text

### Test 7.4: Access Control
As manager A (Accommodation 1):
- [ ] Can view vouchers for Accommodation 1
- [ ] Cannot view vouchers for Accommodation 2
- [ ] Cannot revoke vouchers for Accommodation 2
- [ ] Cannot export vouchers for Accommodation 2

---

## Test 8: Edge Cases & Error Handling

### Test 8.1: No Data Scenarios
- [ ] No vouchers exist - friendly empty state
- [ ] No students match filter - empty state message
- [ ] No active students - warning with CTA
- [ ] No vouchers for month - empty dropdown option

### Test 8.2: Invalid Input
- [ ] Invalid voucher ID in URL - error or redirect
- [ ] Non-numeric voucher ID - handled
- [ ] Negative voucher ID - handled
- [ ] SQL special characters in search - sanitized

### Test 8.3: Concurrent Operations
- [ ] User A revokes voucher
- [ ] User B tries to revoke same voucher - error
- [ ] User A generates vouchers
- [ ] User B views history - new vouchers appear

### Test 8.4: Large Datasets
- [ ] 1000+ vouchers - pagination works
- [ ] 1000+ students - selection UI works
- [ ] Export 1000+ records - no timeout

---

## Test 9: Mobile Responsiveness

Test on mobile devices or browser dev tools (320px, 768px, 1024px):

### Test 9.1: Voucher History Page
- [ ] Table scrolls horizontally
- [ ] Filters stack vertically
- [ ] Buttons are touch-friendly
- [ ] Modal displays correctly
- [ ] Pagination accessible

### Test 9.2: Bulk Generation Page
- [ ] Student table scrolls
- [ ] Checkboxes large enough
- [ ] Form inputs full width
- [ ] Select All buttons accessible
- [ ] Submit button prominent

### Test 9.3: Voucher Details Page
- [ ] Two-column layout stacks on mobile
- [ ] QR code sized appropriately
- [ ] Timeline readable
- [ ] Action buttons full width

---

## Test 10: Browser Compatibility

Test in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

Check:
- [ ] Pages render correctly
- [ ] JavaScript works
- [ ] Modals function
- [ ] CSV download works
- [ ] Forms submit
- [ ] Checkboxes selectable

---

## Test 11: Performance

### Test 11.1: Page Load Times
- [ ] History page (100 vouchers): < 2 seconds
- [ ] Details page: < 1 second
- [ ] Bulk generation page: < 2 seconds
- [ ] Export (100 vouchers): < 3 seconds

### Test 11.2: Database Queries
Use EXPLAIN on:
- [ ] History page query - uses indexes
- [ ] Details page query - efficient JOINs
- [ ] No N+1 queries anywhere
- [ ] Filter queries optimized

---

## Test 12: User Experience

### Test 12.1: Visual Consistency
- [ ] Bootstrap 5 styling consistent
- [ ] Manager blue theme used
- [ ] Icons from Bootstrap Icons
- [ ] Badges use standard colors
- [ ] Forms use standard components

### Test 12.2: Feedback & Messaging
- [ ] Success messages clear
- [ ] Error messages actionable
- [ ] Loading indicators present
- [ ] Empty states helpful
- [ ] Confirmation dialogs clear

### Test 12.3: Navigation Flow
- [ ] Easy to get from dashboard to history
- [ ] Easy to get from history to details
- [ ] Easy to get back from details
- [ ] Breadcrumbs or back buttons present
- [ ] Links intuitive

---

## Post-Testing

### Cleanup:
- [ ] Remove test vouchers if needed
- [ ] Reset test data
- [ ] Check for PHP errors in logs
- [ ] Check for JavaScript console errors

### Documentation:
- [ ] Document any bugs found
- [ ] Note any UX improvements needed
- [ ] Update user documentation if needed
- [ ] Create issue tickets for bugs

### Sign-Off:
- [ ] All P0 tests passed
- [ ] All P1 tests passed
- [ ] P2 tests passed or issues documented
- [ ] Ready for production deployment

---

**Testing completed by:** _________________  
**Date:** _________________  
**Notes:** _________________
