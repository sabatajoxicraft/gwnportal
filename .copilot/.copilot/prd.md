# GWN WiFi System - M0 PRD
**Product Requirements Document**  
Version: 1.0 | Milestone: M0 (Foundation) | Date: 2024

---

## 🏗️ Project Architecture Overview

**This repository contains TWO STANDALONE applications that are NOT integrated:**

### Application 1: gwn-portal (Admin Web Portal)
- **Tech Stack:** PHP 8.2 + MySQL + Bootstrap 5 + Apache
- **Purpose:** Web-based admin interface for managing users, accommodations, and vouchers
- **Users:** Admins, Owners, Managers, Students (via web browser)
- **Deployment:** Docker container (port 80)

### Application 2: Python CLI Tool
- **Tech Stack:** Python 3.10+ (standalone scripts)
- **Purpose:** Command-line automation for GWN Manager API, voucher generation, Twilio messaging
- **Users:** Network administrators (via terminal)
- **Deployment:** Local CLI execution or Docker container for isolated runs

**⚠️ IMPORTANT:** These applications operate independently. The PHP portal does NOT call Python scripts. The Python CLI does NOT interact with the MySQL database. They share configuration files (`.env`) but have separate workflows.

---

## 1. Problem Statement

### The Challenge
Network administrators and property managers in accommodation facilities face recurring operational overhead managing WiFi access:

- **Manual voucher generation**: Staff manually create WiFi credentials, consuming 2-5 hours/week
- **Distribution bottlenecks**: Delivering vouchers via email/paper is slow and error-prone
- **Device sprawl**: Students connect unlimited devices, degrading network performance
- **Compliance gaps**: No audit trail for access control or incident response
- **Scaling friction**: Adding new properties requires duplicating manual processes

### The Solution
This repository provides TWO independent solutions:
1. **gwn-portal**: Web-based RBAC system for multi-accommodation management
2. **Python CLI**: Automation toolkit for GWN Manager API operations and bulk messaging

Result: 90% reduction in manual effort, enforceable device limits, complete audit trails.

---

## 2. Target Users

### gwn-portal Users
| Role | Persona | Primary Goals | Key Pain Points Solved |
|------|---------|---------------|----------------------|
| **Admin** | IT Director managing multi-site deployments | System configuration, user provisioning, compliance reporting | Centralized control, audit logs, backup management |
| **Owner** | Property owner with 3-10 accommodations | Accommodation setup, manager delegation | No-code accommodation onboarding, manager role assignment |
| **Manager** | On-site accommodation manager | Daily student onboarding, voucher distribution, device troubleshooting | Bulk student management, role-based access control |
| **Student** | Resident needing WiFi access | Self-service voucher retrieval, device management | Web-based account access, clear device limits |

### Python CLI Users
| Role | Persona | Primary Goals | Tools Used |
|------|---------|---------------|------------|
| **Network Admin** | Technical staff with terminal access | Automate voucher generation, bulk SMS campaigns, OneDrive imports | `execute.py`, `voucher.py`, `messaging.py` |
| **DevOps** | Infrastructure team | Script GWN API operations, integrate with CI/CD | `network.py`, `config.py` |

### Use Case Matrix
```
┌──────────────────┬──────────────────────────────────────────────┐
│ Application      │ Critical Workflow                            │
├──────────────────┼──────────────────────────────────────────────┤
│ gwn-portal       │ Admin creates users → Owner creates          │
│ (Web Portal)     │ accommodations → Manager assigns students    │
├──────────────────┼──────────────────────────────────────────────┤
│ Python CLI       │ Admin runs execute.py → Generates vouchers   │
│ (Terminal)       │ → Sends bulk SMS via messaging.py            │
└──────────────────┴──────────────────────────────────────────────┘
```

---

## 3. Core Features (MVP)

---

## 📱 Application 1: gwn-portal (Web Portal)

### 3.1 Authentication & Authorization (RBAC)
**Status:** ✅ Implemented  
**Application:** gwn-portal  
**Features:**
- Session-based authentication with bcrypt password hashing
- 4-tier role hierarchy (Admin > Owner > Manager > Student)
- Granular permission checks per route/action
- Password reset via email

**Evidence:** `gwn-portal/index.php`, `gwn-portal/models/User.php`, `RBAC_WORKFLOW.md`

---

### 3.2 Multi-Accommodation Management
**Status:** ✅ Implemented  
**Application:** gwn-portal  
**Features:**
- Owner creates accommodations (name, address, capacity)
- Manager assignment with scope isolation (managers only see assigned properties)
- Student enrollment via unique onboarding codes per accommodation
- Device limit configuration per accommodation

**Evidence:** `gwn-portal/models/Accommodation.php`, `gwn-portal/views/owner/accommodations.php`

---

### 3.3 Student Management (Web Interface)
**Status:** ✅ Implemented  
**Application:** gwn-portal  
**Features:**
- Onboarding code system (8-character alphanumeric)
- Bulk student creation via web forms
- Device tracking (MAC address, connection status)
- Device limit enforcement

**Evidence:** `gwn-portal/models/Student.php`, `gwn-portal/views/manager/students.php`

---

### 3.4 Admin Dashboard
**Status:** ✅ Implemented  
**Application:** gwn-portal  
**Features:**
- User management (create/edit/delete all roles)
- Accommodation oversight (view all properties)
- Onboarding code generation
- Activity audit logs (login, voucher creation, config changes)
- System reports:
  - Voucher usage by accommodation
  - Student registration trends
  - Device connection metrics
- Database backup management

**Evidence:** `gwn-portal/views/admin/dashboard.php`, `gwn-portal/views/admin/users.php`, `gwn-portal/views/admin/reports.php`

---

### 3.5 Portal Infrastructure
**Status:** ✅ Implemented  
**Application:** gwn-portal  
**Features:**
- PHP 8.2 with Apache web server
- MySQL database with prepared statements (SQL injection prevention)
- Session security (HttpOnly, Secure flags)
- Bootstrap 5 responsive UI
- Automated database schema setup

**Evidence:** `Dockerfile.web`, `docker-compose.yml`, `gwn-portal/config/database.php`

---

## 🖥️ Application 2: Python CLI Tool

### 3.6 GWN Manager API Integration
**Status:** ✅ Implemented  
**Application:** Python CLI  
**Features:**
- OAuth 2.0 authentication with token refresh
- Voucher group creation and management
- Network device inventory synchronization
- Automated voucher generation with:
  - Configurable expiration (hours/days)
  - Device limits per voucher
  - Bandwidth profiles
- Command-line interface for all operations

**Evidence:** `network.py`, `execute.py`, `voucher.py`, `token_store.txt`

---

### 3.7 Bulk Messaging System
**Status:** ✅ Implemented  
**Application:** Python CLI  
**Features:**
- Twilio SMS delivery (primary)
- WhatsApp messaging with templated messages
- Delivery status tracking (sent/failed/pending)
- Message templates:
  - Welcome message with voucher credentials
  - Device limit warnings
  - Expiration reminders
- CLI command: `python send_sms.py` or `python messaging.py`

**Evidence:** `messaging.py`, `send_sms.py`, `message.py`

---

### 3.8 OneDrive Student Import
**Status:** ✅ Implemented  
**Application:** Python CLI  
**Features:**
- Excel/OneDrive student database import (name, phone, email, room)
- OAuth 2.0 authentication with Microsoft Graph API
- Bulk data processing and validation
- CLI command: `python onedrive.py`

**Evidence:** `onedrive.py`, `config.py`

---

### 3.9 CLI Utilities
**Status:** ✅ Implemented  
**Application:** Python CLI  
**Features:**
- Environment configuration management (`config.py`)
- Utility functions for data processing (`utils.py`)
- Standalone script execution
- Docker container support for isolated runs

**Evidence:** `main.py`, `utils.py`, `Dockerfile.python`

---

## 4. Out of Scope (M1)

The following will NOT be included in M0:

| Feature | Rationale | Milestone |
|---------|-----------|-----------|
| **Automated Tests** | Unit/integration test suite | M1 |
| **CSRF Protection** | Token-based CSRF middleware | M1-Security |
| **API Rate Limiting** | Prevent auth endpoint abuse | M1-Security |
| **XSS Sanitization** | Input sanitization layer | M1-Security |
| **Monitoring/Alerting** | Prometheus/Grafana dashboards | M1-Ops |
| **SMS Cost Controls** | Rate limiting for messaging costs | M1-Ops |
| **Secrets Management** | HashiCorp Vault integration | M1-Security |
| **API Documentation** | OpenAPI/Swagger specs | M1-Dev |
| **Load Testing** | Performance benchmarks | M1-Ops |
| **Advanced Analytics** | Predictive usage analytics, ML insights | M2 |
| **Multi-Tenancy** | SaaS model with org isolation | M2 |
| **Mobile App** | Native iOS/Android apps | M2 |
| **Payment Integration** | Billing for voucher consumption | M2 |

---

## 5. Success Criteria

### 5.1 Operational Metrics
| Metric | Target | Measurement |
|--------|--------|-------------|
| **Voucher Delivery Rate** | ≥95% successful delivery within 5 minutes | SMS/WhatsApp delivery logs |
| **User Adoption** | ≥80% of target accommodations onboarded in 90 days | Active accommodation count |
| **System Uptime** | ≥99.5% availability (excluding planned maintenance) | Docker container health checks |
| **Voucher Cost Efficiency** | <$0.10 per voucher delivered | Twilio billing reports |
| **Time Savings** | 90% reduction in manual voucher management time | Manager survey (pre/post) |

### 5.2 Technical Acceptance
- [ ] All 4 roles can complete end-to-end workflows without errors
- [ ] GWN Manager API integration passes 100 consecutive voucher creations
- [ ] Database handles 10,000+ students without performance degradation
- [ ] Docker deployment completes in <5 minutes on fresh instance
- [ ] Zero SQL injection vulnerabilities (manual security audit)

### 5.3 User Satisfaction
- [ ] Manager NPS ≥8/10 after 30-day usage
- [ ] Student voucher retrieval time <2 minutes (95th percentile)
- [ ] Admin training time <1 hour to full proficiency

---

## 6. Technical Requirements

### 6.1 Runtime Environment

#### gwn-portal (Web Application)
```yaml
Core Stack:
  - PHP: 8.2+ (with mysqli, curl, mbstring extensions)
  - MySQL: 5.7+ or MariaDB 10.3+ (UTF8MB4 charset)
  - Web Server: Apache 2.4 with mod_rewrite
  - Frontend: Bootstrap 5, jQuery (CDN)

Container:
  - Image: php:8.2-apache
  - Port: 80
  - Volume: ./gwn-portal → /var/www/html
```

#### Python CLI Tool (Standalone)
```yaml
Core Stack:
  - Python: 3.10+
  - No web server required
  - No database required (reads from .env only)

Dependencies:
  - requests, twilio, python-dotenv (see requirements.txt)

Execution:
  - Direct: python execute.py
  - Docker: docker run -it gwn-python python execute.py
```

#### Shared Infrastructure
```yaml
Dependencies:
  - Docker Engine: 20.10+
  - Docker Compose: 2.0+
  - Shared .env configuration file
```

### 6.2 Third-Party Integrations
| Service | Purpose | Used By | Auth Method | SLA Requirement |
|---------|---------|---------|-------------|-----------------|
| **GWN Manager API** | Voucher/network management | Python CLI | OAuth 2.0 | 99.9% uptime |
| **Twilio** | SMS/WhatsApp delivery | Python CLI | API Key | 99.95% delivery |
| **Microsoft OneDrive** | Student database import | Python CLI | OAuth 2.0 | Best effort |
| **MySQL** | Data persistence | gwn-portal | Native | Self-hosted |

### 6.3 Security Baseline
- [x] Password hashing (bcrypt, cost factor ≥12)
- [x] Prepared statements for all SQL queries
- [x] Session security (HttpOnly, Secure, SameSite flags)
- [x] Environment variable separation (`.env` not in version control)
- [ ] **M1 Required:** CSRF protection, XSS sanitization, rate limiting

### 6.4 Data Persistence
**Backup Strategy:**
- Automated daily MySQL dumps (7-day retention)
- Admin-triggered manual backups (stored in `data/backups/`)
- Docker volume persistence for `data/` directory

**Recovery Requirements:**
- RTO (Recovery Time Objective): <1 hour
- RPO (Recovery Point Objective): <24 hours

### 6.5 Deployment Model
**Architecture:**
```
┌────────────────────────────────────────────────────────────┐
│                      Docker Host                           │
│                                                            │
│  ┌─────────────────────┐        ┌─────────────────────┐  │
│  │   gwn-portal        │        │   python-cli        │  │
│  │   (Apache + PHP)    │        │   (Standalone)      │  │
│  │   Port: 80          │        │   No exposed ports  │  │
│  │        │            │        │        │            │  │
│  │        ▼            │        │        ▼            │  │
│  │   ┌──────────┐      │        │   ┌──────────┐     │  │
│  │   │  MySQL   │      │        │   │  .env    │     │  │
│  │   │  :3306   │      │        │   │ (config) │     │  │
│  │   └──────────┘      │        │   └──────────┘     │  │
│  │        │            │        │        │            │  │
│  │   [data/mysql]      │        │   [token_store.txt]│  │
│  └─────────────────────┘        └─────────────────────┘  │
│           │                              │                │
│           │                              │                │
└───────────┼──────────────────────────────┼────────────────┘
            │                              │
            ▼                              ▼
    ┌───────────────┐            ┌───────────────┐
    │  Web Browsers │            │  External APIs│
    │  (Users)      │            │  (GWN, Twilio)│
    └───────────────┘            └───────────────┘

KEY: 
  - gwn-portal and Python CLI do NOT communicate with each other
  - gwn-portal uses MySQL for data persistence
  - Python CLI reads .env and writes to local files (token_store.txt)
  - Both can run simultaneously but operate independently
```

**Scalability Constraints (M0):**
- Single-server deployment
- gwn-portal: Max 50 concurrent users, 10,000 students
- Python CLI: Unlimited executions (no server process)

---

## 7. Dependencies & Risks

### Critical Dependencies
1. **GWN Manager API Stability**: System is unusable if API is down >1 hour
   - *Mitigation:* Cache voucher groups, implement retry logic
2. **Twilio Delivery Reliability**: Core value proposition depends on message delivery
   - *Mitigation:* WhatsApp fallback, delivery status monitoring
3. **OneDrive API Access**: Required for bulk student imports
   - *Mitigation:* Support manual CSV upload alternative

### Known Risks
| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| SQL injection via unvalidated input | **Critical** | Medium | M1: Add input sanitization layer |
| SMS cost overrun from abuse | High | Medium | M1: Rate limiting (10 SMS/user/hour) |
| GWN API token expiration during bulk operations | Medium | High | Implement proactive token refresh |
| Session fixation attacks | High | Low | M1: Regenerate session ID on login |

---

## 8. Open Questions

1. **Multi-Language Support**: Should voucher messages support languages beyond English?
2. **Voucher Reuse Policy**: Can expired vouchers be regenerated with same credentials?
3. **Device Removal Workflow**: Who can remove devices (student vs. manager)?
4. **Audit Log Retention**: How long should activity logs be retained (30/90/365 days)?

---

## 9. Implementation Evidence

### gwn-portal (Web Application)
**Code References:**
- Authentication: `gwn-portal/models/User.php`, `gwn-portal/middleware/Auth.php`
- RBAC: `RBAC_WORKFLOW.md`, `gwn-portal/config/roles.php`
- Database: `gwn-portal/config/database.php`, `gwn-portal/migrations/*.sql`
- Views: `gwn-portal/views/{admin,owner,manager,student}/`
- Models: `gwn-portal/models/{User,Accommodation,Student}.php`
- Entry Point: `gwn-portal/index.php`

### Python CLI Tool
**Code References:**
- GWN Integration: `network.py`, `execute.py`, `voucher.py`
- Messaging: `messaging.py`, `send_sms.py`, `message.py`
- OneDrive Import: `onedrive.py`
- Configuration: `config.py`, `utils.py`
- Main Entry: `main.py`

### Deployment
**Docker:**
- Web Container: `Dockerfile.web` (PHP 8.2 + Apache)
- Python Container: `Dockerfile.python` (Python 3.10)
- Orchestration: `docker-compose.yml`
- Startup Scripts: `docker-start.ps1`, `docker-start.sh`

**Environment Setup:**
- Setup Guide: `ENV_SETUP_GUIDE.md`
- Docker Quickstart: `DOCKER-QUICKREF.md`
- Example Config: `env.example`

---

## Approval Checklist

- [ ] Problem statement validated with 3+ target users
- [ ] All M0 features verified in running system
- [ ] Success criteria metrics collection implemented
- [ ] Technical requirements feasible with existing stack
- [ ] M1 security gaps acknowledged and prioritized
- [ ] Docker deployment tested on clean environment

**Approved by:** _________________  
**Date:** _________________

---

*This PRD defines the **as-built** M0 baseline. M1 planning will address identified security/testing gaps.*
