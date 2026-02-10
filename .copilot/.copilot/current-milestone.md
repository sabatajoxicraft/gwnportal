# Current Milestone: M0 - Foundation & Planning

**Status:** In Progress  
**Started:** 2026-02-07  
**Gate:** PRD Approval

---

## M0 Tasks

### ✅ M0-T1: Capture Mandate
- ✅ Tech stack identified: Two standalone applications
  - gwn-portal: PHP 8.2 + MySQL + Bootstrap 5
  - Python CLI: Standalone tool for GWN API + Twilio
- ✅ Mandate.md updated with correct separation
- ✅ Directory renamed: web → gwn-portal

### ✅ M0-T2: Generate PRD
- ✅ PRD created at `.copilot/prd.md`
- ✅ Two standalone systems documented separately
- ✅ gwn-portal: 4 user personas (Admin, Owner, Manager, Student)
- ✅ Python CLI: Developer/admin tool features
- ✅ Success criteria established per application

### ✅ M0-T3: Architecture Documentation
- ✅ gwn-portal: Database schema documented (9 tables, RBAC)
- ✅ gwn-portal: PHP component structure mapped
- ✅ Python CLI: Standalone tool architecture documented
- ✅ Systems clarified as independent (no integration)
- ✅ Created `.copilot/architecture.md` with separation

### ✅ M0-T4: Identify Gaps & Improvements
- ✅ Security audit completed (gwn-portal only)
- ✅ **CRITICAL**: Password-less login found (gwn-portal/public/login.php:49)
- ✅ **HIGH**: No CSRF protection on forms (gwn-portal)
- ✅ **MEDIUM**: Flash message XSS risk, session timeout missing
- ✅ M1 priorities defined for gwn-portal (5 critical + 8 high/medium items)

### ✅ M0-T5: USER APPROVAL GATE ⚠️
**HUMAN TOUCHPOINT #2 - APPROVED** ✅

---

## M0.5 Phase: Validation (Adapted for Existing Project)

### ✅ M0.5-T1: Verify Current Setup
- ✅ docker-compose.yml updated (gwn-app on 3040, db on 3306)
- ✅ Removed obsolete version field
- ✅ Added phpMyAdmin service (port 3041)
- ✅ Fixed requirements.txt (removed non-existent msdrive)
- ✅ Docker containers rebuilt successfully
- ✅ gwn-portal accessible at http://localhost:3040 (HTTP 200)
- ✅ phpMyAdmin accessible at http://localhost:3041 (HTTP 200)

### ✅ M0.5-T2: Database Validation
- ✅ Database accessible (root credentials working)
- ✅ Schema initialized: 10 tables present
  - users, roles, accommodations, students
  - user_accommodation, user_devices, onboarding_codes
  - voucher_logs, notifications, activity_log
- ✅ Database name: gwn_wifi_system

### ✅ M0.5-T3: Apache Configuration Fix
- ✅ Apache DocumentRoot set to /var/www/html/public
- ✅ Apache config (000-default.conf) applied in Dockerfile.web
- ✅ gwn-app container rebuilt successfully
- ✅ Homepage accessible at http://localhost:3040/ (HTTP 200)
- ✅ Login page accessible at http://localhost:3040/login.php (HTTP 200)

### ✅ M0.5-T4: Documentation Update
- ✅ All web/ references changed to gwn-portal/
- ✅ docker-compose.yml matches running setup
- ✅ Architecture clearly separates PHP and Python apps
- ✅ Dockerfile.web includes Apache configuration

---

## ✅ M0.5 Phase COMPLETE

**All validations passed successfully!**

### Infrastructure ✅
- Docker containers: gwn-app (3040), gwn-db (3306), gwn-python, phpmyadmin (3041)
- Database: MySQL 8.0 with 10 tables initialized
- Apache DocumentRoot configured to /var/www/html/public
- Database user 'gwn_user' created with proper permissions

### Application ✅  
- gwn-portal fully accessible at http://localhost:3040
- Homepage rendering correctly with Bootstrap 5 UI
- Login page accessible at http://localhost:3040/login.php
- phpMyAdmin accessible at http://localhost:3041

### Configuration ✅
- docker-compose.yml updated (services renamed, ports corrected)
- Environment variables properly configured (DB_USER, DB_PASSWORD, DB_PASS)
- Dockerfile.web includes Apache config (000-default.conf)
- requirements.txt fixed (removed non-existent msdrive)

### Documentation ✅
- All web/ references changed to gwn-portal/
- Mandate, PRD, Architecture documents updated
- PHP and Python apps clearly documented as standalone
- Security audit completed (5 critical + 8 medium/high items identified)

---

## M1 Phase: Security Hardening (Proposed)

**Focus:** Fix critical security vulnerabilities before new features

### Critical Priorities:
1. Fix password-less login (gwn-portal/public/login.php:49)
2. Implement CSRF token system
3. Add session security (timeout, regeneration)
4. Escape flash message output
5. Add input validation layer
