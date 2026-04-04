# Cron Job Setup Guide for Auto-Link Script

## Overview

The `auto_link_devices.php` script needs to run daily to automatically link student devices to their WiFi vouchers. This guide covers setup for both cPanel and SSH access.

### How Phase 2 Auto-Linking Works (Phase 1 Hardening)

Auto-linking uses **exact MAC evidence only**. A device is linked automatically only when:

- The GWN voucher/device mapping returns an exact MAC address for a used voucher, **or**
- `voucher_logs.first_used_mac` already contains a previously captured exact MAC for that voucher.

If neither source provides a confirmed MAC, the voucher is queued for **manual review** — no guess-work or heuristic matching is performed. An admin/manager notification is sent so the link can be completed from the Student Details page.

> **Removed in Phase 1 hardening:** heuristic client-history lookup (scanning a ±24h window of connected clients) has been disabled. That approach could not reliably identify the correct device without the voucher code being present in client data, and has been replaced by the manual-review path for unresolved cases.

#### Phase 2 — Full Pass Every Run

Each cron run queries GWN for all current-month, **active** vouchers that have **at least one used-device signal** (i.e. `usedDeviceNum` / `usedNum` > 0, or the controller returned a MAC address for the voucher). The entire filtered set is processed on every run — there is no rotation cursor, offset, or partial-roster behaviour.

Running every 6 hours means a newly used voucher is typically linked within 6 hours of first use. A voucher that GWN still reports with a device signal will be retried on each run until a MAC is captured or the voucher is retired at month-end.

> **Behavioural note:** vouchers where `first_used_at` was recorded but GWN no longer reports a used-device count (e.g. the device disconnected and the controller cleared its count) will not re-enter Phase 2 automatically. They remain in manual-review status until resolved from the Student Details page, or until GWN reports the device signal again.

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
cd /home/joxicaxs/student.joxicraft.co.za

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

- **Common Setting:** 4 Times Per Day (0 \*/6 \* \* \*)
- **Command:**
  ```bash
  cd /home/joxicaxs/student.joxicraft.co.za && php auto_link_devices.php >> /home/joxicaxs/autolink.log 2>&1
  ```

**Alternative (Daily for lower server load):**

- **Common Setting:** Daily (0 0 \* \* \*)
- **Command:** Same as above

### Step 3: Verify

- Save the cron job
- Wait for next scheduled run (check execution times in cPanel)
- Check log file: `/home/joxicaxs/autolink.log`

---

## Setup Method 2: SSH / Terminal Access

### Step 1: Edit Crontab

```bash
# Open crontab editor
crontab -e
```

### Step 2: Add Schedule

```bash
# Every 6 hours (recommended — processes active used-device vouchers each run)
0 */6 * * * cd /home/joxicaxs/student.joxicraft.co.za && php auto_link_devices.php >> /home/joxicaxs/autolink.log 2>&1

# OR daily at midnight for lower server load
0 0 * * * cd /home/joxicaxs/student.joxicraft.co.za && php auto_link_devices.php >> /home/joxicaxs/autolink.log 2>&1
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
tail -n 50 /home/joxicaxs/autolink.log

# Watch live (if running)
tail -f /home/joxicaxs/autolink.log
```

### Expected Log Output

```
[2026-03-05 00:00:01] Auto-Link Devices - Starting (mode: LIVE)
[2026-03-05 00:00:01] --- Phase 2: First-use MAC Linking ---
[2026-03-05 00:00:01] GWN used-voucher mappings found: 23 for 2026-03
[2026-03-05 00:00:01] Vouchers to process this run: 23 (GWN-reported used-device, full pass)
[2026-03-05 00:00:04] LINKED: MAC AA:BB:CC:DD:EE:FF -> John Doe (Other, auto-detected)
[2026-03-05 00:00:07] === Summary ===
[2026-03-05 00:00:07] First-use detected: 15 | Linked: 15 | MAC retries attempted: 2 | Manual review needed: 0 | Already linked: 8 | ...
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
chmod +x /home/joxicaxs/student.joxicraft.co.za/auto_link_devices.php

# Create log directory if missing
mkdir -p /home/joxicaxs
chmod 755 /home/joxicaxs
```

### Issue: PHP Not Found in Cron

```bash
# Find PHP path
which php

# Use full path in cron
0 0 * * * cd /home/joxicaxs/student.joxicraft.co.za && /usr/bin/php auto_link_devices.php >> /home/joxicaxs/autolink.log 2>&1
```

### Issue: Script Reports Errors

```bash
# Check log for details
tail -n 100 /home/joxicaxs/autolink.log

# Test database connection
php -r "require 'includes/db.php'; var_dump(getDbConnection());"
```

---

## Log Rotation (Optional but Recommended)

Prevent logs from growing indefinitely:

### Using logrotate (SSH Access)

Create `/etc/logrotate.d/autolink`:

```
/home/joxicaxs/autolink.log {
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
0 0 * * 0 find /home/joxicaxs/autolink*.log -mtime +30 -delete
```

---

## Performance Notes

### Daily vs. Every 6 Hours

| Schedule                 | Benefit               | Trade-off                          |
| ------------------------ | --------------------- | ---------------------------------- |
| Every 6h (0 \*/6 \* \* \*) | Faster device capture — new links within 6h | 4x server executions |
| Daily (0 0 \* \* \*)     | Lower server load     | Up to 24h delay for device linking |

**Recommendation:** **Every 6 hours** — Phase 2 processes only vouchers GWN currently reports with a used-device signal, so each run is lightweight and completes quickly.

### Retry Window

If GWN reports a voucher with a used-device signal but does not yet expose a MAC address (e.g. the controller hasn't resolved the MAC), the script records `first_used_at` and queues the voucher for manual review. On each subsequent 6-hour run, if GWN still reports a device signal for that voucher, the script will attempt to capture the MAC again (within the `$retryWindowDays` window, default 7 days). This window is configurable in the script:

```php
$retryWindowDays = 7; // Increase to 14 for more aggressive retries
```

If GWN stops reporting a device signal for the voucher before the MAC is captured, the case stays in manual-review status and will not be retried automatically.

---

## Production Checklist

- [ ] Migrations applied to production database
- [ ] `php verify_cron.php` passes with no fatal issues
- [ ] Auto-link script tested with `--dry-run`
- [ ] Log directory created and writable
- [ ] Cron job added (every 6 hours: `0 */6 * * *`)
- [ ] First cron execution verified (check log after 24h)
- [ ] Email notifications configured (cPanel can email cron output)
- [ ] Log rotation set up (optional)

---

## Support

If auto-linking fails consistently:

1. Check `/home/joxicaxs/autolink.log` for errors
2. Run `php verify_cron.php` to check all prerequisites
3. Verify GWN API credentials are set correctly in production `.env`
4. Test manually: `php auto_link_devices.php --debug`
5. If GWN returns no MACs, check that vouchers were issued via the GWN group flow and `gwn_group_id` is recorded in `voucher_logs`

---

**Status:** ✅ Phase 1 hardened — auto-links on exact MAC evidence only; uncertain cases go to manual review. Phase 2 runs a full pass every 6 hours over current-month active vouchers that GWN reports with at least one used-device signal (`usedDeviceNum` / `usedNum` > 0 or MAC returned). No rotation cursor or offset is used.
**Last Updated:** 2026
