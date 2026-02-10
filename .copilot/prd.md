# Product Requirements Document (PRD)
# GWN WiFi Portal

**Version:** 1.0  
**Date:** February 9, 2026  
**Status:** M0 - Foundation Phase  
**Tech Stack:** PHP 8.2, MySQL 8.0, Bootstrap 5, Apache/Docker  

---

## M0-T1: PRD - Problem Statement & Scope

### Problem Statement

Student accommodation owners and managers in South Africa face significant challenges managing WiFi access for their tenants:

1. **Manual Voucher Distribution**: Accommodation staff manually send WiFi vouchers each month via SMS/WhatsApp, leading to errors and missed deliveries
2. **No Centralized Management**: No unified system to track which students have received vouchers, their device registrations, or accommodation assignments
3. **Access Control Issues**: Lack of role-based access control means all staff have the same permissions, creating security and accountability gaps
4. **Multi-Property Challenges**: Owners managing multiple accommodations struggle to delegate management effectively while maintaining oversight
5. **Student Onboarding Friction**: New students face cumbersome manual registration processes, requiring staff intervention for basic account setup

### Target Users

**Primary Users:**
1. **Accommodation Owners** - Manage multiple properties, create accommodations, assign managers, oversee operations
2. **Accommodation Managers** - Manage day-to-day operations for assigned properties, handle student registrations, send vouchers
3. **Students** - Receive WiFi vouchers, manage device registrations, view their accommodation details

**Secondary Users:**
4. **System Administrators** - Platform maintenance, user support, system configuration, global oversight

**Geographic Focus:** South Africa (Kimberley region initially)

### Core Features (MVP Scope)

#### 1. Authentication & Authorization
- Role-based access control (RBAC): Admin, Owner, Manager, Student
- Secure login with password hashing (bcrypt)
- Session management with PHP sessions
- Password reset functionality
- First-login password change enforcement

#### 2. Accommodation Management
- Create, edit, view accommodations
- Multi-accommodation support for owners
- Manager-to-accommodation assignment
- Accommodation owner assignment tracking
- Room number assignments for students

#### 3. User Management
- User creation with role assignment
- Onboarding code system (7-day expiry, single-use)
- Self-service registration via onboarding codes
- User profile updates (contact info, preferences)
- South African ID number validation
- Device registration (MAC address tracking)

#### 4. Voucher Distribution System
- Bulk voucher sending to students
- SMS and WhatsApp delivery options
- Student communication preferences
- Voucher log tracking (sent/failed/pending status)
- Monthly voucher generation
- Integration with GWN WiFi Manager API (via Python CLI)

#### 5. Student Management
- Student listing by accommodation
- Student status tracking (active/pending/inactive)
- Room assignment management
- Student detail views
- Device registration tracking
- Export student data to Excel

#### 6. Dashboard & Reporting
- Role-specific dashboards (Admin, Owner, Manager, Student)
- Quick stats (total students, accommodations, vouchers sent)
- Activity logging
- Recent activity views

#### 7. Notification System
- In-app notifications
- Flash messages for user actions
- Notification read/unread status
- Cross-user notification delivery

### Out of Scope (Post-MVP)

**Phase 2+ Features:**
- Payment/billing integration
- Advanced analytics and reporting dashboards
- Mobile native apps (iOS/Android)
- Student portal app features (WiFi speed testing, support tickets)
- Multi-language support
- Advanced audit trails
- Two-factor authentication (2FA)
- Email notification system
- Real-time WebSocket notifications
- API rate limiting and throttling
- GraphQL API
- Automated voucher generation scheduling
- Student self-service device management portal
- Manager performance analytics
- Tenant satisfaction surveys

**Explicitly Not Included:**
- Direct WiFi controller management (delegated to gwn-python-cli)
- Network infrastructure monitoring
- Router configuration management
- Bandwidth usage analytics
- Payment gateway integration

### Success Criteria

**MVP Launch Criteria (M1):**
1. ✅ **Functional Completeness**: All core features operational
2. 🔒 **Security**: RBAC fully implemented, no SQL injection vulnerabilities, passwords hashed
3. 📱 **Voucher Delivery**: 95%+ success rate on SMS/WhatsApp voucher delivery
4. 👥 **User Adoption**: 100 active students across 4 accommodations
5. ⚡ **Performance**: Page load times <2 seconds on standard connection
6. 🐛 **Stability**: Zero critical bugs, <5 minor bugs post-deployment

**Post-Launch KPIs (3 months):**
- Student satisfaction: >80% positive feedback
- Voucher delivery success rate: >95%
- Manager time saved: 50% reduction in manual voucher distribution time
- System uptime: >99.5%

### Initial Milestones

#### **M0: Foundation (Current Phase)** ✅
- [x] M0-T1: PRD generation
- [x] M0-T2: Component architecture definition
- [x] M0-T3: Folder structure documentation
- [x] M0-T4: API/Data strategy
- [ ] M0-T5: PRD review & approval

#### **M1: Core Infrastructure (Weeks 1-2)**
- Database schema validation & migration scripts
- Authentication system hardening (session security, CSRF protection)
- RBAC permission enforcement audit
- Error handling & logging standardization

#### **M2: Feature Completion (Weeks 3-4)**
- Voucher distribution workflow optimization
- Student management enhancements
- Dashboard analytics integration
- Notification system refinement

#### **M3: Testing & Hardening (Week 5)**
- Security audit (OWASP Top 10 compliance)
- Load testing (100 concurrent users)
- Browser compatibility testing (Chrome, Firefox, Safari, Edge)
- Mobile responsiveness validation

#### **M4: Deployment & Launch (Week 6)**
- Production environment setup
- Database migration & seed data
- User training documentation
- Go-live checklist completion

---

## M0-T2: Component Architecture (PHP/Bootstrap)

### PHP Page Structure

The application follows a **template-based architecture** with reusable components:

#### 1. Entry Point Structure
```
public/{page}.php
├── Session validation
├── Permission checks
├── Business logic & data fetching
├── Page-specific processing
└── Include layout.php (renders template)
```

#### 2. Layout System (`includes/layout.php`)

**Responsibilities:**
- Consistent HTML structure across all pages
- Header, navigation, footer injection
- Role-based navbar styling
- Bootstrap 5 CSS/JS loading
- Flash message rendering

**Usage Pattern:**
```php
// In any page (e.g., dashboard.php)
$pageTitle = "Dashboard";
$activePage = "dashboard";
$pageContent = "HTML content here";
require_once '../includes/layout.php';
```

#### 3. Component Library (`includes/components/`)

**Reusable Components:**
- `header.php` - App branding, user profile dropdown
- `navigation.php` - Role-based navigation menu
- `footer.php` - Copyright, version info
- `messages.php` - Flash message display
- `accommodation-switcher.php` - Multi-accommodation selector for managers

**Component Pattern:**
```php
// Each component is self-contained
<?php
// Component logic
$userData = getUserData($_SESSION['user_id']);
?>
<!-- Component markup -->
<nav class="navbar navbar-expand-lg">
    <!-- Bootstrap 5 structure -->
</nav>
```

### Bootstrap 5 Component Strategy

#### 1. Design System

**Color Palette (Role-Based):**
- **Admin**: Deep Purple (`#6f42c1`) - Full control indication
- **Owner**: Teal (`#20c997`) - Business/ownership
- **Manager**: Blue (`#0d6efd`) - Operational management
- **Student**: Orange (`#fd7e14`) - User/consumer level

**Typography:**
- Primary Font: Nunito (Google Fonts)
- Font Weights: 300 (light), 400 (regular), 600 (semi-bold), 700 (bold)

#### 2. Common Bootstrap Components Used

| Component | Usage | Example Pages |
|-----------|-------|---------------|
| **Cards** | Data containers, student lists, accommodation details | `dashboard.php`, `students.php` |
| **Tables** | Student lists, voucher logs, activity logs | `students.php`, `admin/activity-log.php` |
| **Forms** | User input, filters, create/edit operations | `create-accommodation.php`, `profile.php` |
| **Modals** | Confirmations, detail views, quick actions | `send-vouchers.php`, `student-details.php` |
| **Badges** | Status indicators (active/pending/inactive) | All list views |
| **Buttons** | Actions (primary/secondary/danger hierarchy) | All pages |
| **Alerts** | Flash messages, form validation errors | Via `displayFlashMessage()` |
| **Dropdowns** | User menu, bulk actions | `navigation.php` |
| **Breadcrumbs** | Navigation hierarchy | Admin section pages |

#### 3. Responsive Design Strategy

**Breakpoints:**
- Mobile: <768px (stacked layouts, hamburger menu)
- Tablet: 768px-991px (2-column grids)
- Desktop: ≥992px (full layouts, sidebars)

**Mobile-First Approach:**
- Tables convert to card layouts on mobile
- Navigation collapses to hamburger menu
- Forms use full-width inputs on small screens

### Reusable Template Patterns

#### Pattern 1: List View with Actions
```php
// File: students.php
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2>Students</h2>
        </div>
        <div class="col text-end">
            <a href="create-student.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Student
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <!-- Table content -->
            </table>
        </div>
    </div>
</div>
```

#### Pattern 2: Form with Validation
```php
// File: create-accommodation.php
<div class="container mt-4">
    <div class="card">
        <div class="card-header">Create Accommodation</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Create</button>
            </form>
        </div>
    </div>
</div>
```

#### Pattern 3: Dashboard Stats Cards
```php
// File: dashboard.php
<div class="row g-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Total Students</h5>
                <h2><?= $studentCount ?></h2>
            </div>
        </div>
    </div>
    <!-- More stat cards -->
</div>
```

### RBAC Implementation Approach

#### 1. Role Definition (`db/schema.sql`)

**Roles Table:**
- `1` - Admin (System-level access)
- `2` - Owner (Multi-accommodation management)
- `3` - Manager (Single accommodation operations)
- `4` - Student (Self-service portal)

#### 2. Permission Check Functions (`includes/permissions.php`)

**Currently Implemented (Assumed - file was empty in view):**
```php
function requireRole($allowedRoles) {
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        redirect('/login.php', 'Access denied', 'danger');
    }
}

function canManageAccommodation($accommodationId) {
    $role = $_SESSION['user_role'];
    if ($role === 'admin') return true;
    if ($role === 'owner') {
        // Check if owner owns this accommodation
        return checkOwnership($accommodationId);
    }
    if ($role === 'manager') {
        // Check if manager is assigned to this accommodation
        return $_SESSION['accommodation_id'] == $accommodationId;
    }
    return false;
}
```

#### 3. Page-Level Permission Enforcement

**Pattern:**
```php
// At top of each protected page
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('/login.php', 'Please login', 'warning');
}

requireRole(['admin', 'manager']); // Only admin/manager can access
```

#### 4. Navigation Menu Control

**Role-Based Menu Items:**
```php
// In navigation.php
<?php if (in_array($userRole, ['admin', 'owner'])): ?>
    <li class="nav-item">
        <a href="/accommodations.php">Accommodations</a>
    </li>
<?php endif; ?>

<?php if ($userRole === 'manager'): ?>
    <li class="nav-item">
        <a href="/students.php">Students</a>
    </li>
<?php endif; ?>
```

#### 5. Data Isolation Strategy

**Owner Isolation:**
- Owners only see accommodations they own (`accommodations.owner_id = user_id`)
- Student lists filtered by owned accommodations

**Manager Isolation:**
- Managers only see assigned accommodation (`user_accommodation` table join)
- Single accommodation scope enforced via `$_SESSION['accommodation_id']`

**Student Isolation:**
- Students only see their own data (`students.user_id = $_SESSION['user_id']`)
- Cannot access other students' voucher logs or devices

---

## M0-T3: Folder Structure

### Current Directory Structure

```
gwn-portal/
├── .copilot/                    # Project management artifacts
│   └── prd.md                   # This document
│
├── .github/                     # GitHub Actions CI/CD (future)
│
├── db/
│   └── schema.sql               # MySQL database schema with seed data
│
├── docs/                        # Project documentation
│
├── includes/                    # PHP backend logic & components
│   ├── components/              # Reusable UI components
│   │   ├── accommodation-switcher.php
│   │   ├── footer.php
│   │   ├── header.php
│   │   ├── messages.php
│   │   └── navigation.php
│   │
│   ├── accommodation-handler.php # Manager accommodation assignment logic
│   ├── config.php               # Environment config, constants, DB settings
│   ├── db.php                   # Database connection handler
│   ├── ensure_complete_html.php # HTML validation utility
│   ├── functions.php            # Utility functions (code gen, phone formatting)
│   ├── layout.php               # Master page template
│   ├── permissions.php          # RBAC permission checks (stub)
│   └── python_interface.php     # GWN WiFi API integration via Python CLI
│
├── public/                      # Web-accessible files (document root)
│   ├── admin/                   # Admin-only pages
│   │   ├── accommodations.php
│   │   ├── activity-log.php
│   │   ├── codes.php
│   │   ├── create-accommodation.php
│   │   ├── create-code.php
│   │   ├── create-owner.php
│   │   ├── create-user.php
│   │   ├── dashboard.php
│   │   ├── edit-accommodation.php
│   │   ├── edit-user.php
│   │   ├── reports.php
│   │   ├── settings.php
│   │   ├── system-backup.php
│   │   ├── users.php
│   │   ├── view-accommodation.php
│   │   └── view-user.php
│   │
│   ├── manager/                 # Manager-only pages
│   │   └── edit_student.php
│   │
│   ├── accommodations/          # (Purpose unclear - may be data directory)
│   │
│   ├── assets/                  # Static resources
│   │   ├── css/
│   │   │   └── custom.css       # Custom styles, role-based theming
│   │   ├── img/                 # Images, logos
│   │   └── js/
│   │       └── custom.js        # Client-side JavaScript
│   │
│   ├── accommodations.php       # Owner/Manager accommodation list view
│   ├── codes.php                # Onboarding code management
│   ├── contact.php              # Contact/support page
│   ├── create-accommodation.php # Create new accommodation
│   ├── create-code.php          # Generate onboarding code
│   ├── dashboard.php            # Main dashboard (role-based redirect)
│   ├── edit-accommodation.php   # Edit accommodation details
│   ├── export-students.php      # Export student data to Excel
│   ├── help.php                 # Help/documentation page
│   ├── icon-test.php            # Bootstrap icons test page (dev tool)
│   ├── index.php                # Landing page (redirects to login/dashboard)
│   ├── login.php                # Authentication page
│   ├── logout.php               # Session termination
│   ├── manager-setup.php        # Manager initial accommodation assignment
│   ├── managers.php             # Manager list/assignment view
│   ├── onboard.php              # Self-service registration via code
│   ├── owner-setup.php          # Owner initial accommodation creation
│   ├── profile.php              # User profile management
│   ├── reset_password.php       # Password reset functionality
│   ├── send-voucher.php         # Send single voucher
│   ├── send-vouchers.php        # Bulk voucher distribution
│   ├── student-details.php      # Individual student detail view
│   ├── students.php             # Student list view
│   ├── update_details.php       # Update user details
│   └── view-accommodation.php   # Accommodation detail view
│
├── uploads/                     # User-uploaded files (future use)
│
├── .dockerignore                # Docker build exclusions
├── .env                         # Environment variables (not in git)
├── .gitignore                   # Git exclusions
├── 000-default.conf             # Apache virtual host config
├── admin_credentials.txt        # Default admin credentials (dev only)
├── docker-compose.yml           # Docker orchestration config
├── Dockerfile                   # PHP 8.2 + Apache container definition
├── env.example                  # Environment variable template
├── notifications.php            # Notification display page
├── README.md                    # Project documentation
└── setup_db.php                 # Database initialization script
```

### Structure Analysis & Improvements

#### ✅ Strengths
1. **Clear Separation**: `includes/` (backend) vs `public/` (frontend)
2. **Role-Based Folders**: `admin/`, `manager/` subdirectories enforce access
3. **Component System**: Reusable UI components in `includes/components/`
4. **Docker-Ready**: Containerized with MySQL 8.0 and PHP 8.2

#### ⚠️ Areas for Improvement

**1. Duplicate Pages** (Owner vs Admin views)
- **Issue**: `public/create-accommodation.php` AND `public/admin/create-accommodation.php` exist
- **Fix**: Consolidate to single page with role checks, or clearly differentiate purpose

**2. Missing Folder: `includes/models/`**
- **Recommendation**: Create data access layer for clean separation
- **Example**:
  ```
  includes/models/
  ├── User.php
  ├── Accommodation.php
  ├── Student.php
  └── Voucher.php
  ```

**3. Missing Folder: `includes/controllers/`**
- **Recommendation**: Move business logic out of page files
- **Example**: `includes/controllers/VoucherController.php` handles bulk sending logic

**4. Inconsistent Naming**
- **Issue**: `edit_accommodation.php` vs `edit-accommodation.php` (underscore vs hyphen)
- **Fix**: Standardize on hyphens for all PHP files

**5. `public/accommodations/` Directory**
- **Issue**: Purpose unclear, may conflict with `accommodations.php` file
- **Action**: Verify purpose or remove if unused

**6. Development Files in Production**
- **Issue**: `admin_credentials.txt`, `icon-test.php` should not deploy to production
- **Fix**: Add to `.dockerignore` and exclude from production builds

### Recommended Future Structure (Post-MVP)

```
includes/
├── components/       # UI components
├── controllers/      # Business logic
├── models/          # Data access layer
├── middleware/      # Request processing (auth, CSRF, logging)
├── validators/      # Input validation classes
└── config.php       # Entry point
```

---

## M0-T4: API/Data Strategy

### MySQL Schema Design

#### 1. Core Tables

**`roles`** - User role definitions
- **Purpose**: RBAC role storage
- **Key Fields**: `id`, `name`, `description`
- **Relationships**: 1:M with `users`
- **Indexes**: PRIMARY KEY on `id`, UNIQUE on `name`

**`users`** - Unified user table (all roles)
- **Purpose**: Central authentication & user data
- **Key Fields**: `id`, `username`, `password`, `email`, `first_name`, `last_name`, `role_id`, `status`
- **South African Fields**: `id_number` (13-digit SA ID), `phone_number`, `whatsapp_number`
- **Security**: `password_reset_required` flag for first-login enforcement
- **Relationships**: M:1 with `roles`, 1:M with `students`, 1:M with `accommodations` (as owner)
- **Indexes**: PRIMARY KEY on `id`, UNIQUE on `username`, UNIQUE on `id_number`, INDEX on `role_id`

**`accommodations`** - Properties/venues
- **Purpose**: Property management
- **Key Fields**: `id`, `name`, `owner_id`
- **Relationships**: M:1 with `users` (owner), M:M with `users` (managers via junction), 1:M with `students`
- **Indexes**: PRIMARY KEY on `id`, INDEX on `owner_id`

**`user_accommodation`** - Manager assignments (junction table)
- **Purpose**: M:M relationship between managers and accommodations
- **Key Fields**: `user_id`, `accommodation_id`
- **Relationships**: M:1 with `users`, M:1 with `accommodations`
- **Indexes**: COMPOSITE PRIMARY KEY on (`user_id`, `accommodation_id`)

**`students`** - Student-specific data
- **Purpose**: Extend `users` table for student role
- **Key Fields**: `id`, `user_id`, `accommodation_id`, `room_number`, `status`
- **Relationships**: 1:1 with `users`, M:1 with `accommodations`
- **Indexes**: PRIMARY KEY on `id`, UNIQUE on `user_id`, INDEX on `accommodation_id`
- **Note**: Student status separate from user status (user=login access, student=housing status)

**`onboarding_codes`** - Self-service registration codes
- **Purpose**: Single-use, time-limited registration links
- **Key Fields**: `code`, `created_by`, `accommodation_id`, `used_by`, `status`, `role_id`, `expires_at`
- **Security**: 7-day expiry (configurable), single-use enforcement
- **Relationships**: M:1 with `users` (creator, user), M:1 with `accommodations`, M:1 with `roles`
- **Indexes**: PRIMARY KEY on `id`, UNIQUE on `code`, INDEX on `accommodation_id`

**`voucher_logs`** - Voucher distribution tracking
- **Purpose**: Audit trail for WiFi voucher delivery
- **Key Fields**: `user_id`, `voucher_code`, `voucher_month`, `sent_via`, `status`, `sent_at`
- **Relationships**: M:1 with `users`
- **Indexes**: PRIMARY KEY on `id`, INDEX on `user_id`, INDEX on `sent_at`

**`user_devices`** - Student device registration
- **Purpose**: MAC address tracking for WiFi access control
- **Key Fields**: `id`, `user_id`, `device_type`, `mac_address`
- **Validation**: MAC address format: `XX:XX:XX:XX:XX:XX` (enforced in PHP)
- **Relationships**: M:1 with `users`
- **Indexes**: PRIMARY KEY on `id`, INDEX on `user_id`

**`notifications`** - In-app messaging
- **Purpose**: Cross-user notifications (e.g., manager → student)
- **Key Fields**: `recipient_id`, `sender_id`, `message`, `type`, `read_status`
- **Relationships**: M:1 with `users` (recipient, sender)
- **Indexes**: PRIMARY KEY on `id`, INDEX on `recipient_id`, INDEX on `created_at`

**`activity_log`** - Audit trail
- **Purpose**: Track user actions for compliance & debugging
- **Key Fields**: `user_id`, `action`, `details`, `ip_address`, `timestamp`
- **Indexes**: PRIMARY KEY on `id`, INDEX on `user_id`, INDEX on `timestamp`

#### 2. Data Integrity Rules

**Foreign Key Constraints:**
- `ON DELETE CASCADE`: `accommodations.owner_id`, `students.user_id`, `voucher_logs.user_id`
- `ON DELETE SET NULL`: `onboarding_codes.used_by` (preserve code history if user deleted)

**ENUMs:**
- `users.status`: `active`, `pending`, `inactive`
- `users.preferred_communication`: `SMS`, `WhatsApp`
- `students.status`: `active`, `pending`, `inactive`
- `onboarding_codes.status`: `unused`, `used`, `expired`
- `voucher_logs.status`: `sent`, `failed`, `pending`
- `voucher_logs.sent_via`: `SMS`, `WhatsApp`

**Validation Rules (Application-Level):**
- SA ID Number: Exactly 13 digits
- Phone Numbers: +27 format (South Africa)
- MAC Address: `XX:XX:XX:XX:XX:XX` format
- Username: 3-50 characters, alphanumeric + underscore
- Email: Valid email format

### Session Management

#### 1. PHP Session Configuration (`includes/config.php`)

**Current Implementation:**
```php
session_start();
$_SESSION['user_id']          // User ID
$_SESSION['user_role']        // Role name (admin/owner/manager/student)
$_SESSION['username']         // Username for display
$_SESSION['accommodation_id'] // For managers (single accommodation scope)
$_SESSION['flash']            // Temporary messages (auto-cleared)
```

**Session Security Measures:**
- `session.cookie_httponly = 1` (prevent XSS access to cookies)
- `session.cookie_secure = 1` (HTTPS only - production)
- `session.use_strict_mode = 1` (reject uninitialized session IDs)
- Session timeout: 30 minutes idle (recommended - not yet implemented)

#### 2. Authentication Flow

**Login (`public/login.php`):**
1. User submits credentials
2. Query `users` table for `username`
3. Verify password with `password_verify()` against bcrypt hash
4. Check `status = 'active'`
5. Set session variables
6. If `password_reset_required = 1`, redirect to password change
7. Redirect to role-appropriate dashboard

**Logout (`public/logout.php`):**
1. `session_destroy()`
2. Clear all session variables
3. Redirect to login page

**Session Validation (Every Page):**
```php
if (!isLoggedIn()) {
    redirect('/login.php', 'Please login', 'warning');
}
```

#### 3. Accommodation Context Handling

**Manager Accommodation Assignment:**
- On login, manager's assigned accommodation loaded into `$_SESSION['accommodation_id']`
- If no assignment exists, redirect to `manager-setup.php`
- All manager queries filtered by `accommodation_id`

**Owner Multi-Accommodation:**
- No session filtering (owners can view all owned accommodations)
- Accommodation switching dropdown (future enhancement)

### API Endpoints (Current State)

**Note:** The application currently uses **page-based architecture** (traditional PHP), NOT REST API endpoints. All interactions are form submissions with full page reloads.

#### 1. Form Submission Endpoints (POST Handlers)

**User Management:**
- `POST /login.php` - Authentication
- `POST /onboard.php` - Self-service registration
- `POST /profile.php` - Update user profile
- `POST /reset_password.php` - Password reset
- `POST /admin/create-user.php` - Admin user creation

**Accommodation Management:**
- `POST /create-accommodation.php` - Create new property
- `POST /edit-accommodation.php` - Update property details
- `POST /admin/create-accommodation.php` - Admin accommodation creation

**Voucher Distribution:**
- `POST /send-voucher.php` - Send single voucher
- `POST /send-vouchers.php` - Bulk voucher distribution

**Student Management:**
- `POST /manager/edit_student.php` - Update student details

#### 2. Python CLI Integration (`includes/python_interface.php`)

**Purpose:** Bridge PHP application to GWN WiFi Manager API via Python scripts

**Functions:**
- `executePythonCommand($command, $params)` - Generic CLI executor
- `sendStudentVoucher($student_id, $month)` - Voucher generation & delivery

**External Dependencies:**
- `gwn-python-cli` project (separate repository, not included)
- Python 3.x runtime
- Twilio API (SMS/WhatsApp delivery)
- GWN Manager API credentials

**Voucher Workflow:**
```
PHP Request → python_interface.php → gwn-python-cli → GWN API → Twilio → Student
                                                                   ↓
                                                           voucher_logs table
```

#### 3. Future API Strategy (Post-MVP)

**Recommendation: RESTful API Layer**
```
/api/v1/
├── auth/
│   ├── POST /login
│   ├── POST /logout
│   └── POST /refresh
├── accommodations/
│   ├── GET    /accommodations
│   ├── POST   /accommodations
│   ├── GET    /accommodations/{id}
│   ├── PUT    /accommodations/{id}
│   └── DELETE /accommodations/{id}
├── students/
│   ├── GET    /students
│   ├── POST   /students
│   ├── GET    /students/{id}
│   └── PUT    /students/{id}
└── vouchers/
    ├── POST   /vouchers/send-bulk
    └── GET    /vouchers/logs
```

**Benefits:**
- Mobile app support (future native apps)
- Third-party integrations
- Frontend framework migration (React/Vue)
- API versioning support

### Data Validation Approach

#### 1. Server-Side Validation (Primary)

**Current Pattern:**
```php
// In form processing pages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // Validation
    if (empty($name)) {
        redirect('back', 'Name is required', 'danger');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('back', 'Invalid email', 'danger');
    }
    
    // Process...
}
```

**Validation Functions (`includes/functions.php`):**
- `formatPhoneNumber($number)` - Convert to +27 format
- `formatMacAddress($mac)` - Normalize to `XX:XX:XX:XX:XX:XX`
- `generateOnboardingCode($length)` - Secure random code generation

#### 2. Database-Level Validation

**Constraints:**
- `UNIQUE` constraints on `users.username`, `users.id_number`, `onboarding_codes.code`
- `NOT NULL` constraints on critical fields
- `ENUM` validation on status fields
- `FOREIGN KEY` constraints for referential integrity

#### 3. Client-Side Validation (Upcoming)

**HTML5 Attributes:**
- `required` - Mandatory fields
- `type="email"` - Email format validation
- `pattern` - Regex validation (e.g., phone numbers)
- `maxlength` - Length restrictions

**JavaScript Validation (Future):**
- Real-time field validation feedback
- MAC address format validation
- Phone number format checking
- Password strength meter

#### 4. Security Validation

**SQL Injection Prevention:**
- ✅ Prepared statements (`mysqli::prepare()`) used throughout
- ✅ Parameter binding (`bind_param()`)

**XSS Prevention:**
- ✅ Output escaping with `htmlspecialchars()` in templates
- ⚠️ **TODO**: Implement Content Security Policy (CSP) headers

**CSRF Protection:**
- ❌ **NOT IMPLEMENTED** - Critical security gap
- **TODO**: Add CSRF tokens to all forms

**Password Security:**
- ✅ bcrypt hashing (`password_hash()`)
- ✅ Secure password verification (`password_verify()`)
- ⚠️ **TODO**: Enforce password complexity rules (8+ chars, mixed case, numbers)

---

## Technical Debt & Next Steps

### Immediate Actions (M1 Priority)

1. **Implement `includes/permissions.php`** (currently empty)
   - `requireRole($allowedRoles)` function
   - `canManageAccommodation($id)` function
   - `canViewStudent($id)` function

2. **CSRF Protection**
   - Generate tokens for all forms
   - Validate tokens on POST requests

3. **Consolidate Duplicate Pages**
   - Merge `public/create-accommodation.php` with `public/admin/create-accommodation.php`
   - Clarify purpose or remove `public/accommodations/` directory

4. **Session Security Hardening**
   - Implement 30-minute idle timeout
   - Add session regeneration on privilege escalation
   - Enable `session.cookie_secure` in production

5. **Error Logging**
   - Disable `display_errors` in production
   - Implement centralized error logging

### Code Quality Improvements (M2)

1. **Create Model Layer** (`includes/models/`)
2. **Extract Business Logic** to controllers (`includes/controllers/`)
3. **Add Input Validation Classes** (`includes/validators/`)
4. **Implement Middleware** for auth/CSRF/logging (`includes/middleware/`)
5. **Add PHPUnit Tests** for critical functions

### Documentation Needs (M3)

1. **API Documentation** (current form endpoints)
2. **Deployment Guide** (Docker + production setup)
3. **User Manual** (role-specific guides)
4. **Database Migration Scripts** (version control for schema changes)

---

## Appendix

### Technology Stack Summary

| Layer | Technology | Version |
|-------|-----------|---------|
| **Language** | PHP | 8.2 |
| **Database** | MySQL | 8.0 |
| **Web Server** | Apache | 2.4 (via Docker) |
| **Frontend Framework** | Bootstrap | 5.3 |
| **Icons** | Bootstrap Icons | 1.11.0 |
| **Fonts** | Nunito (Google Fonts) | - |
| **Containerization** | Docker | Compose V2 |
| **External APIs** | GWN Manager API, Twilio | - |

### Environment Variables Reference

**Required `.env` Variables:**
```ini
# Database
DB_HOST=localhost
DB_USER=gwn_user
DB_PASS=secure_password
DB_NAME=gwn_wifi_system

# Application
APP_NAME=WiFi Management System
APP_URL=http://localhost:3040
CODE_EXPIRY_DAYS=7
CODE_LENGTH=8

# GWN API (via Python CLI)
DEFAULT_URL=https://gwn-manager.example.com
ID=app_id
Key=secret_key
ALLOWED_DEVICES=3

# Twilio (SMS/WhatsApp)
TWILIO_ACCOUNT_SID=ACxxx
TWILIO_AUTH_TOKEN=xxx
TWILIO_PHONE_NUMBER=+1234567890
TWILIO_WHATSAPP_NO=+1234567890
```

### Glossary

- **RBAC**: Role-Based Access Control
- **GWN**: Grandstream WiFi Networks (network equipment manufacturer)
- **Voucher**: Time-limited WiFi access code
- **Onboarding Code**: Single-use registration code for self-service account creation
- **Accommodation**: Student housing property/venue
- **SA ID**: South African 13-digit national identification number
- **MAC Address**: Media Access Control address (device network identifier)

---

**End of PRD**

*Last Updated: February 9, 2026*  
*Project Lead: Architect Agent*  
*Status: M0 Complete - Awaiting M0-T5 PRD Review*
