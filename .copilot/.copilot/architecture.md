# GWN WiFi System - Architecture Documentation

**Version:** 2.0  
**Last Updated:** 2026-02-07

---

## System Overview

**IMPORTANT:** This repository contains **TWO STANDALONE APPLICATIONS** that do NOT integrate with each other:

1. **gwn-portal** - PHP-based Admin Portal (web application)
2. **Python CLI Tool** - Command-line interface for GWN API management

These are independent tools that happen to share the same repository. They do NOT communicate with each other.

---

# SECTION A: gwn-portal (PHP Admin Portal)

## Overview
A web-based administrative portal for managing WiFi users, accommodations, and access control through a browser interface.

### Technology Stack
| Component | Technology | Version |
|-----------|-----------|---------|
| Frontend | Bootstrap 5 + Vanilla JS | 5.3 |
| Backend | PHP (Procedural) | 8.2 |
| Database | MariaDB/MySQL | 10.6 |
| Icons | Bootstrap Icons | 1.10 |
| Web Server | Apache | 2.4 |
| Container | Docker | - |

---

## Database Schema

### Entity Relationships
```
roles
  ├── users (RBAC: admin, owner, manager, student)
  │   ├── accommodations (owner_id)
  │   ├── user_accommodation (manager assignments)
  │   ├── students (1:1 profile)
  │   ├── user_devices (MAC tracking)
  │   ├── onboarding_codes (created_by, used_by)
  │   ├── voucher_logs (delivery tracking)
  │   └── notifications (messaging)
  │
  └── accommodations
      ├── user_accommodation (manager links)
      └── students (room assignments)
```

### Key Tables
| Table | Purpose | Critical Fields |
|-------|---------|----------------|
| **users** | Unified user accounts | id, username, email, role_id, status, id_number |
| **roles** | RBAC hierarchy | id, name (admin/owner/manager/student) |
| **accommodations** | Properties/venues | id, name, owner_id |
| **user_accommodation** | Manager-to-property mapping | user_id, accommodation_id (composite PK) |
| **students** | Student profiles | user_id (UNIQUE), accommodation_id, room_number |
| **voucher_logs** | WiFi voucher distribution | user_id, voucher_code, status, sent_via |
| **onboarding_codes** | Registration tokens | code (UNIQUE), role_id, expires_at, status |
| **user_devices** | Device MAC tracking | user_id, mac_address, device_type |
| **activity_log** | Audit trail | user_id, action, ip_address, timestamp |

### RBAC Hierarchy
- **admin (id=1)**: System-wide control
- **owner (id=2)**: Multi-accommodation management
- **manager (id=3)**: Single accommodation (via user_accommodation)
- **student (id=4)**: End-user access

---

## Application Structure

### Directory Layout
```
gwn-portal/
├── public/              # Web root (DocumentRoot)
│   ├── index.php       # Main entry point
│   ├── login.php       # Authentication
│   ├── logout.php      # Session termination
│   ├── admin/          # Admin-only pages
│   │   ├── accommodations.php
│   │   ├── create-user.php
│   │   ├── managers.php
│   │   ├── students.php
│   │   └── settings.php
│   ├── manager/        # Manager-specific pages
│   │   ├── dashboard.php
│   │   ├── students.php
│   │   └── onboarding.php
│   └── assets/         # Static resources
│       ├── css/style.css
│       └── images/
├── includes/           # Shared PHP modules
│   ├── config.php      # Environment + constants
│   ├── db.php         # Database connection pool
│   ├── functions.php   # Helper functions (500+ lines)
│   ├── layout.php      # Page wrapper template
│   ├── accommodation-handler.php
│   └── components/     # Reusable UI components
│       ├── navigation.php
│       └── student-table.php
├── db/                 # Database migrations
│   └── schema.sql      # Table definitions
└── Dockerfile          # PHP 8.2-Apache image
```

### Key Components

**config.php:**
- Session initialization
- Environment variable loading (.env)
- Constants (BASE_URL, ASSETS_PATH)
- Flash message system
- RBAC helper functions

**db.php:**
- mysqli connection pooling
- `getDbConnection()` function
- UTF-8mb4 charset enforcement

**functions.php:**
- `safeQueryPrepare()`: Prepared statement wrapper
- `requireAuth()`: Login gate
- `requireRole()`: RBAC enforcement
- Password hashing helpers
- MAC/phone formatting utilities
- Onboarding code generation

**layout.php:**
- HTML template wrapper
- Dynamic role-based navigation
- Bootstrap 5 CDN includes
- Flash message rendering

---

## gwn-portal: Security Implementation

### ✅ Strong Protections
| Protection | Status | Implementation |
|------------|--------|----------------|
| **SQL Injection** | ✅ Excellent | All queries use `safeQueryPrepare()` with `bind_param()` |
| **XSS Prevention** | ✅ Strong | 80+ `htmlspecialchars()` calls on output |
| **Password Security** | ✅ Good | `password_hash()` with PASSWORD_DEFAULT |
| **Session Management** | ✅ Basic | Session variables for auth state |

### ❌ Critical Gaps (M1 Priority)
| Vulnerability | Impact | Location |
|---------------|--------|----------|
| **Password-less login** | CRITICAL | `gwn-portal/public/login.php:49` - `if (true)` bypasses verification |
| **CSRF tokens** | HIGH | All forms lack CSRF protection |
| **Flash XSS** | MEDIUM | `gwn-portal/includes/config.php:88` - Unescaped flash messages |
| **Session timeout** | MEDIUM | No idle timeout mechanism |
| **Session regeneration** | MEDIUM | Missing after login |

**Note:** Security findings apply to gwn-portal only. Python CLI tool does not have a web interface or authentication system.

---

# SECTION B: Python CLI Tool (Standalone)

## Overview
A command-line interface tool for direct interaction with GWN Manager API. Runs independently of gwn-portal.

### Technology Stack
| Component | Technology | Version |
|-----------|-----------|---------|
| Language | Python | 3.x |
| API Client | Requests | - |
| Messaging | Twilio SDK | - |
| Storage | OneDrive API | - |

### Responsibilities
1. **GWN Manager API**: Direct network device control via CLI
2. **Voucher Generation**: Bulk WiFi credential creation
3. **Twilio Integration**: SMS/WhatsApp delivery automation
4. **OneDrive Sync**: File storage/backup operations

### Application Structure
```
/ (repository root)
├── main.py              # GWN API authentication + orchestration
├── voucher.py           # Voucher group management
├── messaging.py         # Twilio SDK wrapper
├── send_sms.py          # SMS delivery script
├── execute.py           # Command execution layer
├── config.py            # API credentials + settings
├── network.py           # Network operations
├── onedrive.py          # OneDrive integration
├── utils.py             # Helper functions
├── requirements.txt     # Python dependencies
└── Dockerfile.python    # Python container image
```

### Key Modules
| File | Purpose |
|------|---------|
| `main.py` | GWN API authentication + orchestration |
| `voucher.py` | Voucher group management |
| `messaging.py` | Twilio SDK wrapper |
| `execute.py` | Command execution layer |
| `config.py` | API credentials + settings |
| `network.py` | Network device operations |
| `onedrive.py` | Cloud storage integration |

### Usage Pattern
This tool is executed directly from the command line:
```bash
python main.py          # GWN API operations
python send_sms.py      # Send SMS/WhatsApp messages
python voucher.py       # Manage vouchers
```

**No PHP integration** - This tool does NOT communicate with gwn-portal or its database.

---

## Deployment Architecture

### Docker Services (Independent Containers)
```yaml
services:
  gwn-db:          # MariaDB 10.6 on port 3307
                   # Used by: gwn-portal ONLY
  
  gwn-web:         # PHP 8.2-Apache on port 3030
                   # Runs: gwn-portal
                   # Connects to: gwn-db
  
  gwn-python:      # Python 3 with dependencies
                   # Runs: Python CLI Tool
                   # Connects to: NOTHING (standalone)
```

**CRITICAL:** These containers do NOT communicate with each other. The Python container does not access gwn-db, and gwn-portal does not call Python scripts.

### Configuration Files

**gwn-portal:**
- `gwn-portal/.env`: Database credentials, session config
- `docker-compose.yml`: Service orchestration
- `Dockerfile.web`: PHP extensions (mysqli, zip)

**Python CLI Tool:**
- `.env` (root): GWN API keys, Twilio tokens, OneDrive credentials
- `requirements.txt`: Python dependencies
- `Dockerfile.python`: Python runtime + packages

---

## gwn-portal: Quality Metrics

### Code Quality
- **Lines of Code**: ~5,000 PHP
- **Test Coverage**: None (manual testing only)
- **Code Style**: Procedural PHP, no PSR standards
- **Documentation**: Inline comments minimal

### Performance Considerations
- Connection pooling via `getDbConnection()`
- Prepared statements cached by mysqli
- No caching layer (direct DB queries)
- Session files stored on disk

---

## gwn-portal: M1 Enhancement Priorities

### Security (CRITICAL)
1. Fix password-less login vulnerability
2. Implement CSRF token system
3. Escape flash message output
4. Add session timeout (30 min idle)
5. Session regeneration after login

### Architecture (HIGH)
1. Add input validation layer
2. Implement rate limiting (login, code generation)
3. Create API authentication middleware
4. Add Content Security Policy headers

### Testing (MEDIUM)
1. Add PHPUnit test suite
2. Create integration tests for critical flows
3. Add database migration system

### DevOps (LOW)
1. Add health check endpoints
2. Implement logging standard (PSR-3)
3. Add monitoring/alerting
