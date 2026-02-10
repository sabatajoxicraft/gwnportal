# Boundary Enforcer Skill

> **Purpose:** Monitor AI agent execution in real-time to detect and prevent domain boundary violations that cause "doing nonsense and breaking stuff."

---

## Skill Metadata

- **Name:** `boundary-enforcer`
- **Type:** Runtime monitoring + circuit breaker
- **Invoked by:** OVERSEER (automatically during agent execution OR after subtask failure)
- **Output:** Violation report + corrective action
- **Validates:** User's principle that "one AI one issue" must be enforced, not just suggested

---

## Problem Statement

From user feedback:
> "AIs have single track minds and cannot multitask... when one AI handles a task spread across more than one domain, the AI does nonsense and breaks stuff"

**Examples of violations:**
1. Architect edits `auth.service.ts` (SRC) AND `package.json` (CFG) in same session
2. BuildBot runs tests (BLD) AND tries to fix failing code (SRC)
3. CodeScout asked to "find and fix imports" (explore + edit = domain violation)

---

## Monitoring Strategy

### Phase 1: Pre-Execution Analysis

Before spawning agent, analyze the task prompt:

**Red Flags:**
- Prompt contains multiple verbs: "update X AND create Y"
- Prompt mentions files from different domains: "edit auth.service.ts and update package.json"
- Prompt has conditional logic: "if tests fail, fix the code"
- Prompt uses vague scope: "fix the authentication system"

**Action:** If red flags detected → ABORT → Re-decompose using domain-decomposition skill

### Phase 2: Runtime Monitoring

After agent starts, monitor its tool calls:

**Domain Detection Rules:**

| Tool Call | Domain | Allowed Context |
|-----------|--------|-----------------|
| `edit(path)` | Depends on file extension | Only if path matches assigned domain |
| `create(path)` | Depends on file extension | Only if path matches assigned domain |
| `bash('npm install ...')` | DEP | Only if assigned DEP domain |
| `bash('npm test ...')` | BLD | Only if assigned BLD domain |
| `bash('npx tsc ...')` | BLD | Only if assigned BLD or CFG domain |
| `view(path)` | READ-ONLY | Always allowed (no violation) |
| `grep(...)` | READ-ONLY | Always allowed (no violation) |
| `glob(...)` | READ-ONLY | Always allowed (no violation) |

**Violation Detection Logic:**

```python
def detect_violation(agent_task, tool_calls):
    assigned_domain = agent_task.domain  # e.g., "SRC"
    touched_domains = set()
    
    for call in tool_calls:
        if call.tool in ['edit', 'create']:
            file_domain = map_file_to_domain(call.params.path)
            touched_domains.add(file_domain)
        elif call.tool == 'bash':
            cmd_domain = map_command_to_domain(call.params.command)
            touched_domains.add(cmd_domain)
    
    # Violation if agent touched 2+ domains
    if len(touched_domains) > 1:
        return {
            'violation': True,
            'assigned': assigned_domain,
            'touched': list(touched_domains),
            'severity': 'CRITICAL'
        }
    
    # Violation if touched domain doesn't match assigned
    if touched_domains and assigned_domain not in touched_domains:
        return {
            'violation': True,
            'assigned': assigned_domain,
            'touched': list(touched_domains),
            'severity': 'HIGH'
        }
    
    return {'violation': False}
```

### Phase 3: Post-Execution Validation

After agent completes, verify:

1. **File Change Audit:** All modified files belong to assigned domain?
2. **Command Audit:** All bash commands stayed within domain boundaries?
3. **Success Criteria:** Did agent complete ONLY its assigned subtask?

---

## Response Protocol

### Violation Detected → Immediate Abort

1. **Stop Agent:** Use `stop_bash` if running command, or ignore agent output
2. **Log Violation:**
   ```yaml
   timestamp: 2026-02-08T07:30:00Z
   agent_id: architect-001
   assigned_domain: SRC
   violated_domains: [SRC, CFG]
   tool_calls:
     - tool: edit
       file: services/auth.service.ts
       domain: SRC  # OK
     - tool: edit
       file: package.json
       domain: CFG  # VIOLATION
   severity: CRITICAL
   action: ABORT_AND_REDECOMPOSE
   ```

3. **Notify OVERSEER:**
   ```
   ⚠️ BOUNDARY VIOLATION DETECTED
   
   Agent: Architect (architect-001)
   Assigned: [SRC] Update auth.service.ts
   Violated: Attempted to edit package.json (CFG domain)
   
   Root Cause: Task was too broad ("fix Firebase auth")
   Corrective Action: Re-decompose into atomic tasks
   ```

4. **Re-Decompose:** Call domain-decomposition skill with original task + violation context

5. **Update Circuit Breaker:** Increment failure count for this domain

---

## Domain Mapping Functions

### File Path → Domain Mapping

```javascript
function mapFileToDomain(filePath) {
    const patterns = {
        CFG: [/package\.json$/, /\.env/, /\.config\.(js|ts)$/, /tsconfig\.json$/, /app\.json$/],
        SRC: [/^src\/.*\.(ts|tsx|js|jsx)$/, /^services\//, /^components\//],
        TST: [/__tests__\//, /__mocks__\//, /\.(test|spec)\.(ts|tsx|js)$/],
        BLD: [/\.github\/workflows\//, /^scripts\//],
        DOC: [/\.md$/],
        UI: [/styles\//, /theme\//],
        DAT: [/prisma\//, /schema/, /types\//],
        SEC: [/auth/, /security/]
    };
    
    for (const [domain, regexList] of Object.entries(patterns)) {
        if (regexList.some(regex => regex.test(filePath))) {
            return domain;
        }
    }
    return 'UNKNOWN';
}
```

### Bash Command → Domain Mapping

```javascript
function mapCommandToDomain(command) {
    if (command.includes('npm install') || command.includes('npm update')) return 'DEP';
    if (command.includes('npm test') || command.includes('jest')) return 'BLD';
    if (command.includes('npm run build') || command.includes('npx tsc')) return 'BLD';
    if (command.includes('git commit') || command.includes('git push')) return 'BLD';
    if (command.includes('firebase deploy')) return 'BLD';
    if (command.includes('npx react-native run')) return 'BLD';
    
    // Read-only commands = no domain constraint
    if (command.includes('cat ') || command.includes('ls ') || command.includes('grep ')) return 'READ_ONLY';
    
    return 'SRC';  // Default for custom scripts
}
```

---

## Circuit Breaker Integration

Track violations per domain to detect systemic issues:

```yaml
circuit_breaker_state:
  SRC:
    violations: 2
    last_violation: 2026-02-08T07:15:00Z
    status: OPEN  # Block further SRC delegations until reviewed
  TST:
    violations: 0
    status: CLOSED
  BLD:
    violations: 1
    last_violation: 2026-02-08T06:00:00Z
    status: HALF_OPEN  # Allow one retry
```

**Rules:**
- 1st violation → Warning, retry with refined prompt
- 2nd violation (same domain, same session) → Circuit OPEN, escalate to user
- 3rd violation → Halt all delegations, request user intervention

---

## OVERSEER Integration

### Workflow Update

```
OVERSEER delegates task
  ↓
boundary-enforcer: Pre-check task prompt
  ↓
  IF red flags → ABORT + re-decompose
  ELSE → Proceed
  ↓
Agent executes
  ↓
boundary-enforcer: Monitor tool calls in real-time
  ↓
  IF violation detected → ABORT agent + log + re-decompose
  ELSE → Continue
  ↓
Agent completes
  ↓
boundary-enforcer: Post-execution audit
  ↓
  IF violations → Log + update circuit breaker
  ELSE → Mark success
  ↓
OVERSEER aggregates results
```

---

## Example Scenarios

### Scenario 1: Architect Violates Boundary

**Task:** `[SRC] Update auth.service.ts to use correct WebClientId`

**Agent Actions:**
1. ✅ `view('services/auth.service.ts')` - OK (read-only)
2. ✅ `edit('services/auth.service.ts', ...)` - OK (SRC domain)
3. ❌ `edit('package.json', ...)` - **VIOLATION** (CFG domain)

**Enforcer Response:**
```
⚠️ ABORT: Architect violated domain boundary
Assigned: SRC | Touched: SRC, CFG
Action: Stop agent, revert package.json change, re-delegate
```

### Scenario 2: BuildBot Tries to Fix Code

**Task:** `[BLD] Run npm test and report results`

**Agent Actions:**
1. ✅ `bash('npm test')` - OK (BLD domain)
2. ❌ `edit('services/auth.service.ts', ...)` - **VIOLATION** (SRC domain, not BLD)

**Enforcer Response:**
```
⚠️ ABORT: BuildBot attempted code edit (outside role)
Assigned: BLD | Touched: BLD, SRC
Action: Stop agent, delegate code fix to separate Architect
```

### Scenario 3: Task Prompt Contains Multiple Domains

**Prompt:** "Update auth.service.ts and create mocks for testing"

**Enforcer Pre-Check:**
```
🚫 REJECT: Task spans multiple domains
Detected: [SRC: update auth.service.ts] + [TST: create mocks]
Action: Decompose into 2 subtasks:
  T1: [SRC] Update auth.service.ts
  T2: [TST] Create __mocks__/@react-native-firebase/auth.ts
```

---

## Metrics

Track enforcement effectiveness:

- **Violations Prevented:** Pre-execution red flags caught
- **Violations Detected:** Runtime aborts triggered
- **False Positives:** Valid cross-domain tasks incorrectly flagged
- **Escape Rate:** Violations that slipped through (found in post-audit)
- **Circuit Breaker Activations:** Times OVERSEER blocked domain due to repeated failures

---

## Configuration

Allow per-project customization:

```yaml
# .copilot/boundary-enforcer.config.yml
strict_mode: true  # Abort on ANY domain mismatch
allow_read_cross_domain: true  # view/grep/glob allowed across domains
circuit_breaker_threshold: 2  # Violations before circuit opens
whitelisted_cross_domain:
  - [SRC, DAT]  # Allow source code to touch data types
  - [UI, SRC]   # Allow UI components to touch business logic
```

---

## Version History

- **v1.0** (2026-02-08): Initial creation based on user's "single track minds" insight
