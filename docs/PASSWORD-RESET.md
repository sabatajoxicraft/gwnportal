# Emergency Admin Password Reset Guide

## Quick Start (phpMyAdmin Method - Easiest)

### Step 1: Access phpMyAdmin

1. Login to cPanel: `https://joxicraft.co.za:2083`
2. Find and click **phpMyAdmin** (under Databases section)
3. Select database: **`joxicaxs_wifi`** (left sidebar)

### Step 2: Open SQL Tab

1. Click the **SQL** tab at the top
2. You'll see a text area for entering SQL commands

### Step 3: Run the Reset Query

Copy and paste this query into the text area:

```sql
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE username = 'admin' 
LIMIT 1;
```

Click **Go** button at the bottom.

✅ You should see: "1 row affected" or "Query OK"

### Step 4: Test Login

1. Go to: `https://student.joxicraft.co.za/login.php`
2. Login with:
   - Username: `admin`
   - Password: `password`

### Step 5: Change Password Immediately

1. After logging in, go to Profile page
2. Set a strong new password
3. **DO NOT keep "password" as your password!**

---

## Advanced Options

### Option: Find Admin Username First

If you're not sure the username is "admin", run this query first:

```sql
SELECT id, username, email, status 
FROM users 
WHERE role_id = (SELECT id FROM roles WHERE name = 'admin');
```

This shows all admin users. Use the correct username in the UPDATE query.

### Option: Create a New Admin User

If the admin account is locked or deleted, create a new one:

```sql
INSERT INTO users (username, password, email, first_name, last_name, role_id, status, created_at, updated_at)
VALUES (
    'newadmin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin@joxicraft.co.za',
    'Super',
    'Admin',
    (SELECT id FROM roles WHERE name = 'admin' LIMIT 1),
    'active',
    NOW(),
    NOW()
);
```

Then login with:
- Username: `newadmin`  
- Password: `password`

### Option: Log the Reset (Audit Trail)

After resetting, log the action:

```sql
INSERT INTO activity_log (user_id, action, details, created_at)
SELECT id, 'password_reset', 'Password reset via phpMyAdmin', NOW()
FROM users 
WHERE username = 'admin' 
LIMIT 1;
```

---

## Troubleshooting

### "0 rows affected" when running UPDATE

**Cause:** The username doesn't match or user doesn't exist.

**Solution:** Run this to find the admin user:
```sql
SELECT id, username, email FROM users;
```
Then use the correct username in your UPDATE query.

### "Table 'users' doesn't exist"

**Cause:** Wrong database selected.

**Solution:** 
1. Look at left sidebar in phpMyAdmin
2. Click on database: `joxicaxs_wifi`
3. Verify you see tables like `users`, `students`, `voucher_logs`

### "Access denied" or "Permission denied"

**Cause:** Database user doesn't have UPDATE permission.

**Solution:** Contact your hosting provider to grant permissions on the database.

### Can't Access cPanel

**Cause:** Lost cPanel login credentials.

**Solution:** 
1. Check your hosting welcome email
2. Use "Forgot Password" on cPanel login page
3. Contact your hosting provider's support

### Password Reset Works But Still Can't Login

**Possible causes:**
1. **Wrong username** - The default is `admin`, verify in database
2. **Account disabled** - Check the `status` column: should be `active`
3. **Browser cache** - Try incognito/private browsing mode

**Fix disabled account:**
```sql
UPDATE users 
SET status = 'active'
WHERE username = 'admin';
```

---

## Understanding the Password Hash

The hash in the SQL query is:
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

This is a **bcrypt hash** of the password `password`.

### Why not store plain text?
For security! The portal uses PHP's `password_hash()` function with bcrypt algorithm. Even if someone accesses the database, they can't see actual passwords.

### Can I use a different password?
Yes! Generate a custom hash:

**Method 1: Using Command Line (if you have terminal access)**
```bash
php -r "echo password_hash('YourNewPassword123', PASSWORD_DEFAULT);"
```

**Method 2: Using Online PHP Sandbox**
1. Go to: https://onlinephp.io/ (or similar PHP sandbox)
2. Run this code:
```php
<?php
echo password_hash('YourNewPassword123', PASSWORD_DEFAULT);
?>
```
3. Copy the output hash
4. Use it in your UPDATE query instead of the default hash

---

## SQL Script File

A complete SQL script is available at: `reset_admin_password.sql`

This file contains:
- Multiple reset options
- Troubleshooting queries
- Examples for creating new admin users

You can copy queries directly from this file into phpMyAdmin.

---

## Security Notes

✅ **This method is safe because:**
- Only works if you have cPanel/phpMyAdmin access
- No files left on the server
- Password is hashed, not plain text
- Can be logged to audit trail

⚠️ **Important reminders:**
- Change "password" immediately after login
- Use a strong password (8+ characters, mix of letters/numbers/symbols)
- Don't share your admin credentials

---

## Alternative: Ask Hosting Support

If you're uncomfortable running SQL queries, contact your hosting provider:

**Request template:**
> Hi, I've lost access to my admin account on the JoxiSphere portal (student.joxicraft.co.za). Could you please reset the password for the `admin` user in the `joxicaxs_wifi` database? Or grant me temporary access to run SQL queries in phpMyAdmin?

---

**Last Updated:** March 5, 2026
