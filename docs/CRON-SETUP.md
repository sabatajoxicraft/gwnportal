# Cron Job Setup Guide for Auto-Link Script

## Overview

The `auto_link_devices.php` script needs to run daily to automatically link student devices to their WiFi vouchers. This guide covers setup for both cPanel and SSH access.

### How Phase 2 Auto-Linking Works (Phase 1 Hardening)

Auto-linking uses **exact MAC evidence only**. A device is linked automatically only when:

- The GWN voucher/device mapping returns an exact MAC address for a used voucher, **or**
- `voucher_logs.first_used_mac` already contains a previously captured exact MAC for that voucher.

If neither source provides a confirmed MAC, the voucher is queued for **manual review** — no guess-work or heuristic matching is performed. An admin/manager notification is sent so the link can be completed from the Student Details page.

> **Removed in Phase 1 hardening:** heuristic client-history lookup (scanning a ±24h window of connected clients) has been disabled. That approach could not reliably identify the correct device without the voucher code being present in client data, and has been replaced by the manual-review path for unresolved cases.

---

## Production Requirements

**Migrations Required (Already Applied Locally):**

```sql
-- Run these on production if not already applied:
1. db/migrations/create_gwn_voucher_groups.sql
2. db/migrations/add_voucher_revoke_fields.sql
3. db/migrations/add_device_management.sql
```

**Preflight checks (automatic):**

`auto_link_devices.php` performs preflight checks on every run and **exits with code 2** (aborting all work) if any of the following are missing:

- `voucher_logs` columns: `gwn_group_id`, `is_active`, `revoked_at`, `revoke_reason`, `first_used_at`, `first_used_mac`
- A unique index on `user_devices.mac_address`
- At least one current-month `voucher_logs` row with `gwn_group_id` set (if used vouchers exist)

Run `php verify_cron.php` to surface these checks interactively before scheduling.

**Test on Production Before Scheduling:**

```bash
# SSH into production server
ssh user@student.joxicraft.co.za

# Navigate to web root
cd /home/joxicaxs/public_html

# Test in dry-run mode (safe, makes no changes)
php auto_link_devices.php --dry-run --debug
```

---

## Setup Method 1: cPanel Cron Jobs (Easiest)

### Step 1: Access cPanel

1. Go to `https://joxicraft.co.za:2083` (or your cPanel URL)
2. Login with your hosting credentials
3. Find "Cron Jobs" under "Advanced" section

### Step 2: Add Cron Job

**Settings:**

- **Common Setting:** Daily (0 0 \* \* \*)
- **Command:**
  ```bash
  cd /home/joxicaxs/public_html && php auto_link_devices.php >> /home/joxicaxs/logs/autolink.log 2>&1
  ```

**Alternative (Every 6 hours for faster capture):**

- **Common Setting:** 4 Times Per Day (0 _/6 _ \* \*)
- **Command:** Same as above

### Step 3: Verify

- Save the cron job
- Wait for next scheduled run (check execution times in cPanel)
- Check log file: `/home/joxicaxs/logs/autolink.log`

---

## Setup Method 2: SSH / Terminal Access

### Step 1: Edit Crontab

```bash
# Open crontab editor
crontab -e
```

### Step 2: Add Schedule

```bash
# Daily at midnight (recommended)
0 0 * * * cd /home/joxicaxs/public_html && php auto_link_devices.php >> /home/joxicaxs/logs/autolink.log 2>&1

# OR every 6 hours for faster MAC capture
0 */6 * * * cd /home/joxicaxs/public_html && php auto_link_devices.php >> /home/joxicaxs/logs/autolink.log 2>&1
```

### Step 3: Save & Exit

- For `vi/vim`: Press ESC, type `:wq`, press Enter
- For `nano`: Press Ctrl+X, then Y, then Enter

---

## Verification & Monitoring

### Check if Cron Job is Running

```bash
# View current crontab
crontab -l | grep auto_link

# Check recent executions
tail -n 50 /home/joxicaxs/logs/autolink.log

# Watch live (if running)
tail -f /home/joxicaxs/logs/autolink.log
```

### Expected Log Output

```
[2026-03-05 00:00:01] Auto-Link Devices - Starting (mode: LIVE)
[2026-03-05 00:00:01] Found 23 voucher-use mappings for 2026-03
[2026-03-05 00:00:05] ✅ Linked device AA:BB:CC:DD:EE:FF to student John Doe
[2026-03-05 00:00:08] Summary: linked=15, already_linked=8, pending=0, errors=0
```

### Manual Test Run

```bash
# Safe test (no database changes)
php auto_link_devices.php --dry-run --debug

# Live run (makes actual changes)
php auto_link_devices.php

# Monitor execution
php auto_link_devices.php --debug
```

---

## Troubleshooting

### Issue: Cron Not Running

```bash
# Check if cron service is active
sudo systemctl status cron   # Debian/Ubuntu
sudo systemctl status crond  # CentOS/RHEL

# Restart cron service
sudo systemctl restart cron
```

### Issue: Permission Errors

```bash
# Make script executable
chmod +x /home/joxicaxs/public_html/auto_link_devices.php

# Create log directory if missing
mkdir -p /home/joxicaxs/logs
chmod 755 /home/joxicaxs/logs
```

### Issue: PHP Not Found in Cron

```bash
# Find PHP path
which php

# Use full path in cron
0 0 * * * cd /home/joxicaxs/public_html && /usr/bin/php auto_link_devices.php >> /home/joxicaxs/logs/autolink.log 2>&1
```

### Issue: Script Reports Errors

```bash
# Check log for details
tail -n 100 /home/joxicaxs/logs/autolink.log

# Test database connection
php -r "require 'includes/db.php'; var_dump(getDbConnection());"
```

---

## Log Rotation (Optional but Recommended)

Prevent logs from growing indefinitely:

### Using logrotate (SSH Access)

Create `/etc/logrotate.d/autolink`:

```
/home/joxicaxs/logs/autolink.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
}
```

### Or Add to Cron (Simpler)

```bash
# Weekly cleanup (keep last 30 days)
0 0 * * 0 find /home/joxicaxs/logs/autolink*.log -mtime +30 -delete
```

---

## Performance Notes

### Daily vs. Every 6 Hours

| Schedule                 | Benefit               | Trade-off                          |
| ------------------------ | --------------------- | ---------------------------------- |
| Daily (0 0 \* \* \*)     | Lower server load     | Up to 24h delay for device linking |
| Every 6h (0 _/6 _ \* \*) | Faster device capture | 4x server executions               |

**Recommendation:** Start with **daily**, then switch to every 6 hours if you see many "pending manual link" cases.

### Retry Window

The script retries MAC capture for **7 days** after first voucher use. This window is configurable in the script (near the top of the file):

```php
$retryWindowDays = 7; // Increase to 14 for more aggressive retries
```

During the retry window, if GWN still does not return a MAC for the voucher, the case remains in manual-review status. No heuristic matching is attempted.

---

## Production Checklist

- [ ] Migrations applied to production database
- [ ] `php verify_cron.php` passes with no fatal issues
- [ ] Auto-link script tested with `--dry-run`
- [ ] Log directory created and writable
- [ ] Cron job added (daily at midnight)
- [ ] First cron execution verified (check log after 24h)
- [ ] Email notifications configured (cPanel can email cron output)
- [ ] Log rotation set up (optional)

---

## Support

If auto-linking fails consistently:

1. Check `/home/joxicaxs/logs/autolink.log` for errors
2. Run `php verify_cron.php` to check all prerequisites
3. Verify GWN API credentials are set correctly in production `.env`
4. Test manually: `php auto_link_devices.php --debug`
5. If GWN returns no MACs, check that vouchers were issued via the GWN group flow and `gwn_group_id` is recorded in `voucher_logs`

---

**Status:** ✅ Phase 1 hardened — auto-links on exact MAC evidence only; uncertain cases go to manual review
**Last Updated:** 2026
