---
name: task-orchestration
description: Formal workflow for OVERSEER using shrimp-tasks MCP tools. Use for any complex task (5+ steps) requiring coordination, delegation, and tracking. Enforces task decomposition, caching, and circuit breaker integration.
---

# OVERSEER Skill: Task Orchestration

## When to Use This Skill

**MANDATORY for:**
- Complex features (5+ implementation steps)
- Multi-domain tasks (DAT + SRC + TST + BLD)
- Tasks requiring multiple agents
- Any "implement X" command from user

**Optional for:**
- Simple bug fixes (single file, <5 lines)
- Documentation updates
- Configuration tweaks

## Core Principle

**Every complex task must flow through shrimp-tasks system.**

No ad-hoc delegation. No invisible agents. All work tracked, visible, verifiable.

---

## Standard Workflow

### 1. PLAN_TASK - Understand Requirements

```
Tool: shrimp-tasks-plan_task

Input:
- description: Full task requirements from user/mandate
- requirements: Technical constraints, quality standards

Output:
- Task plan with goals, scope, success criteria

When to skip: Requirements already crystal clear
```

**Example:**
```
plan_task(
  description: "Implement Family Management CRUD operations with Firestore integration, optimistic updates, and full test coverage",
  requirements: "Follow wrapServiceCall pattern, use Zustand for state, implement Jest tests, pass type-check and lint"
)
```

---

### 2. ANALYZE_TASK - Technical Breakdown

```
Tool: shrimp-tasks-analyze_task

Input:
- summary: One-sentence task description
- initialConcept: High-level technical approach (50+ chars)

Output:
- Feasibility assessment
- Risk analysis
- Technical approach
- File dependencies

When to skip: Trivial task with obvious approach
```

**Example:**
```
analyze_task(
  summary: "Family CRUD with Firestore and Zustand state management",
  initialConcept: "Create family.service.ts with wrapServiceCall for Firestore operations (create/read/update/delete). Build familyStore.ts Zustand store consuming ServiceResult types. Write comprehensive Jest tests mocking Firebase. Follow DAT → SRC → TST → BLD domain separation."
)
```

---

### 3. REFLECT_TASK - Validate Analysis

```
Tool: shrimp-tasks-reflect_task

Input:
- summary: Same as analyze_task
- analysis: Full technical analysis from step 2

Output:
- Critiques and improvements
- Optimization opportunities
- Completeness check

When to skip: Simple tasks, time-critical fixes
```

**Critical:** Catches issues BEFORE implementation (cheaper than debugging later).

---

### 4. SPLIT_TASKS - Create Formal Task List

```
Tool: shrimp-tasks-split_tasks

Input:
- updateMode: 'clearAllTasks' (new work) or 'append' (add to existing)
- globalAnalysisResult: Overall goal for all subtasks
- tasksRaw: JSON array of subtasks with dependencies

Output:
- Formal task list with IDs
- Dependency graph
- Execution order

MANDATORY fields per task:
- name: Clear, actionable title
- description: What to do, why, acceptance criteria
- implementationGuide: Specific approach (can include pseudocode)
- dependencies: Array of task names this depends on
- relatedFiles: Array of {path, type, description}
- verificationCriteria: How to verify completion
```

**Example:**
```json
{
  "updateMode": "clearAllTasks",
  "globalAnalysisResult": "Implement Family CRUD with Firestore, following One AI One Domain principle",
  "tasksRaw": [
    {
      "name": "Design Family data model (DAT Domain)",
      "description": "Define Firestore schema for families collection with proper indexing",
      "implementationGuide": "Create types/family.types.ts with Family interface. Document Firestore structure in comments. Include: familyId, name, currency, createdAt, members array.",
      "dependencies": [],
      "relatedFiles": [
        {
          "path": "types/family.types.ts",
          "type": "CREATE",
          "description": "Family TypeScript interfaces"
        }
      ],
      "verificationCriteria": "Types compile without errors, interfaces exported correctly"
    },
    {
      "name": "Implement Family service CRUD (SRC Domain)",
      "description": "Create family.service.ts with create/read/update/delete operations using wrapServiceCall",
      "implementationGuide": "Use wrapServiceCall() for all Firestore operations. Return ServiceResult<Family>. Handle errors consistently. Use FAMILIES_COLLECTION constant.",
      "dependencies": ["Design Family data model (DAT Domain)"],
      "relatedFiles": [
        {
          "path": "services/family.service.ts",
          "type": "CREATE",
          "description": "Family CRUD service"
        },
        {
          "path": "utils/error.utils.ts",
          "type": "REFERENCE",
          "description": "wrapServiceCall utility"
        }
      ],
      "verificationCriteria": "All CRUD functions implemented, type-check passes, follows service pattern"
    }
  ]
}
```

---

### 5. EXECUTE_TASK - Assign Work to Agents

```
Tool: shrimp-tasks-execute_task

Input:
- taskId: UUID from split_tasks output

Process:
1. Read task details (get_task_detail if needed)
2. Determine appropriate agent type:
   - DAT Domain → Architect (general-purpose)
   - SRC Domain → Architect (general-purpose)
   - TST Domain → Architect (general-purpose) 
   - BLD Domain → BuildBot (task)
   - Exploration → CodeScout (explore) × 3-5 parallel
3. Check .copilot/.cache/ for validation results (if BLD domain)
4. Delegate via task() tool with full context
5. Update session-state.md with delegation details

Output:
- Guidance for executing the task
- Step-by-step workflow
```

**Example:**
```
execute_task(taskId: "abc-123-def-456")

→ Returns execution guidance
→ OVERSEER then delegates to Architect:

task(
  agent_type: "general-purpose",
  description: "Implement Family CRUD service",
  prompt: "Implement family.service.ts following the task specification. Task ID: abc-123-def-456. Use get_task_detail to read full requirements. Follow wrapServiceCall pattern from utils/error.utils.ts. Return ServiceResult<Family> for all operations."
)
```

---

### 6. VERIFY_TASK - Check Completion

```
Tool: shrimp-tasks-verify_task

Input:
- taskId: UUID of completed task
- summary: Brief completion summary OR issues found
- score: 0-100 (≥80 = auto-complete)

Scoring Guide:
- Requirements Compliance: 30%
- Technical Quality: 30%
- Integration Compatibility: 20%
- Performance/Scalability: 20%

Output:
- Task marked complete (if score ≥80)
- OR task marked needs-revision with feedback

When to use:
- After agent reports completion
- After validation gates pass
- Before moving to dependent tasks
```

**Example:**
```
verify_task(
  taskId: "abc-123-def-456",
  score: 95,
  summary: "Family CRUD service fully implemented. All functions use wrapServiceCall, return ServiceResult<Family>, follow project patterns. Type-check passes, lint passes, 35/35 tests pass. Ready for integration."
)

→ Task marked complete
→ Dependent tasks now unblocked
```

---

### 7. LIST_TASKS - Monitor Progress

```
Tool: shrimp-tasks-list_tasks

Input:
- status: 'all', 'pending', 'in_progress', or 'completed'

Output:
- Formatted task list with status, dependencies, progress

When to use:
- Check what's blocked vs. ready
- Report progress to user
- Identify bottlenecks
```

---

## Validation Caching Rules

### Cache Structure

```
.copilot/.cache/validation-results.json
{
  "type-check": {
    "timestamp": "2026-02-08T09:30:00Z",
    "status": "pass",
    "errors": 0,
    "files_hash": "abc123"
  },
  "lint": {
    "timestamp": "2026-02-08T09:31:00Z",
    "status": "pass", 
    "warnings": 44,
    "files_hash": "abc123"
  },
  "test-family-service": {
    "timestamp": "2026-02-08T09:35:00Z",
    "status": "pass",
    "tests_passed": 35,
    "files_hash": "def456"
  }
}
```

### Caching Workflow

**Before running validation:**
1. Check if .copilot/.cache/validation-results.json exists
2. Read cached result for validation type
3. Compute files_hash of relevant source files
4. If hash matches cached hash → return cached result
5. If hash differs or cache miss → run validation, update cache

**Cache Invalidation:**
- Any file modification → invalidate related caches
- Git operations (checkout, merge) → clear all caches
- Manual: `rm .copilot/.cache/validation-results.json`

**Cache Benefits:**
- 70% reduction in redundant validation runs
- Faster feedback loops
- Less CI usage (if validation offloaded locally)

---

## Circuit Breaker Integration

### Failure Tracking

Store attempt count in task metadata:

```
Task: "Fix TypeScript errors in family.service.ts"
Metadata:
  attempts: 2
  last_error: "Type 'undefined' is not assignable to type 'Family'"
  error_category: "type-narrowing"
  escalation_level: "standard" → "research" → "human"
```

### Escalation Rules

| Attempt | Action | Model | Notes |
|---------|--------|-------|-------|
| 1 | Standard fix | Haiku/Sonnet | Try obvious solution |
| 2 | Research mode | Sonnet | Invoke research_mode tool, investigate root cause |
| 3 | Escalate model | Opus | Use higher-capability model |
| 4 | Human escalation | - | ask_user with full context |

**Circuit Breaker Trigger:**
- Same error pattern 2x in a row → Escalate
- Task taking >30min → Review approach
- Agent non-responsive → Timeout and reassign

---

## Domain-Specific Patterns

### Feature Implementation

```
1. split_tasks with domains:
   - DAT: Define data model, types, schema
   - SRC: Implement service layer, business logic
   - STATE: Create Zustand store (if needed)
   - TST: Write comprehensive tests
   - BLD: Validate (type-check, lint, test)

2. Execute in dependency order:
   DAT → SRC → STATE → TST → BLD

3. Verify each phase before next
```

### Bug Fix

```
1. split_tasks:
   - Reproduce bug (TST domain)
   - Diagnose root cause (explore agents)
   - Fix bug (SRC domain)
   - Verify fix (BLD domain)
   - Regression test (TST domain)

2. Execute with short feedback loops
3. Verify each step
```

### Migration

```
1. Plan with compatibility check:
   - Invoke pre-migration-compatibility-check skill
   - Analyze breaking changes
   - Create rollback plan

2. split_tasks:
   - Update dependencies
   - Migrate code (incrementally)
   - Update tests
   - Validate full suite

3. Execute with staging validation
4. Verify no regressions
```

---

## Session State Integration

**After EVERY task delegation**, update `.copilot/session-state.md`:

```markdown
## Work In Progress

- [x] Family data model designed (abc-123)
- [ ] Family service CRUD in-progress (def-456)
  - Agent: Architect (general-purpose)
  - Status: implementing createFamily()
  - Files: services/family.service.ts (150 lines)
  - Started: 2026-02-08T09:40Z
  - Blocker: None

## Recent Completions

### 2026-02-08 - Family Data Model (DAT)
✅ Created types/family.types.ts
✅ Defined Family interface with all required fields
✅ Type-check passes
```

---

## Error Handling

### Task Tool Failures

```
If shrimp-tasks-* tool errors:
1. Check error message for guidance
2. If Chinese response → Retry with clearer input
3. If persistent error → Fall back to manual tracking in session-state.md
4. Report issue to user
```

### Agent Non-Completion

```
If agent doesn't complete in expected time:
1. Check agent status (if background mode)
2. Review logs for blockers
3. If stuck → Timeout and create new task with context
4. If pattern repeats → Escalate complexity
```

### Validation Failures

```
If validation fails:
1. Invoke build-failure-triage skill
2. Classify error (code error, env issue, config problem)
3. Create task to fix root cause
4. Update circuit breaker metadata
5. Execute fix task
6. Re-validate (with caching)
```

---

## Quick Reference

### Common Commands

```bash
# Start complex task
plan_task → analyze_task → split_tasks → execute_task (for each) → verify_task

# Check progress
list_tasks(status: 'all')

# Get task details
get_task_detail(taskId: 'abc-123')

# Update task
update_task(taskId: 'abc-123', description: 'Updated requirements...')

# Query tasks
query_task(query: 'Family CRUD', isId: false)

# Clear and restart
clear_all_tasks(confirm: true)
```

### Decision Tree

```
User request received
│
├─ Simple (< 5 steps, single file)?
│  ├─ YES → Delegate directly to appropriate agent
│  └─ NO → Continue
│
├─ Complex (5+ steps, multiple domains)?
│  ├─ YES → Use task orchestration workflow
│  └─ Unclear → ask_user
│
Task Orchestration:
1. plan_task (understand)
2. analyze_task (break down)
3. split_tasks (formalize)
4. For each subtask:
   a. execute_task (assign)
   b. Wait for completion
   c. verify_task (check)
5. list_tasks (report progress)
```

---

## Success Criteria

This skill is working when:

✅ All complex tasks tracked in shrimp-tasks (100% visibility)  
✅ Validation runs cached (≤2 runs per implementation)  
✅ Circuit breaker catches failure loops (before 3rd attempt)  
✅ Dependencies enforced (no premature execution)  
✅ Session state always up-to-date  
✅ Progress visible to user at all times

---

**Version:** 1.0  
**Status:** Active  
**Integration:** MANDATORY for OVERSEER on complex tasks  
**Testing:** Validate with Family CRUD implementation
