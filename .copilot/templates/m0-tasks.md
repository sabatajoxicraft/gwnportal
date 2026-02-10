# M0 + M0.5 Tasks

> 🤖 AI AGENTS: Create these tasks via shrimp-tasks-split_tasks with updateMode: "clearAllTasks"

===

## Project Context
- **Name**: [PROJECT_NAME]
- **Description**: [PROJECT_DESCRIPTION]

===

## M0 Phase: PRD Definition

### M0-T1: Generate PRD
| Field | Value |
|-------|-------|
| Description | Create comprehensive PRD from mandate description |
| Dependencies | none |
| Agent | Architect |

**Steps:**
1. Analyze mandate.md description
2. Auto-derive: problem statement, target users, features
3. Define acceptance criteria per feature
4. Create user stories: "As a [user], I want [goal] so that [benefit]"

**Verification:** PRD has problem statement, personas, features with acceptance criteria

---

### M0-T2: Define Component Architecture
| Field | Value |
|-------|-------|
| Description | Apply Atomic Design, create component inventory |
| Dependencies | M0-T1 |
| Agent | Architect |

**Steps:**
1. Map PRD features → UI components
2. Organize by Atomic Design levels:
   - Atoms: Button, Input, Icon, Badge
   - Molecules: SearchBar, FormField, Card
   - Organisms: Header, Sidebar, DataTable
   - Templates: MainLayout, AuthLayout
   - Pages: Actual screens
3. Define TypeScript interfaces for props

**Verification:** Component inventory covers all features, clear atomic separation

---

### M0-T3: Define Folder Structure
| Field | Value |
|-------|-------|
| Description | Create directory layout, configure tooling |
| Dependencies | M0-T2 |
| Agent | Architect |

**Required Structure:**
```
src/
├── components/{atoms,molecules,organisms,templates}
├── pages/ (or screens/ for mobile)
├── hooks/, utils/, services/, stores/, theme/, types/, lib/
```

**Config Required:**
- Tailwind CSS with theme tokens
- TypeScript strict mode
- Material Icons or Lucide

**Verification:** Structure matches MDDF, configs complete

---

### M0-T4: Define API/Data Strategy
| Field | Value |
|-------|-------|
| Description | Define backend requirements if applicable |
| Dependencies | M0-T1 |
| Agent | Architect |

**If Backend Needed:**
- Database schema for core entities
- REST API endpoints with TypeScript interfaces
- Auth approach

**If Frontend-Only:**
- Local state management (Context, Zustand, etc.)
- Persistence strategy (localStorage, etc.)

**Verification:** Decision documented, interfaces defined

---

### M0-T5: USER APPROVAL GATE ⚠️
| Field | Value |
|-------|-------|
| Description | Present M0 deliverables for user approval |
| Dependencies | M0-T1, M0-T2, M0-T3, M0-T4 |
| Agent | OVERSEER |

**Present:**
- PRD summary
- Component architecture
- Folder structure
- Tech stack decisions

**HUMAN TOUCHPOINT #2 - DO NOT AUTO-COMPLETE**

===

## M0.5 Phase: Scaffold Validation (MANDATORY)

> ⚠️ **DO NOT SKIP** - Validates tech stack works in CI BEFORE implementation

### M0.5-T1: Create Minimal Scaffold
| Field | Value |
|-------|-------|
| Description | Initialize project with hello world app |
| Dependencies | M0-T5 |
| Agent | Architect |

**Steps:**
1. Initialize project with chosen tech stack
2. Create minimal "Hello World" component
3. Configure all required dependencies
4. Set up config files (babel, metro, tailwind, etc.)

**Verification:** Project initializes without errors locally

---

### M0.5-T2: Configure CI/CD
| Field | Value |
|-------|-------|
| Description | Set up GitHub repo and Actions workflows |
| Dependencies | M0.5-T1 |
| Agent | BuildBot |

**Steps:**
1. Create GitHub repository (or use existing)
2. Push scaffold code
3. Verify GitHub Actions workflows are in place

**Verification:** Code pushed, workflows visible in Actions tab

---

### M0.5-T3: Verify CI Build ✅
| Field | Value |
|-------|-------|
| Description | **CRITICAL** - Ensure CI build passes before M1 |
| Dependencies | M0.5-T2 |
| Agent | BuildBot |

**Steps:**
1. Trigger CI build (push or manual)
2. Monitor build output
3. **IF FAIL:** Invoke `skill(build-failure-triage)`
4. Fix config issues (NOT code - scaffold only)
5. Re-run until GREEN

**Gate:** ✅ CI BUILD MUST BE GREEN TO PROCEED

**IF BUILD FAILS:**
- DO NOT proceed to M1
- Fix config/dependency issues ONLY
- After 2nd identical failure → CIRCUIT BREAKER

---

### M0.5-T4: Lock Config Files
| Field | Value |
|-------|-------|
| Description | Freeze configs after successful build |
| Dependencies | M0.5-T3 |
| Agent | OVERSEER |

**Lock These Files (DO NOT MODIFY in M1+):**
- `babel.config.js`
- `metro.config.js`
- `tailwind.config.js`
- `tsconfig.json`
- `package.json` (dependencies section)

**Add to decision-log.md:**
- Config versions that work
- Any workarounds applied

**Verification:** Configs documented, marked as frozen

===

## Instructions for AI

### When user says "Start M0":
1. Read mandate.md
2. Create M0-T1 through M0-T5 tasks
3. Execute M0-T1 → M0-T4 via Architect
4. **STOP at M0-T5** for user approval

### When user approves M0-T5:
1. Create M0.5-T1 through M0.5-T4 tasks
2. Execute scaffold validation
3. **GATE: M0.5-T3 must be GREEN**
4. Only then create M1 tasks

### AUTO-TRIGGERS (Enforce These):
| IF | THEN |
|----|------|
| Build fails in M0.5-T3 | `skill(build-failure-triage)` |
| 2nd identical error | CIRCUIT BREAKER → Research → User |
| M0.5-T3 fails 3 times | STOP → Escalate to user |

===

## Quality Standards

| Requirement | Standard |
|-------------|----------|
| PRD | Actionable, specific, has acceptance criteria |
| Components | Clear atomic separation, TypeScript interfaces |
| Structure | Matches MDDF template exactly |
| Configs | Verified in CI before M1 |
| Documentation | Decisions recorded in decision-log.md |