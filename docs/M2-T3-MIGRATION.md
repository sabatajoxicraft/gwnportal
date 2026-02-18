# M2-T3: Enhanced Voucher Management - Migration Guide

## Overview
This migration adds enhanced voucher management features including:
- Bulk voucher generation with student selection
- Comprehensive voucher history with filters
- Voucher details page with QR codes
- Revoke voucher functionality
- CSV export of voucher history

## Database Changes

### New Columns Added to `voucher_logs` table:
- `revoked_at` (TIMESTAMP NULL) - When the voucher was revoked
- `revoked_by` (INT NULL) - User ID who revoked the voucher
- `revoke_reason` (TEXT) - Reason for revoking
- `is_active` (BOOLEAN DEFAULT 1) - Active status flag

### Foreign Key:
- `revoked_by` references `users(id)` with ON DELETE SET NULL

## How to Apply Migration

### Option 1: Using PHP Script (Recommended)
```bash
cd C:\apps\gwn-portal
php db\migrations\apply_voucher_migration.php
```

This script will:
- Check if migration is already applied
- Apply the schema changes
- Add the foreign key constraint
- Display the updated table structure

### Option 2: Manual SQL
If Docker/MySQL is running:
```bash
docker exec -i gwn-portal-db mysql -u root -p gwn_portal < db\migrations\add_voucher_revoke_fields.sql
```

Or via MySQL command line:
```sql
SOURCE C:/apps/gwn-portal/db/migrations/add_voucher_revoke_fields.sql;
```

## New Features

### 1. Bulk Voucher Generation (`/manager/vouchers.php`)
- Select multiple students with checkboxes
- "Select All" / "Deselect All" buttons
- Choose communication method (SMS/WhatsApp or respect preference)
- Progress indicator during generation
- Success/failure summary

**Usage:**
1. Navigate to "Voucher History" → "Send Vouchers" button
2. Select month and communication method
3. Check students to send vouchers to
4. Click "Generate Vouchers for Selected Students"

### 2. Voucher History (`/manager/voucher-history.php`)
- Comprehensive filtering:
  - Date range (from/to)
  - Student search (name/email)
  - Status filter (sent/failed/pending)
  - Voucher month filter
- Sortable columns (click to sort)
- Pagination (50 per page)
- Export to CSV button
- View details and revoke actions

**Usage:**
1. Navigate to "Voucher History" in manager menu
2. Apply desired filters
3. Click column headers to sort
4. Click eye icon to view details
5. Click X icon to revoke a sent voucher

### 3. Voucher Details (`/manager/voucher-details.php`)
- Full voucher information
- QR code visualization (auto-generated)
- Student information
- Status timeline
- Revoke button (if eligible)
- Resend button

**QR Code:**
- Generated using api.qrserver.com API
- Displays voucher code as scannable QR
- Downloadable as PNG

### 4. Revoke Voucher (`/manager/revoke-voucher.php`)
- Requires reason for revoking
- CSRF protection
- Activity logging
- Cannot be undone

**Usage:**
1. From voucher history or details page
2. Click revoke button
3. Enter reason
4. Confirm revocation

### 5. CSV Export (`/manager/export-vouchers.php`)
- Exports filtered voucher data
- Includes all visible columns
- UTF-8 BOM for Excel compatibility
- Timestamped filename

**Columns:**
- Student Name
- Email
- Voucher Code
- Month
- Sent Via
- Status
- Sent Date
- Accommodation
- Active (Yes/No)

## Navigation Updates

Added "Voucher History" link to manager navigation menu:
- Icon: clock-history
- Route: `/manager/voucher-history.php`

## Security Features

1. **CSRF Protection:** All forms include CSRF tokens
2. **Authorization:** Managers can only access vouchers for their accommodation
3. **Input Validation:** All user inputs validated and sanitized
4. **Prepared Statements:** All queries use parameterized statements
5. **Activity Logging:** All voucher actions logged

## Testing Checklist

- [ ] Apply database migration successfully
- [ ] Access bulk voucher page as manager
- [ ] Select multiple students and generate vouchers
- [ ] Verify vouchers appear in history
- [ ] Apply filters and sort columns
- [ ] Export to CSV and verify data
- [ ] View voucher details with QR code
- [ ] Revoke a sent voucher
- [ ] Verify revoked voucher shows correct status
- [ ] Test with no active students
- [ ] Test with single student
- [ ] Test with 50+ students (pagination)

## Troubleshooting

### Migration fails with "column already exists"
The migration has already been applied. Run the verify script:
```bash
php db\migrations\apply_voucher_migration.php
```

### QR codes not displaying
Check Content Security Policy allows `api.qrserver.com`:
```php
// In includes/config.php
header("Content-Security-Policy: ... img-src 'self' data: https://api.qrserver.com;");
```

### Revoke button not showing
Verify:
1. Voucher status is 'sent'
2. Voucher is not already revoked (is_active = 1)
3. Voucher is not expired

### Export CSV shows garbled text
CSV uses UTF-8 BOM for Excel compatibility. If issues persist:
1. Open CSV in Notepad++
2. Convert encoding to UTF-8
3. Save and reopen in Excel

## Code Quality

- ✅ All forms have CSRF protection
- ✅ All outputs HTML-escaped
- ✅ All queries use prepared statements
- ✅ No N+1 query issues
- ✅ Proper error handling
- ✅ Activity logging
- ✅ Responsive design
- ✅ Bootstrap 5 components
- ✅ Follows existing code patterns

## Files Changed

**New Files:**
- `public/manager/vouchers.php` (bulk selection)
- `public/manager/voucher-history.php` (history with filters)
- `public/manager/voucher-details.php` (single voucher view)
- `public/manager/revoke-voucher.php` (revoke endpoint)
- `public/manager/export-vouchers.php` (CSV export)
- `db/migrations/add_voucher_revoke_fields.sql` (SQL migration)
- `db/migrations/apply_voucher_migration.php` (PHP migration script)

**Modified Files:**
- `includes/functions.php` (added revokeVoucher function)
- `includes/components/navigation.php` (added Voucher History link)
- `includes/config.php` (updated CSP for QR code API)
- `.copilot/m2-tasks.md` (marked M2-T3 as complete)

## Next Steps

After successful migration and testing:
1. Deploy to staging environment
2. Run full test suite
3. Verify mobile responsiveness
4. Document any issues in GitHub
5. Deploy to production
6. Monitor activity logs for usage patterns

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the activity_log table for error details
3. Verify database migration was applied correctly
4. Check PHP error logs for exceptions
