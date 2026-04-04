# Database Table vs Migration Audit

**Generated:** March 6, 2026 · **Updated:** July 11, 2026 (removed profile checklist feature)

## Database Tables (15 total)

### Core Tables (from schema.sql - baseline)

These are the foundation tables created during initial setup:

1. **roles** - Schema baseline
2. **users** - Schema baseline
3. **accommodations** - Schema baseline
4. **user_accommodation** - Schema baseline
5. **user_devices** - Schema baseline (with migration additions)
6. **onboarding_codes** - Schema baseline (with migration additions)
7. **voucher_logs** - Schema baseline (with migration additions)
8. **students** - Schema baseline
9. **activity_log** - Schema baseline (with migration modifications)
10. **notifications** - Schema baseline (migration attempted replacement)

### Tables Created by Migrations

11. **\_migrations** - Migration tracking (auto-created)
12. **user_preferences** - ✅ `2026_01_15_100000_create_user_preferences.sql`
13. **gwn_voucher_groups** - ✅ `2026_01_16_100000_create_gwn_voucher_groups.sql`
14. **device_block_log** - ✅ `2026_02_10_100000_add_device_management.sql`
15. **~~profile_checklist~~** - ~~`2026_03_06_100000_create_profile_checklist.sql`~~ → removed by `2026_07_11_100000_remove_profile_checklist.sql`

---

## Migration Coverage Analysis

### ✅ Managed Migrations (9)

| Migration                                             | Purpose                       | Tables/Columns Affected                                                               |
| ----------------------------------------------------- | ----------------------------- | ------------------------------------------------------------------------------------- |
| `2026_01_15_100000_create_user_preferences.sql`       | User notification preferences | Creates `user_preferences`                                                            |
| `2026_01_16_100000_create_gwn_voucher_groups.sql`     | GWN Cloud voucher tracking    | Creates `gwn_voucher_groups`, adds `voucher_logs.gwn_voucher_id`                      |
| `2026_02_10_100000_add_device_management.sql`         | Device management system      | Creates `device_block_log`, adds `user_devices.linked_via`, `user_devices.is_blocked` |
| `2026_02_15_100000_add_phone_to_onboarding_codes.sql` | Phone number for codes        | Adds `onboarding_codes.phone_number`                                                  |
| `2026_02_20_100000_add_profile_photos.sql`            | User profile photos           | Adds `users.profile_photo`, creates indexes                                           |
| `2026_02_25_100000_add_voucher_revoke_fields.sql`     | Voucher revocation            | Adds `voucher_logs.revoked_at`, `voucher_logs.revoked_by`                             |
| `2026_02_28_100000_add_accommodation_details.sql`     | Extended accommodation info   | Adds multiple columns to `accommodations`                                             |
| `2026_03_05_100000_fix_activity_log_timestamp.sql`    | Fix timestamp default         | Modifies `activity_log.timestamp`                                                     |
| ~~`2026_03_06_100000_create_profile_checklist.sql`~~  | ~~Profile completion tracking~~ | ~~Creates `profile_checklist`, adds `user_preferences.checklist_widget_dismissed`~~ (superseded) |
| `2026_07_11_100000_remove_profile_checklist.sql`      | Remove profile checklist      | Drops `profile_checklist` table, drops `user_preferences.checklist_widget_dismissed`  |

### ⛔ Excluded Migrations (2)

| Migration                                          | Reason Excluded                                        | Status      |
| -------------------------------------------------- | ------------------------------------------------------ | ----------- |
| `2024_01_20_100000_add_logging_infrastructure.sql` | Renames `activity_log` → `activity_logs` (destructive) | NOT APPLIED |
| `2026_01_17_100000_create_notifications.sql`       | Drops/recreates `notifications` table (data loss)      | NOT APPLIED |

### 📋 Base Schema (schema.sql)

The following tables are part of the initial database schema:

- `roles` - User roles (admin, owner, manager, student)
- `users` - All system users
- `accommodations` - Student accommodations
- `user_accommodation` - Manager-accommodation relationships
- `user_devices` - Student device registrations
- `onboarding_codes` - Account creation codes
- `voucher_logs` - WiFi voucher history
- `students` - Student profiles
- `activity_log` - System activity tracking
- `notifications` - User notifications

---

## Analysis

### ✅ Good News

- All managed migrations are tracked and applied
- Core schema contains 10 base tables
- Migrations properly extend the base schema
- No missing migrations for existing tables
- Profile checklist feature fully removed via teardown migration

### ⚠️ Architecture Notes

1. **This is correct design**:
   - `schema.sql` = Foundation (initial deployment)
   - `migrations/` = Incremental changes (post-deployment)

2. **Base tables don't need migrations**: Tables in `schema.sql` are created during initial `setup_db.php` run

3. **Migrations extend the base**: Your migrations add new tables/columns to the existing schema

### 🔍 Recommendations

1. **For new deployments**: Run `setup_db.php` (creates base) + migration manager (applies changes)
2. **Excluded migrations**: Review if you need the changes:
   - Logging infrastructure: Requires manual table rename
   - Notifications: Requires data migration strategy
3. **Future changes**: Always create a timestamped migration instead of editing `schema.sql`

---

## Summary

✅ **15 tables in database** (profile_checklist removed)
✅ **10 base tables** (from schema.sql)  
✅ **3 active tables created by migrations** (user_preferences, gwn_voucher_groups, device_block_log)
✅ **10 migrations tracked** (9 original + teardown for profile checklist)
⛔ **2 migrations excluded** (destructive operations)

**Status**: Database structure is complete and properly tracked. No missing migrations detected.
