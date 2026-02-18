# Security Audit Checklist - EPIC 5

> Comprehensive security audit checklist covering OWASP Top 10 and PHP/MySQL best practices.

---

## OWASP Top 10 - 2021

### 1. Broken Access Control

- [ ] **Role-Based Access Control (RBAC)**
  - [ ] All admin endpoints require ROLE_ADMIN
  - [ ] All owner endpoints require ROLE_OWNER
  - [ ] All manager endpoints require ROLE_MANAGER
  - [ ] Students cannot access management functions
  - [ ] PermissionHelper::requireRole() used consistently

- [ ] **Horizontal Privilege Escalation**
  - [ ] Users cannot edit other users' profiles
  - [ ] Manager cannot access other accommodations
  - [ ] Student cannot access other students' devices
  - [ ] Verify: `PermissionHelper::canEditStudent()` validates ownership

- [ ] **Vertical Privilege Escalation**
  - [ ] Regular user cannot become admin via parameter manipulation
  - [ ] Role changes only possible via direct database updates or admin panel
  - [ ] Session role verification on every protected operation

- [ ] **Function-Level Access**
  - [ ] Verify endpoint `/api/accommodations/*/delete` only for owners
  - [ ] Verify endpoint `/api/codes/generate` only for managers
  - [ ] Verify endpoint `/api/devices/register` only for students

- [ ] **Access Control Testing**
  - [ ] Test direct URL manipulation (e.g., change user ID in URL)
  - [ ] Test API endpoint access with invalid tokens
  - [ ] Test cross-accommodation data access

### 2. Cryptographic Failures

- [ ] **Passwords**
  - [ ] All passwords hashed with bcrypt (cost factor 12+)
  - [ ] Verify: `UserService::createUser()` uses password_hash()
  - [ ] No plaintext passwords in logs
  - [ ] No default passwords

- [ ] **Database**
  - [ ] Database credentials in `.env` file (not in code)
  - [ ] Database connection uses SSL/TLS in production
  - [ ] Sensitive fields encrypted at rest (optional, check requirements)
  - [ ] `.env` file in `.gitignore`

- [ ] **Transport Security**
  - [ ] HTTPS enforced in production
  - [ ] HSTS header set (strict-transport-security)
  - [ ] Cookies marked secure and httponly
  - [ ] Session cookies have secure flag

- [ ] **Encryption**
  - [ ] Data at rest encrypted (if handling sensitive data)
  - [ ] Keys stored separately from data
  - [ ] API tokens hashed before storage
  - [ ] MAC addresses not logged in plaintext

### 3. Injection

- [ ] **SQL Injection**
  - [ ] All database queries use parameterized statements
  - [ ] Verify: `$stmt->bind_param()` used in all queries
  - [ ] No user input concatenated into SQL
  - [ ] Run SQLi scanner on all endpoints
  - [ ] Test: `'; DROP TABLE users; --` in input fields

- [ ] **Command Injection**
  - [ ] No shell_exec() or system() calls
  - [ ] If needed: escapeshellarg(), escapeshellcmd()
  - [ ] No user input in exec() calls

- [ ] **LDAP Injection** (if using LDAP)
  - [ ] Input properly escaped
  - [ ] LDAP queries parameterized

- [ ] **OS Command Injection**
  - [ ] No system/shell commands executed with user input
  - [ ] If needed: use escapeshellarg()

### 4. Insecure Design

- [ ] **Authentication Design**
  - [ ] Session timeout implemented (30 min default)
  - [ ] Session regeneration on login
  - [ ] CSRF tokens on all forms
  - [ ] Failed login attempts tracked (implement rate limiting)
  - [ ] Account lockout after N failed attempts

- [ ] **Password Reset**
  - [ ] Token expires after 1 hour
  - [ ] Token one-time use enforced
  - [ ] Token sent via email (not SMS for sensitive resets)
  - [ ] Verify token belongs to user before reset

- [ ] **Registration**
  - [ ] Email verification required (if applicable)
  - [ ] Invitation codes for closed system
  - [ ] No default accounts with known credentials

- [ ] **Rate Limiting**
  - [ ] Login endpoint rate limited
  - [ ] Password reset rate limited
  - [ ] Code generation rate limited
  - [ ] API endpoints rate limited

### 5. Security Misconfiguration

- [ ] **Server Configuration**
  - [ ] Debug mode OFF in production
  - [ ] Error reporting configured (not to users)
  - [ ] Unnecessary services disabled
  - [ ] Security headers configured
  - [ ] `.htaccess` proper: deny access to `.env`, `includes/`, etc.

- [ ] **PHP Configuration**
  - [ ] `display_errors = 0` in production
  - [ ] `error_reporting = 0` in production
  - [ ] `register_globals = OFF` (ancient but verify)
  - [ ] `file_uploads` restricted to safe directory
  - [ ] `upload_tmp_dir` outside web root

- [ ] **Database Configuration**
  - [ ] Database user has minimal required permissions
  - [ ] Remote database access disabled if not needed
  - [ ] Database backups encrypted
  - [ ] Database backups stored securely

- [ ] **Dependencies**
  - [ ] All PHP packages up to date
  - [ ] No known vulnerabilities in dependencies
  - [ ] Check with: `composer audit`
  - [ ] No test/debug code in production

### 6. Vulnerable & Outdated Components

- [ ] **PHP Version**
  - [ ] PHP 7.4 or newer
  - [ ] No deprecated functions used
  - [ ] Latest security patches applied

- [ ] **Dependencies**
  - [ ] List all dependencies: `ls includes/`
  - [ ] Verify each has no known CVEs
  - [ ] No abandoned/unmaintained packages
  - [ ] Update process documented

- [ ] **Third-Party Services**
  - [ ] Twilio API - latest version
  - [ ] GWN Cloud API - latest version
  - [ ] Bootstrap - latest version

### 7. Identification & Authentication Failures

- [ ] **Weak Credentials**
  - [ ] Password requirements enforced (8+ chars, uppercase, number, symbol)
  - [ ] Password not in common wordlist
  - [ ] Account lockout after N attempts
  - [ ] Admin accounts use strong passwords

- [ ] **Session Management**
  - [ ] Session timeout implemented and tested
  - [ ] Session tokens sufficient randomness (PHP default OK)
  - [ ] Session regeneration on privilege change
  - [ ] Secure cookies (HttpOnly, Secure, SameSite)
  - [ ] Session data encrypted (PHP default OK)

- [ ] **MFA** (if applicable)
  - [ ] SMS/Email verification implemented
  - [ ] Backup codes provided
  - [ ] Recovery process documented

### 8. Software & Data Integrity Failures

- [ ] **Source Control**
  - [ ] All code in git repository
  - [ ] `.env` not committed
  - [ ] Sensitive files ignored
  - [ ] Commit history reviewed for secrets

- [ ] **Build Process**
  - [ ] Deployments signed or verified
  - [ ] No man-in-the-middle possible
  - [ ] Deployment process documented

- [ ] **Data Integrity**
  - [ ] Checksums for important files (if applicable)
  - [ ] Tampering detected
  - [ ] Backup integrity verified

### 9. Logging & Monitoring Failures

- [ ] **Activity Logging**
  - [ ] User actions logged: login, logout, data changes
  - [ ] Administrative actions logged
  - [ ] Failed login attempts logged
  - [ ] Permission checks logged
  - [ ] Verify: `ActivityLogger` used throughout

- [ ] **Error Logging**
  - [ ] Errors logged to file and database
  - [ ] Stack traces logged (development) or abstract message (production)
  - [ ] Verify: `ErrorHandler` configured
  - [ ] Verify: `DatabaseErrorLogger` capturing errors

- [ ] **Monitoring**
  - [ ] Logs reviewed regularly (at least weekly)
  - [ ] Unresolved errors tracked as tickets
  - [ ] Security events alerted
  - [ ] Performance degradation monitored

- [ ] **Log Protection**
  - [ ] Logs not accessible to public
  - [ ] Logs owned by appropriate user
  - [ ] Logs backed up
  - [ ] Logs retained per policy (90+ days)

### 10. Server-Side Request Forgery (SSRF)

- [ ] **External Requests**
  - [ ] No unsanitized URLs in `file_get_contents()`, `curl_exec()`, etc.
  - [ ] Requests to trusted hosts only
  - [ ] No file:// or other dangerous protocols
  - [ ] Timeouts set on external requests

- [ ] **API Calls**
  - [ ] GWN API calls validated
  - [ ] Twilio API calls validated
  - [ ] Response validation implemented

---

## Additional Security Checks

### Input Validation

- [ ] **All User Inputs**
  - [ ] `$_GET` validated and escaped
  - [ ] `$_POST` validated and escaped
  - [ ] File uploads validated (type, size, content)
  - [ ] JSON inputs decoded safely

- [ ] **Output Escaping**
  - [ ] All user data escaped before output
  - [ ] `htmlspecialchars()` used: `htmlspecialchars($var, ENT_QUOTES)`
  - [ ] JSON output: `json_encode()` with proper flags
  - [ ] Database output: parameterized queries

- [ ] **Validation Functions**
  - [ ] Email validation
  - [ ] Phone number validation
  - [ ] MAC address validation
  - [ ] SA ID validation (Luhn check)
  - [ ] Verify: `FormValidator` class used

### File Security

- [ ] **File Upload Handling**
  - [ ] Upload directory outside web root
  - [ ] File type validated (check MIME, extension, magic bytes)
  - [ ] File size limited
  - [ ] Filename sanitized
  - [ ] Executable files blocked
  - [ ] Verify: `.htaccess` denies script execution in upload dir

- [ ] **File Access**
  - [ ] Only authorized users can download files
  - [ ] Direct file access prevented (use download script)
  - [ ] Verify: `Response::download()` used

### API Security

- [ ] **Authentication**
  - [ ] All endpoints require valid session or API token
  - [ ] API tokens validate user identity
  - [ ] Session validation on each request

- [ ] **Rate Limiting**
  - [ ] Endpoint `/login` rate limited
  - [ ] Endpoint `/codes/generate` rate limited
  - [ ] Endpoint `/vouchers/send` rate limited
  - [ ] Implement per IP address

- [ ] **Input/Output**
  - [ ] Input validated (see above)
  - [ ] JSON output validated
  - [ ] No sensitive data in URLs (use POST)

### CSRF Protection

- [ ] **CSRF Tokens**
  - [ ] Token generated per session
  - [ ] Token on all state-changing forms
  - [ ] Token validated before processing
  - [ ] Verify: `CSRF_PROTECT()` used
  - [ ] Token rotated after use

- [ ] **SameSite Cookies**
  - [ ] Session cookie has SameSite=Strict or SameSite=Lax
  - [ ] Verify: `session.cookie_samesite = "Lax"`

### XXE & XML

- [ ] **XML Processing**
  - [ ] If XML is processed: XXE protection enabled
  - [ ] DTD processing disabled
  - [ ] External entity loading disabled

### Secure Headers

- [ ] **Security Headers Present**
  - [ ] `X-Frame-Options: DENY` (prevent clickjacking)
  - [ ] `X-Content-Type-Options: nosniff` (prevent MIME sniffing)
  - [ ] `Content-Security-Policy` configured
  - [ ] `Referrer-Policy: strict-origin-when-cross-origin`
  - [ ] `Strict-Transport-Security` in production
  - [ ] `Permissions-Policy` configured

- [ ] **Header Verification**
  - [ ] Use Browser DevTools
  - [ ] Use `curl -I https://example.com`
  - [ ] Use Online Header Checker

### Path Traversal

- [ ] **File Operations**
  - [ ] No `../` in user-supplied paths
  - [ ] `realpath()` used to validate paths
  - [ ] Files accessed are within allowed directories
  - [ ] Test: `../../../../etc/passwd` in file parameters

### Access to Sensitive Files

- [ ] **`.env` File**
  - [ ] Not accessible via web
  - [ ] `.htaccess` denies access
  - [ ] Permissions: `644` (not readable by web server group)

- [ ] **`.git` Directory**
  - [ ] Not publicly accessible
  - [ ] `.htaccess` denies access
  - [ ] Or remove `.git` from production

- [ ] **`includes/` Directory**
  - [ ] Not directly accessible via web
  - [ ] `.htaccess` denies access
  - [ ] Direct PHP file access blocked

---

## Penetration Testing Checklist

### Broken Access

- [ ] Attempt to access `/admin/` without admin role
- [ ] Attempt to edit user ID in URL for another user
- [ ] Attempt to change role via POST parameter
- [ ] Attempt to access accommodation data from different user

### SQL Injection

- [ ] Test username field: `' OR '1'='1`
- [ ] Test username field: `admin'--`
- [ ] Test search field: `'; DROP TABLE users; --`
- [ ] Test ID parameter: `-1 UNION SELECT ...`

### XSS Injection

- [ ] Test comment field: `<script>alert('XSS')</script>`
- [ ] Test name field: `<img src=x onerror="alert('XSS')">`
- [ ] Test all text input fields

### CSRF

- [ ] Create form on external site posting to application
- [ ] Attempt to change user password
- [ ] Attempt to delete accommodation

### Authentication Bypass

- [ ] Attempt to access protected pages without login
- [ ] Attempt to access with invalid session ID
- [ ] Attempt to modify session cookie
- [ ] Attempt to use expired session

### File Upload

- [ ] Upload executable file (`.php`, `.phtml`)
- [ ] Upload oversized file
- [ ] Upload file with double extension (`.php.jpg`)
- [ ] Access uploaded file directly

### Timing Attacks

- [ ] Measure login time with valid vs invalid username
- [ ] Measure response time for existing vs non-existing data

---

## Compliance Checks

- [ ] **GDPR** (if EU users)
  - [ ] User consent for data collection
  - [ ] Data deletion capability
  - [ ] Privacy policy available
  - [ ] Data breach notification plan

- [ ] **Data Protection Act** (if South Africa)
  - [ ] Personal information protected
  - [ ] Data processing documented
  - [ ] Purpose limitation enforced

- [ ] **PCI DSS** (if handling payments)
  - [ ] Credit card data not stored
  - [ ] Use external payment processor
  - [ ] SSL/TLS enforced

---

## Security Sign-Off

- [ ] All OWASP checks completed: **DATE: ****\_\_******
- [ ] Penetration testing completed: **DATE: ****\_\_******
- [ ] Security issues found: ******\_\_****** (count)
- [ ] Critical issues resolved: **YES / NO**
- [ ] High issues resolved: **YES / NO**
- [ ] Medium issues documented: **YES / NO**
- [ ] Auditor Name: **************\_**************
- [ ] Auditor Signature: ************\_\_\_\_************

---

## Remediation Tracking

Create tickets for all security issues found:

| Issue | Severity | Status | Assigned | Due Date |
| ----- | -------- | ------ | -------- | -------- |
|       |          |        |          |          |
|       |          |        |          |          |
|       |          |        |          |          |

---

**Review this checklist before each release. Document all findings and verify fixes.**
