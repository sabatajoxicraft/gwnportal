# Emergency Admin Password Reset Guide

## Quick Start (cPanel Users)

### Step 1: Prepare the Script

1. Open `reset_admin_password.php` in VS Code
2. Find line 23: `define('SECURITY_TOKEN', 'CHANGE_ME_...')`
3. Change it to something unique, for example:
   ```php
   define('SECURITY_TOKEN', 'my-secret-reset-token-2026');
   ```
4. **IMPORTANT:** Remember this token - you'll need it!
5. Save the file

### Step 2: Upload via cPanel File Manager

1. Login to cPanel: `https://joxicraft.co.za:2083`
2. Go to **File Manager**
3. Navigate to: `public_html/`
4. Click **Upload** (top toolbar)
5. Select `reset_admin_password.php` from your computer
6. Wait for upload to complete

### Step 3: Run the Reset

1. Visit: `https://student.joxicraft.co.za/reset_admin_password.php`
2. You'll see a password reset form
3. Copy the security token displayed (or paste the one you set)
4. Enter your new password (minimum 8 characters)
5. Click "Reset Password"
6. ✅ Success! You should see a green confirmation message

### Step 4: Clean Up (CRITICAL!)

1. Go back to cPanel File Manager
2. Delete these files:
   - `reset_admin_password.php`
   - `reset_admin_password.php.used`
3. **DO NOT SKIP THIS STEP** - leaving the file is a major security risk!

### Step 5: Test Login

1. Go to: `https://student.joxicraft.co.za/login.php`
2. Login with:
   - Username: `admin`
   - Password: (your new password)

---

## Alternative: Upload via FTP (if you have FTP credentials)

### Using FileZilla or FTP Client

1. Open your FTP client
2. Connect to: `ftp.joxicraft.co.za` (port 21)
3. Use credentials from your hosting account
4. Navigate to: `/public_html/`
5. Upload `reset_admin_password.php`
6. Follow Steps 3-5 above

---

## Troubleshooting

### "Database connection failed"
- Check the database credentials in the script (lines 28-31)
- Default production values are already set:
  ```php
  DB_HOST: localhost
  DB_USER: joxicaxs_admin
  DB_PASS: P@55w0rd123!
  DB_NAME: joxicaxs_wifi
  ```
- If these don't work, check your production `env.production` file

### "Invalid security token"
- Make sure you changed the `SECURITY_TOKEN` in the script
- Copy the exact token shown on the page (case-sensitive)

### "User not found: admin"
- The admin username might be different
- Change line 26 in the script:
  ```php
  define('TARGET_USERNAME', 'your-actual-username');
  ```

### "This script has already been used"
- Delete `reset_admin_password.php.used` via File Manager
- Then try again

### Can't access cPanel
- Contact your hosting provider for cPanel login credentials
- Alternative: Ask them to reset your password directly

---

## Security Notes

⚠️ **CRITICAL SECURITY WARNINGS:**

1. **Change the SECURITY_TOKEN** before uploading - never use the default
2. **Delete the file** immediately after use
3. **DO NOT commit this file to git** with real credentials
4. **DO NOT leave it on the server** - it bypasses all authentication
5. The script only works once - it creates a `.used` file to prevent reuse

The script logs the password reset to `activity_log` table for audit purposes.

---

## File Locations

| File | Purpose |
|------|---------|
| `reset_admin_password.php` | Main reset script (upload to server root) |
| `reset_admin_password.php.used` | Marker file (auto-created, delete after) |

---

## Alternative Methods

If you can't use this script, try:

1. **phpMyAdmin via cPanel:**
   - Login to cPanel → phpMyAdmin
   - Select database: `joxicaxs_wifi`
   - Find `users` table
   - Edit the admin user row
   - Set password field to: `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`
   - This sets password to: `password`
   - Login and change it immediately

2. **Contact Hosting Support:**
   - They can reset MySQL passwords or provide alternative access

---

**Last Updated:** March 5, 2026
