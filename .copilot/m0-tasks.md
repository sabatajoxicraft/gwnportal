# M0 + M0.5 Tasks

> Create via shrimp-tasks-split_tasks with updateMode: "clearAllTasks"

===

## Project Context
| Field | Value |
|-------|-------|
| Name | Joxicraft WiFi |
| Stack | React + Vite |
| Platform | Web |

**Description:** This is a system that integrates to the GWN cloud system of grandstream and manages different establishments ie student accommodations, hotels and the like where bulk usgae of wifi must be managed through the use of voucher system, and where the students should be registered and self managed through the system

===

## M0 Phase: PRD Definition

### M0-T1: Generate PRD
| Field | Value |
|-------|-------|
| Agent | Architect |
| Dependencies | none |

Auto-derive from mandate.md: problem statement, users, features, acceptance criteria.

---

### M0-T2: Component Architecture  
| Field | Value |
|-------|-------|
| Agent | Architect |
| Dependencies | M0-T1 |

Apply Atomic Design: atoms → molecules → organisms → templates → pages.
Use web-optimized components.

---

### M0-T3: Folder Structure
| Field | Value |
|-------|-------|
| Agent | Architect |
| Dependencies | M0-T2 |

Create MDDF structure. Configure Tailwind + shadcn/ui, TypeScript strict.

---

### M0-T4: API/Data Strategy
| Field | Value |
|-------|-------|
| Agent | Architect |
| Dependencies | M0-T1 |

Define backend needs, API contracts, TypeScript interfaces.

---

### M0-T5: USER APPROVAL ⚠️
| Field | Value |
|-------|-------|
| Agent | OVERSEER |
| Dependencies | M0-T1, M0-T2, M0-T3, M0-T4 |

**HUMAN TOUCHPOINT #2 - DO NOT AUTO-COMPLETE**

===

## M0.5 Phase: Scaffold Validation (MANDATORY)

> ⚠️ DO NOT SKIP - Validates tech stack works in CI BEFORE implementation

### M0.5-T1: Create Minimal Scaffold
| Field | Value |
|-------|-------|
| Agent | Architect |
| Dependencies | M0-T5 |

Initialize project, create "Hello World", configure all dependencies.

---

### M0.5-T2: Configure CI/CD
| Field | Value |
|-------|-------|
| Agent | BuildBot |
| Dependencies | M0.5-T1 |

Push to GitHub, verify workflows in place.

---

### M0.5-T3: Verify CI Build ✅
| Field | Value |
|-------|-------|
| Agent | BuildBot |
| Dependencies | M0.5-T2 |

**GATE: CI BUILD MUST BE GREEN**

IF BUILD FAILS:
- Invoke `skill(build-failure-triage)`
- Fix configs only (not code)
- 2nd identical error → CIRCUIT BREAKER

---

### M0.5-T4: Lock Configs
| Field | Value |
|-------|-------|
| Agent | OVERSEER |
| Dependencies | M0.5-T3 |

Freeze: babel, metro, tailwind, tsconfig, package.json deps.
Document working versions in decision-log.md.

===

## AI Instructions

### "Start M0":
1. Read mandate.md
2. Create M0-T1 through M0-T5 in shrimp-tasks
3. Execute M0-T1 → M0-T4 via Architect
4. **STOP at M0-T5** for approval

### After M0-T5 approved:
1. Create M0.5-T1 through M0.5-T4
2. Execute scaffold validation
3. **GATE: M0.5-T3 must be GREEN**
4. Only then proceed to M1

### AUTO-TRIGGERS:
| IF | THEN |
|----|------|
| Build fails | `skill(build-failure-triage)` |
| 2nd identical error | CIRCUIT BREAKER → Research |
| M0.5-T3 fails 3x | STOP → Escalate to user |
