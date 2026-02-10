# Domain Decomposition Skill

> **Purpose:** Transform compound, cross-domain tasks into atomic, single-domain subtasks that can be safely delegated to specialized AI agents without cognitive overload or context switching failures.

---

## Skill Metadata

- **Name:** `domain-decomposition`
- **Type:** Pre-delegation analysis
- **Invoked by:** OVERSEER (mandatory before ANY delegation)
- **Output:** Dependency graph of single-domain subtasks
- **Validates:** User's principle that "AIs have single track minds and cannot multitask"

---

## Research Foundation

Based on 2024-2026 research into LLM agent cognitive load and multi-agent orchestration:

1. **Context switching increases error rates** - LLM agents perform worse when tasks span multiple domains
2. **Single Responsibility Principle** - Each agent should focus on ONE domain for optimal accuracy
3. **Parallel orchestration** - Independent subtasks should run simultaneously, not sequentially
4. **Context folding** - Completed work should be summarized to free working memory

**Sources:**
- Microsoft Azure AI Agent Orchestration Patterns
- Anthropic: Effective Context Engineering for AI Agents
- ArXiv: "Beyond Accuracy: A Cognitive Load Framework for Mapping the Capability..."
- Redis: "Multi-agent systems: Why coordinated AI beats going solo"

---

## Domain Taxonomy

Each subtask MUST belong to exactly ONE domain:

| Domain ID | Domain Name | Examples | File Patterns |
|-----------|-------------|----------|---------------|
| `CFG` | Configuration Management | Edit package.json, .env, app.json, tsconfig.json | `*.json`, `.env*`, `*.config.*` |
| `SRC` | Source Code Logic | Edit services, components, utilities, business logic | `src/**/*.ts`, `src/**/*.tsx` |
| `TST` | Test Infrastructure | Create mocks, write tests, update jest.setup.js | `__tests__/**/*`, `__mocks__/**/*`, `*.test.*`, `*.spec.*` |
| `BLD` | Build/CI/CD | Run builds, npm commands, GitHub Actions | `.github/**/*`, build scripts, CI commands |
| `DOC` | Documentation | Update README, write guides, comments | `*.md`, inline docs |
| `DEP` | Dependency Management | npm install, version updates, package resolution | package.json + lock files |
| `MON` | Monitoring/Logging | Add logging, monitoring, error tracking | Logger setup, analytics |
| `SEC` | Security/Auth | Auth flows, permissions, credential handling | Auth services, security config |
| `UI` | UI/UX | Styling, layouts, visual components | Style files, UI components |
| `DAT` | Data/Schema | Database schemas, migrations, data models | Prisma, Firebase rules, types |

---

## Decomposition Algorithm

### Input
Task description from user or mandate (e.g., "Fix Firebase Authentication")

### Process

1. **Parse Task Description**
   - Extract all verbs (fix, update, create, test, deploy)
   - Extract all nouns (auth service, config, tests, Firebase Console)
   - Identify conjunctions (AND, THEN, ALSO, PLUS)

2. **Map to Domains**
   - For each noun/verb pair, determine which domain(s) it touches
   - Example: "Update auth.service.ts" → `SRC`
   - Example: "Create Firebase mocks" → `TST`
   - Example: "Verify Firebase Console settings" → `DOC` (research) + `SEC`

3. **Detect Cross-Domain Tasks**
   - If task maps to 2+ domains → SPLIT REQUIRED
   - If task contains "AND" between different domains → SPLIT REQUIRED
   - If task involves both code + config → SPLIT REQUIRED

4. **Generate Atomic Subtasks**
   - Each subtask = ONE domain + ONE action + ONE file/target
   - Format: `[Domain] Action on Target`
   - Example: `[SRC] Update GoogleAuthProvider in auth.service.ts`
   - Example: `[TST] Create @react-native-firebase/auth mock`
   - Example: `[BLD] Run npm test to verify mocks`

5. **Build Dependency Graph**
   - Identify which subtasks can run in parallel (no dependencies)
   - Identify which must run sequentially (output of A feeds into B)
   - Format: `[Parallel: A1, A2, A3] → [Sequential: B] → [Parallel: C1, C2]`

6. **Assign Agent Types**
   - `explore` → Research, discovery, read-only analysis
   - `general-purpose` → Code changes (`SRC`, `UI`, `DAT`)
   - `task` → Commands (`BLD`, `DEP`)
   - `code-review` → Validation, security checks

### Output

```yaml
original_task: "Fix Firebase Authentication"
domains_detected: [SRC, TST, BLD, SEC]
cross_domain: true
decomposition:
  phase_1_parallel:
    - id: T1
      domain: SEC
      agent: explore
      task: "Research Firebase auth/argument-error causes and verify Console settings"
      files: []
    - id: T2
      domain: SRC
      agent: explore
      task: "Find all files importing @react-native-firebase/auth"
      files: ["services/auth.service.ts", "hooks/useAuth.ts"]
    - id: T3
      domain: CFG
      agent: explore
      task: "Verify google-services.json and GoogleService-Info.plist have correct WebClientId"
      files: ["android/app/google-services.json", "ios/KidCoin/GoogleService-Info.plist"]
  phase_2_parallel:
    - id: T4
      domain: SRC
      agent: general-purpose
      task: "Update auth.service.ts GoogleAuthProvider with correct WebClientId from T3"
      files: ["services/auth.service.ts"]
      depends_on: [T1, T3]
    - id: T5
      domain: TST
      agent: general-purpose
      task: "Create __mocks__/@react-native-firebase/auth.ts with proper Firebase Native SDK mock"
      files: ["__mocks__/@react-native-firebase/auth.ts"]
      depends_on: []
  phase_3_sequential:
    - id: T6
      domain: BLD
      agent: task
      task: "Run npm test -- auth.service.test.ts to verify mocks work"
      files: []
      depends_on: [T4, T5]
  phase_4_sequential:
    - id: T7
      domain: BLD
      agent: task
      task: "Build APK and test Google Sign-In on device"
      files: []
      depends_on: [T6]
```

---

## OVERSEER Integration

### Mandatory Workflow

```
User Request → OVERSEER
  ↓
1. CALL domain-decomposition skill
  ↓
2. RECEIVE decomposition YAML
  ↓
3. FOR EACH phase in decomposition:
     IF parallel:
       SPAWN ALL agents in ONE response (task tool × N)
     ELSE:
       SPAWN agents sequentially
     WAIT for completion
     LOG results to session-state.md
  ↓
4. AGGREGATE results using context folding
  ↓
5. REPORT to user
```

### Circuit Breaker Integration

If ANY subtask fails:
1. Check if same domain failed in previous attempt
2. If YES → Invoke `boundary-enforcer` skill to analyze WHY
3. If 2nd failure → Escalate to user with full diagnostic

---

## Example Decompositions

### Example 1: "Implement user profile editing"

**Before decomposition:** Architect gets overwhelmed (UI + logic + validation + tests)

**After decomposition:**
```
Phase 1 (Parallel):
  [UI] Create ProfileEditScreen.tsx component skeleton
  [SRC] Create updateProfile function in user.service.ts
  [DAT] Define UserProfile type in types/user.ts

Phase 2 (Sequential):
  [UI] Wire ProfileEditScreen to updateProfile service
  [TST] Create ProfileEditScreen.test.tsx
  [TST] Create user.service.test.ts

Phase 3 (Sequential):
  [BLD] Run npm test
```

### Example 2: "Fix failing CI/CD pipeline"

**Before decomposition:** BuildBot investigates logs + fixes code + updates config (breaks things)

**After decomposition:**
```
Phase 1 (Parallel):
  [BLD] Download CI logs and identify error type
  [CFG] Check .github/workflows/*.yml for syntax errors

Phase 2 (Conditional):
  IF error = "missing dependency":
    [DEP] npm install missing-package
  IF error = "test failure":
    [TST] Delegate to test-specific subtask decomposition
  IF error = "build config":
    [CFG] Fix tsconfig.json or metro.config.js
```

---

## Validation Rules

A decomposition is VALID if:

✅ Every subtask touches only ONE domain
✅ No subtask contains "AND" between different domain actions
✅ Dependency graph is acyclic (no circular dependencies)
✅ Each subtask has clear success criteria
✅ Parallel phases have NO inter-dependencies
✅ File list for each subtask is complete and specific

A decomposition is INVALID if:

❌ Subtask touches 2+ domains (e.g., "update auth.service.ts AND create mock")
❌ Subtask is too vague ("fix the app")
❌ Dependency creates circular wait
❌ Missing agent type assignment

---

## Failure Modes Prevented

| Old Approach | Failure Mode | New Approach | Prevention |
|--------------|--------------|--------------|------------|
| "Architect: Fix auth" | Architect edits code, config, tests → breaks tests | Phase 1: SRC only, Phase 2: TST only | Domain isolation |
| "BuildBot: Run tests and fix failures" | BuildBot tries to edit code (not its job) | Phase 1: BLD runs tests, Phase 2: SRC fixes code | Role clarity |
| Sequential exploration | Takes 50 seconds to read 5 files | Parallel CodeScouts read all at once | Concurrency |
| One Architect does everything | Cognitive overload, context switching errors | 3-5 specialized agents per feature | Load distribution |

---

## Metrics

Track these to measure skill effectiveness:

- **Decomposition Time:** Time to generate decomposition YAML
- **Subtask Count:** Average number of subtasks per user request
- **Parallelization Ratio:** (Parallel tasks) / (Total tasks)
- **Domain Violations:** Times an agent strayed outside assigned domain
- **Success Rate:** % of subtasks completing without retry
- **Time Savings:** Sequential time - Parallel time

---

## Version History

- **v1.0** (2026-02-08): Initial creation based on user insight and research validation
