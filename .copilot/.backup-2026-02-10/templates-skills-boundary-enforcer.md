---
name: boundary-enforcer
description: Monitor agent execution to detect and prevent domain boundary violations. Enforces "One AI, One Domain" at runtime.
---

# Boundary Enforcer

## Monitoring Phases

### Phase 1: Pre-Execution (Before Spawning Agent)

| Red Flag in Prompt | Action |
|--------------------|--------|
| Multiple verbs: "update X AND create Y" | ABORT → Re-decompose |
| Files from different domains | ABORT → Re-decompose |
| Conditional logic: "if tests fail, fix code" | ABORT → Split into 2 tasks |
| Vague scope: "fix the system" | ABORT → Clarify with user |

### Phase 2: Runtime (Monitor Tool Calls)

| Tool Call | Domain Detection |
|-----------|-----------------|
| `edit(path)` / `create(path)` | Map file path to domain |
| `bash('npm install')` | DEP domain |
| `bash('npm test')` / `bash('jest')` | BLD domain |
| `view()` / `grep()` / `glob()` | READ-ONLY (always allowed) |

**Violation:** Agent touches domain ≠ assigned domain → ABORT immediately.

### Phase 3: Post-Execution

1. **File audit:** All modified files belong to assigned domain?
2. **Command audit:** All bash commands stayed within boundaries?
3. **Scope audit:** `git diff --name-only` matches task's `relatedFiles`?
4. **Standards audit:** Code follows coding-standards.md? (TypeScript strict, Tailwind, Atomic Design)
5. If scope violations → Flag for Auditor review

## Response Protocol

| Violation Type | Action |
|----------------|--------|
| 1st violation | Warning, retry with refined prompt |
| 2nd violation (same domain) | Circuit OPEN → Escalate to user |
| 3rd violation | Halt all delegations → User intervention |

## On Violation Detected

1. STOP agent
2. LOG violation (agent, assigned domain, touched domains, tool calls)
3. NOTIFY OVERSEER with root cause
4. RE-DECOMPOSE using domain-decomposition skill
5. UPDATE circuit breaker state
6. If standards violations → Flag for Auditor
